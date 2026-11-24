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
    return '';
}

function bap_render_estadisticas_page() {
    global $wpdb;
    bap_maybe_create_logs_table();

    $notice      = bap_handle_stats_actions();
    $table       = bap_get_logs_table_name();
    $filter_uri  = isset( $_GET['filter_uri'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_uri'] ) ) : '';
    $filter_q    = isset( $_GET['filter_q'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_q'] ) ) : '';

    $total_blocks  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
    $top_countries = $wpdb->get_results( "SELECT country_code, COUNT(*) as total FROM `{$table}` GROUP BY country_code ORDER BY total DESC LIMIT 5" );
    $top_asns      = $wpdb->get_results( "SELECT asn, COUNT(*) as total FROM `{$table}` WHERE asn != '' GROUP BY asn ORDER BY total DESC LIMIT 5" );
    $top_uris      = $wpdb->get_results( "SELECT request_uri, COUNT(*) as total FROM `{$table}` WHERE request_uri != '' GROUP BY request_uri ORDER BY total DESC LIMIT 5" );

    $where  = [];
    $params = [];

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

    wp_enqueue_script( 'bap-admin-estadisticas', plugin_dir_url( __FILE__ ) . 'admin-estadisticas.js', [], '1.4', true );
    require plugin_dir_path( __FILE__ ) . 'admin-estadisticas-view.php';
}
