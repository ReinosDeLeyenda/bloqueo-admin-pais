<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'notify-general-fields.php';

function bap_render_notificaciones_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $sub      = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'general';
    $valid    = [ 'general' => 'General', 'email' => 'Plantillas Email', 'telegram' => 'Plantillas Telegram' ];
    if ( ! isset( $valid[ $sub ] ) ) {
        $sub = 'general';
    }
    $base_url = admin_url( 'admin.php?page=bap-notificaciones' );
    ?>
    <div class="wrap">
        <h1>Control de Acceso al Panel</h1>
        <?php bap_render_tabs( 'bap-notificaciones' ); ?>

        <ul class="subsubsub" style="margin:0 0 16px 0;font-size:13px;float:none;">
            <?php
            $items = [];
            foreach ( $valid as $s_key => $s_lbl ) {
                $url   = add_query_arg( 'subtab', $s_key, $base_url );
                $cls   = ( $sub === $s_key ) ? ' class="current"' : '';
                $items[] = '<li><a href="' . esc_url( $url ) . '"' . $cls . '>' . esc_html( $s_lbl ) . '</a></li>';
            }
            echo implode( ' | ', $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </ul>

        <?php if ( 'email' === $sub ) : ?>
            <?php if ( function_exists( 'bap_render_email_templates_subtab' ) ) { bap_render_email_templates_subtab(); } ?>
        <?php elseif ( 'telegram' === $sub ) : ?>
            <?php if ( function_exists( 'bap_render_tg_templates_subtab' ) ) { bap_render_tg_templates_subtab(); } ?>
        <?php else :
            $test_notice = function_exists( 'bap_handle_test_email_post' ) ? bap_handle_test_email_post() : '';
            wp_enqueue_script( 'bap-admin-bloqueos', plugins_url( 'backend/bloqueoa/admin-bloqueos.js', dirname( __DIR__, 2 ) . '/bloqueo-admin-pais.php' ), [], '12.3', true );
        ?>
            <?php settings_errors(); ?>
            <?php echo wp_kses_post( $test_notice ); ?>

            <div class="card" style="max-width:960px;padding:16px 24px;margin-top:8px;">
                <h2 style="margin-top:0;">Canales de Alerta y Avisos de Conexión</h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'bap_notify_settings_group' ); ?>
                    <table class="form-table">
                        <?php bap_render_only_notify_fields(); ?>
                    </table>
                    <?php submit_button( 'Guardar configuración de notificaciones' ); ?>
                </form>
            </div>

            <form id="bap_test_email_form" method="post" style="display:none;">
                <?php wp_nonce_field( 'bap_test_email_action' ); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}