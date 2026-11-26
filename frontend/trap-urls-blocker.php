<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_get_default_trap_urls() {
    return implode( "\n", [
        '/.env',
        '/.git/config',
        '/wp-config.php.bak',
        '/wp-config.bak',
        '/wp-config.old',
        '/wp-config.save',
        '/backup.sql',
        '/backup.zip',
        '/db.sql',
        '/dump.sql',
        '/phpmyadmin/*',
        '/pma/*',
        '/adminer.php',
        '/shell.php',
        '/c99.php',
        '/wso.php',
        '/alfa.php',
        '/wp-content/plugins/*/readme.txt',
        '/wp-content/plugins/*/changelog.txt',
    ] );
}

add_action( 'init', function() {
    if ( is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() ) {
        return;
    }

    $raw = trim( (string) get_option( 'bap_trap_urls', '' ) );
    if ( '' === $raw ) {
        return;
    }

    $ip = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
    if ( bap_is_frontend_request_exempt( $ip ) ) {
        return;
    }

    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
    $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
    if ( '' === $path ) {
        $path = $uri;
    }

    $matched = '';
    foreach ( bap_parse_list( $raw, false ) as $pat ) {
        $p = strtolower( trim( $pat ) );
        if ( '' === $p ) {
            continue;
        }
        if ( '/' !== $p[0] && '*' !== $p[0] ) {
            $p = '/' . $p;
        }
        $regex = '#^' . str_replace( '\*', '.*', preg_quote( $p, '#' ) ) . '$#i';
        if ( preg_match( $regex, $path ) || ( '/*' === substr( $p, -2 ) && rtrim( $path, '/' ) === substr( $p, 0, -2 ) ) ) {
            $matched = $pat;
            break;
        }
    }

    if ( '' === $matched ) {
        return;
    }

    $reason  = 'Web: Ruta Trampa activada (' . $matched . ')';
    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    bap_log_blocked_attempt( $ip, $country, $reason );

    bap_add_ip_to_blacklist( $ip, true );

    $msg = (string) get_option( 'bap_web_block_message', 'Acceso bloqueado por seguridad.' );
    if ( function_exists( 'bap_render_custom_403_page' ) ) {
        bap_render_custom_403_page( 'Acceso denegado', $msg, $ip, $country, $reason );
    }
    wp_die( '<h1>Acceso denegado</h1><p>' . esc_html( $msg ) . '</p>', 'Acceso Restringido', [ 'response' => 403 ] );
}, 1 );