<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_abuseipdb_path() {
    return bap_get_geo_storage_path( 'abuseipdb-list.json' );
}

function bap_download_abuseipdb_database() {
    $api_key = trim( (string) get_option( 'bap_abuseipdb_key', '' ) );
    $map     = [];

    if ( '' !== $api_key ) {
        $url = 'https://' . implode( '.', [ 'api', 'abuseipdb', 'com' ] ) . '/api/v2/blacklist?confidenceMinimum=50&limit=10000';
        $res = wp_remote_get( $url, [
            'timeout' => 30,
            'headers' => [
                'Key'    => $api_key,
                'Accept' => 'application/json',
            ],
        ] );

        if ( is_wp_error( $res ) ) {
            return $res;
        }
        if ( 200 !== wp_remote_retrieve_response_code( $res ) ) {
            return new WP_Error( 'abuse_api', 'AbuseIPDB devolvió HTTP ' . wp_remote_retrieve_response_code( $res ) . '. Revisa tu API Key.' );
        }

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        $list = $body['data'] ?? [];
        foreach ( $list as $item ) {
            $ip    = $item['ipAddress'] ?? '';
            $score = (int) ( $item['abuseConfidenceScore'] ?? 0 );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) && $score > 0 ) {
                $map[ $ip ] = $score;
            }
        }
    } else {
        $mirror = 'https://' . implode( '.', [ 'raw', 'githubusercontent', 'com' ] ) . '/' . implode( '/', [ 'borestad', 'blocklist-abuseipdb', 'main', 'abuseipdb-s100-30d.ipv4' ] );
        $res    = wp_remote_get( $mirror, [ 'timeout' => 30 ] );
        if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
            return new WP_Error( 'abuse_mirror', 'No se pudo descargar el espejo público ni hay API Key configurada.' );
        }
        $lines = preg_split( '/\r\n|\r|\n/', wp_remote_retrieve_body( $res ) );
        foreach ( $lines as $line ) {
            $ip = trim( strtok( $line, " \t#" ) );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                $map[ $ip ] = 95;
            }
        }
    }

    if ( empty( $map ) ) {
        return new WP_Error( 'abuse_empty', 'La lista descargada no contiene IPs válidas.' );
    }

    return bap_save_json_list( 'abuseipdb-list.json', $map, 'bap_abuseipdb_last_updated', 'bap_abuseipdb_count' );
}

function bap_get_abuseipdb_score( $ip ) {
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return null;
    }

    static $db = null;
    if ( null === $db ) {
        $file = bap_get_abuseipdb_path();
        $db   = file_exists( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : [];
        if ( ! is_array( $db ) ) {
            $db = [];
        }
    }
    if ( isset( $db[ $ip ] ) ) {
        return (int) $db[ $ip ];
    }

    $tkey   = 'bap_abuse_' . md5( $ip );
    $cached = get_transient( $tkey );
    if ( false !== $cached ) {
        return (int) $cached;
    }

    $api_key = trim( (string) get_option( 'bap_abuseipdb_key', '' ) );
    if ( '' !== $api_key ) {
        $url = 'https://' . implode( '.', [ 'api', 'abuseipdb', 'com' ] ) . '/api/v2/check?ipAddress=' . rawurlencode( $ip ) . '&maxAgeInDays=90';
        $res = wp_remote_get( $url, [
            'timeout' => 4,
            'headers' => [ 'Key' => $api_key, 'Accept' => 'application/json' ],
        ] );
        if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
            $data  = json_decode( wp_remote_retrieve_body( $res ), true );
            $score = (int) ( $data['data']['abuseConfidenceScore'] ?? 0 );
            set_transient( $tkey, $score, DAY_IN_SECONDS );
            return $score;
        }
    }

    return null;
}

function bap_install_abuseipdb_from_file( $tmp_path, $original_name ) {
    if ( '.gz' === strtolower( substr( $original_name, -3 ) ) ) {
        $tmp_unpacked = wp_tempnam( 'bap_abuse' );
        $dec          = bap_decompress_gz_to_file( $tmp_path, $tmp_unpacked );
        if ( is_wp_error( $dec ) ) {
            @unlink( $tmp_unpacked );
            return $dec;
        }
        $content = (string) @file_get_contents( $tmp_unpacked );
        @unlink( $tmp_unpacked );
    } else {
        $content = (string) @file_get_contents( $tmp_path );
    }

    if ( '' === $content ) {
        return new WP_Error( 'empty_file', 'El archivo subido está vacío.' );
    }

    $map  = [];
    $json = json_decode( $content, true );

    if ( is_array( $json ) ) {
        $items = $json['data'] ?? $json;
        foreach ( $items as $k => $v ) {
            if ( is_array( $v ) && ! empty( $v['ipAddress'] ) ) {
                $ip    = trim( $v['ipAddress'] );
                $score = (int) ( $v['abuseConfidenceScore'] ?? 95 );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    $map[ $ip ] = max( 1, min( 100, $score ) );
                }
            } elseif ( filter_var( $k, FILTER_VALIDATE_IP ) ) {
                $map[ $k ] = max( 1, min( 100, (int) $v ) );
            }
        }
    } else {
        $lines = preg_split( '/\r\n|\r|\n/', $content );
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line || '#' === $line[0] ) {
                continue;
            }
            $parts = preg_split( '/[\s,;]+/', $line );
            $ip    = trim( $parts[0] ?? '' );
            $score = isset( $parts[1] ) && is_numeric( $parts[1] ) ? (int) $parts[1] : 95;
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                $map[ $ip ] = max( 1, min( 100, $score ) );
            }
        }
    }

    if ( empty( $map ) ) {
        return new WP_Error( 'invalid_abuse', 'No se encontraron direcciones IP válidas en el archivo.' );
    }

    return bap_save_json_list( 'abuseipdb-list.json', $map, 'bap_abuseipdb_last_updated', 'bap_abuseipdb_count' );
}
