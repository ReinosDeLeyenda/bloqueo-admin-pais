<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_plantillas_hub_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $sub   = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'email';
    $valid = [ 'email' => 'Email', 'telegram' => 'Telegram' ];
    if ( ! isset( $valid[ $sub ] ) ) {
        $sub = 'email';
    }
    $base_url = admin_url( 'admin.php?page=bap-plantillas' );
    ?>
    <div class="wrap">
        <h1>Control de Acceso al Panel</h1>
        <?php bap_render_tabs( 'bap-plantillas' ); ?>

        <?php bap_render_subtabs( $base_url, $valid, $sub ); ?>

        <?php
        if ( 'telegram' === $sub ) {
            if ( function_exists( 'bap_render_tg_templates_subtab' ) ) { bap_render_tg_templates_subtab(); }
        } else {
            if ( function_exists( 'bap_render_email_templates_subtab' ) ) { bap_render_email_templates_subtab(); }
        }
        ?>
    </div>
    <?php
}