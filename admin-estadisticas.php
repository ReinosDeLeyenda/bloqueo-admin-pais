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
    if ( isset( $_POST['bap_clear_logs'] ) && check_admin_referer( 'bap_logs_action' ) ) {
        delete_option( 'bap_blocked_logs' );
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
    $notice = bap_handle_stats_actions();
    $logs   = get_option( 'bap_blocked_logs', [] );
    if ( ! is_array( $logs ) ) {
        $logs = [];
    }

    $total_hits   = 0;
    $country_rank = [];
    $asn_rank     = [];

    foreach ( $logs as $entry ) {
        $cnt = (int) ( $entry['count'] ?? 1 );
        $total_hits += $cnt;

        $c = $entry['country'] ?? 'DESCONOCIDO';
        $country_rank[ $c ] = ( $country_rank[ $c ] ?? 0 ) + $cnt;

        $asn_label = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $entry['ip'] ) : 'N/D';
        $asn_rank[ $asn_label ] = ( $asn_rank[ $asn_label ] ?? 0 ) + $cnt;
    }

    arsort( $country_rank );
    arsort( $asn_rank );
    uasort( $logs, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );

    $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );

    require plugin_dir_path( __FILE__ ) . 'admin-estadisticas-view.php';
}
