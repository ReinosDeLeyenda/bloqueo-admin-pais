<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_find_wordfence_log_table() {
    global $wpdb;
    $candidates = [
        $wpdb->prefix . 'wfHits',
        $wpdb->prefix . 'wfhits',
        $wpdb->prefix . 'wfBlockedIPLog',
        $wpdb->prefix . 'wfblockediplog',
    ];
    foreach ( $candidates as $tbl ) {
        $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) );
        if ( is_string( $found ) && '' !== $found ) {
            return $found;
        }
    }
    return '';
}

function bap_decode_wordfence_ip( $raw_ip ) {
    if ( null === $raw_ip || '' === $raw_ip ) {
        return '';
    }
    $str = (string) $raw_ip;
    if ( filter_var( trim( $str ), FILTER_VALIDATE_IP ) ) {
        return trim( $str );
    }
    if ( 16 === strlen( $str ) ) {
        if ( "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff" === substr( $str, 0, 12 ) ) {
            $v4 = @inet_ntop( substr( $str, 12, 4 ) );
            return false !== $v4 ? $v4 : '';
        }
        $v6 = @inet_ntop( $str );
        return false !== $v6 ? $v6 : '';
    }
    if ( 4 === strlen( $str ) ) {
        $v4 = @inet_ntop( $str );
        return false !== $v4 ? $v4 : '';
    }
    return '';
}

function bap_import_wordfence_stats() {
    global $wpdb;

    bap_maybe_create_logs_table();
    $src_table = bap_find_wordfence_log_table();
    if ( '' === $src_table ) {
        return new WP_Error( 'no_wf_table', 'No se encontró ninguna tabla de registros de Wordfence (wfHits / wfBlockedIPLog) en MySQL.' );
    }

    $cols = $wpdb->get_col( "DESCRIBE `{$src_table}`", 0 );
    if ( empty( $cols ) ) {
        return new WP_Error( 'no_wf_cols', 'No se pudieron leer las columnas de ' . esc_html( $src_table ) . '.' );
    }

    $is_blocked_ip_log = ( false !== stripos( $src_table, 'blockedip' ) );

    $dst       = bap_get_active_logs_destination();
    $use_ext   = $dst['use_ext'];
    $dst_db    = $dst['db'];
    $dst_table = $dst['table'];

    $site_domain = (string) wp_parse_url( home_url(), PHP_URL_HOST );
    $batch       = 1000;
    $offset      = 0;
    $imported    = 0;
    $asn_cache   = [];
    $ctry_cache  = [];

    while ( true ) {
        if ( $is_blocked_ip_log ) {
            $sql = $wpdb->prepare(
                "SELECT IP AS raw_ip, countryCode AS country, unixday AS ts_day, blockCount AS hits, blockType AS reason
                 FROM `{$src_table}`
                 LIMIT %d OFFSET %d",
                $batch,
                $offset
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT IP AS raw_ip, ctime AS ts, URL AS uri, UA AS ua, action AS act, actionDescription AS act_desc
                 FROM `{$src_table}`
                 WHERE action = 'blocked' OR statusCode IN (403, 429, 503) OR (actionDescription IS NOT NULL AND actionDescription != '')
                 LIMIT %d OFFSET %d",
                $batch,
                $offset
            );
        }

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( empty( $rows ) ) {
            break;
        }

        $values = [];
        $params = [];

        foreach ( $rows as $row ) {
            $ip = bap_decode_wordfence_ip( $row['raw_ip'] ?? '' );
            if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                continue;
            }

            if ( ! isset( $ctry_cache[ $ip ] ) ) {
                $raw_cc = strtoupper( trim( (string) ( $row['country'] ?? '' ) ) );
                if ( 2 !== strlen( $raw_cc ) && function_exists( 'bap_get_country_by_ip' ) ) {
                    $raw_cc = bap_get_country_by_ip( $ip );
                }
                $ctry_cache[ $ip ] = ( '' !== $raw_cc && '-' !== $raw_cc ) ? $raw_cc : 'DESCONOCIDO';
            }

            if ( ! isset( $asn_cache[ $ip ] ) ) {
                $asn_cache[ $ip ] = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : '';
            }

            if ( $is_blocked_ip_log ) {
                $ts      = ( (int) ( $row['ts_day'] ?? 0 ) ) * DAY_IN_SECONDS;
                $created = $ts > 0 ? wp_date( 'Y-m-d H:i:s', $ts ) : current_time( 'mysql' );
                $hits    = max( 1, (int) ( $row['hits'] ?? 1 ) );
                $raw_r   = trim( (string) ( $row['reason'] ?? '' ) );
                $reason  = 'Wordfence: ' . ( '' !== $raw_r ? $raw_r : 'IP bloqueada' ) . ( $hits > 1 ? " ({$hits} hits)" : '' );
                $uri     = '/';
                $ua      = '';
            } else {
                $ts      = (int) floor( (float) ( $row['ts'] ?? 0 ) );
                $created = $ts > 0 ? wp_date( 'Y-m-d H:i:s', $ts ) : current_time( 'mysql' );
                $raw_r   = trim( (string) ( $row['act_desc'] ?? ( $row['act'] ?? '' ) ) );
                $reason  = 'Wordfence: ' . ( '' !== $raw_r ? $raw_r : 'Petición bloqueada' );
                $raw_uri = trim( (string) ( $row['uri'] ?? '' ) );
                $parsed  = '' !== $raw_uri ? (string) wp_parse_url( $raw_uri, PHP_URL_PATH ) : '/';
                $uri     = '' !== $parsed ? $parsed : '/';
                $ua      = mb_substr( sanitize_text_field( (string) ( $row['ua'] ?? '' ) ), 0, 500 );
            }

            if ( $use_ext ) {
                $values[] = '(%s, %s, %s, %s, %s, %s, %s, %s, %s)';
                array_push( $params, $site_domain, $ip, $ctry_cache[ $ip ], $asn_cache[ $ip ], 'wordfence', $reason, $uri, $ua, $created );
            } else {
                $values[] = '(%s, %s, %s, %s, %s, %s, %s, %s)';
                array_push( $params, $ip, $ctry_cache[ $ip ], $asn_cache[ $ip ], 'wordfence', $reason, $uri, $ua, $created );
            }
            $imported++;
        }

        if ( ! empty( $values ) ) {
            if ( $use_ext ) {
                $ins = "INSERT INTO `{$dst_table}` (site_domain, ip_address, country_code, asn, block_type, reason, request_uri, user_agent, created_at) VALUES " . implode( ', ', $values );
            } else {
                $ins = "INSERT INTO `{$dst_table}` (ip_address, country_code, asn, block_type, reason, request_uri, user_agent, created_at) VALUES " . implode( ', ', $values );
            }
            $dst_db->query( $dst_db->prepare( $ins, $params ) );
        }

        $offset += $batch;
    }

    if ( 0 === $imported ) {
        return new WP_Error( 'empty_wf_logs', 'No se encontraron intentos bloqueados válidos en la tabla de Wordfence.' );
    }

    return [ 'hits' => $imported ];
}