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

function bap_sanitize_protected_paths( $input ) {
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
}

function bap_sanitize_secret_key( $input ) {
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
}

function bap_sanitize_notify_emails( $input ) {
    $emails = [];
    foreach ( preg_split( '/[\r\n,;]+/', (string) $input ) as $m ) {
        $clean = sanitize_email( trim( $m ) );
        if ( is_email( $clean ) ) {
            $emails[] = $clean;
        }
    }
    return implode( ', ', array_unique( $emails ) );
}

add_action( 'admin_init', function() {
    $bool_cb = fn( $i ) => ( '1' === (string) $i ) ? '1' : '0';

    register_setting( 'bap_settings_group', 'bap_protected_paths', [
        'sanitize_callback' => 'bap_sanitize_protected_paths',
        'default'           => function_exists( 'bap_get_default_protected_paths' ) ? bap_get_default_protected_paths() : "/wp-admin/*\n/wp-login.php",
    ] );

  register_setting( 'bap_settings_group', 'bap_login_notify_channel', [
      'sanitize_callback' => fn( $i ) => in_array( $i, [ 'none', 'email', 'telegram', 'both' ], true ) ? $i : 'none',
      'default'           => 'none',
  ] );

    $booleans = [
        'bap_2fa_enabled'             => '0',
        'bap_2fa_enforce_admins'      => '0',
        'bap_2fa_remember_device'     => '1',
        'bap_hibp_enabled'            => '1',
        'bap_hibp_check_login'        => '1',
        'bap_enable_htaccess'         => '0',
        'bap_block_nonexistent_users' => '1',
        'bap_block_xmlrpc'            => '1',
        'bap_block_user_enum'         => '1',
        'bap_block_tor'               => '1',
        'bap_block_firehol'           => '1',
    ];

    foreach ( $booleans as $opt => $def ) {
        register_setting( 'bap_settings_group', $opt, [
            'sanitize_callback' => $bool_cb,
            'default'           => $def,
        ] );
    }

    register_setting( 'bap_settings_group', 'bap_hibp_login_action', [ 'sanitize_callback' => fn( $i ) => ( 'block' === $i ) ? 'block' : 'warn', 'default' => 'warn' ] );
    register_setting( 'bap_settings_group', 'bap_country_mode', [ 'sanitize_callback' => fn( $i ) => ( 'block' === $i ) ? 'block' : 'allow', 'default' => 'allow' ] );
    register_setting( 'bap_settings_group', 'bap_asn_mode', [ 'sanitize_callback' => fn( $i ) => ( 'block' === $i ) ? 'block' : 'allow', 'default' => 'allow' ] );
    register_setting( 'bap_settings_group', 'bap_allowed_countries', [ 'sanitize_callback' => fn( $i ) => implode( ', ', bap_parse_list( $i, true ) ), 'default' => 'ES' ] );
    register_setting( 'bap_settings_group', 'bap_blocked_countries', [ 'sanitize_callback' => fn( $i ) => implode( ', ', bap_parse_list( $i, true ) ), 'default' => '' ] );
    register_setting( 'bap_settings_group', 'bap_allowed_asns', [ 'sanitize_callback' => 'bap_sanitize_asn_list', 'default' => '' ] );
    register_setting( 'bap_settings_group', 'bap_blocked_asns', [ 'sanitize_callback' => 'bap_sanitize_asn_list', 'default' => '' ] );
    register_setting( 'bap_settings_group', 'bap_auto_ban_attempts', [ 'sanitize_callback' => fn( $i ) => max( 0, (int) $i ), 'default' => 5 ] );
    register_setting( 'bap_settings_group', 'bap_auto_ban_duration', [ 'sanitize_callback' => fn( $i ) => max( 0, (int) $i ), 'default' => 24 ] );
    register_setting( 'bap_settings_group', 'bap_abuseipdb_min_risk', [ 'sanitize_callback' => fn( $i ) => max( 0, min( 100, (int) $i ) ), 'default' => 90 ] );
    register_setting( 'bap_settings_group', 'bap_whitelist_ips', [ 'sanitize_callback' => fn( $i ) => implode( "\n", array_filter( bap_parse_list( $i ), 'bap_is_valid_ip_or_cidr' ) ), 'default' => '' ] );
    register_setting( 'bap_settings_group', 'bap_blacklist_ips', [ 'sanitize_callback' => fn( $i ) => implode( "\n", array_filter( bap_parse_list( $i ), 'bap_is_valid_ip_or_cidr' ) ), 'default' => '' ] );
    register_setting( 'bap_settings_group', 'bap_redirect_url', [ 'sanitize_callback' => fn( $i ) => '' === trim( (string) $i ) ? '' : esc_url_raw( trim( (string) $i ) ), 'default' => '' ] );
    register_setting( 'bap_settings_group', 'bap_ip_source', [ 'sanitize_callback' => fn( $i ) => in_array( $i, [ 'remote_addr', 'cloudflare', 'x_forwarded_for', 'x_real_ip' ], true ) ? $i : 'remote_addr', 'default' => 'remote_addr' ] );
    register_setting( 'bap_settings_group', 'bap_secret_key', [ 'sanitize_callback' => 'bap_sanitize_secret_key', 'default' => '' ] );

    // Notificaciones unificadas (Canal + Frecuencia + Email + Telegram)
    register_setting( 'bap_settings_group', 'bap_notify_channel', [
        'sanitize_callback' => fn( $i ) => in_array( $i, [ 'none', 'email', 'telegram', 'both' ], true ) ? $i : 'none',
        'default'           => 'none',
    ] );
    register_setting( 'bap_settings_group', 'bap_notify_freq', [
        'sanitize_callback' => fn( $i ) => in_array( $i, [ 'instant', 'hourly', '3hours', '6hours', '12hours', 'daily', 'weekly', '2weeks', 'monthly' ], true ) ? $i : 'daily',
        'default'           => 'daily',
    ] );
    register_setting( 'bap_settings_group', 'bap_notify_emails', [
        'sanitize_callback' => 'bap_sanitize_notify_emails',
        'default'           => '',
    ] );
    register_setting( 'bap_settings_group', 'bap_email_template', [
        'sanitize_callback' => fn( $i ) => ( in_array( $i, [ 'full', 'top10', 'top5', 'text' ], true ) || 0 === strpos( (string) $i, 'custom_' ) ) ? (string) $i : 'full',
        'default'           => 'full',
    ] );
    register_setting( 'bap_settings_group', 'bap_tg_bot_token', [
        'sanitize_callback' => fn( $i ) => trim( sanitize_text_field( (string) $i ) ),
        'default'           => '',
    ] );
    register_setting( 'bap_settings_group', 'bap_tg_chat_ids', [
        'sanitize_callback' => fn( $i ) => implode( ', ', array_unique( array_filter( array_map( 'trim', preg_split( '/[\r\n,;]+/', (string) $i ) ) ) ) ),
        'default'           => '',
    ] );
    register_setting( 'bap_settings_group', 'bap_login_notify_routes', [
        'sanitize_callback' => function( $ignored ) {
            $users   = (array) ( $_POST['bap_login_route_user'] ?? [] );
            $targets = (array) ( $_POST['bap_login_route_target'] ?? [] );
            $types   = (array) ( $_POST['bap_login_route_type'] ?? [] );
            $clean   = [];

            foreach ( $targets as $idx => $raw_target ) {
                $uid  = max( 0, (int) ( $users[ $idx ] ?? 0 ) );
                $type = ( 'email' === ( $types[ $idx ] ?? 'telegram' ) ) ? 'email' : 'telegram';
                $val  = trim( sanitize_text_field( wp_unslash( (string) $raw_target ) ) );

                if ( '' === $val ) {
                    continue;
                }
                if ( 'email' === $type ) {
                    $mail = sanitize_email( $val );
                    if ( ! is_email( $mail ) ) {
                        continue;
                    }
                    $val = $mail;
                } else {
                    $val = preg_replace( '/[^0-9\-]/', '', $val );
                    if ( '' === $val ) {
                        continue;
                    }
                }

                $clean[] = [
                    'user_id' => $uid,
                    'target'  => $val,
                    'type'    => $type,
                ];
            }

            return ! empty( $clean ) ? $clean : [ [ 'user_id' => 0, 'target' => '', 'type' => 'telegram' ] ];
        },
        'default' => [ [ 'user_id' => 0, 'target' => '', 'type' => 'telegram' ] ],
    ] );
} );

add_action( 'update_option_bap_enable_htaccess', 'bap_sync_htaccess_rules' );
add_action( 'update_option_bap_protected_paths', 'bap_sync_htaccess_rules' );
add_action( 'update_option_bap_secret_key', 'bap_sync_htaccess_rules' );
add_action( 'update_option_bap_block_xmlrpc', 'bap_sync_htaccess_rules' );
