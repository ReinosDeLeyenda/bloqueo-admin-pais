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
    return bap_get_geo_storage_path( 'custom-db-' . $safe . '.json' );
}

function bap_parse_custom_db_from_file( $file_path ) {
    if ( ! is_readable( $file_path ) ) {
        return new WP_Error( 'unreadable_file', 'No se pudo leer el archivo temporal de la base de datos.' );
    }

    $fh = @fopen( $file_path, 'rb' );
    if ( ! $fh ) {
        return new WP_Error( 'open_fail', 'No se pudo abrir el archivo temporal.' );
    }

    // Detectar si es JSON mirando el primer carácter no vacío
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

    $exact     = [];
    $cidrs_map = [];

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
                    if ( false !== strpos( $cand, '/' ) ) {
                        $cidrs_map[ $cand ] = 1;
                    } else {
                        $exact[ $cand ] = 1;
                    }
                }
            }
        }
    } else {
        while ( ( $line = fgets( $fh ) ) !== false ) {
            $line = trim( $line );
            if ( '' === $line || '#' === $line[0] || ';' === $line[0] || '//' === substr( $line, 0, 2 ) ) {
                continue;
            }
            $token = trim( strtok( $line, " \t,;" ) );
            if ( '' !== $token && bap_is_valid_ip_or_cidr( $token ) ) {
                if ( false !== strpos( $token, '/' ) ) {
                    $cidrs_map[ $token ] = 1;
                } else {
                    $exact[ $token ] = 1;
                }
            }
        }
        fclose( $fh );
    }

    $cidrs = array_keys( $cidrs_map );
    unset( $cidrs_map );

    $total = count( $exact ) + count( $cidrs );
    if ( 0 === $total ) {
        return new WP_Error( 'empty_custom_db', 'No se encontraron direcciones IP ni rangos CIDR válidos en la fuente.' );
    }

    return [
        'exact' => $exact,
        'cidrs' => $cidrs,
        'count' => $total,
    ];
}

function bap_install_custom_db_from_file( $id, $file_path ) {
    $parsed = bap_parse_custom_db_from_file( $file_path );
    if ( is_wp_error( $parsed ) ) {
        return $parsed;
    }

    $path    = bap_get_custom_db_file_path( $id );
    $encoded = wp_json_encode( [ 'exact' => $parsed['exact'], 'cidrs' => $parsed['cidrs'] ] );
    unset( $parsed['exact'], $parsed['cidrs'] );

    if ( false === $encoded || false === file_put_contents( $path, $encoded ) ) {
        return new WP_Error( 'write_fail', 'No se pudo guardar el archivo de la base de datos personalizada.' );
    }
    unset( $encoded );

    $dbs = bap_get_custom_dbs();
    if ( isset( $dbs[ $id ] ) ) {
        $dbs[ $id ]['count']        = $parsed['count'];
        $dbs[ $id ]['last_updated'] = current_time( 'mysql' );
        update_option( 'bap_custom_dbs', $dbs );
    }

    return $parsed['count'];
}

function bap_install_custom_db_from_content( $id, $content ) {
    $tmp = wp_tempnam( 'bap_cdb_raw' );
    file_put_contents( $tmp, (string) $content );
    unset( $content );
    $res = bap_install_custom_db_from_file( $id, $tmp );
    @unlink( $tmp );
    return $res;
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

function bap_match_ip_in_custom_dbs( $ip, $context = 'admin' ) {
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return null;
    }

    $dbs = bap_get_custom_dbs();
    if ( empty( $dbs ) ) {
        return null;
    }

    static $loaded = [];

    foreach ( $dbs as $id => $meta ) {
        if ( '1' !== (string) ( $meta['enabled'] ?? '1' ) ) {
            continue;
        }
        $scope = (string) ( $meta['scope'] ?? 'both' );
        if ( 'both' !== $scope && $scope !== $context ) {
            continue;
        }

        if ( ! isset( $loaded[ $id ] ) ) {
            $file          = bap_get_custom_db_file_path( $id );
            $raw           = file_exists( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : [];
            $loaded[ $id ] = is_array( $raw ) ? $raw : [];
        }

        $data  = $loaded[ $id ];
        $exact = $data['exact'] ?? [];
        $cidrs = $data['cidrs'] ?? [];

        if ( isset( $exact[ $ip ] ) || ( ! empty( $cidrs ) && bap_ip_in_list( $ip, $cidrs ) ) ) {
            return [
                'id'     => $id,
                'name'   => (string) ( $meta['name'] ?? $id ),
                'action' => ( 'allow' === ( $meta['action'] ?? 'block' ) ) ? 'allow' : 'block',
            ];
        }
    }

    return null;
}
