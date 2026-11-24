<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_default_blocked_user_agents() {
    return implode( "\n", [
        'sqlmap',
        'nikto',
        'wpscan',
        'acunetix',
        'nessus',
        'openvas',
        'nmap',
        'masscan',
        'zgrab',
        'gobuster',
        'dirbuster',
        'feroxbuster',
        'ffuf',
        'nuclei',
        'httpx',
        'python-requests',
        'python-urllib',
        'aiohttp',
        'scrapy',
        'curl/',
        'wget/',
        'libwww-perl',
        'go-http-client',
        'java/',
        'phantomjs',
        'headlesschrome',
        'ahrefsbot',
        'semrushbot',
        'mj12bot',
        'dotbot',
        'bytespider',
        'gptbot',
        'claudebot',
        'ccbot',
    ] );
}

function bap_match_blocked_user_agent( $ua ) {
    $raw = (string) get_option( 'bap_blocked_user_agents', '' );
    if ( '' === trim( $raw ) ) {
        return '';
    }

    $ua_lower = strtolower( (string) $ua );
    foreach ( preg_split( '/[\r\n,]+/', $raw ) as $token ) {
        $pat = strtolower( trim( $token ) );
        if ( '' !== $pat && false !== strpos( $ua_lower, $pat ) ) {
            return trim( $token );
        }
    }

    return '';
}

add_action( 'init', function() {
    if ( is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() ) {
        return;
    }

    $block_empty = ( '1' === (string) get_option( 'bap_block_empty_ua', '0' ) );
    $raw_list    = trim( (string) get_option( 'bap_blocked_user_agents', '' ) );
    if ( ! $block_empty && '' === $raw_list ) {
        return;
    }

    $ip = bap_get_user_ip();
    if ( '' === $ip || bap_is_server_ip( $ip ) ) {
        return;
    }
    if ( bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return;
    }
    if ( '1' === (string) get_option( 'bap_web_allow_seo_bots', '1' ) && function_exists( 'bap_is_verified_seo_bot' ) && bap_is_verified_seo_bot( $ip ) ) {
        return;
    }

    $ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
    $reason = '';

    if ( '' === $ua && $block_empty ) {
        $reason = 'Web: User-Agent vacío bloqueado';
    } elseif ( '' !== $ua ) {
        $matched_token = bap_match_blocked_user_agent( $ua );
        if ( '' !== $matched_token ) {
            $reason = 'Web: User-Agent sospechoso (' . $matched_token . ')';
        }
    }

    if ( '' === $reason ) {
        return;
    }

    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    bap_log_blocked_attempt( $ip, $country, $reason );

    $url = trim( (string) get_option( 'bap_web_redirect_url', '' ) );
    if ( '' !== $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
        wp_redirect( esc_url_raw( $url ), 302 );
        exit;
    }

    $msg = (string) get_option( 'bap_web_block_message', 'Este contenido no está disponible para tu cliente o navegador.' );
    if ( function_exists( 'bap_render_custom_403_page' ) ) {
        bap_render_custom_403_page( 'Acceso restringido', $msg, $ip, $country, $reason );
    }
    wp_die( '<h1>Acceso restringido</h1><p>' . esc_html( $msg ) . '</p>', 'Acceso Restringido', [ 'response' => 403 ] );
}, 3 );
