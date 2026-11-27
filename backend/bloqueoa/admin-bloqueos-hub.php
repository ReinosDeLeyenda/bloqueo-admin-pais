<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'ip-source-field.php';
require_once plugin_dir_path( __FILE__ ) . 'admin-bloqueos-fields-rules.php';
require_once dirname( __DIR__ ) . '/notificaciones/admin-bloqueos-fields-notify.php';
require_once dirname( __DIR__ ) . '/notificaciones/notify-general-fields.php';

function bap_render_bloqueos_hub_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $sub   = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'general';
    $valid = [
        'general'  => 'General',
        'acceso'   => 'Acceso e IP',
        'login'    => 'Login y Autenticación',
        'frontend' => 'Frontend',
    ];
    if ( ! isset( $valid[ $sub ] ) ) {
        $sub = 'general';
    }
    $base_url = admin_url( 'admin.php?page=bap-bloqueos' );
    ?>
    <div class="wrap">
        <h1>Control de Acceso al Panel</h1>
        <?php bap_render_tabs( 'bap-bloqueos' ); ?>

        <?php bap_render_subtabs( $base_url, $valid, $sub ); ?>
        <?php if ( 'acceso' === $sub ) :
            $ip     = bap_get_user_ip();
            $src    = '';
            $ctry   = bap_is_server_ip( $ip ) ? 'SERVIDOR LOCAL' : bap_get_country_by_ip( $ip, $src );
            $bl_raw = get_option( 'bap_blacklist_ips', '' );
            $bl_ips = bap_parse_list( $bl_raw, false );
            $ip_meta = function_exists( 'bap_build_blacklist_tooltip_meta' ) ? bap_build_blacklist_tooltip_meta( $bl_ips ) : [];

            wp_enqueue_script( 'bap-admin-bloqueos', plugin_dir_url( __FILE__ ) . 'admin-bloqueos.js', [], '12.4', true );
            wp_enqueue_script( 'bap-countries-data', plugins_url( 'backend/comun/countries-data.js', dirname( __DIR__, 2 ) . '/bloqueo-admin-pais.php' ), [], '1.0', true );
            wp_enqueue_script( 'bap-countries-picker', plugins_url( 'backend/comun/countries-picker.js', dirname( __DIR__, 2 ) . '/bloqueo-admin-pais.php' ), [ 'bap-countries-data' ], '13.2', true );
            wp_localize_script( 'bap-admin-bloqueos', 'bapAdminData', [ 'ipMeta' => $ip_meta ] );
        ?>
            <?php settings_errors(); ?>
            <div class="notice notice-info inline">
                <p><strong>Tu conexión actual:</strong> IP <code><?php echo esc_html( $ip ); ?></code> | País <code><?php echo esc_html( $ctry ); ?></code> <?php echo $src ? '(' . esc_html( $src ) . ')' : ''; ?> | ASN <code><?php echo esc_html( bap_get_asn_by_ip( $ip ) ); ?></code></p>
            </div>

            <div class="card" style="max-width:960px;padding:16px 24px;margin-top:12px;">
                <form id="bap_rules_form" method="post" action="options.php">
                    <?php settings_fields( 'bap_settings_group' ); ?>
                    <table class="form-table">
                        <?php bap_render_bloqueos_rules_fields( $bl_raw ); ?>
                    </table>
                    <?php submit_button( 'Guardar reglas de Acceso e IP' ); ?>
                </form>
            </div>

        <?php elseif ( 'login' === $sub ) : ?>
            <?php settings_errors(); ?>
            <div class="card" style="max-width:960px;padding:16px 24px;margin-top:12px;">
                <form method="post" action="options.php">
                    <?php settings_fields( 'bap_login_settings_group' ); ?>
                    <table class="form-table">
                        <?php if ( function_exists( 'bap_render_bloqueos_login_extra_fields' ) ) { bap_render_bloqueos_login_extra_fields(); } ?>
                        <?php bap_render_bloqueos_auth_fields(); ?>
                    </table>
                    <?php submit_button( 'Guardar ajustes de Login y Autenticación' ); ?>
                </form>
            </div>
            <?php if ( function_exists( 'bap_render_vacation_mode_box' ) ) { bap_render_vacation_mode_box(); } ?>


        <?php elseif ( 'frontend' === $sub ) :
            if ( function_exists( 'bap_render_web_bloqueos_subtab' ) ) {
                bap_render_web_bloqueos_subtab();
            }
        ?>

        <?php else : ?>
            <?php settings_errors(); ?>

            <div class="card" style="max-width:960px;padding:16px 24px;margin-top:8px;">
                <h2 style="margin-top:0;">Detección de IP del Visitante</h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'bap_general_settings_group' ); ?>
                    <table class="form-table">
                        <?php bap_render_general_ip_source_field(); ?>
                    </table>
                    <?php submit_button( 'Guardar detección de IP' ); ?>
                </form>
            </div>

            <?php if ( function_exists( 'bap_render_ip_simulator_box' ) ) { bap_render_ip_simulator_box(); } ?>
            <?php if ( function_exists( 'bap_render_under_attack_panic_box' ) ) { bap_render_under_attack_panic_box(); } ?>
            <?php if ( function_exists( 'bap_render_guest_demo_card' ) ) { bap_render_guest_demo_card(); } ?>
        <?php endif; ?>
        <?php if ( function_exists( 'bap_render_post_db_recommendations_box' ) ) { bap_render_post_db_recommendations_box(); } ?>
    </div>
    <?php
}