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

        <div style="display:flex;gap:8px;margin:0 0 18px 0;flex-wrap:wrap;">
            <?php foreach ( $valid as $s_key => $s_lbl ) :
                $url = add_query_arg( 'subtab', $s_key, $base_url );
                $cls = ( $sub === $s_key ) ? 'button button-primary' : 'button button-secondary';
            ?>
                <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $cls ); ?>">
                    <?php echo esc_html( $s_lbl ); ?>
                </a>
            <?php endforeach; ?>
        </div>

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