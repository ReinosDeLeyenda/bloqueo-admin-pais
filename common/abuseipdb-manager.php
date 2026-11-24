<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_abuseipdb_path() {
    bap_get_mmdb_path();
    $upload = wp_upload_dir();
    return trailingslashit( $upload['basedir'] ) . 'bap-geo/abuseipdb-list.json';
}

function bap_download_abuseipdb_database() {
    $api_key = trim( (string) get_option( 'bap_abuseipdb_key', '' ) );
    $map     = [];

    // 1. Si hay API Key oficial de AbuseIPDB, descargar blacklist con puntuación exacta (0-100)
    if ( '' !== $api_key ) {
        $url = 'https://' . implode( '.', [ 'api', 'abuseipdb', 'com' ] ) . '/api/v2/blacklist?confidenceMinimum=50&limit=10000';
        $res = wp_remote_get( $url, [
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => [
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
        // 2. Sin API Key: usar espejo público de GitHub (IPs con >= 90% de confianza en AbuseIPDB)
        $mirror = 'https://' . implode( '.', [ 'raw', 'githubusercontent', 'com' ] ) . '/' . implode( '/', [ 'borestad', 'blocklist-abuseipdb', 'main', 'abuseipdb-s100-30d.ipv4' ] );
        $res    = wp_remote_get( $mirror, [ 'timeout' => 30, 'sslverify' => false ] );
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

    file_put_contents( bap_get_abuseipdb_path(), wp_json_encode( $map ) );
    update_option( 'bap_abuseipdb_last_updated', current_time( 'mysql' ) );
    update_option( 'bap_abuseipdb_count', count( $map ) );
    return true;
}

function bap_get_abuseipdb_score( $ip ) {
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return null;
    }

    // 1. Buscar en la base de datos local descargada
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

    // 2. Consultar en caché de consultas individuales (24h)
    $tkey   = 'bap_abuse_' . md5( $ip );
    $cached = get_transient( $tkey );
    if ( false !== $cached ) {
        return (int) $cached;
    }

    // 3. Si hay API Key, consultar la IP puntual en vivo
    $api_key = trim( (string) get_option( 'bap_abuseipdb_key', '' ) );
    if ( '' !== $api_key ) {
        $url = 'https://' . implode( '.', [ 'api', 'abuseipdb', 'com' ] ) . '/api/v2/check?ipAddress=' . rawurlencode( $ip ) . '&maxAgeInDays=90';
        $res = wp_remote_get( $url, [
            'timeout'   => 4,
            'sslverify' => false,
            'headers'   => [ 'Key' => $api_key, 'Accept' => 'application/json' ],
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
        if ( ! function_exists( 'gzopen' ) ) {
            return new WP_Error( 'no_zlib', 'PHP no tiene activa la extensión zlib (gzopen).' );
        }
        $gz      = @gzopen( $tmp_path, 'rb' );
        $content = '';
        if ( ! $gz ) {
            return new WP_Error( 'gz_fail', 'No se pudo abrir el archivo .gz.' );
        }
        while ( ! gzeof( $gz ) ) {
            $content .= gzread( $gz, 65536 );
        }
        gzclose( $gz );
    } else {
        $content = @file_get_contents( $tmp_path );
    }

    if ( empty( $content ) ) {
        return new WP_Error( 'empty_file', 'El archivo subido está vacío.' );
    }

    $map  = [];
    $json = json_decode( $content, true );

    // Formato 1: JSON oficial de AbuseIPDB (con "data" -> ipAddress + abuseConfidenceScore) o mapa IP => score
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
        // Formato 2: Texto plano / CSV / .ipv4 (una IP por línea, opcionalmente "IP,score")
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

    file_put_contents( bap_get_abuseipdb_path(), wp_json_encode( $map ) );
    update_option( 'bap_abuseipdb_last_updated', current_time( 'mysql' ) );
    update_option( 'bap_abuseipdb_count', count( $map ) );
    return true;
}
