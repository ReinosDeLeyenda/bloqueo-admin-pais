<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_db_schema_get_current_tables() {
    global $wpdb;
    $tables = $wpdb->get_col( 'SHOW TABLES' );
    $list   = array_values( array_unique( array_map( 'strval', (array) $tables ) ) );
    sort( $list );
    return $list;
}

function bap_db_schema_refresh_baseline() {
    $tables = bap_db_schema_get_current_tables();
    $snap   = [
        'tables'   => $tables,
        'count'    => count( $tables ),
        'saved_at' => current_time( 'mysql' ),
    ];
    update_option( 'bap_db_schema_baseline', $snap, false );
    return $snap;
}

function bap_db_schema_verify() {
    $stored = get_option( 'bap_db_schema_baseline', null );
    if ( ! is_array( $stored ) || ! is_array( $stored['tables'] ?? null ) ) {
        $stored = bap_db_schema_refresh_baseline();
    }

    $base_tables = (array) $stored['tables'];
    $cur_tables  = bap_db_schema_get_current_tables();

    $added   = array_values( array_diff( $cur_tables, $base_tables ) );
    $removed = array_values( array_diff( $base_tables, $cur_tables ) );

    return [
        'changed'  => ( ! empty( $added ) || ! empty( $removed ) ),
        'added'    => $added,
        'removed'  => $removed,
        'total'    => count( $cur_tables ),
        'saved_at' => (string) ( $stored['saved_at'] ?? 'N/D' ),
    ];
}
function bap_render_db_schema_monitor_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';

    if ( isset( $_POST['bap_approve_db_schema'] ) && check_admin_referer( 'bap_db_schema_action', 'bap_db_schema_nonce' ) ) {
        $saved  = bap_db_schema_refresh_baseline();
        $notice = '<div class="notice notice-success inline"><p><strong>Esquema de base de datos aprobado:</strong> Se han registrado <strong>' . (int) $saved['count'] . '</strong> tablas como referencia limpia.</p></div>';
    }

    $res = bap_db_schema_verify();
    ?>
    <div class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Monitor de Tablas en Base de Datos (Schema &amp; Rogue Tables Watcher)</h2>
        <p class="description">Vigila la lista de tablas de MySQL (<code>SHOW TABLES</code>) para detectar la creacion de tablas ocultas maliciosas o la eliminacion inesperada de tablas del sitio.</p>
        <?php echo wp_kses_post( $notice ); ?>

        <p style="margin:10px 0;">
            <strong>Estado actual:</strong>
            <?php echo bap_render_status_badge( $res['changed'] ? 'CAMBIOS DETECTADOS' : 'INTACTO', $res['changed'] ? '#d63638' : '#00a32a' ); ?>
            &nbsp;|&nbsp; Tablas actuales: <code><?php echo (int) $res['total']; ?></code>
            &nbsp;|&nbsp; Referencia: <code><?php echo esc_html( $res['saved_at'] ); ?></code>
        </p>

        <?php if ( $res['changed'] ) : ?>
            <table class="widefat striped" style="margin:10px 0;">
                <thead><tr><th style="width:140px;">Cambio</th><th>Tabla MySQL</th></tr></thead>
                <tbody>
                    <?php foreach ( $res['added'] as $tbl ) : ?>
                        <tr><td><?php echo bap_render_status_badge( 'TABLA NUEVA', '#d97706' ); ?></td><td><code><?php echo esc_html( $tbl ); ?></code></td></tr>
                    <?php endforeach; ?>
                    <?php foreach ( $res['removed'] as $tbl ) : ?>
                        <tr><td><?php echo bap_render_status_badge( 'ELIMINADA', '#d63638' ); ?></td><td><code><?php echo esc_html( $tbl ); ?></code></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <form method="post" style="margin-top:12px;">
            <?php wp_nonce_field( 'bap_db_schema_action', 'bap_db_schema_nonce' ); ?>
            <button type="submit" name="bap_approve_db_schema" class="button button-secondary">Aprobar / Actualizar lista de tablas actual</button>
        </form>
    </div>
    <?php
}
add_action( 'admin_bar_menu', function( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) || ! bap_is_under_attack_active() ) { return; }
    $wp_admin_bar->add_node( [
        'id'    => 'bap-under-attack-pill',
        'title' => '<span style="background:#dc2626;color:#fff;padding:2px 8px;border-radius:4px;font-weight:700;">&#9888; MODO BAJO ATAQUE ACTIVO</span>',
        'href'  => admin_url( 'admin.php?page=bap-bloqueos&subtab=general' ),
    ] );
}, 100 );

function bap_render_under_attack_panic_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    if ( isset( $_POST['bap_toggle_under_attack'] ) && check_admin_referer( 'bap_panic_mode_action', 'bap_panic_mode_nonce' ) ) {
        if ( bap_is_under_attack_active() ) {
            delete_option( 'bap_under_attack_until' );
            $notice = '<div class="notice notice-success inline"><p><strong>Modo Bajo Ataque desactivado.</strong> La web ha vuelto a sus reglas normales.</p></div>';
        } else {
            $hours = (int) ( $_POST['bap_panic_hours'] ?? 3 );
            $until = ( -1 === $hours ) ? -1 : ( time() + ( max( 1, $hours ) * HOUR_IN_SECONDS ) );
            update_option( 'bap_under_attack_until', $until, false );
            $notice = '<div class="notice notice-error inline"><p><strong>&#9888; MODO BAJO ATAQUE ACTIVADO:</strong> Challenge JS forzado en toda la web, registros cerrados y bloqueo estricto de Tor/FireHOL/AbuseIPDB.</p></div>';
        }
    }

    $active = bap_is_under_attack_active();
    $until  = (int) get_option( 'bap_under_attack_until', 0 );
    $card_css = $active
        ? 'max-width:960px;padding:18px 24px;margin-top:20px;border:2px solid #dc2626;background:rgba(220,38,38,0.08);'
        : 'max-width:960px;padding:18px 24px;margin-top:20px;';
    ?>
    <div class="card" style="<?php echo esc_attr( $card_css ); ?>">
        <h2 style="margin-top:0;<?php echo $active ? 'color:#dc2626;' : ''; ?>">Botón de Pánico — Modo &laquo;Bajo Ataque&raquo; (DDoS / Capa 7)</h2>
        <p class="description">Activa con un clic la máxima defensa perimetral: fuerza el <strong>Challenge JS SHA-256</strong> en toda la web pública, cierra registros abiertos, bloquea User-Agents vacíos y corta tráfico de Tor, FireHOL y AbuseIPDB (&ge; 25%).</p>
        <?php echo wp_kses_post( $notice ); ?>

        <form method="post" style="margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_panic_mode_action', 'bap_panic_mode_nonce' ); ?>
            <?php if ( ! $active ) : ?>
                <label><strong>Auto-desactivar tras:</strong>
                    <select name="bap_panic_hours">
                        <option value="1">1 hora</option>
                        <option value="3" selected>3 horas</option>
                        <option value="6">6 horas</option>
                        <option value="12">12 horas</option>
                        <option value="24">24 horas</option>
                        <option value="-1">Manual (hasta que lo desactive)</option>
                    </select>
                </label>
                <button type="submit" name="bap_toggle_under_attack" class="button button-primary" style="background:#dc2626;border-color:#b91c1c;">ACTIVAR MODO BAJO ATAQUE</button>
            <?php else : ?>
                <span style="font-weight:600;">
                    <?php echo ( -1 === $until ) ? 'Activo de forma manual.' : 'Se desactivará automáticamente el: <code>' . esc_html( wp_date( 'Y-m-d H:i:s', $until ) ) . '</code>'; ?>
                </span>
                <button type="submit" name="bap_toggle_under_attack" class="button button-secondary">Desactivar Modo Bajo Ataque</button>
            <?php endif; ?>
        </form>
    </div>
    <?php
}