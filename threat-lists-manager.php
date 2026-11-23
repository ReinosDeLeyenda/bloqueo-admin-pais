<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_tor_list_path() {
    bap_get_mmdb_path();
    $upload = wp_upload_dir();
    return trailingslashit( $upload['basedir'] ) . 'bap-geo/tor-exits.json';
}

function bap_get_firehol_list_path() {
    bap_get_mmdb_path();
    $upload = wp_upload_dir();
    return trailingslashit( $upload['basedir'] ) . 'bap-geo/firehol-l1.json';
}

function bap_install_tor_from_content( $content ) {
    $ips = [];
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $content ) as $line ) {
        $ip = trim( strtok( $line, " \t#" ) );
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            $ips[ $ip ] = 1;
        }
    }
    if ( empty( $ips ) ) {
        return new WP_Error( 'tor_empty', 'No se encontraron IPs válidas de nodos Tor.' );
    }
    file_put_contents( bap_get_tor_list_path(), wp_json_encode( $ips ) );
    update_option( 'bap_tor_last_updated', current_time( 'mysql' ) );
    update_option( 'bap_tor_count', count( $ips ) );
    return true;
}

function bap_download_tor_database() {
    $urls = [
        'https://' . implode( '.', [ 'check', 'torproject', 'org' ] ) . '/torbulkexitlist',
        'https://' . implode( '.', [ 'raw', 'githubusercontent', 'com' ] ) . '/' . implode( '/', [ 'SecOps-Institute', 'Tor-IP-Addresses', 'master', 'tor-exit-nodes.lst' ] ),
    ];
    foreach ( $urls as $url ) {
        $res = wp_remote_get( $url, [ 'timeout' => 25, 'sslverify' => false ] );
        if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
            $ok = bap_install_tor_from_content( wp_remote_retrieve_body( $res ) );
            if ( true === $ok ) {
                return true;
            }
        }
    }
    return new WP_Error( 'tor_dl', 'No se pudo descargar la lista de nodos Tor.' );
}

function bap_install_firehol_from_content( $content ) {
    $cidrs = [];
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $content ) as $line ) {
        $item = trim( strtok( $line, " \t#" ) );
        if ( '' === $item ) {
            continue;
        }
        if ( false === strpos( $item, '/' ) && filter_var( $item, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $item .= '/32';
        }
        if ( preg_match( '/^([0-9.]+)\/([0-9]{1,2})$/', $item, $m ) && filter_var( $m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $prefix = (int) $m[2];
            if ( $prefix >= 8 && $prefix <= 32 ) {
                $cidrs[] = $item;
            }
        }
    }
    $cidrs = array_values( array_unique( $cidrs ) );
    if ( empty( $cidrs ) ) {
        return new WP_Error( 'fh_empty', 'No se encontraron rangos CIDR válidos en el archivo.' );
    }
    file_put_contents( bap_get_firehol_list_path(), wp_json_encode( $cidrs ) );
    update_option( 'bap_firehol_last_updated', current_time( 'mysql' ) );
    update_option( 'bap_firehol_count', count( $cidrs ) );
    return true;
}

function bap_download_firehol_database() {
    $urls = [
        'https://' . implode( '.', [ 'raw', 'githubusercontent', 'com' ] ) . '/' . implode( '/', [ 'firehol', 'blocklist-ipsets', 'master', 'firehol_level1.netset' ] ),
        'https://' . implode( '.', [ 'iplists', 'firehol', 'org' ] ) . '/files/firehol_level1.netset',
    ];
    foreach ( $urls as $url ) {
        $res = wp_remote_get( $url, [ 'timeout' => 30, 'sslverify' => false ] );
        if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
            $ok = bap_install_firehol_from_content( wp_remote_retrieve_body( $res ) );
            if ( true === $ok ) {
                return true;
            }
        }
    }
    return new WP_Error( 'fh_dl', 'No se pudo descargar la lista FireHOL Level 1.' );
}

function bap_is_tor_exit_node( $ip ) {
    static $tor_db = null;
    if ( null === $tor_db ) {
        $f      = bap_get_tor_list_path();
        $tor_db = file_exists( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : [];
        if ( ! is_array( $tor_db ) ) {
            $tor_db = [];
        }
    }
    return isset( $tor_db[ $ip ] );
}

function bap_is_in_firehol( $ip ) {
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        return false;
    }
    static $cidrs = null;
    if ( null === $cidrs ) {
        $f     = bap_get_firehol_list_path();
        $cidrs = file_exists( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : [];
        if ( ! is_array( $cidrs ) ) {
            $cidrs = [];
        }
    }
    if ( empty( $cidrs ) ) {
        return false;
    }
    $ip_long = ip2long( $ip );
    foreach ( $cidrs as $cidr ) {
        list( $subnet, $bits ) = explode( '/', $cidr );
        $mask = -1 << ( 32 - (int) $bits );
        if ( ( $ip_long & $mask ) === ( ip2long( $subnet ) & $mask ) ) {
            return $cidr;
        }
    }
    return false;
}
