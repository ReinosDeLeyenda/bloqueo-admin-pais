<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_handle_web_rule_actions() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return [ 'notice' => '', 'selected' => '__all__', 'rules' => bap_get_web_rules() ];
    }

    $rules    = bap_get_web_rules();
    $selected = isset( $_GET['rule_scope'] ) ? sanitize_text_field( wp_unslash( $_GET['rule_scope'] ) ) : '__all__';
    $notice   = '';

    if ( isset( $_POST['bap_add_web_url'] ) && check_admin_referer( 'bap_web_rules_nonce' ) ) {
        $new_url = trim( (string) wp_unslash( $_POST['bap_new_web_url'] ?? '' ) );
        if ( '' !== $new_url ) {
            $parsed_path = wp_parse_url( $new_url, PHP_URL_PATH );
            $clean_path  = '/' . ltrim( (string) ( $parsed_path ?: $new_url ), '/' );
            if ( ! isset( $rules[ $clean_path ] ) ) {
                $rules[ $clean_path ] = bap_get_default_web_rule( '1' );
                update_option( 'bap_web_rules', $rules );
            }
            $selected = $clean_path;
            $notice   = '<div class="notice notice-success is-dismissible"><p>Ámbito <strong>' . esc_html( $clean_path ) . '</strong> añadido. Configura sus filtros abajo y pulsa Guardar.</p></div>';
        }
    }

    if ( isset( $_POST['bap_delete_web_url'] ) && check_admin_referer( 'bap_web_rules_nonce' ) ) {
        $del_scope = sanitize_text_field( wp_unslash( $_POST['bap_active_scope'] ?? '' ) );
        if ( '' !== $del_scope && '__all__' !== $del_scope && isset( $rules[ $del_scope ] ) ) {
            unset( $rules[ $del_scope ] );
            update_option( 'bap_web_rules', $rules );
            $selected = '__all__';
            $notice   = '<div class="notice notice-warning is-dismissible"><p>Regla para <strong>' . esc_html( $del_scope ) . '</strong> eliminada.</p></div>';
        }
    }

    if ( isset( $_POST['bap_save_web_rule'] ) && check_admin_referer( 'bap_web_rules_nonce' ) ) {
        $scope = sanitize_text_field( wp_unslash( $_POST['bap_active_scope'] ?? '__all__' ) );
        if ( '__all__' !== $scope && ! isset( $rules[ $scope ] ) ) {
            $scope = '__all__';
        }

        update_option( 'bap_waf_enabled', ( '1' === (string) ( $_POST['bap_waf_enabled'] ?? '0' ) ) ? '1' : '0' );
        update_option( 'bap_waf_autoban', ( '1' === (string) ( $_POST['bap_waf_autoban'] ?? '0' ) ) ? '1' : '0' );

        $cmode   = ( 'block' === ( $_POST['bap_rule_country_mode'] ?? 'allow' ) ) ? 'block' : 'allow';
        $amode   = ( 'allow' === ( $_POST['bap_rule_asn_mode'] ?? 'block' ) ) ? 'allow' : 'block';
        $raw_ord = explode( ',', sanitize_text_field( wp_unslash( $_POST['bap_rule_order'] ?? '' ) ) );
        $raw_ops = (array) ( $_POST['bap_rule_ops'] ?? [] );

        $rules[ $scope ] = bap_normalize_web_rule( [
            'enabled'       => ( '1' === (string) ( $_POST['bap_rule_enabled'] ?? '0' ) ) ? '1' : '0',
            'order'         => array_map( 'trim', $raw_ord ),
            'ops'           => array_map( 'sanitize_text_field', wp_unslash( $raw_ops ) ),
            'country_mode'  => $cmode,
            'countries'     => implode( ', ', bap_parse_list( wp_unslash( $_POST['bap_rule_countries'] ?? '' ), true ) ),
            'asn_mode'      => $amode,
            'asns'          => function_exists( 'bap_sanitize_asn_list' )
                ? bap_sanitize_asn_list( wp_unslash( $_POST['bap_rule_asns'] ?? '' ) )
                : implode( "\n", bap_parse_list( wp_unslash( $_POST['bap_rule_asns'] ?? '' ), true ) ),
            'abuse_risk'    => max( 0, min( 100, (int) ( $_POST['bap_rule_abuse_risk'] ?? 0 ) ) ),
            'block_tor'     => ( '1' === (string) ( $_POST['bap_rule_block_tor'] ?? '0' ) ) ? '1' : '0',
            'block_firehol' => ( '1' === (string) ( $_POST['bap_rule_block_firehol'] ?? '0' ) ) ? '1' : '0',
            'redirect_url'  => esc_url_raw( trim( (string) wp_unslash( $_POST['bap_rule_redirect_url'] ?? '' ) ) ),
            'block_message' => sanitize_text_field( trim( (string) wp_unslash( $_POST['bap_rule_block_message'] ?? '' ) ) ),
        ] );

        update_option( 'bap_web_rules', $rules );

        // Guardar también los ajustes generales agrupados
        $allow_seo = ( '1' === (string) ( $_POST['bap_web_allow_seo_bots'] ?? '0' ) ) ? '1' : '0';
        update_option( 'bap_web_allow_seo_bots', $allow_seo );

        $raw_redir = (string) wp_unslash( $_POST['bap_web_country_redirects'] ?? '' );
        if ( function_exists( 'bap_sanitize_country_redirects' ) ) {
            $raw_redir = bap_sanitize_country_redirects( $raw_redir );
        }
        update_option( 'bap_web_country_redirects', $raw_redir );
        update_option( 'bap_rl_max_req_min', max( 0, min( 5000, (int) ( $_POST['bap_rl_max_req_min'] ?? 0 ) ) ) );
        update_option( 'bap_rl_max_404_min', max( 0, min( 1000, (int) ( $_POST['bap_rl_max_404_min'] ?? 0 ) ) ) );
        update_option( 'bap_rl_autoban', ( '1' === (string) ( $_POST['bap_rl_autoban'] ?? '0' ) ) ? '1' : '0' );
        $ua_lines = [];
        foreach ( preg_split( '/[\r\n,]+/', (string) wp_unslash( $_POST['bap_blocked_user_agents'] ?? '' ) ) as $ua_item ) {
            $clean_ua = trim( sanitize_text_field( $ua_item ) );
            if ( '' !== $clean_ua ) {
                $ua_lines[] = $clean_ua;
            }
        }
        update_option( 'bap_blocked_user_agents', implode( "\n", array_unique( $ua_lines ) ) );
        update_option( 'bap_block_empty_ua', ( '1' === (string) ( $_POST['bap_block_empty_ua'] ?? '0' ) ) ? '1' : '0' );
        update_option( 'bap_honeypot_enabled', ( '1' === (string) ( $_POST['bap_honeypot_enabled'] ?? '0' ) ) ? '1' : '0' );
        update_option( 'bap_hp_autoban', ( '1' === (string) ( $_POST['bap_hp_autoban'] ?? '0' ) ) ? '1' : '0' );

        $selected = $scope;
        $label    = ( '__all__' === $scope ) ? 'Toda la web' : $scope;
        $notice   = '<div class="notice notice-success is-dismissible"><p><strong>Configuración guardada</strong> para <code>' . esc_html( $label ) . '</code> y ajustes generales.</p></div>';
    }

    if ( ! isset( $rules[ $selected ] ) ) {
        $selected = '__all__';
    }

    return [
        'notice'   => $notice,
        'selected' => $selected,
        'rules'    => $rules,
    ];
}

function bap_render_web_bloqueos_page() {
    require_once plugin_dir_path( __FILE__ ) . 'web-bloqueos-helpers.php';

    $state    = bap_handle_web_rule_actions();
    $notice   = $state['notice'];
    $selected = $state['selected'];
    $rules    = $state['rules'];
    $current  = bap_normalize_web_rule( (array) ( $rules[ $selected ] ?? [] ) );

    wp_enqueue_script( 'bap-countries-data', plugins_url( 'backend/comun/countries-data.js', dirname( __DIR__, 2 ) . '/bloqueo-admin-pais.php' ), [], '1.0', true );
    wp_enqueue_script( 'bap-countries-picker', plugins_url( 'backend/comun/countries-picker.js', dirname( __DIR__, 2 ) . '/bloqueo-admin-pais.php' ), [ 'bap-countries-data' ], '13.4', true );
    wp_enqueue_script( 'bap-web-logic-switch', plugin_dir_url( __FILE__ ) . 'web-logic-switch.js', [], '1.2', true );

    require plugin_dir_path( __FILE__ ) . 'web-bloqueos-view.php';
}
