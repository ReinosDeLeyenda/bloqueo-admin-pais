<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_custom_dbs() {
    $list = get_option( 'bap_custom_dbs', [] );
    return is_array( $list ) ? $list : [];
}

function bap_get_custom_db_file_path( $id ) {
    $safe = sanitize_key( $id );
    return bap_get_geo_storage_path( 'custom-db-' . $safe . '.dat' );
}

function bap_parse_custom_db_to_stream( $file_path, $out_path ) {
    if ( ! is_readable( $file_path ) ) {
        return new WP_Error( 'unreadable_file', 'No se pudo leer el archivo temporal de la base de datos.' );
    }

    $fh = @fopen( $file_path, 'rb' );
    if ( ! $fh ) {
        return new WP_Error( 'open_fail', 'No se pudo abrir el archivo temporal.' );
    }

    $tmp_raw = wp_tempnam( 'bap_cdb_lines' );
    $out_fh  = @fopen( $tmp_raw, 'wb' );
    if ( ! $out_fh ) {
        fclose( $fh );
        return new WP_Error( 'write_fail', 'No se pudo crear el archivo temporal de salida.' );
    }

    $first_char = '';
    while ( ! feof( $fh ) ) {
        $chunk = fread( $fh, 256 );
        $trim  = ltrim( (string) $chunk );
        if ( '' !== $trim ) {
            $first_char = $trim[0];
            break;
        }
    }
    rewind( $fh );

    $count = 0;

    if ( '{' === $first_char || '[' === $first_char ) {
        $raw  = stream_get_contents( $fh );
        fclose( $fh );
        $json = json_decode( (string) $raw, true );
        unset( $raw );

        if ( is_array( $json ) ) {
            $items = $json['prefixes'] ?? ( $json['data'] ?? $json );
            foreach ( (array) $items as $k => $v ) {
                $cand = '';
                if ( is_string( $v ) ) {
                    $cand = $v;
                } elseif ( is_array( $v ) ) {
                    $cand = $v['ipv4Prefix'] ?? ( $v['ipv6Prefix'] ?? ( $v['cidr'] ?? ( $v['ip'] ?? ( $v['ipAddress'] ?? '' ) ) ) );
                } elseif ( is_string( $k ) ) {
                    $cand = $k;
                }
                $cand = trim( (string) $cand );
                if ( '' !== $cand && bap_is_valid_ip_or_cidr( $cand ) ) {
                    fwrite( $out_fh, $cand . "\n" );
                    $count++;
                }
            }
        }
        unset( $json );
    } else {
        while ( ( $line = fgets( $fh ) ) !== false ) {
            $line = trim( $line );
            if ( '' === $line || '#' === $line[0] || ';' === $line[0] || '//' === substr( $line, 0, 2 ) ) {
                continue;
            }
            $token = trim( strtok( $line, " \t,;" ) );
            if ( '' !== $token && bap_is_valid_ip_or_cidr( $token ) ) {
                fwrite( $out_fh, $token . "\n" );
                $count++;
            }
        }
        fclose( $fh );
    }

    fclose( $out_fh );

    if ( 0 === $count ) {
        @unlink( $tmp_raw );
        return new WP_Error( 'empty_custom_db', 'No se encontraron direcciones IP ni rangos CIDR válidos en la fuente.' );
    }

    if ( ! @rename( $tmp_raw, $out_path ) ) {
        @copy( $tmp_raw, $out_path );
        @unlink( $tmp_raw );
    }

    return $count;
}

function bap_install_custom_db_from_file( $id, $file_path ) {
    $path  = bap_get_custom_db_file_path( $id );
    $count = bap_parse_custom_db_to_stream( $file_path, $path );
    if ( is_wp_error( $count ) ) {
        return $count;
    }

    // Eliminar formato .json antiguo si existía
    $old_json = preg_replace( '/\.dat$/', '.json', $path );
    if ( $old_json !== $path && file_exists( $old_json ) ) {
        @unlink( $old_json );
    }

    $dbs = bap_get_custom_dbs();
    if ( isset( $dbs[ $id ] ) ) {
        $dbs[ $id ]['count']        = $count;
        $dbs[ $id ]['last_updated'] = current_time( 'mysql' );
        update_option( 'bap_custom_dbs', $dbs );
    }

    bap_bump_custom_dbs_cache_version();

    return $count;
}


function bap_download_custom_db( $id ) {
    $dbs = bap_get_custom_dbs();
    if ( empty( $dbs[ $id ]['url'] ) ) {
        return new WP_Error( 'no_url', 'Esta base de datos no tiene una URL configurada.' );
    }

    $url = $dbs[ $id ]['url'];
    $tmp = wp_tempnam( 'bap_cdb_dl' );
    $res = wp_remote_get( $url, [
        'timeout'  => 45,
        'stream'   => true,
        'filename' => $tmp,
    ] );

    if ( is_wp_error( $res ) ) {
        @unlink( $tmp );
        return $res;
    }
    if ( 200 !== wp_remote_retrieve_response_code( $res ) ) {
        @unlink( $tmp );
        return new WP_Error( 'http_err', 'El servidor devolvió HTTP ' . wp_remote_retrieve_response_code( $res ) );
    }

    if ( '.gz' === strtolower( substr( (string) wp_parse_url( $url, PHP_URL_PATH ), -3 ) ) ) {
        $unp = wp_tempnam( 'bap_cdb_unp' );
        $dec = bap_decompress_gz_to_file( $tmp, $unp );
        @unlink( $tmp );
        if ( is_wp_error( $dec ) ) {
            @unlink( $unp );
            return $dec;
        }
        $tmp = $unp;
    }

    $installed = bap_install_custom_db_from_file( $id, $tmp );
    @unlink( $tmp );
    return $installed;
}

function bap_update_all_custom_dbs() {
    $errs = [];
    foreach ( bap_get_custom_dbs() as $id => $db ) {
        if ( ! empty( $db['url'] ) ) {
            $r = bap_download_custom_db( $id );
            if ( is_wp_error( $r ) ) {
                $errs[] = ( $db['name'] ?? $id ) . ': ' . $r->get_error_message();
            }
        }
    }
    return $errs;
}

function bap_stream_file_matches_ip( $file_path, $ip ) {
    $fh = @fopen( $file_path, 'rb' );
    if ( ! $fh ) {
        return false;
    }
    $matched = false;
    while ( ( $line = fgets( $fh ) ) !== false ) {
        $entry = trim( $line );
        if ( '' !== $entry && bap_ip_matches_cidr( $ip, $entry ) ) {
            $matched = true;
            break;
        }
    }
    fclose( $fh );
    return $matched;
}

function bap_get_custom_dbs_cache_version() {
    return (string) get_option( 'bap_custom_dbs_ver', '1' );
}

function bap_bump_custom_dbs_cache_version() {
    update_option( 'bap_custom_dbs_ver', (string) microtime( true ), false );
}

add_action( 'update_option_bap_custom_dbs', 'bap_bump_custom_dbs_cache_version' );

function bap_match_ip_in_custom_dbs( $ip, $context = 'admin' ) {
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return null;
    }

    $dbs = bap_get_custom_dbs();
    if ( empty( $dbs ) ) {
        return null;
    }

    static $mem_cache = [];
    $ver       = bap_get_custom_dbs_cache_version();
    $cache_key = 'bap_cdb_' . md5( $ver . '|' . $context . '|' . $ip );

    if ( array_key_exists( $cache_key, $mem_cache ) ) {
        return $mem_cache[ $cache_key ];
    }

    $cached = get_transient( $cache_key );
    if ( false !== $cached ) {
        $result                  = ( '__none__' === $cached ) ? null : $cached;
        $mem_cache[ $cache_key ] = $result;
        return $result;
    }

    $result = null;
    foreach ( $dbs as $id => $meta ) {
        if ( '1' !== (string) ( $meta['enabled'] ?? '1' ) ) {
            continue;
        }
        $scope = (string) ( $meta['scope'] ?? 'both' );
        if ( 'both' !== $scope && $scope !== $context ) {
            continue;
        }

        $file = bap_get_custom_db_file_path( $id );
        if ( ! file_exists( $file ) ) {
            continue;
        }

        if ( bap_stream_file_matches_ip( $file, $ip ) ) {
            $result = [
                'id'     => $id,
                'name'   => (string) ( $meta['name'] ?? $id ),
                'action' => ( 'allow' === ( $meta['action'] ?? 'block' ) ) ? 'allow' : 'block',
            ];
            break;
        }
    }

    set_transient( $cache_key, null === $result ? '__none__' : $result, 15 * MINUTE_IN_SECONDS );
    $mem_cache[ $cache_key ] = $result;

    return $result;
}
