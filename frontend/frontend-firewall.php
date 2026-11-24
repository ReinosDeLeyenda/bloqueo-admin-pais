<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_check_frontend_rules() {
    if ( is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() ) {
        return;
    }
    if ( '1' === (string) get_option( 'bap_web_allow_seo_bots', '1' ) && function_exists( 'bap_is_verified_seo_bot' ) && bap_is_verified_seo_bot() ) {
        return;
    }

    $uri     = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
    $matched = bap_match_web_rule_for_uri( $uri );
    $rule    = $matched['rule'];

    if ( '1' !== (string) ( $rule['enabled'] ?? '0' ) ) {
        return;
    }

    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return;
    }

    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';

    if ( function_exists( 'bap_get_country_redirect_url' ) ) {
        $custom_redir = bap_get_country_redirect_url( $country );
        if ( '' !== $custom_redir ) {
            wp_redirect( esc_url_raw( $custom_redir ), 302 );
            exit;
        }
    }

    $verdict = bap_evaluate_rule_profile( $ip, $country, $rule, false );
    if ( 'block' === $verdict['status'] ) {
        $scope_tag = ( '__all__' === $matched['scope'] ) ? 'Web Global' : 'Web (' . $matched['scope'] . ')';
        $reason    = $verdict['reason'];
        bap_log_blocked_attempt( $ip, $country, $scope_tag . ': ' . $reason );

        // 1. Prioridad: URL de redirección propia de esta regla -> respaldo global
        $url = trim( (string) ( $rule['redirect_url'] ?? '' ) );
        if ( '' === $url ) {
            $url = trim( (string) get_option( 'bap_web_redirect_url', '' ) );
        }
        if ( '' !== $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
            wp_redirect( esc_url_raw( $url ), 302 );
            exit;
        }

        // 2. Prioridad: Mensaje 403 propio de esta regla -> respaldo global
        $msg = trim( (string) ( $rule['block_message'] ?? '' ) );
        if ( '' === $msg ) {
            $msg = (string) get_option( 'bap_web_block_message', 'Este contenido no está disponible en tu ubicación o red.' );
        }

        if ( function_exists( 'bap_render_custom_403_page' ) ) {
            bap_render_custom_403_page( 'Acceso restringido', $msg, $ip, $country, $reason );
        }
        wp_die( '<h1>Acceso restringido</h1><p>' . esc_html( $msg ) . '</p>', 'Acceso Restringido', [ 'response' => 403 ] );
    }
}
