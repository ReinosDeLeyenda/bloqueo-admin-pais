<?php
/**
 * Plugin Name: Bloqueo Admin por País e IP
 * Plugin URI: https://github.com
 * Description: Bloquea wp-admin, wp-login.php y el frontend por país, ASN, AbuseIPDB, Tor, FireHOL e IP usando bases de datos locales.
 * Version: 1.2.8
 * Author: ReinosDeLeyenda
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bap_dir   = plugin_dir_path( __FILE__ );
$bap_files = [
    // Common
    'common/cidr-helper.php',
    'common/external-db-manager.php',
    'common/logs-db-manager.php',
    'common/htaccess-manager.php',
    'common/mmdb-reader.php',
    'common/geo-db-manager.php',
    'common/abuseipdb-manager.php',
    'common/threat-lists-manager.php',
    'common/notificaciones-mail.php',
    'common/github-updater.php',
    'common/web-rules-manager.php',
    'common/security-evaluator.php',
    // Frontend
    'frontend/seo-bots-helper.php',
    'frontend/block-page-403.php',
    'frontend/country-redirects.php',
    'frontend/frontend-firewall.php',
    // Backend
    'backend/admin-firewall.php',
    'backend/admin-db.php',
    'backend/admin-bloqueos.php',
    'backend/web-bloqueos.php',
    'backend/admin-estadisticas.php',
    'backend/ip-simulator.php',
    'backend/ip2location-importer.php',
    'backend/logs-exporter.php',
    'backend/config-exporter.php',
    'backend/db-settings.php',
    'backend/hibp-checker.php',
    'backend/two-factor-crypto.php',
    'backend/two-factor-auth.php',
    'backend/two-factor-profile.php',
];

foreach ( $bap_files as $bap_file ) {
    if ( file_exists( $bap_dir . $bap_file ) ) {
        require_once $bap_dir . $bap_file;
    }
}

register_activation_hook( __FILE__, 'bap_maybe_create_logs_table' );

function bap_get_user_ip() {
    $candidates = [];

    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $candidates[] = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) );
    }
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ips          = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
        $candidates[] = trim( reset( $ips ) );
    }
    if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
        $candidates[] = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) );
    }
    if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $candidates[] = trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
    }

    foreach ( $candidates as $ip ) {
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }
    }

    return '';
}

function bap_is_server_ip( $ip ) {
    $srv = isset( $_SERVER['SERVER_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) ) : '';
    if ( '' !== $srv && $ip === $srv ) {
        return true;
    }
    return ( filter_var( $ip, FILTER_VALIDATE_IP ) && ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) );
}

function bap_parse_list( $raw, $upper = false ) {
    if ( empty( $raw ) ) {
        return [];
    }
    $clean = [];
    foreach ( preg_split( '/[\r\n,]+/', $raw ) as $item ) {
        $v = trim( $item );
        if ( '' !== $v ) {
            $clean[] = $upper ? strtoupper( $v ) : $v;
        }
    }
    return array_unique( $clean );
}

function bap_deny_access( $ip, $country, $reason ) {
    bap_log_blocked_attempt( $ip, $country, $reason );

    $url = trim( (string) get_option( 'bap_redirect_url', '' ) );
    if ( '' !== $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
        wp_redirect( esc_url_raw( $url ), 302 );
        exit;
    }
    if ( function_exists( 'bap_render_custom_403_page' ) ) {
        bap_render_custom_403_page(
            'Acceso denegado',
            'El acceso al panel de administración está restringido por las políticas de seguridad del sitio.',
            $ip,
            $country,
            $reason
        );
    }
    wp_die(
        '<h1>Acceso denegado</h1><p>El acceso al panel de administración está restringido (' . esc_html( $reason ) . ').</p>',
        'Acceso Restringido',
        [ 'response' => 403 ]
    );
}

register_deactivation_hook( __FILE__, function() {
    if ( ! function_exists( 'insert_with_markers' ) ) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }
    $htaccess_file = ABSPATH . '.htaccess';
    if ( file_exists( $htaccess_file ) && is_writable( $htaccess_file ) ) {
        insert_with_markers( $htaccess_file, 'Bloqueo Admin Pais', [] );
    }

    wp_clear_scheduled_hook( 'bap_auto_update_dbs_event' );
    wp_clear_scheduled_hook( 'bap_daily_logs_cleanup' );
    wp_clear_scheduled_hook( 'bap_send_digest_email_event' );

    $recipients = function_exists( 'bap_get_notify_recipients' ) ? bap_get_notify_recipients() : [ get_option( 'admin_email' ) ];
    if ( ! empty( $recipients ) ) {
        $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
        $user      = wp_get_current_user();
        $user_info = ( $user && $user->exists() ) ? $user->user_login . ' (' . $user->user_email . ')' : 'Desconocido';
        $ip        = bap_get_user_ip();

        $subject = sprintf( '[%s] Aviso: Plugin Bloqueo Admin desactivado', $site_name );
        $body    = "El plugin \"Bloqueo Admin por País e IP\" ha sido DESACTIVADO en " . home_url() . ".\n"
                 . "- Fecha y hora: " . current_time( 'Y-m-d H:i:s' ) . "\n- Usuario: {$user_info}\n- IP: {$ip}\n";
        wp_mail( array_unique( $recipients ), $subject, $body );
    }
});
