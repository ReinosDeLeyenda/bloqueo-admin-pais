<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_find_newstatpress_table() {
    global $wpdb;
    $cand = $wpdb->prefix . 'statpress';
    $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cand ) );
    if ( is_string( $found ) && '' !== $found ) {
        return $found;
    }
    $tables = $wpdb->get_col( "SHOW TABLES LIKE '%statpress%'" );
    return ! empty( $tables ) ? (string) reset( $tables ) : '';
}

function bap_find_iawp_views_table() {
    global $wpdb;
    $cand = $wpdb->prefix . 'independent_analytics_views';
    $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cand ) );
    return ( is_string( $found ) && '' !== $found ) ? $found : '';
}

function bap_import_newstatpress_visits() {
    global $wpdb;
    bap_maybe_create_visits_table();

    $src = bap_find_newstatpress_table();
    if ( '' === $src ) {
        return new WP_Error( 'no_nsp', 'No se encontro la tabla de NewStatPress en MySQL.' );
    }

    $dst       = bap_get_visits_table_name();
    $batch     = 1000;
    $offset    = 0;
    $imported  = 0;
    $asn_cache = [];
    $cc_cache  = [];

    while ( true ) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT date, time, ip, urlrequested, agent, referrer, spider
                 FROM `{$src}`
                 WHERE (spider IS NULL OR spider = '')
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

        foreach ( $rows as $r ) {
            $ip = trim( (string) ( $r['ip'] ?? '' ) );
            if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                continue;
            }

            if ( ! isset( $cc_cache[ $ip ] ) ) {
                $cc_cache[ $ip ] = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
            }
            if ( ! isset( $asn_cache[ $ip ] ) ) {
                $asn_cache[ $ip ] = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : '';
            }

            $d_raw = preg_replace( '/[^0-9]/', '', (string) ( $r['date'] ?? '' ) );
            $t_raw = trim( (string) ( $r['time'] ?? '00:00:00' ) );
            if ( 8 === strlen( $d_raw ) ) {
                $dt = substr( $d_raw, 0, 4 ) . '-' . substr( $d_raw, 4, 2 ) . '-' . substr( $d_raw, 6, 2 ) . ' ' . ( '' !== $t_raw ? $t_raw : '00:00:00' );
            } else {
                $dt = current_time( 'mysql' );
            }

            $uri = trim( (string) ( $r['urlrequested'] ?? '' ) );
            $uri = '' !== $uri ? '/' . ltrim( $uri, '/' ) : '/';
            $ref = mb_substr( esc_url_raw( (string) ( $r['referrer'] ?? '' ) ), 0, 500 );
            $ua  = mb_substr( sanitize_text_field( (string) ( $r['agent'] ?? '' ) ), 0, 500 );

            $values[] = '(%s, %s, %s, %s, %s, %s, %s)';
            array_push( $params, $ip, $cc_cache[ $ip ], $asn_cache[ $ip ], $uri, $ref, $ua, $dt );
            $imported++;
        }

        if ( ! empty( $values ) ) {
            $sql = "INSERT INTO `{$dst}` (ip_address, country_code, asn, request_uri, referer, user_agent, created_at) VALUES " . implode( ', ', $values );
            $wpdb->query( $wpdb->prepare( $sql, $params ) );
        }

        $offset += $batch;
    }

    if ( 0 === $imported ) {
        return new WP_Error( 'empty_nsp', 'No se encontraron visitas humanas validas en NewStatPress.' );
    }

    return [ 'hits' => $imported ];
}
function bap_import_independent_analytics_visits() {
    global $wpdb;
    bap_maybe_create_visits_table();

    $v_tbl = bap_find_iawp_views_table();
    if ( '' === $v_tbl ) {
        return new WP_Error( 'no_iawp', 'No se encontraron las tablas de Independent Analytics en MySQL.' );
    }

    $p      = $wpdb->prefix . 'independent_analytics_';
    $s_tbl  = $p . 'sessions';
    $r_tbl  = $p . 'resources';
    $rf_tbl = $p . 'referrers';
    $c_tbl  = $p . 'countries';
    $b_tbl  = $p . 'browsers';
    $os_tbl = $p . 'operating_systems';
    $dt_tbl = $p . 'device_types';

    $has_s  = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $s_tbl ) ) === $s_tbl );
    $has_r  = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $r_tbl ) ) === $r_tbl );
    $has_rf = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rf_tbl ) ) === $rf_tbl );
    $has_c  = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $c_tbl ) ) === $c_tbl );
    $has_b  = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $b_tbl ) ) === $b_tbl );
    $has_os = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $os_tbl ) ) === $os_tbl );
    $has_dt = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $dt_tbl ) ) === $dt_tbl );

    $v_cols = $wpdb->get_col( "DESCRIBE `{$v_tbl}`", 0 );
    $s_cols = $has_s ? $wpdb->get_col( "DESCRIBE `{$s_tbl}`", 0 ) : [];
    $r_cols = $has_r ? $wpdb->get_col( "DESCRIBE `{$r_tbl}`", 0 ) : [];

    $date_col = in_array( 'viewed_at', $v_cols, true ) ? 'v.viewed_at' : ( in_array( 'created_at', $v_cols, true ) ? 'v.created_at' : 'NOW()' );
    $url_col  = in_array( 'cached_url', $r_cols, true ) ? 'r.cached_url' : ( in_array( 'url', $r_cols, true ) ? 'r.url' : "''" );
    $cc_col   = ( $has_c && in_array( 'country_id', $s_cols, true ) ) ? 'c.country_code' : "''";
    $ref_col  = ( $has_rf && in_array( 'referrer_id', $s_cols, true ) ) ? 'rf.domain' : "''";
    $br_col   = ( $has_b && in_array( 'browser_id', $s_cols, true ) ) ? 'b.name' : "''";
    $os_col   = ( $has_os && in_array( 'operating_system_id', $s_cols, true ) ) ? 'os.name' : "''";
    $dv_col   = ( $has_dt && in_array( 'device_type_id', $s_cols, true ) ) ? 'dt.name' : "''";

    $joins = '';
    if ( $has_s && in_array( 'session_id', $v_cols, true ) ) {
        $joins .= " LEFT JOIN `{$s_tbl}` s ON s.session_id = v.session_id";
        if ( $has_rf && in_array( 'referrer_id', $s_cols, true ) ) { $joins .= " LEFT JOIN `{$rf_tbl}` rf ON rf.referrer_id = s.referrer_id"; }
        if ( $has_c && in_array( 'country_id', $s_cols, true ) ) { $joins .= " LEFT JOIN `{$c_tbl}` c ON c.country_id = s.country_id"; }
        if ( $has_b && in_array( 'browser_id', $s_cols, true ) ) { $joins .= " LEFT JOIN `{$b_tbl}` b ON b.browser_id = s.browser_id"; }
        if ( $has_os && in_array( 'operating_system_id', $s_cols, true ) ) { $joins .= " LEFT JOIN `{$os_tbl}` os ON os.operating_system_id = s.operating_system_id"; }
        if ( $has_dt && in_array( 'device_type_id', $s_cols, true ) ) { $joins .= " LEFT JOIN `{$dt_tbl}` dt ON dt.device_type_id = s.device_type_id"; }
    }
    if ( $has_r && in_array( 'resource_id', $v_cols, true ) ) {
        $joins .= " LEFT JOIN `{$r_tbl}` r ON r.resource_id = v.resource_id";
    }

    $dst      = bap_get_visits_table_name();
    $batch    = 1000;
    $offset   = 0;
    $imported = 0;

    while ( true ) {
        $sql = $wpdb->prepare(
            "SELECT {$date_col} AS dt, {$url_col} AS uri, {$cc_col} AS cc, {$ref_col} AS ref_dom,
                    {$br_col} AS br, {$os_col} AS os_name, {$dv_col} AS dev, v.session_id AS sid
             FROM `{$v_tbl}` v {$joins}
             LIMIT %d OFFSET %d",
            $batch,
            $offset
        );
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( empty( $rows ) ) {
            break;
        }

        $values = [];
        $params = [];

        foreach ( $rows as $r ) {
            $dt  = trim( (string) ( $r['dt'] ?? '' ) ) ?: current_time( 'mysql' );
            $raw_u = trim( (string) ( $r['uri'] ?? '' ) );
            $path  = '' !== $raw_u ? (string) wp_parse_url( $raw_u, PHP_URL_PATH ) : '/';
            $uri   = '' !== $path ? $path : '/';

            $cc = strtoupper( trim( (string) ( $r['cc'] ?? '' ) ) );
            if ( 2 !== strlen( $cc ) ) {
                $cc = 'DESCONOCIDO';
            }

            $ref_dom = trim( (string) ( $r['ref_dom'] ?? '' ) );
            $ref     = ( '' !== $ref_dom && false === strpos( $ref_dom, '://' ) ) ? 'https://' . $ref_dom : $ref_dom;

            $ua_parts = array_filter( [
                trim( (string) ( $r['br'] ?? '' ) ),
                trim( (string) ( $r['os_name'] ?? '' ) ),
                trim( (string) ( $r['dev'] ?? '' ) ),
            ] );
            $ua = ! empty( $ua_parts ) ? implode( ' / ', $ua_parts ) . ' (IAWP)' : 'Independent Analytics';

            $sid_label = 'iawp-sess-' . (int) ( $r['sid'] ?? 0 );

            $values[] = '(%s, %s, %s, %s, %s, %s, %s)';
            array_push( $params, $sid_label, $cc, 'Independent Analytics', $uri, $ref, $ua, $dt );
            $imported++;
        }

        if ( ! empty( $values ) ) {
            $ins = "INSERT INTO `{$dst}` (ip_address, country_code, asn, request_uri, referer, user_agent, created_at) VALUES " . implode( ', ', $values );
            $wpdb->query( $wpdb->prepare( $ins, $params ) );
        }

        $offset += $batch;
    }

    if ( 0 === $imported ) {
        return new WP_Error( 'empty_iawp', 'No se encontraron vistas registradas en Independent Analytics.' );
    }

    return [ 'hits' => $imported ];
}