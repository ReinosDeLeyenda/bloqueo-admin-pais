<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_handle_test_email_post() {
    if ( isset( $_POST['bap_send_test_email'] ) && check_admin_referer( 'bap_test_email_action' ) ) {
        $recipients = function_exists( 'bap_get_notify_recipients' ) ? bap_get_notify_recipients() : [ get_option( 'admin_email' ) ];
        $site       = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $subject    = '[' . $site . '] Email de prueba - Bloqueo Admin';
        $body       = "Este es un correo de prueba enviado desde el plugin Bloqueo Admin por País e IP.\n\nFecha/Hora: " . current_time( 'mysql' ) . "\nDestinatarios activos: " . implode( ', ', $recipients ) . "\n";
        return wp_mail( $recipients, $subject, $body )
            ? '<div class="notice notice-success is-dismissible"><p><strong>¡Email de prueba enviado correctamente a:</strong> <code>' . esc_html( implode( ', ', $recipients ) ) . '</code>!</p></div>'
            : '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> WordPress (<code>wp_mail</code>) no pudo enviar el correo.</p></div>';
    }
    if ( isset( $_POST['bap_send_test_digest'] ) && check_admin_referer( 'bap_test_email_action' ) ) {
        $recipients = function_exists( 'bap_get_notify_recipients' ) ? bap_get_notify_recipients() : [ get_option( 'admin_email' ) ];
        $sent       = function_exists( 'bap_send_test_digest_email' ) ? bap_send_test_digest_email() : false;
        return $sent
            ? '<div class="notice notice-success is-dismissible"><p><strong>¡Email de prueba de resumen agrupado enviado a:</strong> <code>' . esc_html( implode( ', ', $recipients ) ) . '</code>!</p></div>'
            : '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> WordPress (<code>wp_mail</code>) no pudo enviar el correo de resumen.</p></div>';
    }
    return '';
}

function bap_build_blacklist_tooltip_meta( array $bl_ips ) {
    if ( empty( $bl_ips ) ) {
        return [];
    }
    global $wpdb;
    $logs_table   = function_exists( 'bap_get_logs_table_name' ) ? bap_get_logs_table_name() : $wpdb->prefix . 'bap_blocked_logs';
    $placeholders = implode( ',', array_fill( 0, count( $bl_ips ), '%s' ) );
    $rows         = $wpdb->get_results(
        $wpdb->prepare( "SELECT ip_address, COUNT(*) AS cnt, MAX(id) AS max_id FROM {$logs_table} WHERE ip_address IN ({$placeholders}) GROUP BY ip_address", $bl_ips ),
        ARRAY_A
    );

    $ip_stats = [];
    foreach ( $rows as $r ) {
        $last_reason = (string) $wpdb->get_var( $wpdb->prepare( "SELECT reason FROM {$logs_table} WHERE id = %d", (int) $r['max_id'] ) );
        $ip_stats[ $r['ip_address'] ] = [ 'count' => (int) $r['cnt'], 'reason' => $last_reason ?: 'Bloqueada' ];
    }

    $ip_meta = [];
    foreach ( $bl_ips as $b_ip ) {
        $risk = function_exists( 'bap_get_abuseipdb_score' ) ? bap_get_abuseipdb_score( $b_ip ) : null;
        $ip_meta[ $b_ip ] = [
            'asn'     => function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $b_ip ) : 'N/D',
            'country' => function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $b_ip ) : 'N/D',
            'count'   => $ip_stats[ $b_ip ]['count'] ?? 0,
            'reason'  => $ip_stats[ $b_ip ]['reason'] ?? 'Añadida manualmente',
            'risk'    => ( null === $risk ) ? 'Sin datos (N/D)' : $risk . '%',
        ];
    }
    return $ip_meta;
}

function bap_render_bloqueos_page() {
    $test_notice = bap_handle_test_email_post();
    $ip          = bap_get_user_ip();
    $src         = '';
    $ctry        = bap_is_server_ip( $ip ) ? 'SERVIDOR LOCAL' : bap_get_country_by_ip( $ip, $src );
    $bl_raw      = get_option( 'bap_blacklist_ips', '' );
    $bl_ips      = bap_parse_list( $bl_raw, false );
    $freq        = (string) get_option( 'bap_notify_freq', 'none' );
    $ip_meta     = bap_build_blacklist_tooltip_meta( $bl_ips );

    wp_enqueue_script( 'bap-admin-bloqueos', plugin_dir_url( __FILE__ ) . 'admin-bloqueos.js', [], '12.2', true );
    wp_enqueue_script( 'bap-countries-data', plugin_dir_url( __FILE__ ) . 'countries-data.js', [], '1.0', true );
    wp_enqueue_script( 'bap-countries-picker', plugin_dir_url( __FILE__ ) . 'countries-picker.js', [ 'bap-countries-data' ], '13.2', true );
    wp_localize_script( 'bap-admin-bloqueos', 'bapAdminData', [ 'ipMeta' => $ip_meta ] );

    require plugin_dir_path( __FILE__ ) . 'admin-bloqueos-view.php';
}
