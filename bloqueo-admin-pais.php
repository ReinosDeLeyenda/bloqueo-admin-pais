<?php
/**
 * Plugin Name: Bloqueo Admin por País e IP
 * Plugin URI: https://github.com/ReinosDeLeyenda/bloqueo-admin-pais
 * Description: Bloquea wp-admin, wp-login.php y el frontend por país, ASN, AbuseIPDB, Tor, FireHOL e IP usando bases de datos locales.
 * Version: 1.4.72
 * Author: ReinosDeLeyenda
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bap_dir   = plugin_dir_path( __FILE__ );
$bap_files = [
    // Common - Motor compartido (cargar PRIMERO)
    'common/mmdb-reader.php',
    'common/cidr-helper.php',
    'common/subnet-autoban.php',
    'common/geo-db-manager.php',
    'common/abuseipdb-manager.php',
    'common/threat-lists-manager.php',
    'common/custom-db-manager.php',
    'common/web-rules-manager.php',
    'common/security-evaluator.php',
    'common/htaccess-manager.php',
    'common/external-db-manager.php',
    'common/logs-db-manager.php',
    'common/email-digest-template.php',
    'common/notificaciones-mail.php',
    'common/self-lockout-guard.php',
    'backend/comun/post-db-setup-wizard.php',
    'common/telegram-template-renderer.php',
    'common/telegram-notifier.php',
    'common/webhook-notifier.php',
    'common/login-notifier.php',
    'common/github-updater.php',
    // Frontend - Cortafuegos público
    'frontend/block-page-403.php',
    'frontend/anti-spam-forms-guard.php',
    'frontend/seo-bots-helper.php',
    'frontend/anti-spam-forms-guard.php',
    'frontend/country-redirects.php',
    'frontend/anti-spam-forms-guard.php',
    'frontend/frontend-firewall.php',
    'frontend/anti-spam-forms-guard.php',
    'frontend/rate-limiter.php',
    'frontend/anti-spam-forms-guard.php',
    'frontend/user-agent-blocker.php',
    'frontend/anti-spam-forms-guard.php',
    'frontend/honeypot-protector.php',
    'frontend/anti-spam-forms-guard.php',
    'frontend/waf-payload-inspector.php',
    'frontend/anti-spam-forms-guard.php',
    'frontend/trap-urls-blocker.php',
    'frontend/anti-spam-forms-guard.php',
    'frontend/js-browser-challenge.php',
    'frontend/anti-spam-forms-guard.php',
    'frontend/under-attack-mode.php',
    'frontend/anti-spam-forms-guard.php',
        // Backend - 1. Bases de Datos
    'backend/admindb/admin-db.php',
    'backend/admindb/admin-custom-dbs.php',
    'backend/admindb/db-settings.php',
    'backend/admindb/config-exporter.php',
    'backend/admindb/db-backup-telegram.php',
    // Backend - 4. Auditoría y Hardening
    'backend/auditoria/plugin-usage-scanner.php',
    'backend/auditoria/admin-auditoria-view.php',
    'backend/auditoria/security-executive-report.php',
    'backend/auditoria/all-scanners-runner.php',
    'backend/auditoria/core-integrity-scanner.php',
    'backend/auditoria/root-directory-watcher.php',
    'backend/auditoria/unused-files-scanner.php',
    'backend/auditoria/server-error-log-scanner.php',
    'backend/auditoria/ssh-auth-log-scanner.php',
    'backend/auditoria/custom-integrity-baseline.php',
    'backend/auditoria/custom-integrity-view.php',
    'backend/auditoria/malware-scanner.php',
    'backend/auditoria/malware-quarantine-manager.php',
    'backend/auditoria/abandoned-plugins-scanner.php',
    'backend/auditoria/rogue-admin-scanner.php',
    'backend/auditoria/rogue-admin-view.php',
    'backend/auditoria/roles-integrity-monitor.php',
    'backend/auditoria/weak-passwords-auditor.php',
    'backend/auditoria/wp-cron-inspector.php',
    'backend/auditoria/cve-vulnerability-scanner.php',
    'backend/auditoria/cve-scanner-view.php',
    'backend/auditoria/cve-virtual-patcher.php',
    'backend/auditoria/scheduled-db-scanner.php',
    'backend/auditoria/scheduled-db-scanner-view.php',
    'backend/auditoria/db-schema-monitor.php',
    'backend/auditoria/options-autoload-auditor.php',
    'backend/auditoria/seo-spam-scanner.php',
    'backend/auditoria/content-defacement-monitor.php',
    'backend/auditoria/wp-hardening.php',
    'backend/auditoria/upload-magic-bytes-guard.php',
    'backend/auditoria/file-permissions-auditor.php',
    'backend/auditoria/ssl-and-config-monitor.php',
    'backend/auditoria/server-stack-auditor.php',
    'backend/auditoria/headers-and-mail-dns-auditor.php',
    'backend/auditoria/headers-and-mail-dns-view.php',
    'backend/auditoria/realtime-config-watcher.php',
    'backend/auditoria/dnsbl-reputation-checker.php',
    'backend/auditoria/dns-hijack-monitor.php',
    'backend/auditoria/emergency-mode-manager.php',
    'backend/auditoria/early-waf-manager.php',
    'backend/auditoria/outbound-http-firewall.php',
    // Backend - 2. Bloqueos Admin
    'backend/bloqueoa/custom-login-slug.php',
    'backend/bloqueoa/admin-firewall.php',
    'backend/bloqueoa/admin-settings-register.php',
    'backend/bloqueoa/admin-bloqueos.php',
    'backend/bloqueoa/admin-bloqueos-hub.php',
    'backend/bloqueoa/ip-simulator.php',
    // Backend - 3. Bloqueos Web
    'backend/bloqueow/web-bloqueos.php',
    // Backend - 4. Estadísticas
    'backend/estadisticas/admin-estadisticas.php',
    'backend/estadisticas/activity-logger.php',
    'backend/estadisticas/ip2location-importer.php',
    'backend/estadisticas/wordfence-importer.php',
    'backend/estadisticas/logs-exporter.php',
    // Backend - 5 y 6. Plantillas Email y Telegram
    'backend/notificaciones/admin-notificaciones.php',
    'backend/plantillase/admin-plantillas-hub.php',
    'backend/plantillase/admin-email-builder.php',
    'backend/plantillast/admin-tg-builder.php',
    // Backend - Seguridad de Cuentas y Comunes
    'backend/adminuser/hibp-checker.php',
    'backend/adminuser/password-policy.php',
    'backend/adminuser/active-sessions-manager.php',
    'backend/adminuser/vacation-mode.php',
    'backend/adminuser/vacation-mode-view.php',
    'backend/adminuser/session-security-notices.php',
    'backend/adminuser/session-hijack-shield.php',
    'backend/adminuser/captcha-manager.php',
    'backend/adminuser/two-factor-crypto.php',
    'backend/adminuser/two-factor-auth.php',
    'backend/adminuser/two-factor-profile.php',
    'backend/adminuser/magic-login-links.php',
    'backend/adminuser/per-user-geo-asn-lock.php',
    'backend/comun/dashboard-widget.php',
    'backend/comun/menu-alerts-badge.php',
    'backend/comun/guest-demo-mode.php',
];

foreach ( $bap_files as $bap_file ) {
    if ( file_exists( $bap_dir . $bap_file ) ) {
        require_once $bap_dir . $bap_file;
    }
}

register_activation_hook( __FILE__, 'bap_maybe_create_logs_table' );

function bap_get_user_ip() {
    $source = (string) get_option( 'bap_ip_source', 'remote_addr' );
    $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';

    if ( 'cloudflare' === $source && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $cf_ip = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) );
        if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
            return $cf_ip;
        }
    }

    if ( 'x_forwarded_for' === $source && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
        $fwd = trim( reset( $ips ) );
        if ( filter_var( $fwd, FILTER_VALIDATE_IP ) ) {
            return $fwd;
        }
    }

    if ( 'x_real_ip' === $source && ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
        $real = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) );
        if ( filter_var( $real, FILTER_VALIDATE_IP ) ) {
            return $real;
        }
    }

    return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
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

function bap_is_ip_whitelisted_or_server( $ip ) {
    if ( '' === (string) $ip ) { return false; }
    return bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) );
}

function bap_has_valid_secret_bypass( $set_cookie = false ) {
    $secret = trim( (string) get_option( 'bap_secret_key', '' ) );
    if ( '' === $secret ) {
        return false;
    }
    $cname = 'bap_bypass_' . md5( $secret );
    $token = hash( 'sha256', $secret . AUTH_SALT );

    $has_param = array_key_exists( $secret, $_GET );
    if ( ! $has_param && ! empty( $_GET['redirect_to'] ) ) {
        $redir_query = (string) wp_parse_url( wp_unslash( $_GET['redirect_to'] ), PHP_URL_QUERY );
        if ( '' !== $redir_query ) {
            parse_str( $redir_query, $redir_params );
            $has_param = array_key_exists( $secret, $redir_params );
        }
    }

    if ( $has_param ) {
        if ( $set_cookie && ! headers_sent() ) {
            setcookie( $cname, $token, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
        return true;
    }

    return ( isset( $_COOKIE[ $cname ] ) && hash_equals( $token, (string) $_COOKIE[ $cname ] ) );
}
function bap_destroy_user_sessions_except_current( $user_id ) {
    if ( $user_id > 0 && get_current_user_id() !== (int) $user_id && class_exists( 'WP_Session_Tokens' ) ) {
        WP_Session_Tokens::get_instance( (int) $user_id )->destroy_all();
    }
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

    wp_clear_scheduled_hook( 'bap_send_telegram_digest_event' );
    wp_clear_scheduled_hook( 'bap_auto_update_dbs_event' );
    wp_clear_scheduled_hook( 'bap_daily_logs_cleanup' );
    wp_clear_scheduled_hook( 'bap_send_digest_email_event' );
    wp_clear_scheduled_hook( 'bap_purge_autobans_event' );
    wp_clear_scheduled_hook( 'bap_scheduled_security_scan_event' );
    wp_clear_scheduled_hook( 'bap_auto_db_backup_telegram_event' );

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


register_activation_hook( __FILE__, function() {
    // 1. Iniciar en modo seguro: ningun bloqueo activo hasta cargar las bases de datos
    $safe_defaults = [
        'bap_country_mode'            => 'off',
        'bap_fe_country_mode'         => 'off',
        'bap_block_tor'               => '0',
        'bap_block_vpn'               => '0',
        'bap_block_hosting'           => '0',
        'bap_block_proxies'           => '0',
        'bap_fe_block_tor'            => '0',
        'bap_fe_block_hosting'        => '0',
        'bap_fe_form_antispam_enabled'=> '1',
    ];
    foreach ( $safe_defaults as $opt_key => $opt_val ) {
        if ( false === get_option( $opt_key, false ) ) {
            add_option( $opt_key, $opt_val );
        }
    }
    // 2. Blindar automaticamente la IP del administrador que activa el plugin
    $admin_ip = function_exists( 'bap_get_client_ip' ) ? (string) bap_get_client_ip() : (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
    if ( '' !== $admin_ip && filter_var( $admin_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
        $wl = trim( (string) get_option( 'bap_whitelist_ips', '' ) );
        if ( false === strpos( $wl, $admin_ip ) ) {
            $new_wl = ( '' !== $wl ? $wl . "\n" : '' ) . $admin_ip . ' # IP inicial del administrador al activar';
            update_option( 'bap_whitelist_ips', $new_wl );
        }
    }
} );