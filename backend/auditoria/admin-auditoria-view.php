<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_auditoria_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Control de Acceso al Panel</h1>
        <?php bap_render_tabs( 'bap-auditoria' ); ?>

        <?php if ( function_exists( 'bap_render_core_integrity_box' ) ) { bap_render_core_integrity_box(); } ?>
        <?php if ( function_exists( 'bap_render_malware_scanner_box' ) ) { bap_render_malware_scanner_box(); } ?>
        <?php if ( function_exists( 'bap_render_abandoned_plugins_box' ) ) { bap_render_abandoned_plugins_box(); } ?>
        <?php if ( function_exists( 'bap_render_db_injection_and_cron_box' ) ) { bap_render_db_injection_and_cron_box(); } ?>
        <?php if ( function_exists( 'bap_render_hardening_box' ) ) { bap_render_hardening_box(); } ?>
        <?php if ( function_exists( 'bap_render_file_permissions_box' ) ) { bap_render_file_permissions_box(); } ?>
        <?php if ( function_exists( 'bap_render_ssl_and_config_monitor_box' ) ) { bap_render_ssl_and_config_monitor_box(); } ?>
        <?php if ( function_exists( 'bap_render_emergency_mode_box' ) ) { bap_render_emergency_mode_box(); } ?>
    </div>
    <?php
}