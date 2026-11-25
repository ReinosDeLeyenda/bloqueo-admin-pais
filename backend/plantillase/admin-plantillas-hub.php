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

        <ul class="subsubsub" style="margin:0 0 16px 0;font-size:13px;float:none;">
            <?php
            $links = [];
            foreach ( $valid as $s_key => $s_lbl ) {
                $url     = add_query_arg( 'subtab', $s_key, $base_url );
                $cls     = ( $sub === $s_key ) ? ' class="current"' : '';
                $links[] = '<li><a href="' . esc_url( $url ) . '"' . $cls . '>' . esc_html( $s_lbl ) . '</a></li>';
            }
            echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </ul>

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