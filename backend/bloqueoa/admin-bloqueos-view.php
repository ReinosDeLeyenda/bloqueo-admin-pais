<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once plugin_dir_path( __FILE__ ) . 'admin-bloqueos-fields-rules.php';
require_once dirname( __DIR__ ) . '/notificaciones/admin-bloqueos-fields-notify.php';
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-admin-bloqueos' ); ?>
    <?php settings_errors(); ?>
    <?php echo $test_notice; ?>

    <div class="notice notice-info inline">
        <p><strong>Tu conexión actual:</strong> IP <code><?php echo esc_html( $ip ); ?></code> | País <code><?php echo esc_html( $ctry ); ?></code> <?php echo $src ? '(' . esc_html( $src ) . ')' : ''; ?> | ASN <code><?php echo esc_html( bap_get_asn_by_ip( $ip ) ); ?></code></p>
    </div>

    <?php if ( function_exists( 'bap_render_ip_simulator_box' ) ) { bap_render_ip_simulator_box(); } ?>

    <div class="card" style="max-width:960px;padding:16px 24px;margin-top:12px;">
        <form id="bap_rules_form" method="post" action="options.php">
            <?php settings_fields( 'bap_settings_group' ); ?>
            <table class="form-table">
                <?php bap_render_bloqueos_rules_fields( $bl_raw ); ?>
                <?php bap_render_bloqueos_notify_and_auth_fields(); ?>
            </table>
            <?php submit_button( 'Guardar reglas de bloqueo' ); ?>
        </form>
    </div>

    <form id="bap_test_email_form" method="post" style="display:none;">
        <?php wp_nonce_field( 'bap_test_email_action' ); ?>
    </form>
</div>
