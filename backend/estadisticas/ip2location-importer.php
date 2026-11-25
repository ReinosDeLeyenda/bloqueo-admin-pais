<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_find_ip2location_log_table() {
    global $wpdb;
    $own_table = bap_get_logs_table_name();
    $default   = $wpdb->prefix . 'ip2location_country_blocker_log';
    $found     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $default ) );
    if ( $found === $default && $found !== $own_table ) {
        return $default;
    }
    $tables = $wpdb->get_col( "SHOW TABLES LIKE '%ip2location%log%'" );
    foreach ( $tables as $t ) {
        if ( $t !== $own_table ) {
            return (string) $t;
        }
    }
    return '';
}

function bap_import_ip2location_stats() {
    global $wpdb;

    bap_maybe_create_logs_table();
    $src_table = bap_find_ip2location_log_table();
    if ( '' === $src_table ) {
        return new WP_Error( 'no_table', 'No se encontró ninguna tabla de registros de IP2Location Country Blocker en MySQL.' );
    }

    $cols = $wpdb->get_col( "DESCRIBE `{$src_table}`", 0 );
    if ( empty( $cols ) ) {
        return new WP_Error( 'no_cols', 'No se pudieron leer las columnas de la tabla ' . esc_html( $src_table ) . '.' );
    }

    $ip_col      = in_array( 'ip_address', $cols, true ) ? 'ip_address' : ( in_array( 'ip', $cols, true ) ? 'ip' : null );
    $country_col = in_array( 'country_code', $cols, true ) ? 'country_code' : ( in_array( 'country', $cols, true ) ? 'country' : null );
    $date_col    = in_array( 'created_at', $cols, true ) ? 'created_at' : ( in_array( 'date_created', $cols, true ) ? 'date_created' : ( in_array( 'date', $cols, true ) ? 'date' : null ) );
    $reason_col  = in_array( 'block_reason', $cols, true ) ? 'block_reason' : ( in_array( 'reason', $cols, true ) ? 'reason' : null );
    $uri_col     = in_array( 'url', $cols, true ) ? 'url' : ( in_array( 'request_uri', $cols, true ) ? 'request_uri' : ( in_array( 'uri', $cols, true ) ? 'uri' : null ) );
    $ua_col      = in_array( 'user_agent', $cols, true ) ? 'user_agent' : ( in_array( 'agent', $cols, true ) ? 'agent' : null );

    if ( ! $ip_col ) {
        return new WP_Error( 'no_ip_col', 'La tabla encontrada no contiene una columna de dirección IP reconocible.' );
    }

    $sel_country = $country_col ? "`{$country_col}`" : "''";
    $sel_date    = $date_col ? "`{$date_col}`" : "'" . esc_sql( current_time( 'mysql' ) ) . "'";
    $sel_reason  = $reason_col ? "`{$reason_col}`" : "''";
    $sel_uri     = $uri_col ? "`{$uri_col}`" : "''";
    $sel_ua      = $ua_col ? "`{$ua_col}`" : "''";

    $dst       = bap_get_active_logs_destination();
    $use_ext   = $dst['use_ext'];
    $dst_db    = $dst['db'];
    $dst_table = $dst['table'];

    $site_domain = (string) wp_parse_url( home_url(), PHP_URL_HOST );
    $batch       = 1000;
    $offset      = 0;
    $imported    = 0;
    $asn_cache   = [];

    while ( true ) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT `{$ip_col}` AS ip, {$sel_country} AS country, {$sel_date} AS created_at, {$sel_reason} AS reason, {$sel_uri} AS uri, {$sel_ua} AS ua
                 FROM `{$src_table}`
                 WHERE `{$ip_col}` IS NOT NULL AND `{$ip_col}` != ''
                 LIMIT %d OFFSET %d",
                $batch,
                $offset
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            break;
        }

        $values = [];
        $params = [];

        foreach ( $rows as $row ) {
            $ip = trim( (string) $row['ip'] );
            if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                continue;
            }

            $country = strtoupper( trim( (string) $row['country'] ) );
            if ( ( '' === $country || '-' === $country ) && function_exists( 'bap_get_country_by_ip' ) ) {
                $country = bap_get_country_by_ip( $ip );
            }
            if ( '' === $country || '-' === $country ) {
                $country = 'DESCONOCIDO';
            }

            if ( ! isset( $asn_cache[ $ip ] ) ) {
                $asn_cache[ $ip ] = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : '';
            }

            $created = trim( (string) $row['created_at'] );
            if ( is_numeric( $created ) ) {
                $created = wp_date( 'Y-m-d H:i:s', (int) $created );
            } elseif ( '' === $created ) {
                $created = current_time( 'mysql' );
            }

            $raw_reason = trim( (string) $row['reason'] );
            $reason     = '' !== $raw_reason ? 'IP2Location: ' . $raw_reason : 'Importado desde IP2Location';
            $uri        = '' !== trim( (string) $row['uri'] ) ? trim( (string) $row['uri'] ) : '/';
            $ua         = mb_substr( sanitize_text_field( (string) $row['ua'] ), 0, 500 );

            if ( $use_ext ) {
                $values[] = '(%s, %s, %s, %s, %s, %s, %s, %s, %s)';
                array_push( $params, $site_domain, $ip, $country, $asn_cache[ $ip ], 'ip2location', $reason, $uri, $ua, $created );
            } else {
                $values[] = '(%s, %s, %s, %s, %s, %s, %s, %s)';
                array_push( $params, $ip, $country, $asn_cache[ $ip ], 'ip2location', $reason, $uri, $ua, $created );
            }
            $imported++;
        }

        if ( ! empty( $values ) ) {
            if ( $use_ext ) {
                $sql = "INSERT INTO `{$dst_table}` (site_domain, ip_address, country_code, asn, block_type, reason, request_uri, user_agent, created_at) VALUES " . implode( ', ', $values );
            } else {
                $sql = "INSERT INTO `{$dst_table}` (ip_address, country_code, asn, block_type, reason, request_uri, user_agent, created_at) VALUES " . implode( ', ', $values );
            }
            $dst_db->query( $dst_db->prepare( $sql, $params ) );
        }

        $offset += $batch;
    }

    if ( 0 === $imported ) {
        return new WP_Error( 'empty_logs', 'La tabla de IP2Location no contiene registros válidos para importar.' );
    }

    return [ 'hits' => $imported ];
}
