<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( false !== strpos( $hook, 'bap-admin-estadisticas' ) ) {
        wp_enqueue_script( 'bap-admin-estadisticas', plugin_dir_url( __FILE__ ) . 'admin-estadisticas.js', [], '11.2', true );
    }
});

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

    if ( ! $ip_col ) {
        return new WP_Error( 'no_ip_col', 'La tabla encontrada no contiene una columna de dirección IP reconocible.' );
    }

    $sel_country = $country_col ? "`{$country_col}`" : "''";
    $sel_date    = $date_col ? "`{$date_col}`" : "'" . esc_sql( current_time( 'mysql' ) ) . "'";
    $sel_reason  = $reason_col ? "`{$reason_col}`" : "''";
    $sel_uri     = $uri_col ? "`{$uri_col}`" : "''";

    $dst_table = bap_get_logs_table_name();
    $batch     = 1000;
    $offset    = 0;
    $imported  = 0;
    $asn_cache = [];

    while ( true ) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT `{$ip_col}` AS ip, {$sel_country} AS country, {$sel_date} AS created_at, {$sel_reason} AS reason, {$sel_uri} AS uri
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
            $uri        = trim( (string) $row['uri'] );

            $values[] = '(%s, %s, %s, %s, %s, %s, %s)';
            array_push( $params, $ip, $country, $asn_cache[ $ip ], $reason, $uri, 'ip2location', $created );
            $imported++;
        }

        if ( ! empty( $values ) ) {
            $sql = "INSERT INTO {$dst_table} (ip_address, country_code, asn, reason, request_uri, block_type, created_at) VALUES " . implode( ', ', $values );
            $wpdb->query( $wpdb->prepare( $sql, $params ) );
        }

        $offset += $batch;
    }

    if ( 0 === $imported ) {
        return new WP_Error( 'empty_logs', 'La tabla de IP2Location no contiene registros válidos para importar.' );
    }

    return [ 'hits' => $imported ];
}

function bap_handle_stats_actions() {
    global $wpdb;
    $table = bap_get_logs_table_name();

    if ( isset( $_POST['bap_save_retention'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $allowed = [ 0, 7, 15, 30, 60, 90, 180, 365 ];
        $days    = isset( $_POST['bap_logs_retention_days'] ) ? (int) $_POST['bap_logs_retention_days'] : 90;
        if ( ! in_array( $days, $allowed, true ) ) {
            $days = 90;
        }
        update_option( 'bap_logs_retention_days', $days );
        $purged = bap_purge_old_logs( $days );
        $extra  = $purged > 0 ? ' Se han eliminado <code>' . number_format_i18n( $purged ) . '</code> registros antiguos ahora mismo.' : '';
        $label  = ( 0 === $days ) ? 'Sin límite (conservar siempre)' : $days . ' días';
        return '<div class="notice notice-success is-dismissible"><p><strong>Retención guardada:</strong> ' . esc_html( $label ) . '.' . $extra . '</p></div>';
    }

    if ( isset( $_POST['bap_import_ip2loc'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $res = bap_import_ip2location_stats();
        if ( is_wp_error( $res ) ) {
            return '<div class="notice notice-error is-dismissible"><p><strong>Error al importar:</strong> ' . esc_html( $res->get_error_message() ) . '</p></div>';
        }
        return '<div class="notice notice-success is-dismissible"><p><strong>¡Importación completada!</strong> Se han migrado <code>' . number_format_i18n( (int) $res['hits'] ) . '</code> registros individuales a la tabla MySQL <code>' . esc_html( $table ) . '</code>.</p></div>';
    }
    if ( isset( $_POST['bap_clear_logs'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $wpdb->query( "TRUNCATE TABLE {$table}" );
        return '<div class="notice notice-success is-dismissible"><p><strong>Historial vaciado correctamente.</strong></p></div>';
    }
    if ( isset( $_POST['bap_ban_ip'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $ip_to_ban = trim( sanitize_text_field( wp_unslash( $_POST['bap_ban_ip'] ) ) );
        if ( filter_var( $ip_to_ban, FILTER_VALIDATE_IP ) ) {
            $current_bl = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
            if ( ! in_array( $ip_to_ban, $current_bl, true ) ) {
                $current_bl[] = $ip_to_ban;
                update_option( 'bap_blacklist_ips', implode( "\n", $current_bl ) );
            }
            return '<div class="notice notice-success is-dismissible"><p><strong>IP <code>' . esc_html( $ip_to_ban ) . '</code> añadida a la Lista Negra.</strong></p></div>';
        }
    }
    if ( isset( $_POST['bap_unban_ip'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $ip_to_unban = trim( sanitize_text_field( wp_unslash( $_POST['bap_unban_ip'] ) ) );
        $current_bl  = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
        update_option( 'bap_blacklist_ips', implode( "\n", array_diff( $current_bl, [ $ip_to_unban ] ) ) );
        return '<div class="notice notice-success is-dismissible"><p><strong>IP <code>' . esc_html( $ip_to_unban ) . '</code> eliminada de la Lista Negra.</strong></p></div>';
    }
    return '';
}

function bap_render_estadisticas_page() {
    global $wpdb;
    bap_maybe_create_logs_table();

    $notice     = bap_handle_stats_actions();
    $ip2loc_tbl = bap_find_ip2location_log_table();
    $table      = bap_get_logs_table_name();

    $total_hits = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    $unique_ips = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT ip_address) FROM {$table}" );

    // Top 8 Países
    $country_rank = [];
    $c_rows = $wpdb->get_results(
        "SELECT country_code, COUNT(*) AS hits FROM {$table} GROUP BY country_code ORDER BY hits DESC LIMIT 8",
        ARRAY_A
    );
    foreach ( $c_rows as $r ) {
        $country_rank[ $r['country_code'] ] = (int) $r['hits'];
    }

    // Top 8 ASNs
    $asn_rank = [];
    $a_rows = $wpdb->get_results(
        "SELECT IF(asn = '', 'N/D', asn) AS asn_label, COUNT(*) AS hits FROM {$table} GROUP BY asn_label ORDER BY hits DESC LIMIT 8",
        ARRAY_A
    );
    foreach ( $a_rows as $r ) {
        $asn_rank[ $r['asn_label'] ] = (int) $r['hits'];
    }

    // Top 500 IPs agrupadas para la tabla visual (mantiene la interfaz rápida)
    $grouped = $wpdb->get_results(
        "SELECT ip_address AS ip, MAX(country_code) AS country, COUNT(*) AS count, MAX(created_at) AS last_hit, MAX(id) AS max_id
         FROM {$table}
         GROUP BY ip_address
         ORDER BY count DESC
         LIMIT 500",
        ARRAY_A
    );

    $logs = [];
    foreach ( $grouped as $row ) {
        $reason = (string) $wpdb->get_var(
            $wpdb->prepare( "SELECT reason FROM {$table} WHERE id = %d", (int) $row['max_id'] )
        );
        $logs[ $row['ip'] ] = [
            'ip'       => $row['ip'],
            'country'  => $row['country'],
            'count'    => (int) $row['count'],
            'reason'   => $reason,
            'last_hit' => $row['last_hit'],
        ];
    }

    $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
    $retention_days = (int) get_option( 'bap_logs_retention_days', 90 );
    require plugin_dir_path( __FILE__ ) . 'admin-estadisticas-view.php';
}
