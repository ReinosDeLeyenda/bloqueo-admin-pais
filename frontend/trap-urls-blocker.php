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
    if ( '' === $ip || bap_is_server_ip( $ip ) ) {
        return;
    }
    if ( bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return;
    }
    if ( '1' === (string) get_option( 'bap_web_allow_seo_bots', '1' ) && function_exists( 'bap_is_verified_seo_bot' ) && bap_is_verified_seo_bot( $ip ) ) {
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

    // Auto-baneo instantáneo en Lista Negra
    $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
    if ( ! in_array( $ip, $blacklist, true ) ) {
        $blacklist[] = $ip;
        update_option( 'bap_blacklist_ips', implode( "\n", $blacklist ) );

        $duration_hours = (int) get_option( 'bap_auto_ban_duration', 24 );
        if ( $duration_hours > 0 ) {
            $expirations        = get_option( 'bap_auto_ban_expirations', [] );
            $expirations[ $ip ] = time() + ( $duration_hours * HOUR_IN_SECONDS );
            update_option( 'bap_auto_ban_expirations', $expirations, false );
        }
    }

    $msg = (string) get_option( 'bap_web_block_message', 'Acceso bloqueado por seguridad.' );
    if ( function_exists( 'bap_render_custom_403_page' ) ) {
        bap_render_custom_403_page( 'Acceso denegado', $msg, $ip, $country, $reason );
    }
    wp_die( '<h1>Acceso denegado</h1><p>' . esc_html( $msg ) . '</p>', 'Acceso Restringido', [ 'response' => 403 ] );
}, 1 );