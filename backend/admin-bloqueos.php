<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_sanitize_asn_list( $input ) {
    $clean = [];
    foreach ( bap_parse_list( $input, true ) as $item ) {
        $num = preg_replace( '/[^0-9]/', '', $item );
        if ( '' !== $num ) {
            $clean[] = 'AS' . $num;
        }
    }
    return implode( ', ', array_unique( $clean ) );
}

add_action( 'admin_init', function() {
    register_setting( 'bap_db_settings_group', 'bap_abuseipdb_key', [
        'sanitize_callback' => fn( $i ) => trim( sanitize_text_field( (string) $i ) ),
        'default'           => ''
    ]);

    register_setting( 'bap_settings_group', 'bap_protected_paths', [
        'sanitize_callback' => function( $input ) {
            $lines = [];
            foreach ( preg_split( '/[\r\n,]+/', (string) $input ) as $line ) {
                $clean = trim( sanitize_text_field( $line ) );
                if ( '' !== $clean ) {
                    if ( '/' !== $clean[0] && '*' !== $clean[0] ) {
                        $clean = '/' . $clean;
                    }
                    $lines[] = $clean;
                }
            }
            return implode( "\n", array_unique( $lines ) );
        },
        'default' => function_exists( 'bap_get_default_protected_paths' ) ? bap_get_default_protected_paths() : "/wp-admin/*\n/wp-login.php"
    ]);
    register_setting( 'bap_settings_group', 'bap_2fa_enabled', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '0',
    ]);
    register_setting( 'bap_settings_group', 'bap_block_xmlrpc', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '1'
    ]);

    register_setting( 'bap_settings_group', 'bap_block_user_enum', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '1'
    ]);
    register_setting( 'bap_settings_group', 'bap_2fa_enforce_admins', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '0',
    ]);
    register_setting( 'bap_settings_group', 'bap_2fa_remember_device', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '1',
    ]);
    register_setting( 'bap_settings_group', 'bap_hibp_enabled', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '1',
    ]);
    register_setting( 'bap_settings_group', 'bap_hibp_check_login', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '1',
    ]);
    register_setting( 'bap_settings_group', 'bap_hibp_login_action', [
        'sanitize_callback' => fn( $i ) => ( 'block' === $i ) ? 'block' : 'warn',
        'default'           => 'warn',
    ]);

    register_setting( 'bap_settings_group', 'bap_enable_htaccess', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '0'
    ]);

    register_setting( 'bap_settings_group', 'bap_country_mode', [
        'sanitize_callback' => fn( $i ) => ( 'block' === $i ) ? 'block' : 'allow',
        'default'           => 'allow'
    ]);

    register_setting( 'bap_settings_group', 'bap_asn_mode', [
        'sanitize_callback' => fn( $i ) => ( 'block' === $i ) ? 'block' : 'allow',
        'default'           => 'allow'
    ]);

    register_setting( 'bap_settings_group', 'bap_block_nonexistent_users', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '1'
    ]);

    register_setting( 'bap_settings_group', 'bap_block_tor', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '1'
    ]);

    register_setting( 'bap_settings_group', 'bap_block_firehol', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '1'
    ]);

    register_setting( 'bap_settings_group', 'bap_allowed_countries', [
        'sanitize_callback' => fn( $i ) => implode( ', ', bap_parse_list( $i, true ) ),
        'default'           => 'ES'
    ]);

    register_setting( 'bap_settings_group', 'bap_blocked_countries', [
        'sanitize_callback' => fn( $i ) => implode( ', ', bap_parse_list( $i, true ) ),
        'default'           => ''
    ]);

    register_setting( 'bap_settings_group', 'bap_allowed_asns', [
        'sanitize_callback' => 'bap_sanitize_asn_list',
        'default'           => ''
    ]);

    register_setting( 'bap_settings_group', 'bap_blocked_asns', [
        'sanitize_callback' => 'bap_sanitize_asn_list',
        'default'           => ''
    ]);

    register_setting( 'bap_settings_group', 'bap_auto_ban_attempts', [
        'sanitize_callback' => fn( $i ) => max( 0, (int) $i ),
        'default'           => 5
    ]);

    register_setting( 'bap_settings_group', 'bap_abuseipdb_min_risk', [
        'sanitize_callback' => fn( $i ) => max( 0, min( 100, (int) $i ) ),
        'default'           => 90
    ]);

    register_setting( 'bap_settings_group', 'bap_whitelist_ips', [
        'sanitize_callback' => fn( $i ) => implode( "\n", array_filter( bap_parse_list( $i ), 'bap_is_valid_ip_or_cidr' ) ),
        'default'           => ''
    ]);

    register_setting( 'bap_settings_group', 'bap_blacklist_ips', [
        'sanitize_callback' => fn( $i ) => implode( "\n", array_filter( bap_parse_list( $i ), 'bap_is_valid_ip_or_cidr' ) ),
        'default'           => ''
    ]);

    register_setting( 'bap_settings_group', 'bap_redirect_url', [
        'sanitize_callback' => fn( $i ) => '' === trim( (string) $i ) ? '' : esc_url_raw( trim( (string) $i ) ),
        'default'           => ''
    ]);

    register_setting( 'bap_settings_group', 'bap_ip_source', [
        'sanitize_callback' => fn( $i ) => in_array( $i, [ 'remote_addr', 'cloudflare', 'x_forwarded_for', 'x_real_ip' ], true ) ? $i : 'remote_addr',
        'default'           => 'remote_addr'
    ]);

    register_setting( 'bap_settings_group', 'bap_secret_key', [
        'sanitize_callback' => function( $input ) {
            $raw = trim( (string) $input );
            if ( '' === $raw ) {
                return '';
            }
            if ( preg_match( '/[\x{0590}-\x{08FF}\x{FB1D}-\x{FEFC}]/u', $raw ) ) {
                add_settings_error( 'bap_secret_key', 'bap_key_rtl', 'Llave secreta no guardada: solo se permiten alfabetos de izquierda a derecha (LTR).' );
                return get_option( 'bap_secret_key', '' );
            }
            $clean = preg_replace( '/[^\p{L}\p{N}\-_.~!*:@]/u', '', $raw );
            if ( $clean !== $raw ) {
                add_settings_error( 'bap_secret_key', 'bap_key_chars', 'Llave secreta no guardada: contiene símbolos no permitidos para URL.' );
                return get_option( 'bap_secret_key', '' );
            }
            $groups  = (int) (bool) preg_match( '/\p{Lu}/u', $clean )
                     + (int) (bool) preg_match( '/\p{Ll}/u', $clean )
                     + (int) (bool) preg_match( '/[0-9]/', $clean )
                     + (int) (bool) preg_match( '/[\-_.~!*:@]/', $clean );
            $len     = mb_strlen( $clean, 'UTF-8' );
            $min_len = ( 4 === $groups ) ? 15 : ( ( 3 === $groups ) ? 20 : ( ( 2 === $groups ) ? 25 : 30 ) );

            if ( $len < $min_len ) {
                add_settings_error( 'bap_secret_key', 'bap_key_weak', sprintf( 'Llave secreta no guardada: con %d tipo(s) de caracteres necesita al menos %d caracteres (actual: %d).', $groups, $min_len, $len ) );
                return get_option( 'bap_secret_key', '' );
            }
            return $clean;
        },
        'default' => ''
    ]);

    register_setting( 'bap_settings_group', 'bap_notify_freq', [
        'sanitize_callback' => fn( $i ) => in_array( $i, [ 'none', 'instant', 'hourly', '6hours', '12hours', 'daily', 'weekly', '2weeks', 'monthly' ], true ) ? $i : 'none',
        'default'           => 'none'
    ]);

    register_setting( 'bap_settings_group', 'bap_notify_emails', [
        'sanitize_callback' => function( $i ) {
            $emails = [];
            foreach ( preg_split( '/[\r\n,;]+/', (string) $i ) as $m ) {
                $clean = sanitize_email( trim( $m ) );
                if ( is_email( $clean ) ) {
                    $emails[] = $clean;
                }
            }
            return implode( ', ', array_unique( $emails ) );
        },
        'default' => ''
    ]);
});

add_action( 'update_option_bap_enable_htaccess', 'bap_sync_htaccess_rules' );
add_action( 'update_option_bap_protected_paths', 'bap_sync_htaccess_rules' );
add_action( 'update_option_bap_secret_key', 'bap_sync_htaccess_rules' );
add_action( 'update_option_bap_block_xmlrpc', 'bap_sync_htaccess_rules' );

function bap_render_bloqueos_page() {
    $test_notice = '';
    if ( isset( $_POST['bap_send_test_email'] ) && check_admin_referer( 'bap_test_email_action' ) ) {
        $recipients  = function_exists( 'bap_get_notify_recipients' ) ? bap_get_notify_recipients() : [ get_option( 'admin_email' ) ];
        $site        = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $subject     = '[' . $site . '] Email de prueba - Bloqueo Admin';
        $body        = "Este es un correo de prueba enviado desde el plugin Bloqueo Admin por País e IP.\n\nFecha/Hora: " . current_time( 'mysql' ) . "\nDestinatarios activos: " . implode( ', ', $recipients ) . "\n";
        $test_notice = wp_mail( $recipients, $subject, $body )
            ? '<div class="notice notice-success is-dismissible"><p><strong>¡Email de prueba enviado correctamente a:</strong> <code>' . esc_html( implode( ', ', $recipients ) ) . '</code>!</p></div>'
            : '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> WordPress (<code>wp_mail</code>) no pudo enviar el correo.</p></div>';
    }

    $ip     = bap_get_user_ip();
    $src    = '';
    $ctry   = bap_is_server_ip( $ip ) ? 'SERVIDOR LOCAL' : bap_get_country_by_ip( $ip, $src );
    $bl_raw = get_option( 'bap_blacklist_ips', '' );
    $bl_ips = bap_parse_list( $bl_raw, false );
    $freq   = (string) get_option( 'bap_notify_freq', 'none' );

    global $wpdb;
    $logs_table = function_exists( 'bap_get_logs_table_name' ) ? bap_get_logs_table_name() : $wpdb->prefix . 'bap_blocked_logs';
    $ip_stats   = [];
    if ( ! empty( $bl_ips ) ) {
        $placeholders = implode( ',', array_fill( 0, count( $bl_ips ), '%s' ) );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ip_address, COUNT(*) AS cnt, MAX(id) AS max_id FROM {$logs_table} WHERE ip_address IN ({$placeholders}) GROUP BY ip_address",
                $bl_ips
            ),
            ARRAY_A
        );
        foreach ( $rows as $r ) {
            $last_reason = (string) $wpdb->get_var(
                $wpdb->prepare( "SELECT reason FROM {$logs_table} WHERE id = %d", (int) $r['max_id'] )
            );
            $ip_stats[ $r['ip_address'] ] = [
                'count'  => (int) $r['cnt'],
                'reason' => $last_reason ?: 'Bloqueada',
            ];
        }
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

    wp_enqueue_script( 'bap-admin-bloqueos', plugin_dir_url( __FILE__ ) . 'admin-bloqueos.js', [], '12.2', true );
    wp_enqueue_script( 'bap-countries-data', plugin_dir_url( __FILE__ ) . 'countries-data.js', [], '1.0', true );
    wp_enqueue_script( 'bap-countries-picker', plugin_dir_url( __FILE__ ) . 'countries-picker.js', [ 'bap-countries-data' ], '13.2', true );
    wp_localize_script( 'bap-admin-bloqueos', 'bapAdminData', [ 'ipMeta' => $ip_meta ] );

    require plugin_dir_path( __FILE__ ) . 'admin-bloqueos-view.php';
}
