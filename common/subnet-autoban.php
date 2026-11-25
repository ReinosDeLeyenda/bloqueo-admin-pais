<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_get_ip_subnet_cidr( $ip ) {
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        $long = ip2long( $ip );
        if ( false === $long ) {
            return '';
        }
        return long2ip( $long & 0xFFFFFF00 ) . '/24';
    }
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        $bin = @inet_pton( $ip );
        if ( false === $bin || 16 !== strlen( $bin ) ) {
            return '';
        }
        $prefix = substr( $bin, 0, 8 ) . str_repeat( "\0", 8 );
        $net    = @inet_ntop( $prefix );
        return false !== $net ? $net . '/64' : '';
    }
    return '';
}

function bap_track_and_autoban_subnet( $ip ) {
    $threshold = (int) get_option( 'bap_subnet_autoban_threshold', 0 );
    if ( $threshold <= 1 || '' === $ip || bap_is_server_ip( $ip ) ) {
        return;
    }

    $cidr = bap_get_ip_subnet_cidr( $ip );
    if ( '' === $cidr ) {
        return;
    }

    $key  = 'bap_sub_' . md5( $cidr );
    $seen = get_transient( $key );
    if ( ! is_array( $seen ) ) {
        $seen = [];
    }

    $seen[ $ip ] = time();
    set_transient( $key, $seen, HOUR_IN_SECONDS );

    if ( count( $seen ) >= $threshold ) {
        delete_transient( $key );
        $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
        if ( ! in_array( $cidr, $blacklist, true ) ) {
            $blacklist[] = $cidr;
            update_option( 'bap_blacklist_ips', implode( "\n", $blacklist ) );

            $duration_hours = (int) get_option( 'bap_auto_ban_duration', 24 );
            if ( $duration_hours > 0 ) {
                $expirations          = get_option( 'bap_auto_ban_expirations', [] );
                $expirations[ $cidr ] = time() + ( $duration_hours * HOUR_IN_SECONDS );
                update_option( 'bap_auto_ban_expirations', $expirations, false );
            }
        }
    }
}