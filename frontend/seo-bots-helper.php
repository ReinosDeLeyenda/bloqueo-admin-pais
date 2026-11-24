<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_is_verified_seo_bot( $ip = '' ) {
    if ( '' === $ip ) {
        $ip = bap_get_user_ip();
    }
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return false;
    }

    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
    if ( '' === $ua ) {
        return false;
    }

    $allowed_bots = [
        'googlebot'   => [ '.googlebot.com', '.google.com' ],
        'bingbot'     => [ '.search.msn.com' ],
        'duckduckbot' => [ '.duckduckgo.com' ],
        'applebot'    => [ '.applebot.apple.com' ],
    ];

    $matched_suffixes = [];
    foreach ( $allowed_bots as $token => $suffixes ) {
        if ( false !== strpos( $ua, $token ) ) {
            $matched_suffixes = $suffixes;
            break;
        }
    }

    if ( empty( $matched_suffixes ) ) {
        return false;
    }

    $cache_key = 'bap_seo_bot_' . md5( $ip );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return '1' === $cached;
    }

    // 1. Reverse DNS (PTR)
    $host = @gethostbyaddr( $ip );
    if ( ! $host || $host === $ip ) {
        set_transient( $cache_key, '0', HOUR_IN_SECONDS );
        return false;
    }

    $host_lower   = strtolower( rtrim( $host, '.' ) );
    $valid_domain = false;
    foreach ( $matched_suffixes as $suffix ) {
        if ( substr( $host_lower, -strlen( $suffix ) ) === $suffix ) {
            $valid_domain = true;
            break;
        }
    }

    if ( ! $valid_domain ) {
        set_transient( $cache_key, '0', HOUR_IN_SECONDS );
        return false;
    }

    // 2. Forward DNS (FCrDNS) compatible con IPv4 (A) e IPv6 (AAAA)
    $resolved_ips = [];
    $records      = @dns_get_record( $host_lower, DNS_A + DNS_AAAA );
    if ( is_array( $records ) ) {
        foreach ( $records as $rec ) {
            if ( ! empty( $rec['ip'] ) ) {
                $resolved_ips[] = inet_ntop( inet_pton( $rec['ip'] ) );
            } elseif ( ! empty( $rec['ipv6'] ) ) {
                $resolved_ips[] = inet_ntop( inet_pton( $rec['ipv6'] ) );
            }
        }
    }

    $norm_ip  = inet_ntop( inet_pton( $ip ) );
    $verified = in_array( $norm_ip, $resolved_ips, true );

    set_transient( $cache_key, $verified ? '1' : '0', DAY_IN_SECONDS );
    return $verified;
}
