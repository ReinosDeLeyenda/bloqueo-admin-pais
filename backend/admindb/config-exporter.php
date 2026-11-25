<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_exportable_options( $include_secrets = false ) {
    $keys = [
        // 1. Bases de datos y MySQL externo
        'bap_db_auto_update_freq', 'bap_auto_scan_freq', 'bap_custom_dbs',
        'bap_harden_disable_file_edit', 'bap_harden_hide_wp_version', 'bap_harden_block_uploads_php', 'bap_harden_security_headers',
        'bap_ext_db_enabled', 'bap_ext_db_host', 'bap_ext_db_port',
        'bap_ext_db_name', 'bap_ext_db_user', 'bap_ext_db_table',

        // 2. Bloqueos Admin (Reglas, Red, HIBP, Política Contraseñas y 2FA)
        'bap_ip_source', 'bap_custom_login_slug', 'bap_away_mode_enabled', 'bap_away_mode_start', 'bap_away_mode_end', 'bap_away_mode_days', 'bap_protected_paths', 'bap_enable_htaccess', 'bap_redirect_url',
        'bap_country_mode', 'bap_allowed_countries', 'bap_blocked_countries',
        'bap_asn_mode', 'bap_allowed_asns', 'bap_blocked_asns',
        'bap_abuseipdb_min_risk', 'bap_auto_ban_attempts', 'bap_auto_ban_duration',
        'bap_block_nonexistent_users', 'bap_block_xmlrpc', 'bap_block_user_enum',
        'bap_block_tor', 'bap_block_firehol',
        'bap_whitelist_ips', 'bap_blacklist_ips',
        'bap_hibp_enabled', 'bap_hibp_check_login', 'bap_hibp_login_action',
        'bap_pwd_policy_enabled', 'bap_pwd_policy_scope', 'bap_pwd_min_length',
        'bap_pwd_require_complexity', 'bap_pwd_prevent_reuse', 'bap_pwd_expiry_days',
        'bap_captcha_provider', 'bap_captcha_site_key', 'bap_recaptcha_v3_score', 'bap_captcha_scopes',
        'bap_2fa_enabled', 'bap_2fa_enforce_admins', 'bap_2fa_remember_device',

        // 3. Notificaciones y Plantillas Email/Telegram
        'bap_notify_channel', 'bap_notify_freq', 'bap_notify_emails', 'bap_tg_custom_templates',
        'bap_email_template', 'bap_custom_email_templates', 'bap_tg_chat_ids',
        'bap_login_notify_channel', 'bap_login_notify_routes',

        // 4. Bloqueos Web (Frontend, Rate Limit y User-Agents)
        'bap_web_rules', 'bap_web_allow_seo_bots', 'bap_web_country_redirects',
        'bap_web_redirect_url', 'bap_web_block_message',
        'bap_waf_enabled', 'bap_waf_autoban',
        'bap_rl_max_req_min', 'bap_rl_max_404_min', 'bap_rl_autoban',
        'bap_blocked_user_agents', 'bap_block_empty_ua', 'bap_honeypot_enabled', 'bap_hp_autoban',
    ];

    if ( $include_secrets ) {
        $keys[] = 'bap_secret_key';
        $keys[] = 'bap_abuseipdb_key';
        $keys[] = 'bap_ext_db_pass';
        $keys[] = 'bap_tg_bot_token';
        $keys[] = 'bap_captcha_secret_key';
    }
    return $keys;
}

function bap_import_json_config( $tmp_file ) {
    $data = json_decode( (string) file_get_contents( $tmp_file ), true );
    if ( ! is_array( $data ) || 'bloqueo-admin-pais' !== ( $data['plugin'] ?? '' ) || empty( $data['options'] ) ) {
        return '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> El archivo JSON no es una configuración válida del plugin.</p></div>';
    }
    $allowed_keys = bap_get_exportable_options( true );
    $imported     = 0;
    foreach ( $data['options'] as $k => $v ) {
        if ( in_array( $k, $allowed_keys, true ) ) {
            update_option( $k, $v );
            $imported++;
        }
    }
    if ( function_exists( 'bap_sync_htaccess_rules' ) ) {
        bap_sync_htaccess_rules();
    }
    return '<div class="notice notice-success is-dismissible"><p><strong>¡Configuración importada correctamente (' . (int) $imported . ' ajustes restaurados)!</strong></p></div>';
}

add_action( 'admin_init', function() {
    if ( isset( $_POST['bap_export_json'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_db_action' ) ) {
        $inc_secrets = ! empty( $_POST['bap_export_secrets'] );
        $payload     = [
            'plugin'      => 'bloqueo-admin-pais',
            'version'     => '14.0',
            'exported_at' => current_time( 'mysql' ),
            'options'     => [],
        ];
        foreach ( bap_get_exportable_options( $inc_secrets ) as $opt ) {
            $val = get_option( $opt, null );
            if ( null !== $val ) {
                $payload['options'][ $opt ] = $val;
            }
        }
        bap_send_download_headers( 'bloqueo-admin-config-' . gmdate( 'Y-m-d' ) . '.json', 'application/json' );
        echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }
});
