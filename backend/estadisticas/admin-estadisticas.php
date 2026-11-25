<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_handle_stats_actions() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '';
    }

    global $wpdb;
    $table = bap_get_logs_table_name();

    if ( isset( $_POST['bap_clear_logs'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $wpdb->query( "TRUNCATE TABLE `{$table}`" );
        return '<div class="notice notice-success is-dismissible"><p>Historial de ataques vaciado.</p></div>';
    }
    if ( isset( $_POST['bap_save_retention'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $days = max( 0, min( 3650, (int) ( $_POST['bap_logs_retention_days'] ?? 30 ) ) );
        update_option( 'bap_logs_retention_days', $days );
        return '<div class="notice notice-success is-dismissible"><p>Retención guardada (' . ( $days > 0 ? $days . ' días' : 'Ilimitada' ) . ').</p></div>';
    }
    if ( isset( $_POST['bap_import_ip2location'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $res = function_exists( 'bap_import_ip2location_stats' ) ? bap_import_ip2location_stats() : new WP_Error( 'missing', 'Importador no disponible.' );
        return is_wp_error( $res )
            ? '<div class="notice notice-error is-dismissible"><p><strong>Error al importar:</strong> ' . esc_html( $res->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success is-dismissible"><p><strong>¡Migración completada!</strong> Se han importado <strong>' . number_format_i18n( (int) $res['hits'] ) . '</strong> registros históricos desde IP2Location.</p></div>';
    }
    if ( isset( $_POST['bap_import_wordfence'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $res = function_exists( 'bap_import_wordfence_stats' ) ? bap_import_wordfence_stats() : new WP_Error( 'missing', 'Importador no disponible.' );
        return is_wp_error( $res )
            ? '<div class="notice notice-error is-dismissible"><p><strong>Error al importar:</strong> ' . esc_html( $res->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success is-dismissible"><p><strong>¡Migración completada!</strong> Se han importado <strong>' . number_format_i18n( (int) $res['hits'] ) . '</strong> registros históricos desde Wordfence.</p></div>';
    }
    return '';
}

function bap_render_estadisticas_page() {
    global $wpdb;
    bap_maybe_create_logs_table();

    $notice      = bap_handle_stats_actions();
    $table       = bap_get_logs_table_name();
    $filter_uri  = isset( $_GET['filter_uri'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_uri'] ) ) : '';
    $filter_q    = isset( $_GET['filter_q'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_q'] ) ) : '';
    $raw_type    = isset( $_GET['filter_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_type'] ) ) : 'all';
    $filter_type = in_array( $raw_type, [ 'all', 'admin', 'web' ], true ) ? $raw_type : 'all';

    $type_where_sql = '';
    if ( 'admin' === $filter_type ) {
        $type_where_sql = " AND block_type = 'admin'";
    } elseif ( 'web' === $filter_type ) {
        $type_where_sql = " AND block_type = 'web'";
    }

    $total_blocks  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE 1=1{$type_where_sql}" );
    $top_countries = $wpdb->get_results( "SELECT country_code, COUNT(*) as total FROM `{$table}` WHERE 1=1{$type_where_sql} GROUP BY country_code ORDER BY total DESC LIMIT 5" );
    $top_asns      = $wpdb->get_results( "SELECT asn, COUNT(*) as total FROM `{$table}` WHERE asn != ''{$type_where_sql} GROUP BY asn ORDER BY total DESC LIMIT 5" );
    $top_uris      = $wpdb->get_results( "SELECT request_uri, COUNT(*) as total FROM `{$table}` WHERE request_uri != ''{$type_where_sql} GROUP BY request_uri ORDER BY total DESC LIMIT 5" );

    $where  = [];
    $params = [];

    if ( 'admin' === $filter_type || 'web' === $filter_type ) {
        $where[]  = 'block_type = %s';
        $params[] = $filter_type;
    }
    if ( '' !== $filter_uri ) {
        $where[]  = 'request_uri LIKE %s';
        $params[] = '%' . $wpdb->esc_like( $filter_uri ) . '%';
    }
    if ( '' !== $filter_q ) {
        $like     = '%' . $wpdb->esc_like( $filter_q ) . '%';
        $where[]  = '(ip_address LIKE %s OR country_code LIKE %s OR asn LIKE %s OR reason LIKE %s OR request_uri LIKE %s)';
        array_push( $params, $like, $like, $like, $like, $like );
    }

    $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
    $sql       = "SELECT * FROM `{$table}` {$where_sql} ORDER BY id DESC LIMIT 100";
    $logs      = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

    $ip_history = [];
    if ( ! empty( $logs ) ) {
        $unique_ips = array_values( array_unique( array_filter( array_map( fn( $l ) => trim( (string) $l->ip_address ), $logs ) ) ) );
        if ( ! empty( $unique_ips ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $unique_ips ), '%s' ) );
            $agg_sql      = "SELECT ip_address, MIN(created_at) AS first_seen, MAX(created_at) AS last_seen, COUNT(*) AS total_hits
                             FROM `{$table}`
                             WHERE ip_address IN ({$placeholders}){$type_where_sql}
                             GROUP BY ip_address";
            $agg_rows     = $wpdb->get_results( $wpdb->prepare( $agg_sql, $unique_ips ), ARRAY_A );
            foreach ( (array) $agg_rows as $r ) {
                $ip_history[ $r['ip_address'] ] = $r;
            }
        }
    }

    wp_enqueue_script( 'bap-admin-estadisticas', plugin_dir_url( __FILE__ ) . 'admin-estadisticas.js', [], '1.5', true );
    require plugin_dir_path( __FILE__ ) . 'admin-estadisticas-view.php';
}