<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_cron_resolve_hook_origin( $hook ) {
    global $wp_filter;
    if ( empty( $wp_filter[ $hook ] ) ) {
        return [ 'origin' => 'Sin callback (Huerfano)', 'suspicious' => true ];
    }
    $root    = wp_normalize_path( WP_CONTENT_DIR );
    $origins = [];
    foreach ( $wp_filter[ $hook ]->callbacks as $prio => $items ) {
        foreach ( $items as $cb ) {
            $fn   = $cb['function'] ?? null;
            $file = '';
            try {
                if ( is_string( $fn ) && function_exists( $fn ) ) {
                    $file = ( new ReflectionFunction( $fn ) )->getFileName();
                } elseif ( is_array( $fn ) && count( $fn ) === 2 ) {
                    $file = ( new ReflectionMethod( $fn[0], $fn[1] ) )->getFileName();
                } elseif ( $fn instanceof Closure ) {
                    $file = ( new ReflectionFunction( $fn ) )->getFileName();
                }
            } catch ( Throwable $e ) {}
            if ( '' !== $file ) {
                $norm = wp_normalize_path( $file );
                if ( preg_match( '#/plugins/([^/]+)/#', $norm, $m ) ) { $origins[] = 'Plugin: ' . $m[1]; }
                elseif ( preg_match( '#/themes/([^/]+)/#', $norm, $m ) ) { $origins[] = 'Tema: ' . $m[1]; }
                elseif ( 0 === strpos( $norm, $root . '/mu-plugins/' ) ) { $origins[] = 'MU-Plugin: ' . basename( $norm ); }
                else { $origins[] = 'WordPress Core'; }
            }
        }
    }
    $origins = array_values( array_unique( $origins ) );
    $bad_pat = (bool) preg_match( '/(?:eval|base64|shell|backdoor|inject|hack|miner)/i', (string) $hook );
    return [
        'origin'     => ! empty( $origins ) ? implode( ', ', $origins ) : 'Callback desconocido',
        'suspicious' => ( empty( $origins ) || $bad_pat ),
    ];
}

function bap_get_scheduled_cron_events_list() {
    $crons = function_exists( '_get_cron_array' ) ? _get_cron_array() : [];
    if ( ! is_array( $crons ) ) { return []; }
    $list = [];
    foreach ( $crons as $ts => $hooks ) {
        if ( ! is_array( $hooks ) ) { continue; }
        foreach ( $hooks as $hook => $instances ) {
            $info = bap_cron_resolve_hook_origin( $hook );
            foreach ( (array) $instances as $sig => $cfg ) {
                $list[] = [
                    'ts'         => (int) $ts,
                    'next_run'   => wp_date( 'Y-m-d H:i:s', (int) $ts ),
                    'hook'       => (string) $hook,
                    'sig'        => (string) $sig,
                    'args'       => (array) ( $cfg['args'] ?? [] ),
                    'schedule'   => (string) ( $cfg['schedule'] ?: 'Una vez' ),
                    'origin'     => $info['origin'],
                    'suspicious' => $info['suspicious'],
                ];
            }
        }
    }
    usort( $list, fn( $a, $b ) => $a['ts'] <=> $b['ts'] );
    return $list;
}
function bap_render_wp_cron_inspector_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    if ( isset( $_POST['bap_delete_cron_event'] ) && check_admin_referer( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ) ) {
        $hook = sanitize_text_field( wp_unslash( $_POST['bap_cron_hook'] ?? '' ) );
        $ts   = (int) ( $_POST['bap_cron_ts'] ?? 0 );
        $args = json_decode( (string) wp_unslash( $_POST['bap_cron_args'] ?? '[]' ), true );
        if ( '' !== $hook && $ts > 0 && is_array( $args ) ) {
            wp_unschedule_event( $ts, $hook, $args );
            $notice = '<div class="notice notice-warning inline"><p>Tarea cron <code>' . esc_html( $hook ) . '</code> eliminada.</p></div>';
        }
    }
    if ( isset( $_POST['bap_run_cron_now'] ) && check_admin_referer( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ) ) {
        $hook = sanitize_text_field( wp_unslash( $_POST['bap_cron_hook'] ?? '' ) );
        $args = json_decode( (string) wp_unslash( $_POST['bap_cron_args'] ?? '[]' ), true );
        if ( '' !== $hook && is_array( $args ) ) {
            do_action_ref_array( $hook, $args );
            $notice = '<div class="notice notice-success inline"><p>Hook cron <code>' . esc_html( $hook ) . '</code> ejecutado manualmente.</p></div>';
        }
    }

    $events = bap_get_scheduled_cron_events_list();
    $core_protected = [ 'wp_version_check', 'wp_update_plugins', 'wp_update_themes', 'wp_scheduled_delete', 'wp_privacy_delete_old_export_files' ];
    ?>
    <div class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Inspector de Tareas Programadas (WP-Cron)</h2>
        <p class="description">Audita todas las tareas programadas en WordPress, identifica que plugin o tema las ejecuta y elimina eventos huerfanos o maliciosos ocultos.</p>
        <?php echo wp_kses_post( $notice ); ?>
        <div style="overflow-x:auto;margin-top:12px;">
            <table class="widefat striped">
                <thead><tr><th>Hook / Evento</th><th>Origen</th><th>Frecuencia</th><th>Proxima ejecucion</th><th style="width:155px;">Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ( $events as $ev ) :
                        $is_prot = in_array( $ev['hook'], $core_protected, true ) || 0 === strpos( $ev['hook'], 'bap_' );
                        $args_j  = wp_json_encode( $ev['args'] );
                    ?>
                        <tr>
                            <td>
                                <code><?php echo esc_html( $ev['hook'] ); ?></code>
                                <?php if ( $ev['suspicious'] ) : ?>
                                    <span style="background:#d63638;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;margin-left:4px;font-weight:700;">REVISAR</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo esc_html( $ev['origin'] ); ?></small></td>
                            <td><code><?php echo esc_html( $ev['schedule'] ); ?></code></td>
                            <td><code><?php echo esc_html( $ev['next_run'] ); ?></code></td>
                            <td style="white-space:nowrap;">
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ); ?>
                                    <input type="hidden" name="bap_cron_hook" value="<?php echo esc_attr( $ev['hook'] ); ?>" />
                                    <input type="hidden" name="bap_cron_ts" value="<?php echo (int) $ev['ts']; ?>" />
                                    <input type="hidden" name="bap_cron_args" value="<?php echo esc_attr( $args_j ); ?>" />
                                    <button type="submit" name="bap_run_cron_now" class="button button-small">Ejecutar</button>
                                    <?php if ( ! $is_prot ) : ?>
                                        <button type="submit" name="bap_delete_cron_event" class="button button-small" style="color:#d63638;" onclick="return confirm('Eliminar esta tarea programada?');">Borrar</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}