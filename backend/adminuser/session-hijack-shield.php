<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_session_compute_fingerprint() {
    $ip  = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    $net = function_exists( 'bap_get_ip_subnet_cidr' ) ? bap_get_ip_subnet_cidr( $ip ) : $ip;
    if ( '' === $net ) { $net = $ip; }
    $ua  = substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 160 );
    return hash_hmac( 'sha256', $net . '|' . $ua, AUTH_SALT );
}

add_filter( 'attach_session_information', function( $session ) {
    $session['bap_fp'] = bap_session_compute_fingerprint();
    return $session;
}, 10, 1 );

add_action( 'init', function() {
    if ( '1' !== (string) get_option( 'bap_session_hijack_shield', '0' ) ) { return; }
    if ( ! is_user_logged_in() || wp_doing_cron() ) { return; }

    $token = wp_get_session_token();
    if ( '' === $token || ! class_exists( 'WP_Session_Tokens' ) ) { return; }

    $uid     = get_current_user_id();
    $manager = WP_Session_Tokens::get_instance( $uid );
    $sess    = $manager->get( $token );
    if ( ! is_array( $sess ) || empty( $sess['bap_fp'] ) ) { return; }

    $current_fp = bap_session_compute_fingerprint();
    if ( ! hash_equals( (string) $sess['bap_fp'], $current_fp ) ) {
        $manager->destroy( $token );
        wp_clear_auth_cookie();

        $ip      = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
        $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
        if ( function_exists( 'bap_log_blocked_attempt' ) ) {
            bap_log_blocked_attempt( $ip, $country, 'Secuestro de sesion bloqueado (Cambio IP/UA en cookie)' );
        }

        wp_safe_redirect( add_query_arg( 'bap_session_hijack', '1', wp_login_url() ) );
        exit;
    }
}, 4 );
add_filter( 'login_message', function( $msg ) {
    if ( ! empty( $_GET['bap_session_hijack'] ) ) {
        $msg .= '<div id="login_error"><strong>Seguridad:</strong> Tu sesion se ha cerrado automaticamente porque cambio tu red IP o navegador.</div>';
    }
    return $msg;
} );
