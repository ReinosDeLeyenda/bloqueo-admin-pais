<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_render_custom_integrity_box() {
    $notice = '';
    $res    = $GLOBALS['bap_all_scans_custom'] ?? get_transient( 'bap_last_custom_integrity_res' );
    if ( ! is_array( $res ) ) { $res = null; }

    if ( isset( $_POST['bap_refresh_custom_baseline'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_custom_base_action', 'bap_custom_base_nonce' ) ) {
        $saved  = bap_refresh_custom_integrity_baseline(); delete_transient( 'bap_last_custom_integrity_res' ); $res = null;
        $notice = '<div class="notice notice-success inline"><p><strong>Huella base SHA-256 guardada:</strong> Se han indexado <strong>' . number_format_i18n( $saved['count'] ) . '</strong> archivos de temas y plugins.</p></div>';
    }

    if ( isset( $_POST['bap_check_custom_baseline'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_custom_base_action', 'bap_custom_base_nonce' ) ) {
        $res = bap_verify_custom_integrity_baseline(); if ( is_array( $res ) ) { set_transient( 'bap_last_custom_integrity_res', $res, 30 * MINUTE_IN_SECONDS ); }
        if ( null === $res ) {
            $notice = '<div class="notice notice-warning inline"><p>Aún no has creado ninguna huella base. Pulsa <strong>Crear / Aprobar huella base SHA-256</strong> primero.</p></div>';
        }
    }
    ?>
    <div id="bap-box-custom-base" class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Monitor de Integridad para Temas y Plugins Privados (SHA-256)</h2>
        <p class="description">Guarda una referencia local SHA-256 de tus temas y plugins (incluidos los privados o de pago fuera de WordPress.org) para detectar archivos modificados, añadidos o eliminados.</p>
        <?php echo wp_kses_post( $notice ); ?>

        <form method="post" style="margin:14px 0;display:flex;gap:10px;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_custom_base_action', 'bap_custom_base_nonce' ); ?>
            <button type="submit" name="bap_check_custom_baseline" class="button button-primary">Verificar cambios ahora</button>
            <button type="submit" name="bap_refresh_custom_baseline" class="button button-secondary" onclick="return confirm('¿Guardar el estado actual de temas y plugins como nueva referencia limpia?');">Crear / Aprobar huella base SHA-256</button>
        </form>

        <?php if ( is_array( $res ) ) :
            $total_diff = count( $res['modified'] ) + count( $res['added'] ) + count( $res['deleted'] );
        ?>
            <?php if ( 0 === $total_diff ) : ?>
                <div class="notice notice-success inline"><p><strong>¡Sin cambios detectados!</strong> Se han verificado <strong><?php echo number_format_i18n( $res['checked'] ); ?></strong> archivos respecto a la referencia del <code><?php echo esc_html( $res['created_at'] ); ?></code>.</p></div>
            <?php else : ?>
                <div class="notice notice-error inline"><p><strong>¡Atención! Se han detectado <?php echo (int) $total_diff; ?> cambio(s)</strong> respecto a la huella base del <code><?php echo esc_html( $res['created_at'] ); ?></code>:</p></div>
                <details open style="margin-top:10px;border:1px solid #dcdcde;border-radius:6px;padding:10px 14px;background:#fff;"><summary style="cursor:pointer;font-weight:700;user-select:none;">&#9662; Mostrar / Ocultar lista de archivos modificados o nuevos (<?php echo (int) $total_diff; ?>)</summary><div style="margin-top:10px;max-height:360px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:4px;"><table class="widefat striped" style="border:0;">
                    <thead><tr><th style="width:140px;">Estado</th><th>Archivo</th></tr></thead>
                    <tbody>
                        <?php foreach ( $res['modified'] as $f ) : ?>
                            <tr><td><?php echo bap_render_status_badge( 'MODIFICADO', '#d63638' ); ?></td><td><code><?php echo esc_html( $f ); ?></code></td></tr>
                        <?php endforeach; ?>
                        <?php foreach ( $res['added'] as $f ) : ?>
                            <tr><td><?php echo bap_render_status_badge( 'AÑADIDO', '#d97706' ); ?></td><td><code><?php echo esc_html( $f ); ?></code></td></tr>
                        <?php endforeach; ?>
                        <?php foreach ( $res['deleted'] as $f ) : ?>
                            <tr><td><?php echo bap_render_status_badge( 'ELIMINADO', '#646970' ); ?></td><td><code><?php echo esc_html( $f ); ?></code></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div></details>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}