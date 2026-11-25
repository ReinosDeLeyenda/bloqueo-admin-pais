<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_is_valid_ip_or_cidr( $entry ) {
    $entry = trim( (string) $entry );
    if ( '' === $entry ) {
        return false;
    }
    if ( false === strpos( $entry, '/' ) ) {
        return (bool) filter_var( $entry, FILTER_VALIDATE_IP );
    }
    list( $ip, $mask ) = explode( '/', $entry, 2 );
    if ( ! ctype_digit( $mask ) ) {
        return false;
    }
    $bits = (int) $mask;
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        return ( $bits >= 0 && $bits <= 32 );
    }
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        return ( $bits >= 0 && $bits <= 128 );
    }
    return false;
}

function bap_ip_in_list( $ip, $list ) {
    if ( empty( $list ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return false;
    }
    foreach ( $list as $entry ) {
        if ( bap_ip_matches_cidr( $ip, (string) $entry ) ) {
            return true;
        }
    }
    return false;
}


function bap_add_ip_to_blacklist( $ip, $temporary = true ) {
    $ip = trim( (string) $ip );
    if ( '' === $ip || ( function_exists( 'bap_is_server_ip' ) && bap_is_server_ip( $ip ) ) ) {
        return false;
    }
    $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
    if ( ! in_array( $ip, $blacklist, true ) ) {
        $blacklist[] = $ip;
        update_option( 'bap_blacklist_ips', implode( "\n", $blacklist ) );
    }
    if ( $temporary ) {
        $duration_hours = (int) get_option( 'bap_auto_ban_duration', 24 );
        if ( $duration_hours > 0 ) {
            $expirations        = get_option( 'bap_auto_ban_expirations', [] );
            $expirations[ $ip ] = time() + ( $duration_hours * HOUR_IN_SECONDS );
            update_option( 'bap_auto_ban_expirations', $expirations, false );
        }
    }
    return true;
}

function bap_is_frontend_request_exempt( $ip, $exempt_ajax = true ) {
    if ( is_user_logged_in() || ( $exempt_ajax && wp_doing_ajax() ) || wp_doing_cron() ) {
        return true;
    }
    if ( '' === $ip || bap_is_server_ip( $ip ) ) {
        return true;
    }
    if ( bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return true;
    }
    if ( '1' === (string) get_option( 'bap_web_allow_seo_bots', '1' ) && function_exists( 'bap_is_verified_seo_bot' ) && bap_is_verified_seo_bot( $ip ) ) {
        return true;
    }
    return false;
}
function bap_get_site_root_domain() {
    $host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    return ( 0 === strpos( $host, 'www.' ) ) ? substr( $host, 4 ) : $host;
}

function bap_ip_matches_cidr( $ip, $cidr ) {
    if ( false === strpos( $cidr, '/' ) ) {
        return ( $ip === $cidr );
    }
    list( $subnet, $mask ) = explode( '/', $cidr, 2 );
    $ip_bin  = @inet_pton( $ip );
    $sub_bin = @inet_pton( $subnet );
    if ( false === $ip_bin || false === $sub_bin || strlen( $sub_bin ) !== strlen( $ip_bin ) || ! ctype_digit( $mask ) ) {
        return false;
    }
    $ip_len = strlen( $ip_bin );
    $bits   = (int) $mask;
    if ( $bits < 0 || $bits > ( $ip_len * 8 ) ) {
        return false;
    }
    if ( 0 === $bits ) {
        return true;
    }
    $bytes = intdiv( $bits, 8 );
    $rem   = $bits % 8;
    if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $sub_bin, 0, $bytes ) ) {
        return false;
    }
    if ( 0 === $rem ) {
        return true;
    }
    $byte_mask = ( 0xFF << ( 8 - $rem ) ) & 0xFF;
    return ( ( ord( $ip_bin[ $bytes ] ) & $byte_mask ) === ( ord( $sub_bin[ $bytes ] ) & $byte_mask ) );
}