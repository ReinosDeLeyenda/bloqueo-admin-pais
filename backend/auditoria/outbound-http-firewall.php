<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_outbound_detect_caller() {
    $root = wp_normalize_path( WP_CONTENT_DIR );
    foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 14 ) as $f ) {
        if ( empty( $f['file'] ) ) { continue; }
        $p = wp_normalize_path( $f['file'] );
        if ( preg_match( '#/plugins/([^/]+)/#', $p, $m ) && 'bloqueo-admin-pais' !== $m[1] ) { return 'Plugin: ' . $m[1]; }
        if ( preg_match( '#/themes/([^/]+)/#', $p, $m ) ) { return 'Tema: ' . $m[1]; }
        if ( 0 === strpos( $p, $root . '/mu-plugins/' ) ) { return 'MU-Plugin: ' . basename( $p ); }
    }
    return 'Core / Cron / Otros';
}

function bap_outbound_get_blocked_hosts() {
    $raw = (string) get_option( 'bap_outbound_blocked_hosts', '' );
    $out = [];
    foreach ( preg_split( '/[\r\n,]+/', $raw ) as $line ) {
        $h = strtolower( trim( (string) preg_replace( '#^https?://#', '', trim( $line ) ), '/' ) );
        if ( '' !== $h ) { $out[] = $h; }
    }
    return array_values( array_unique( $out ) );
}

function bap_outbound_record_event( $host, $method, $path, $caller, $blocked ) {
    if ( '1' !== (string) get_option( 'bap_outbound_audit_enabled', '0' ) ) { return; }
    $logs = get_option( 'bap_outbound_http_logs', [] );
    if ( ! is_array( $logs ) ) { $logs = []; }
    array_unshift( $logs, [
        'time'    => current_time( 'mysql' ),
        'host'    => $host,
        'method'  => $method,
        'path'    => mb_substr( $path, 0, 120 ),
        'caller'  => $caller,
        'blocked' => $blocked ? 1 : 0,
    ] );
    if ( count( $logs ) > 200 ) { $logs = array_slice( $logs, 0, 200 ); }
    update_option( 'bap_outbound_http_logs', $logs, false );
}

add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
    if ( false !== $preempt ) { return $preempt; }
    $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
    $own  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    if ( '' === $host || $host === $own ) { return $preempt; }

    $blocked_list = bap_outbound_get_blocked_hosts();
    $is_blocked   = false;
    foreach ( $blocked_list as $b ) {
        if ( $host === $b || substr( $host, -strlen( '.' . $b ) ) === '.' . $b ) {
            $is_blocked = true;
            break;
        }
    }

    $method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
    $path   = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?: '/' );
    $caller = bap_outbound_detect_caller();

    if ( $is_blocked ) {
        bap_outbound_record_event( $host, $method, $path, $caller, true );
        return new WP_Error( 'bap_outbound_blocked', 'Conexion saliente bloqueada por firewall: ' . $host );
    }

    bap_outbound_record_event( $host, $method, $path, $caller, false );
    return $preempt;
}, 10, 3 );
function bap_render_outbound_firewall_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    if ( isset( $_POST['bap_save_outbound_cfg'] ) && check_admin_referer( 'bap_outbound_action', 'bap_outbound_nonce' ) ) {
        update_option( 'bap_outbound_audit_enabled', ! empty( $_POST['bap_outbound_audit_enabled'] ) ? '1' : '0' );
        $clean = [];
        foreach ( preg_split( '/[\r\n,]+/', (string) wp_unslash( $_POST['bap_outbound_blocked_hosts'] ?? '' ) ) as $l ) {
            $h = strtolower( trim( (string) preg_replace( '#^https?://#', '', trim( sanitize_text_field( $l ) ) ), '/' ) );
            if ( '' !== $h ) { $clean[] = $h; }
        }
        update_option( 'bap_outbound_blocked_hosts', implode( "\n", array_unique( $clean ) ) );
        $notice = '<div class="notice notice-success inline"><p><strong>Configuracion de firewall saliente guardada.</strong></p></div>';
    }
    if ( isset( $_POST['bap_block_outbound_host'] ) && check_admin_referer( 'bap_outbound_action', 'bap_outbound_nonce' ) ) {
        $h = strtolower( trim( sanitize_text_field( wp_unslash( $_POST['bap_target_out_host'] ?? '' ) ) ) );
        if ( '' !== $h ) {
            $list = bap_outbound_get_blocked_hosts();
            if ( ! in_array( $h, $list, true ) ) { $list[] = $h; update_option( 'bap_outbound_blocked_hosts', implode( "\n", $list ) ); }
            $notice = '<div class="notice notice-warning inline"><p>Dominio <code>' . esc_html( $h ) . '</code> bloqueado para peticiones salientes.</p></div>';
        }
    }
    if ( isset( $_POST['bap_clear_outbound_logs'] ) && check_admin_referer( 'bap_outbound_action', 'bap_outbound_nonce' ) ) {
        delete_option( 'bap_outbound_http_logs' );
        $notice = '<div class="notice notice-success inline"><p>Historial saliente vaciado.</p></div>';
    }

    $enabled = ( '1' === (string) get_option( 'bap_outbound_audit_enabled', '0' ) );
    $blocked = bap_outbound_get_blocked_hosts();
    $logs    = get_option( 'bap_outbound_http_logs', [] );
    if ( ! is_array( $logs ) ) { $logs = []; }
    ?>
    <div class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Firewall y Auditoria de Peticiones Salientes (HTTP Outbound)</h2>
        <p class="description">Audita a que dominios externos se conectan tus plugins o temas desde PHP y bloquea conexiones no autorizadas.</p>
        <?php echo wp_kses_post( $notice ); ?>
        <form method="post" style="margin:12px 0;">
            <?php wp_nonce_field( 'bap_outbound_action', 'bap_outbound_nonce' ); ?>
            <label style="display:block;margin-bottom:10px;">
                <input type="checkbox" name="bap_outbound_audit_enabled" value="1" <?php checked( $enabled ); ?> />
                Activar registro de peticiones HTTP salientes (ultimas 200)
            </label>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Dominios salientes bloqueados (uno por linea):</label>
            <textarea name="bap_outbound_blocked_hosts" rows="4" class="large-text code" style="max-width:440px;" placeholder="ej. tracking-externo.com"><?php echo esc_textarea( implode( "\n", $blocked ) ); ?></textarea>
            <p style="margin-top:10px;display:flex;gap:8px;">
                <button type="submit" name="bap_save_outbound_cfg" class="button button-primary">Guardar reglas salientes</button>
                <?php if ( ! empty( $logs ) ) : ?>
                    <button type="submit" name="bap_clear_outbound_logs" class="button button-secondary">Vaciar historial</button>
                <?php endif; ?>
            </p>
        </form>
        <?php if ( ! empty( $logs ) ) : ?>
            <table class="widefat striped" style="margin-top:12px;">
                <thead><tr><th>Hora</th><th>Dominio</th><th>Metodo / Ruta</th><th>Origen</th><th>Estado</th><th>Accion</th></tr></thead>
                <tbody>
                    <?php foreach ( array_slice( $logs, 0, 30 ) as $r ) :
                        $is_b  = ! empty( $r['blocked'] );
                        $badge = $is_b ? bap_render_status_badge( 'BLOQUEADA', '#d63638' ) : bap_render_status_badge( 'PERMITIDA', '#00a32a' );
                    ?>
                        <tr>
                            <td><code><?php echo esc_html( $r['time'] ); ?></code></td>
                            <td><strong><?php echo esc_html( $r['host'] ); ?></strong></td>
                            <td><code><?php echo esc_html( $r['method'] . ' ' . $r['path'] ); ?></code></td>
                            <td><small><?php echo esc_html( $r['caller'] ); ?></small></td>
                            <td><?php echo $badge; ?></td>
                            <td>
                                <?php if ( ! in_array( $r['host'], $blocked, true ) ) : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'bap_outbound_action', 'bap_outbound_nonce' ); ?>
                                        <input type="hidden" name="bap_target_out_host" value="<?php echo esc_attr( $r['host'] ); ?>" />
                                        <button type="submit" name="bap_block_outbound_host" class="button button-small" style="color:#d63638;">Bloquear</button>
                                    </form>
                                <?php else : ?>
                                    <small style="color:#d63638;">Bloqueado</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}