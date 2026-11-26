<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_custom_login_slug() {
    $raw  = trim( (string) get_option( 'bap_custom_login_slug', '' ), " \t\n\r\0\x0B/" );
    $slug = sanitize_title( $raw );
    $reserved = [ 'wp-admin', 'wp-login', 'wp-login.php', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'xmlrpc.php' ];
    if ( '' === $slug || in_array( $slug, $reserved, true ) ) {
        return '';
    }
    return $slug;
}

function bap_is_custom_login_request() {
    $slug = bap_get_custom_login_slug();
    if ( '' === $slug ) {
        return false;
    }
    $req_uri  = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( (string) wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $req_path = trim( (string) wp_parse_url( $req_uri, PHP_URL_PATH ), '/' );
    $home_path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
    if ( '' !== $home_path && 0 === strpos( $req_path, $home_path ) ) {
        $req_path = trim( substr( $req_path, strlen( $home_path ) ), '/' );
    }
    return ( $req_path === $slug );
}

add_action( 'plugins_loaded', function() {
    $slug = bap_get_custom_login_slug();
    if ( '' === $slug ) {
        return;
    }

    // 1. Si se solicita la URL secreta personalizada, cargar wp-login.php internamente
    if ( bap_is_custom_login_request() ) {
        global $pagenow;
        $pagenow                 = 'wp-login.php';
        $_SERVER['SCRIPT_NAME']  = '/wp-login.php';
        $_SERVER['PHP_SELF']     = '/wp-login.php';
        define( 'BAP_CUSTOM_LOGIN_SERVED', true );

        add_action( 'init', function() {
            global $error, $interim_login, $action, $user_login;
            require_once ABSPATH . 'wp-login.php';
            exit;
        }, 2 );
    }
}, 1 );

// 2. Bloquear acceso directo a /wp-login.php o /wp-admin para no autenticados
add_action( 'init', function() {
    $slug = bap_get_custom_login_slug();
    if ( '' === $slug || defined( 'BAP_CUSTOM_LOGIN_SERVED' ) || is_user_logged_in() ) {
        return;
    }
    if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    if ( bap_has_valid_secret_bypass( false ) || ( function_exists( 'bap_is_guest_demo_session' ) && bap_is_guest_demo_session() ) ) {
        return;
    }

    $ip = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
    if ( '' !== $ip && ( bap_is_ip_whitelisted_or_server( $ip ) ) ) {
        return;
    }

    global $pagenow;
    $req_uri  = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( rawurldecode( (string) wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
    $req_path = (string) wp_parse_url( $req_uri, PHP_URL_PATH );

    $is_direct_login = ( 'wp-login.php' === $pagenow || false !== strpos( $req_path, '/wp-login.php' ) );
    $is_direct_admin = ( is_admin() && false === strpos( $req_path, '/admin-ajax.php' ) && false === strpos( $req_path, '/admin-post.php' ) );

    if ( $is_direct_login || $is_direct_admin ) {
        $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
        bap_deny_access( $ip, $country, 'Acceso directo bloqueado a URL oculta de login' );
    }
}, 1 );

// 3. Reescribir todas las URLs internas de wp-login.php al nuevo slug
function bap_rewrite_login_url_to_custom_slug( $url ) {
    $slug = bap_get_custom_login_slug();
    if ( '' === $slug || false === strpos( $url, 'wp-login.php' ) ) {
        return $url;
    }
    $parsed = wp_parse_url( $url );
    $new    = home_url( '/' . $slug );
    if ( ! empty( $parsed['query'] ) ) {
        $new .= '?' . $parsed['query'];
    }
    return $new;
}

add_filter( 'site_url', function( $url, $path ) {
    if ( false !== strpos( (string) $path, 'wp-login.php' ) ) {
        return bap_rewrite_login_url_to_custom_slug( $url );
    }
    return $url;
}, 10, 2 );

add_filter( 'network_site_url', function( $url, $path ) {
    if ( false !== strpos( (string) $path, 'wp-login.php' ) ) {
        return bap_rewrite_login_url_to_custom_slug( $url );
    }
    return $url;
}, 10, 2 );

add_filter( 'wp_redirect', function( $location ) {
    if ( false !== strpos( (string) $location, 'wp-login.php' ) ) {
        return bap_rewrite_login_url_to_custom_slug( $location );
    }
    return $location;
}, 10 );