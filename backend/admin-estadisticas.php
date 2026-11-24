<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( false !== strpos( $hook, 'bap-admin-estadisticas' ) ) {
        wp_enqueue_script( 'bap-admin-estadisticas', plugin_dir_url( __FILE__ ) . 'admin-estadisticas.js', [], '11.2', true );
    }
});

function bap_handle_stats_actions() {
    global $wpdb;
    $db    = function_exists( 'bap_get_logs_db' ) ? bap_get_logs_db() : $wpdb;
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
        $db->query( "TRUNCATE TABLE `{$table}`" );
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
    $ip2loc_tbl = function_exists( 'bap_find_ip2location_log_table' ) ? bap_find_ip2location_log_table() : '';
    $db         = function_exists( 'bap_get_logs_db' ) ? bap_get_logs_db() : $wpdb;
    $table      = bap_get_logs_table_name();

    $total_hits = (int) $db->get_var( "SELECT COUNT(*) FROM `{$table}`" );
    $unique_ips = (int) $db->get_var( "SELECT COUNT(DISTINCT ip_address) FROM `{$table}`" );

    $country_rank = [];
    $c_rows = $db->get_results(
        "SELECT country_code, COUNT(*) AS hits FROM `{$table}` GROUP BY country_code ORDER BY hits DESC LIMIT 8",
        ARRAY_A
    );
    foreach ( (array) $c_rows as $r ) {
        $country_rank[ $r['country_code'] ] = (int) $r['hits'];
    }

    $asn_rank = [];
    $a_rows = $db->get_results(
        "SELECT IF(asn = '', 'N/D', asn) AS asn_label, COUNT(*) AS hits FROM `{$table}` GROUP BY asn_label ORDER BY hits DESC LIMIT 8",
        ARRAY_A
    );
    foreach ( (array) $a_rows as $r ) {
        $asn_rank[ $r['asn_label'] ] = (int) $r['hits'];
    }

    $grouped = $db->get_results(
        "SELECT ip_address AS ip, MAX(country_code) AS country, COUNT(*) AS count, MAX(created_at) AS last_hit, MAX(id) AS max_id
         FROM `{$table}`
         GROUP BY ip_address
         ORDER BY count DESC
         LIMIT 500",
        ARRAY_A
    );

    $logs = [];
    foreach ( (array) $grouped as $row ) {
        $reason = (string) $db->get_var(
            $db->prepare( "SELECT reason FROM `{$table}` WHERE id = %d", (int) $row['max_id'] )
        );
        $logs[ $row['ip'] ] = [
            'ip'       => $row['ip'],
            'country'  => $row['country'],
            'count'    => (int) $row['count'],
            'reason'   => $reason,
            'last_hit' => $row['last_hit'],
        ];
    }

    $blacklist      = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
    $retention_days = (int) get_option( 'bap_logs_retention_days', 90 );
    require plugin_dir_path( __FILE__ ) . 'admin-estadisticas-view.php';
}
