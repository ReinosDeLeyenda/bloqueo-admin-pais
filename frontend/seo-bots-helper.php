<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_is_verified_seo_bot() {
    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
    if ( '' === $ua ) {
        return false;
    }

    $bot_domains = [
        'googlebot'   => [ '.googlebot.com', '.google.com' ],
        'bingbot'     => [ '.search.msn.com' ],
        'applebot'    => [ '.applebot.apple.com' ],
        'duckduckbot' => [ '.duckduckgo.com' ],
    ];

    $matched_domains = null;
    foreach ( $bot_domains as $sig => $domains ) {
        if ( false !== strpos( $ua, $sig ) ) {
            $matched_domains = $domains;
            break;
        }
    }
    if ( null === $matched_domains ) {
        return false;
    }

    $ip = bap_get_user_ip();
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return false;
    }

    $cache_key = 'bap_rdns_' . md5( $ip );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return ( '1' === $cached );
    }

    $host = @gethostbyaddr( $ip );
    if ( ! $host || $host === $ip ) {
        set_transient( $cache_key, '0', 6 * HOUR_IN_SECONDS );
        return false;
    }

    $host_lower  = strtolower( rtrim( $host, '.' ) );
    $domain_pass = false;
    foreach ( $matched_domains as $suffix ) {
        if ( substr( $host_lower, -strlen( $suffix ) ) === $suffix ) {
            $domain_pass = true;
            break;
        }
    }

    if ( ! $domain_pass ) {
        set_transient( $cache_key, '0', 6 * HOUR_IN_SECONDS );
        return false;
    }

    $resolved = @gethostbynamel( $host_lower );
    $verified = ( is_array( $resolved ) && in_array( $ip, $resolved, true ) );

    set_transient( $cache_key, $verified ? '1' : '0', DAY_IN_SECONDS );
    return $verified;
}
