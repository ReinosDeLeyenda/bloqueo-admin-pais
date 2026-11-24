<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_login_failed', function( $username ) {
    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return;
    }
    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    bap_log_blocked_attempt( $ip, $country, 'Login fallido (' . sanitize_user( $username ) . ')' );
});

add_filter( 'authenticate', function( $user, $username, $password ) {
    if ( '' === trim( (string) $username ) || '1' !== (string) get_option( 'bap_block_nonexistent_users', '1' ) ) {
        return $user;
    }

    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return $user;
    }

    $clean_user = sanitize_text_field( wp_unslash( $username ) );
    $exists     = username_exists( $clean_user ) || ( is_email( $clean_user ) && email_exists( $clean_user ) );

    if ( ! $exists ) {
        $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
        $label   = mb_substr( sanitize_user( $clean_user, true ), 0, 40 );
        bap_deny_access( $ip, $country, 'Usuario inexistente: ' . ( '' !== $label ? $label : 'desconocido' ) );
    }

    return $user;
}, 30, 3 );

add_action( 'init', function() {
    if ( ! bap_is_protected_request() ) {
        if ( function_exists( 'bap_check_frontend_rules' ) ) {
            bap_check_frontend_rules();
        }
        return;
    }

    $secret = trim( (string) get_option( 'bap_secret_key', '' ) );
    if ( '' !== $secret ) {
        $cname = 'bap_bypass_' . md5( $secret );
        $token = hash( 'sha256', $secret . AUTH_SALT );
        $uri   = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $redir = isset( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : '';
        if ( isset( $_GET[ $secret ] ) || false !== strpos( $uri, $secret ) || false !== strpos( $redir, $secret ) ) {
            if ( ! headers_sent() ) {
                setcookie( $cname, $token, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            }
            return;
        }
        if ( isset( $_COOKIE[ $cname ] ) && hash_equals( $token, $_COOKIE[ $cname ] ) ) {
            return;
        }
    }

    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return;
    }

    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    $verdict = bap_evaluate_ip_access( $ip, $country, 'admin', true );

    if ( 'block' === $verdict['status'] ) {
        bap_deny_access( $ip, $country, $verdict['reason'] );
    }
}, 1 );
