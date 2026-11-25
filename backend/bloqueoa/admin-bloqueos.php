<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_handle_test_email_post() {
    if ( isset( $_POST['bap_send_test_email'] ) && check_admin_referer( 'bap_test_email_action' ) ) {
        $recipients = function_exists( 'bap_get_notify_recipients' ) ? bap_get_notify_recipients() : [ get_option( 'admin_email' ) ];
        $site       = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $subject    = '[' . $site . '] Verificación de notificaciones de seguridad';
        $body       = "Hola,\n\nEste mensaje confirma que las notificaciones por correo electrónico de Bloqueo Admin están configuradas correctamente en {$site}.\n\nFecha y hora: " . current_time( 'mysql' ) . "\nDestinatarios activos: " . implode( ', ', $recipients ) . "\n";
        $sent       = function_exists( 'bap_send_mail' ) ? bap_send_mail( $recipients, $subject, $body ) : wp_mail( $recipients, $subject, $body );
        return $sent
            ? '<div class="notice notice-success is-dismissible"><p><strong>¡Correo de verificación enviado correctamente a:</strong> <code>' . esc_html( implode( ', ', $recipients ) ) . '</code>!</p></div>'
            : '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> WordPress (<code>wp_mail</code>) no pudo enviar el correo.</p></div>';
    }

    if ( isset( $_POST['bap_send_test_digest'] ) && check_admin_referer( 'bap_test_email_action' ) ) {
        $recipients = function_exists( 'bap_get_notify_recipients' ) ? bap_get_notify_recipients() : [ get_option( 'admin_email' ) ];
        $sent       = function_exists( 'bap_send_test_digest_email' ) ? bap_send_test_digest_email() : false;
        return $sent
            ? '<div class="notice notice-success is-dismissible"><p><strong>¡Email de prueba de resumen agrupado enviado a:</strong> <code>' . esc_html( implode( ', ', $recipients ) ) . '</code>!</p></div>'
            : '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> WordPress (<code>wp_mail</code>) no pudo enviar el correo de resumen.</p></div>';
    }
    if ( isset( $_POST['bap_send_test_tg'] ) && check_admin_referer( 'bap_test_email_action' ) ) {
        $site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $item = [
            'time'    => current_time( 'mysql' ),
            'ip'      => bap_get_user_ip() ?: '185.220.101.4',
            'country' => 'DE',
            'asn'     => 'AS205100 — F3 Netze e.V.',
            'risk'    => '100%',
            'reason'  => 'Nodo de salida Tor (Prueba)',
            'uri'     => '/wp-login.php',
        ];
        $msg = bap_build_telegram_single_article( $item, $site, true );
        $res = bap_send_telegram_rich_article( $msg['rich'], $msg['fallback'] );
        return is_wp_error( $res )
            ? '<div class="notice notice-error is-dismissible"><p><strong>Error Telegram:</strong> ' . esc_html( $res->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success is-dismissible"><p><strong>¡Alerta de prueba enviada a Telegram!</strong></p></div>';
    }
    if ( isset( $_POST['bap_send_test_tg_digest'] ) && check_admin_referer( 'bap_test_email_action' ) ) {
        $site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $now   = current_time( 'mysql' );
        $queue = [
            [ 'time' => $now, 'ip' => '34.140.28.27', 'country' => 'BE', 'asn' => 'AS396982 — Google LLC', 'risk' => '95%', 'reason' => 'Web Global: IP en lista negra', 'uri' => '/wp-login.php' ],
            [ 'time' => $now, 'ip' => '34.140.28.27', 'country' => 'BE', 'asn' => 'AS396982 — Google LLC', 'risk' => '95%', 'reason' => 'Web Global: IP en lista negra', 'uri' => '/wp-login.php' ],
            [ 'time' => $now, 'ip' => '185.220.101.4', 'country' => 'DE', 'asn' => 'AS205100 — F3 Netze e.V.', 'risk' => '100%', 'reason' => 'Nodo de salida Tor', 'uri' => '/xmlrpc.php' ],
        ];
        $msg = bap_build_telegram_digest_article( $queue, $site, 'Resumen de prueba', true );
        $res = bap_send_telegram_rich_article( $msg['rich'], $msg['fallback'] );
        return is_wp_error( $res )
            ? '<div class="notice notice-error is-dismissible"><p><strong>Error Telegram:</strong> ' . esc_html( $res->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success is-dismissible"><p><strong>¡Resumen Rich Article de prueba enviado a Telegram!</strong></p></div>';
    }
    if ( isset( $_POST['bap_send_test_login_alert'] ) && check_admin_referer( 'bap_test_email_action' ) ) {
        $uid = (int) ( $_POST['bap_test_route_user'] ?? 0 );
        $u   = ( $uid > 0 ) ? get_user_by( 'id', $uid ) : wp_get_current_user();
        if ( ! ( $u instanceof WP_User ) ) {
            $u = wp_get_current_user();
        }

        $test_route = [
            'user_id' => $uid,
            'target'  => trim( sanitize_text_field( wp_unslash( $_POST['bap_test_route_target'] ?? '' ) ) ),
            'type'    => ( 'email' === ( $_POST['bap_test_route_type'] ?? 'telegram' ) ) ? 'email' : 'telegram',
        ];

        $ok = function_exists( 'bap_send_successful_login_alert' ) ? bap_send_successful_login_alert( $u, true, $test_route ) : false;
        return $ok
            ? '<div class="notice notice-success is-dismissible"><p><strong>¡Aviso de prueba de conexión exitosa enviado (' . esc_html( strtoupper( $test_route['type'] ) ) . ')!</strong></p></div>'
            : '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> No se pudo enviar el aviso de prueba de esa línea (revisa el destino o el Bot Token).</p></div>';
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
    wp_enqueue_script( 'bap-countries-data', plugins_url( 'backend/comun/countries-data.js', dirname( __DIR__, 2 ) . '/bloqueo-admin-pais.php' ), [], '1.0', true );
    wp_enqueue_script( 'bap-countries-picker', plugins_url( 'backend/comun/countries-picker.js', dirname( __DIR__, 2 ) . '/bloqueo-admin-pais.php' ), [ 'bap-countries-data' ], '13.2', true );
    wp_localize_script( 'bap-admin-bloqueos', 'bapAdminData', [ 'ipMeta' => $ip_meta ] );

    require plugin_dir_path( __FILE__ ) . 'admin-bloqueos-view.php';
}
