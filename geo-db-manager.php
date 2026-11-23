<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_mmdb_path() {
    $upload = wp_upload_dir();
    $dir    = trailingslashit( $upload['basedir'] ) . 'bap-geo';
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
        @file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
        @file_put_contents( $dir . '/index.php', "<?php // Silence\n" );
    }
    return $dir . '/dbip-lite.mmdb';
}

function bap_get_asn_mmdb_path() {
    bap_get_mmdb_path();
    $upload = wp_upload_dir();
    return trailingslashit( $upload['basedir'] ) . 'bap-geo/dbip-asn.mmdb';
}

function bap_verify_and_save_mmdb( $tmp_file, $is_gz, $type = 'country' ) {
    $dest = ( 'asn' === $type ) ? bap_get_asn_mmdb_path() : bap_get_mmdb_path();
    $tmp  = $dest . '.tmp';

    if ( $is_gz ) {
        if ( ! function_exists( 'gzopen' ) ) {
            return new WP_Error( 'no_zlib', 'PHP no tiene activa la extensión zlib (gzopen).' );
        }
        $gz  = @gzopen( $tmp_file, 'rb' );
        $out = @fopen( $tmp, 'wb' );
        if ( ! $gz || ! $out ) {
            return new WP_Error( 'gz_fail', 'Error al descomprimir el archivo .gz.' );
        }
        while ( ! gzeof( $gz ) ) {
            fwrite( $out, gzread( $gz, 65536 ) );
        }
        gzclose( $gz );
        fclose( $out );
    } elseif ( ! @copy( $tmp_file, $tmp ) ) {
        return new WP_Error( 'copy_fail', 'No se pudo copiar el archivo.' );
    }

    try {
        $test = new BAP_MMDB_Reader( $tmp );
        if ( 'asn' === $type ) {
            $check = $test->lookup_asn( '8.8.8.8' );
            unset( $test );
            if ( false === strpos( $check, 'AS15169' ) ) {
                @unlink( $tmp );
                return new WP_Error( 'verify_fail', 'El archivo no es una base de datos ASN válida.' );
            }
        } else {
            $code = $test->lookup_country( '8.8.8.8' );
            unset( $test );
            if ( 'US' !== $code ) {
                @unlink( $tmp );
                return new WP_Error( 'verify_fail', 'El archivo no es una base de datos de Países válida.' );
            }
        }
    } catch ( Exception $e ) {
        @unlink( $tmp );
        return new WP_Error( 'parse_fail', $e->getMessage() );
    }

    @rename( $tmp, $dest );
    $opt_key = ( 'asn' === $type ) ? 'bap_asn_mmdb_last_updated' : 'bap_mmdb_last_updated';
    update_option( $opt_key, current_time( 'mysql' ) );
    return true;
}

function bap_download_mmdb_database( $type = 'country' ) {
    $ym   = gmdate( 'Y-m' );
    $prev = gmdate( 'Y-m', strtotime( '-1 month' ) );

    if ( 'asn' === $type ) {
        $urls = [
            'https://' . implode( '.', [ 'raw', 'githubusercontent', 'com' ] ) . '/' . implode( '/', [ 'sapics', 'ip-location-db', 'main', 'dbip-asn', 'dbip-asn.mmdb.gz' ] ),
            'https://' . implode( '.', [ 'download', 'db-ip', 'com' ] ) . '/' . implode( '/', [ 'free', 'dbip-asn-lite-' . $ym . '.mmdb.gz' ] ),
            'https://' . implode( '.', [ 'download', 'db-ip', 'com' ] ) . '/' . implode( '/', [ 'free', 'dbip-asn-lite-' . $prev . '.mmdb.gz' ] ),
        ];
    } else {
        $urls = [
            'https://' . implode( '.', [ 'raw', 'githubusercontent', 'com' ] ) . '/' . implode( '/', [ 'sapics', 'ip-location-db', 'main', 'dbip-country', 'dbip-country.mmdb.gz' ] ),
            'https://' . implode( '.', [ 'download', 'db-ip', 'com' ] ) . '/' . implode( '/', [ 'free', 'dbip-country-lite-' . $ym . '.mmdb.gz' ] ),
            'https://' . implode( '.', [ 'download', 'db-ip', 'com' ] ) . '/' . implode( '/', [ 'free', 'dbip-country-lite-' . $prev . '.mmdb.gz' ] ),
        ];
    }

    $dest   = ( 'asn' === $type ) ? bap_get_asn_mmdb_path() : bap_get_mmdb_path();
    $tmp_gz = $dest . '.tmp.gz';
    $errors = [];

    foreach ( $urls as $url ) {
        $res = wp_remote_get( $url, [ 'timeout' => 30, 'stream' => true, 'filename' => $tmp_gz, 'sslverify' => false ] );
        if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) && file_exists( $tmp_gz ) && filesize( $tmp_gz ) > 100000 ) {
            $ok = bap_verify_and_save_mmdb( $tmp_gz, true, $type );
            @unlink( $tmp_gz );
            return $ok;
        }
        $errors[] = basename( $url ) . ': ' . ( is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
        @unlink( $tmp_gz );
    }
    return new WP_Error( 'dl_fail', implode( ' | ', $errors ) );
}

function bap_get_country_by_ip( $ip, &$source = '' ) {
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return 'DESCONOCIDO';
    }
    $mmdb = bap_get_mmdb_path();
    if ( file_exists( $mmdb ) ) {
        try {
            static $reader = null;
            if ( null === $reader ) {
                $reader = new BAP_MMDB_Reader( $mmdb );
            }
            $code = $reader->lookup_country( $ip );
            if ( preg_match( '/^[A-Z]{2}$/i', $code ) ) {
                $source = 'Base de datos local (.mmdb)';
                return strtoupper( $code );
            }
        } catch ( Exception $e ) {}
    }
    $source = 'Sin base de datos local';
    return 'DESCONOCIDO';
}

function bap_get_asn_by_ip( $ip, &$asn_number = '' ) {
    $asn_number = '';
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return 'IP inválida';
    }

    $asn_mmdb = bap_get_asn_mmdb_path();
    if ( file_exists( $asn_mmdb ) ) {
        try {
            static $asn_reader = null;
            if ( null === $asn_reader ) {
                $asn_reader = new BAP_MMDB_Reader( $asn_mmdb );
            }
            $local_asn = $asn_reader->lookup_asn( $ip );
            if ( '' !== $local_asn ) {
                if ( preg_match( '/^(AS\d+)/i', $local_asn, $m ) ) {
                    $asn_number = strtoupper( reset( $m ) );
                }
                return $local_asn;
            }
        } catch ( Exception $e ) {}
    }

    $cache = get_option( 'bap_asn_cache', [] );
    if ( is_array( $cache ) && isset( $cache[ $ip ] ) ) {
        if ( preg_match( '/^(AS\d+)/i', $cache[ $ip ], $m ) ) {
            $asn_number = strtoupper( reset( $m ) );
        }
        return $cache[ $ip ];
    }

    return 'Sin base de datos ASN';
}
