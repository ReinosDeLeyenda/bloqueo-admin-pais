<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_abuseipdb_path() {
    return bap_get_geo_storage_path( 'abuseipdb-list.json' );
}

function bap_get_abuseipdb_live_cache_path() {
    return bap_get_geo_storage_path( 'abuseipdb-live-cache.json' );
}

function bap_get_abuseipdb_keys() {
    $raw = (string) get_option( 'bap_abuseipdb_key', '' );
    if ( '' === trim( $raw ) ) {
        return [];
    }
    $keys = [];
    foreach ( preg_split( '/[\r\n,;]+/', $raw ) as $part ) {
        $k = trim( $part );
        if ( '' !== $k ) {
            $keys[] = $k;
        }
    }
    return array_values( array_unique( $keys ) );
}

function bap_get_abuseipdb_usage_stats() {
    $today_utc = gmdate( 'Y-m-d' );
    $stats     = get_option( 'bap_abuseipdb_usage_stats', [] );
    if ( ! is_array( $stats ) || ( $stats['date_utc'] ?? '' ) !== $today_utc ) {
        $stats = [
            'date_utc'  => $today_utc,
            'used'      => 0,
            'exhausted' => [],
        ];
    }
    return $stats;
}

function bap_record_abuseipdb_stat( $field, $exhausted_key_hash = '' ) {
    $stats = bap_get_abuseipdb_usage_stats();
    if ( isset( $stats[ $field ] ) ) {
        $stats[ $field ] = (int) $stats[ $field ] + 1;
    }
    if ( '' !== $exhausted_key_hash && ! in_array( $exhausted_key_hash, $stats['exhausted'], true ) ) {
        $stats['exhausted'][] = $exhausted_key_hash;
    }
    update_option( 'bap_abuseipdb_usage_stats', $stats, false );
}

function bap_download_abuseipdb_database() {
    $keys    = bap_get_abuseipdb_keys();
    $api_key = $keys[0] ?? '';
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
            return new WP_Error( 'abuse_api', 'AbuseIPDB devolvio HTTP ' . wp_remote_retrieve_response_code( $res ) . '. Revisa tu API Key.' );
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
            return new WP_Error( 'abuse_mirror', 'No se pudo descargar el espejo publico ni hay API Key configurada.' );
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
        return new WP_Error( 'abuse_empty', 'La lista descargada no contiene IPs validas.' );
    }

    return bap_save_json_list( 'abuseipdb-list.json', $map, 'bap_abuseipdb_last_updated', 'bap_abuseipdb_count' );
}

function bap_get_abuseipdb_score( $ip, $allow_live_api = true ) {
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return null;
    }

    $db = bap_load_cached_json_list( 'abuseipdb-list.json' );
    if ( isset( $db[ $ip ] ) ) {
        return (int) $db[ $ip ];
    }

    static $live_cache = null;
    $cache_file        = bap_get_abuseipdb_live_cache_path();
    if ( null === $live_cache ) {
        $raw        = file_exists( $cache_file ) ? json_decode( (string) @file_get_contents( $cache_file ), true ) : [];
        $live_cache = is_array( $raw ) ? $raw : [];
    }

    $now = time();
    $ttl = 30 * DAY_IN_SECONDS;
    if ( isset( $live_cache[ $ip ] ) && is_array( $live_cache[ $ip ] ) ) {
        if ( ( $now - (int) ( $live_cache[ $ip ]['ts'] ?? 0 ) ) < $ttl ) {
            return (int) ( $live_cache[ $ip ]['score'] ?? 0 );
        }
    }

    if ( ! $allow_live_api ) {
        return null;
    }

    $keys = bap_get_abuseipdb_keys();
    if ( empty( $keys ) ) {
        return null;
    }

    $stats            = bap_get_abuseipdb_usage_stats();
    $exhausted        = (array) ( $stats['exhausted'] ?? [] );
    $secs_to_midnight = max( 60, strtotime( 'tomorrow 00:00:00 UTC' ) - time() );

    foreach ( $keys as $api_key ) {
        $k_hash = substr( md5( $api_key ), 0, 10 );
        if ( in_array( $k_hash, $exhausted, true ) || false !== get_transient( 'bap_abuse_cd_' . $k_hash ) ) {
            continue;
        }

        $url = 'https://' . implode( '.', [ 'api', 'abuseipdb', 'com' ] ) . '/api/v2/check?ipAddress=' . rawurlencode( $ip ) . '&maxAgeInDays=90';
        $res = wp_remote_get( $url, [
            'timeout' => 4,
            'headers' => [ 'Key' => $api_key, 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $res ) ) {
            return null;
        }

        $code = wp_remote_retrieve_response_code( $res );
        if ( 200 === $code ) {
            bap_record_abuseipdb_stat( 'used' );
            $data  = json_decode( wp_remote_retrieve_body( $res ), true );
            $score = (int) ( $data['data']['abuseConfidenceScore'] ?? 0 );

            $live_cache[ $ip ] = [ 'score' => $score, 'ts' => $now ];
            if ( count( $live_cache ) > 25000 ) {
                $live_cache = array_slice( $live_cache, -20000, null, true );
            }
            @file_put_contents( $cache_file, wp_json_encode( $live_cache ), LOCK_EX );

            return $score;
        }

        if ( 429 === $code ) {
            set_transient( 'bap_abuse_cd_' . $k_hash, '1', $secs_to_midnight );
            bap_record_abuseipdb_stat( 'exhausted_event', $k_hash );
            continue;
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
        return new WP_Error( 'empty_file', 'El archivo subido esta vacio.' );
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
        return new WP_Error( 'invalid_abuse', 'No se encontraron direcciones IP validas en el archivo.' );
    }

    return bap_save_json_list( 'abuseipdb-list.json', $map, 'bap_abuseipdb_last_updated', 'bap_abuseipdb_count' );
}