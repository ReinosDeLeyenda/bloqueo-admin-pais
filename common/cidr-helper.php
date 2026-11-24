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
    if ( in_array( $ip, $list, true ) ) {
        return true;
    }

    $ip_bin = @inet_pton( $ip );
    if ( false === $ip_bin ) {
        return false;
    }
    $ip_len = strlen( $ip_bin );

    foreach ( $list as $entry ) {
        if ( false === strpos( $entry, '/' ) ) {
            continue;
        }
        list( $subnet, $mask ) = explode( '/', $entry, 2 );
        $sub_bin = @inet_pton( $subnet );
        if ( false === $sub_bin || strlen( $sub_bin ) !== $ip_len || ! ctype_digit( $mask ) ) {
            continue;
        }

        $bits = (int) $mask;
        $max  = $ip_len * 8;
        if ( $bits < 0 || $bits > $max ) {
            continue;
        }
        if ( 0 === $bits ) {
            return true;
        }

        $bytes = intdiv( $bits, 8 );
        $rem   = $bits % 8;

        if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $sub_bin, 0, $bytes ) ) {
            continue;
        }
        if ( 0 === $rem ) {
            return true;
        }
        $byte_mask = ( 0xFF << ( 8 - $rem ) ) & 0xFF;
        if ( ( ord( $ip_bin[ $bytes ] ) & $byte_mask ) === ( ord( $sub_bin[ $bytes ] ) & $byte_mask ) ) {
            return true;
        }
    }

    return false;
}
