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

    if ( isset( $_POST['bap_ban_ip_from_stats'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $target_ip = trim( sanitize_text_field( wp_unslash( $_POST['bap_target_ip'] ?? '' ) ) );
        if ( ! filter_var( $target_ip, FILTER_VALIDATE_IP ) ) {
            return '<div class="notice notice-error is-dismissible"><p>Direccion IP no valida.</p></div>';
        }
        $my_ip = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
        if ( $target_ip === $my_ip || bap_is_server_ip( $target_ip ) ) {
            return '<div class="notice notice-error is-dismissible"><p><strong>Seguridad:</strong> No puedes anadir tu propia IP o la del servidor a la Lista Negra.</p></div>';
        }
        $bl = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
        if ( ! in_array( $target_ip, $bl, true ) ) {
            $bl[] = $target_ip;
            update_option( 'bap_blacklist_ips', implode( "\n", $bl ) );
        }
        return '<div class="notice notice-success is-dismissible"><p><strong>IP bloqueada:</strong> <code>' . esc_html( $target_ip ) . '</code> se ha anadido permanentemente a la Lista Negra.</p></div>';
    }
    if ( isset( $_POST['bap_clear_logs'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $wpdb->query( "TRUNCATE TABLE `{$table}`" );
        return '<div class="notice notice-success is-dismissible"><p>Historial de ataques vaciado.</p></div>';
    }
    if ( isset( $_POST['bap_save_retention'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $days = max( 0, min( 3650, (int) ( $_POST['bap_logs_retention_days'] ?? 30 ) ) );
        update_option( 'bap_logs_retention_days', $days );
        return '<div class="notice notice-success is-dismissible"><p>Retencion guardada (' . ( $days > 0 ? $days . ' dias' : 'Ilimitada' ) . ').</p></div>';
    }
    if ( isset( $_POST['bap_import_ip2location'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $res = function_exists( 'bap_import_ip2location_stats' ) ? bap_import_ip2location_stats() : new WP_Error( 'missing', 'Importador no disponible.' );
        return is_wp_error( $res )
            ? '<div class="notice notice-error is-dismissible"><p><strong>Error al importar:</strong> ' . esc_html( $res->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success is-dismissible"><p><strong>Migracion completada:</strong> Se han importado <strong>' . number_format_i18n( (int) $res['hits'] ) . '</strong> registros historicos desde IP2Location.</p></div>';
    }
    if ( isset( $_POST['bap_import_wordfence'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        $res = function_exists( 'bap_import_wordfence_stats' ) ? bap_import_wordfence_stats() : new WP_Error( 'missing', 'Importador no disponible.' );
        return is_wp_error( $res )
            ? '<div class="notice notice-error is-dismissible"><p><strong>Error al importar:</strong> ' . esc_html( $res->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success is-dismissible"><p><strong>Migracion completada:</strong> Se han importado <strong>' . number_format_i18n( (int) $res['hits'] ) . '</strong> registros historicos desde Wordfence.</p></div>';
    }
    return '';
}
function bap_render_estadisticas_page() {
    global $wpdb;
    bap_maybe_create_logs_table();

    $notice      = bap_handle_stats_actions();
    $table       = bap_get_logs_table_name();
    $raw_type    = isset( $_GET['filter_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_type'] ) ) : 'all';
    $filter_type = in_array( $raw_type, [ 'all', 'admin', 'web' ], true ) ? $raw_type : 'all';

    $allowed_fields = [ 'any', 'uri', 'ip', 'country', 'asn', 'reason', 'abuseipdb' ];
    $filter_rows    = [];

    if ( isset( $_GET['f_val'] ) || isset( $_GET['f_field'] ) ) {
        $vals   = (array) wp_unslash( $_GET['f_val'] ?? [] );
        $fields = (array) wp_unslash( $_GET['f_field'] ?? [] );
        $modes  = (array) wp_unslash( $_GET['f_mode'] ?? [] );
        $max_r  = max( count( $vals ), count( $fields ) );

        for ( $i = 0; $i < $max_r; $i++ ) {
            $v = trim( sanitize_text_field( (string) ( $vals[ $i ] ?? '' ) ) );
            $f = sanitize_key( (string) ( $fields[ $i ] ?? 'any' ) );
            if ( ! in_array( $f, $allowed_fields, true ) ) { $f = 'any'; }
            $m = ( 'exclude' === ( $modes[ $i ] ?? 'include' ) ) ? 'exclude' : 'include';
            if ( '' !== $v || 'abuseipdb' === $f ) {
                $filter_rows[] = [ 'val' => $v, 'field' => $f, 'mode' => $m ];
            }
        }
    } else {
        $leg_u = isset( $_GET['filter_uri'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['filter_uri'] ) ) ) : '';
        $leg_q = isset( $_GET['filter_q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['filter_q'] ) ) ) : '';
        if ( '' !== $leg_u ) { $filter_rows[] = [ 'val' => $leg_u, 'field' => 'uri', 'mode' => 'include' ]; }
        if ( '' !== $leg_q ) { $filter_rows[] = [ 'val' => $leg_q, 'field' => 'any', 'mode' => 'include' ]; }
    }
    if ( empty( $filter_rows ) ) {
        $filter_rows[] = [ 'val' => '', 'field' => 'any', 'mode' => 'include' ];
    }

    $type_where_sql = ( 'admin' === $filter_type ) ? " AND block_type = 'admin'" : ( ( 'web' === $filter_type ) ? " AND block_type = 'web'" : '' );
    $total_blocks   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE 1=1{$type_where_sql}" );
    $top_countries  = $wpdb->get_results( "SELECT country_code, COUNT(*) as total FROM `{$table}` WHERE 1=1{$type_where_sql} GROUP BY country_code ORDER BY total DESC LIMIT 5" );
    $top_asns       = $wpdb->get_results( "SELECT asn, COUNT(*) as total FROM `{$table}` WHERE asn != ''{$type_where_sql} GROUP BY asn ORDER BY total DESC LIMIT 5" );
    $top_uris       = $wpdb->get_results( "SELECT request_uri, COUNT(*) as total FROM `{$table}` WHERE request_uri != ''{$type_where_sql} GROUP BY request_uri ORDER BY total DESC LIMIT 5" );

    $where         = [];
    $params        = [];
    $abuse_filters = [];

    if ( 'admin' === $filter_type || 'web' === $filter_type ) {
        $where[]  = 'block_type = %s';
        $params[] = $filter_type;
    }

    $col_map = [ 'uri' => 'request_uri', 'ip' => 'ip_address', 'country' => 'country_code', 'asn' => 'asn', 'reason' => 'reason' ];

    foreach ( $filter_rows as $fr ) {
        $f      = $fr['field'];
        $v      = $fr['val'];
        $is_inc = ( 'exclude' !== $fr['mode'] );

        if ( 'abuseipdb' === $f ) {
            $abuse_filters[] = [ 'val' => $v, 'include' => $is_inc ];
            continue;
        }
        if ( '' === $v ) { continue; }

        $like = '%' . $wpdb->esc_like( $v ) . '%';
        if ( isset( $col_map[ $f ] ) ) {
            $col      = $col_map[ $f ];
            $where[]  = $is_inc ? "`{$col}` LIKE %s" : "`{$col}` NOT LIKE %s";
            $params[] = $like;
        } else {
            $where[] = $is_inc
                ? '(ip_address LIKE %s OR country_code LIKE %s OR asn LIKE %s OR reason LIKE %s OR request_uri LIKE %s)'
                : '(ip_address NOT LIKE %s AND country_code NOT LIKE %s AND asn NOT LIKE %s AND reason NOT LIKE %s AND request_uri NOT LIKE %s)';
            array_push( $params, $like, $like, $like, $like, $like );
        }
    }

    $fetch_limit = ! empty( $abuse_filters ) ? 800 : 100;
    $where_sql   = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
    $sql         = "SELECT * FROM `{$table}` {$where_sql} ORDER BY id DESC LIMIT {$fetch_limit}";
    $logs        = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

    if ( ! empty( $abuse_filters ) && ! empty( $logs ) ) {
        $logs = array_values( array_filter( $logs, function( $row ) use ( $abuse_filters ) {
            $ip_v  = trim( (string) $row->ip_address );
            $score = ( '' !== $ip_v && function_exists( 'bap_get_abuseipdb_score' ) ) ? bap_get_abuseipdb_score( $ip_v, false ) : null;
            if ( null === $score && preg_match( '/AbuseIPDB\s*\((\d+)%/i', (string) $row->reason, $m ) ) {
                $score = (int) $m[1];
            }
            foreach ( $abuse_filters as $af ) {
                $raw_v   = trim( $af['val'] );
                $matched = ( '' === $raw_v ) ? ( null !== $score && $score > 0 ) : ( null !== $score && $score >= (int) preg_replace( '/[^0-9]/', '', $raw_v ) );
                if ( ( $af['include'] && ! $matched ) || ( ! $af['include'] && $matched ) ) { return false; }
            }
            return true;
        } ) );
        $logs = array_slice( $logs, 0, 100 );
    }

    $ip_history = [];
    if ( ! empty( $logs ) ) {
        $unique_ips = array_values( array_unique( array_filter( array_map( fn( $l ) => trim( (string) $l->ip_address ), $logs ) ) ) );
        if ( ! empty( $unique_ips ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $unique_ips ), '%s' ) );
            $agg_sql      = "SELECT ip_address, MIN(created_at) AS first_seen, MAX(created_at) AS last_seen, COUNT(*) AS total_hits FROM `{$table}` WHERE ip_address IN ({$placeholders}){$type_where_sql} GROUP BY ip_address";
            $agg_rows     = $wpdb->get_results( $wpdb->prepare( $agg_sql, $unique_ips ), ARRAY_A );
            foreach ( (array) $agg_rows as $r ) { $ip_history[ $r['ip_address'] ] = $r; }
        }
    }

    wp_enqueue_script( 'bap-admin-estadisticas', plugin_dir_url( __FILE__ ) . 'admin-estadisticas.js', [], '1.7', true );
    require plugin_dir_path( __FILE__ ) . 'admin-estadisticas-view.php';
}