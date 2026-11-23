<?php
/**
 * Plugin Name: Bloqueo Admin por País e IP
 * Plugin URI: https://github.com/ReinosDeLeyenda/bloqueo-admin-pais
 * Description: Bloquea wp-admin, wp-login.php y el frontend por país, ASN, AbuseIPDB, Tor, FireHOL e IP usando bases de datos locales.
 * Version: 1.0.5
 * Author: ReinosDeLeyenda
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bap_dir   = plugin_dir_path( __FILE__ );
$bap_files = [
    'mmdb-reader.php',
    'geo-db-manager.php',
    'abuseipdb-manager.php',
    'threat-lists-manager.php',
    'notificaciones-mail.php',
    'admin-db.php',
    'admin-bloqueos.php',
    'web-bloqueos.php',
    'admin-estadisticas.php',
    'github-updater.php',
    'hibp-checker.php',
    'two-factor-auth.php',
];

foreach ( $bap_files as $bap_file ) {
    if ( file_exists( $bap_dir . $bap_file ) ) {
        require_once $bap_dir . $bap_file;
    }
}

function bap_get_user_ip() {
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        return trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) );
    }
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
        return trim( reset( $ips ) );
    }
    if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
        return trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) );
    }
    return isset( $_SERVER['REMOTE_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
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

function bap_log_blocked_attempt( $ip, $country, $reason ) {
    if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return;
    }
    $logs = get_option( 'bap_blocked_logs', [] );
    if ( ! is_array( $logs ) ) {
        $logs = [];
    }

    if ( isset( $logs[ $ip ] ) ) {
        $logs[ $ip ]['count']    = (int) $logs[ $ip ]['count'] + 1;
        $logs[ $ip ]['last_hit'] = current_time( 'mysql' );
        $logs[ $ip ]['country']  = $country;
        $logs[ $ip ]['reason']   = $reason;
    } else {
        $logs[ $ip ] = [
            'ip'       => $ip,
            'country'  => $country,
            'reason'   => $reason,
            'count'    => 1,
            'last_hit' => current_time( 'mysql' ),
        ];
    }

    $max_attempts = (int) get_option( 'bap_auto_ban_attempts', 5 );
    if ( $max_attempts > 0 && (int) $logs[ $ip ]['count'] >= $max_attempts ) {
        $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
        if ( ! in_array( $ip, $blacklist, true ) ) {
            $blacklist[] = $ip;
            update_option( 'bap_blacklist_ips', implode( "\n", $blacklist ) );
        }
        $logs[ $ip ]['reason'] = 'Auto-bloqueada (' . $logs[ $ip ]['count'] . ' intentos)';
    }

    if ( count( $logs ) > 500 ) {
        uasort( $logs, function( $a, $b ) {
            return strcmp( $b['last_hit'], $a['last_hit'] );
        });
        $logs = array_slice( $logs, 0, 500, true );
    }

    update_option( 'bap_blocked_logs', $logs, false );

    if ( function_exists( 'bap_queue_or_send_notification' ) ) {
        bap_queue_or_send_notification( $ip, $country, $reason );
    }
}

add_action( 'wp_login_failed', function( $username ) {
    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || in_array( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ), true ) ) {
        return;
    }
    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    bap_log_blocked_attempt( $ip, $country, 'Login fallido (' . sanitize_user( $username ) . ')' );
});

add_filter( 'authenticate', function( $user, $username, $password ) {
    if ( '' === trim( (string) $username ) || '1' !== (string) get_option( 'bap_block_nonexistent_users', '1' ) ) {
        return $user;
    }

    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || in_array( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ), true ) ) {
        return $user;
    }

    $clean_user = sanitize_text_field( wp_unslash( $username ) );
    $exists     = username_exists( $clean_user ) || ( is_email( $clean_user ) && email_exists( $clean_user ) );

    if ( ! $exists ) {
        $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
        $label   = mb_substr( sanitize_user( $clean_user, true ), 0, 40 );
        bap_deny_access( $ip, $country, 'Usuario inexistente: ' . ( '' !== $label ? $label : 'desconocido' ) );
    }

    return $user;
}, 30, 3 );

function bap_deny_access( $ip, $country, $reason ) {
    bap_log_blocked_attempt( $ip, $country, $reason );

    $url = trim( (string) get_option( 'bap_redirect_url', '' ) );
    if ( '' !== $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
        wp_redirect( esc_url_raw( $url ), 302 );
        exit;
    }
    wp_die(
        '<h1>Acceso denegado</h1><p>El acceso al panel de administración está restringido (' . esc_html( $reason ) . ').</p>',
        'Acceso Restringido',
        [ 'response' => 403 ]
    );
}

function bap_get_default_protected_paths() {
    return implode( "\n", [
        '/wp-admin/*',
        '/wp-login.php',
        '/wp-register.php',
        '/xmlrpc.php',
        '/wp-json/wp/v2/users*',
        '*.log',
        '*.zip',
        '*.rar',
        '*.tar',
        '*.tar.gz',
        '*.tgz',
        '*.7z',
        '*.gz',
        '*.bak',
        '*.sql',
        '*.env',
        '*.git*',
    ] );
}

function bap_is_protected_request() {
    $raw_paths = get_option( 'bap_protected_paths', null );
    if ( null === $raw_paths ) {
        $raw_paths = bap_get_default_protected_paths();
    }

    $patterns = bap_parse_list( $raw_paths, false );
    if ( empty( $patterns ) ) {
        return false;
    }

    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
    $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
    if ( '' === $path ) {
        $path = $uri;
    }

    if ( false !== strpos( $path, '/wp-admin/admin-ajax.php' ) || false !== strpos( $path, '/wp-admin/admin-post.php' ) ) {
        return false;
    }

    foreach ( $patterns as $pattern ) {
        $pat = strtolower( trim( $pattern ) );
        if ( '' === $pat ) {
            continue;
        }
        $regex = '#^' . str_replace( '\*', '.*', preg_quote( $pat, '#' ) ) . '$#i';
        if ( preg_match( $regex, $path ) || preg_match( $regex, $uri ) ) {
            return true;
        }
        if ( '/*' === substr( $pat, -2 ) && rtrim( $path, '/' ) === substr( $pat, 0, -2 ) ) {
            return true;
        }
    }

    return false;
}

function bap_sync_htaccess_rules() {
    if ( ! function_exists( 'insert_with_markers' ) ) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }

    $htaccess_file = ABSPATH . '.htaccess';
    if ( ! file_exists( $htaccess_file ) || ! is_writable( $htaccess_file ) ) {
        return false;
    }

    if ( '1' !== (string) get_option( 'bap_enable_htaccess', '0' ) ) {
        return insert_with_markers( $htaccess_file, 'Bloqueo Admin Pais', [] );
    }

    $raw_paths = get_option( 'bap_protected_paths', null );
    if ( null === $raw_paths ) {
        $raw_paths = bap_get_default_protected_paths();
    }

    $exts = [];
    foreach ( bap_parse_list( $raw_paths, false ) as $pat ) {
        if ( preg_match( '/^\*\.([a-zA-Z0-9._-]+)$/', $pat, $m ) ) {
            $clean_ext = str_replace( '*', '', $m );
            if ( '' !== $clean_ext ) {
                $exts[] = preg_quote( $clean_ext, '"' );
            }
        }
    }

    $rules = [];
    if ( ! empty( $exts ) ) {
        $secret = trim( (string) get_option( 'bap_secret_key', '' ) );
        $cname  = '' !== $secret ? 'bap_bypass_' . md5( $secret ) : '';

        $rules[] = '<IfModule mod_rewrite.c>';
        $rules[] = 'RewriteEngine On';
        if ( '' !== $cname ) {
            $rules[] = 'RewriteCond %{HTTP_COOKIE} !' . $cname . '= [NC]';
        }
        $rules[] = 'RewriteCond %{REQUEST_URI} \.(' . implode( '|', array_unique( $exts ) ) . ')$ [NC]';
        $rules[] = 'RewriteRule .* - [F,L]';
        $rules[] = '</IfModule>';
    }

    return insert_with_markers( $htaccess_file, 'Bloqueo Admin Pais', $rules );
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

    $recipients = [];
    foreach ( bap_parse_list( get_option( 'bap_notify_emails', '' ), false ) as $email ) {
        $clean = sanitize_email( $email );
        if ( is_email( $clean ) ) {
            $recipients[] = $clean;
        }
    }
    if ( empty( $recipients ) ) {
        $admin_email = sanitize_email( (string) get_option( 'admin_email', '' ) );
        if ( is_email( $admin_email ) ) {
            $recipients[] = $admin_email;
        }
    }

    if ( ! empty( $recipients ) ) {
        $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
        $user      = wp_get_current_user();
        $user_info = ( $user && $user->exists() ) ? $user->user_login . ' (' . $user->user_email . ')' : 'Desconocido';
        $ip        = bap_get_user_ip();

        $subject = sprintf( '[%s] Aviso: Plugin Bloqueo Admin desactivado', $site_name );
        $body    = "Hola,\n\n"
                 . "El plugin \"Bloqueo Admin por País e IP\" ha sido DESACTIVADO en " . home_url() . ".\n"
                 . "Mientras permanezca desactivado, las reglas de bloqueo de acceso no estarán protegiendo el sitio.\n\n"
                 . "- Fecha y hora: " . current_time( 'Y-m-d H:i:s' ) . "\n"
                 . "- Usuario: {$user_info}\n"
                 . "- IP: {$ip}\n";

        wp_mail( array_unique( $recipients ), $subject, $body );
    }
});

function bap_is_verified_seo_bot() {
    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
    if ( '' === $ua ) {
        return false;
    }

    $bot_domains = [
        'googlebot'   => [ '.googlebot.com', '.google.com' ],
        'bingbot'     => [ '.search.msn.com' ],
        'applebot'    => [ '.applebot.apple.com' ],
        'duckduckbot' => [ '.duckduckgo.com' ],
    ];

    $matched_domains = null;
    foreach ( $bot_domains as $sig => $domains ) {
        if ( false !== strpos( $ua, $sig ) ) {
            $matched_domains = $domains;
            break;
        }
    }
    if ( null === $matched_domains ) {
        return false;
    }

    $ip = bap_get_user_ip();
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return false;
    }

    $cache_key = 'bap_rdns_' . md5( $ip );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return ( '1' === $cached );
    }

    $host = @gethostbyaddr( $ip );
    if ( ! $host || $host === $ip ) {
        set_transient( $cache_key, '0', 6 * HOUR_IN_SECONDS );
        return false;
    }

    $host_lower  = strtolower( rtrim( $host, '.' ) );
    $domain_pass = false;
    foreach ( $matched_domains as $suffix ) {
        if ( substr( $host_lower, -strlen( $suffix ) ) === $suffix ) {
            $domain_pass = true;
            break;
        }
    }

    if ( ! $domain_pass ) {
        set_transient( $cache_key, '0', 6 * HOUR_IN_SECONDS );
        return false;
    }

    // Confirmación DNS directa (Forward-confirmed reverse DNS)
    $resolved = @gethostbynamel( $host_lower );
    $verified = ( is_array( $resolved ) && in_array( $ip, $resolved, true ) );

    set_transient( $cache_key, $verified ? '1' : '0', DAY_IN_SECONDS );
    return $verified;
}

function bap_check_frontend_rules() {
    if ( '1' !== (string) get_option( 'bap_web_enabled', '0' ) ) {
        return;
    }
    if ( is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() ) {
        return;
    }
    if ( '1' === (string) get_option( 'bap_web_allow_seo_bots', '1' ) && bap_is_verified_seo_bot() ) {
        return;
    }

    $scope = get_option( 'bap_web_scope', 'all' );
    if ( 'paths' === $scope ) {
        $patterns = bap_parse_list( get_option( 'bap_web_paths', '' ), false );
        if ( empty( $patterns ) ) {
            return;
        }
        $uri   = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
        $path  = (string) wp_parse_url( $uri, PHP_URL_PATH );
        $match = false;
        foreach ( $patterns as $pat ) {
            $regex = '#^' . str_replace( '\*', '.*', preg_quote( strtolower( trim( $pat ) ), '#' ) ) . '$#i';
            if ( preg_match( $regex, $path ) || preg_match( $regex, $uri ) ) {
                $match = true;
                break;
            }
        }
        if ( ! $match ) {
            return;
        }
    }

    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || in_array( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ), true ) ) {
        return;
    }

    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    $deny_fn = function( $reason ) use ( $ip, $country ) {
        bap_log_blocked_attempt( $ip, $country, 'Web: ' . $reason );
        $url = trim( (string) get_option( 'bap_web_redirect_url', '' ) );
        if ( '' !== $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
            wp_redirect( esc_url_raw( $url ), 302 );
            exit;
        }
        $msg = get_option( 'bap_web_block_message', 'Este contenido no está disponible en tu ubicación o red.' );
        wp_die( '<h1>Acceso restringido</h1><p>' . esc_html( $msg ) . '</p>', 'Acceso Restringido', [ 'response' => 403 ] );
    };

    if ( in_array( $ip, bap_parse_list( get_option( 'bap_blacklist_ips', '' ) ), true ) ) {
        $deny_fn( 'IP en lista negra' );
    }

    $min_risk = (int) get_option( 'bap_web_abuse_risk', 0 );
    if ( $min_risk > 0 && function_exists( 'bap_get_abuseipdb_score' ) ) {
        $score = bap_get_abuseipdb_score( $ip );
        if ( null !== $score && $score >= $min_risk ) {
            $deny_fn( 'Riesgo AbuseIPDB ' . $score . '%' );
        }
    }

    if ( '1' === (string) get_option( 'bap_web_block_tor', '0' ) && function_exists( 'bap_is_tor_exit_node' ) && bap_is_tor_exit_node( $ip ) ) {
        $deny_fn( 'Nodo Tor' );
    }
    if ( '1' === (string) get_option( 'bap_web_block_firehol', '0' ) && function_exists( 'bap_is_in_firehol' ) && false !== bap_is_in_firehol( $ip ) ) {
        $deny_fn( 'Red FireHOL' );
    }

    $amode = get_option( 'bap_web_asn_mode', 'block' );
    $alist = bap_parse_list( get_option( ( 'block' === $amode ) ? 'bap_web_blocked_asns' : 'bap_web_allowed_asns', '' ), true );
    $asn_ok = false;
    if ( ! empty( $alist ) && function_exists( 'bap_get_asn_by_ip' ) ) {
        $acode  = '';
        $alabel = bap_get_asn_by_ip( $ip, $acode );
        if ( 'block' === $amode && '' !== $acode && in_array( $acode, $alist, true ) ) {
            $deny_fn( 'ASN bloqueado (' . $alabel . ')' );
        }
        if ( 'allow' === $amode && '' !== $acode && in_array( $acode, $alist, true ) ) {
            $asn_ok = true;
        }
    }
    if ( $asn_ok ) {
        return;
    }

    $cmode = get_option( 'bap_web_country_mode', 'allow' );
    $clist = bap_parse_list( get_option( ( 'block' === $cmode ) ? 'bap_web_blocked_countries' : 'bap_web_allowed_countries', '' ), true );
    if ( ! empty( $clist ) ) {
        if ( 'block' === $cmode && in_array( $country, $clist, true ) ) {
            $deny_fn( 'País bloqueado (' . $country . ')' );
        }
        if ( 'allow' === $cmode && ! in_array( $country, $clist, true ) ) {
            $deny_fn( 'País no permitido (' . $country . ')' );
        }
    } elseif ( 'allow' === $amode && ! empty( $alist ) ) {
        $deny_fn( 'ASN no autorizado' );
    }
}

add_action( 'init', function() {
    if ( ! bap_is_protected_request() ) {
        bap_check_frontend_rules();
        return;
    }

    $secret = trim( (string) get_option( 'bap_secret_key', '' ) );
    if ( '' !== $secret ) {
        $cname = 'bap_bypass_' . md5( $secret );
        $token = hash( 'sha256', $secret . AUTH_SALT );
        $uri   = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $redir = isset( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : '';
        if ( isset( $_GET[ $secret ] ) || false !== strpos( $uri, $secret ) || false !== strpos( $redir, $secret ) ) {
            if ( ! headers_sent() ) {
                setcookie( $cname, $token, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            }
            return;
        }
        if ( isset( $_COOKIE[ $cname ] ) && hash_equals( $token, $_COOKIE[ $cname ] ) ) {
            return;
        }
    }

    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || in_array( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ), true ) ) {
        return;
    }

    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';

    // 1. Lista Negra de IPs
    if ( in_array( $ip, bap_parse_list( get_option( 'bap_blacklist_ips', '' ) ), true ) ) {
        bap_deny_access( $ip, $country, 'IP en lista negra' );
    }

    // 2. Riesgo AbuseIPDB >= umbral configurado (90% por defecto)
    $min_risk = (int) get_option( 'bap_abuseipdb_min_risk', 90 );
    if ( $min_risk > 0 && function_exists( 'bap_get_abuseipdb_score' ) ) {
        $risk_score = bap_get_abuseipdb_score( $ip );
        if ( null !== $risk_score && $risk_score >= $min_risk ) {
            $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
            if ( ! in_array( $ip, $blacklist, true ) ) {
                $blacklist[] = $ip;
                update_option( 'bap_blacklist_ips', implode( "\n", $blacklist ) );
            }
            bap_deny_access( $ip, $country, 'Riesgo AbuseIPDB: ' . $risk_score . '%' );
        }
    }

    // 2.6 Bloqueo de Nodos Tor y Redes FireHOL Level 1
    if ( '1' === (string) get_option( 'bap_block_tor', '1' ) && function_exists( 'bap_is_tor_exit_node' ) && bap_is_tor_exit_node( $ip ) ) {
        bap_deny_access( $ip, $country, 'Nodo de salida Tor' );
    }
    if ( '1' === (string) get_option( 'bap_block_firehol', '1' ) && function_exists( 'bap_is_in_firehol' ) ) {
        $cidr_hit = bap_is_in_firehol( $ip );
        if ( false !== $cidr_hit ) {
            bap_deny_access( $ip, $country, 'Red maliciosa FireHOL (' . $cidr_hit . ')' );
        }
    }

    // 3. ASNs Admin (Modo exclusivo: Permitidos o Bloqueados; vacío = desactivado)
    $asn_mode = (string) get_option( 'bap_asn_mode', '' );
    if ( '' === $asn_mode ) {
        $asn_mode = ( '' !== trim( (string) get_option( 'bap_blocked_asns', '' ) ) && '' === trim( (string) get_option( 'bap_allowed_asns', '' ) ) ) ? 'block' : 'allow';
    }
    $asn_list          = bap_parse_list( get_option( ( 'block' === $asn_mode ) ? 'bap_blocked_asns' : 'bap_allowed_asns', '' ), true );
    $asn_allowed_match = false;

    if ( ! empty( $asn_list ) && function_exists( 'bap_get_asn_by_ip' ) ) {
        $asn_code  = '';
        $asn_label = bap_get_asn_by_ip( $ip, $asn_code );
        if ( 'block' === $asn_mode ) {
            if ( '' !== $asn_code && in_array( $asn_code, $asn_list, true ) ) {
                bap_deny_access( $ip, $country, 'ASN bloqueado: ' . $asn_label );
            }
        } else {
            if ( '' !== $asn_code && in_array( $asn_code, $asn_list, true ) ) {
                $asn_allowed_match = true;
            }
        }
    }

    if ( $asn_allowed_match ) {
        return;
    }

    // 4. Países Admin (Modo exclusivo: Permitidos o Bloqueados; vacío = desactivado)
    $country_mode = (string) get_option( 'bap_country_mode', '' );
    if ( '' === $country_mode ) {
        $country_mode = ( '' !== trim( (string) get_option( 'bap_blocked_countries', '' ) ) && '' === trim( (string) get_option( 'bap_allowed_countries', 'ES' ) ) ) ? 'block' : 'allow';
    }
    $country_list = bap_parse_list( get_option( ( 'block' === $country_mode ) ? 'bap_blocked_countries' : 'bap_allowed_countries', ( 'allow' === $country_mode ? 'ES' : '' ) ), true );

    if ( ! empty( $country_list ) ) {
        if ( 'block' === $country_mode && in_array( $country, $country_list, true ) ) {
            bap_deny_access( $ip, $country, 'País bloqueado: ' . $country );
        }
        if ( 'allow' === $country_mode && ! in_array( $country, $country_list, true ) ) {
            bap_deny_access( $ip, $country, 'País no permitido: ' . $country );
        }
    } elseif ( 'allow' === $asn_mode && ! empty( $asn_list ) ) {
        bap_deny_access( $ip, $country, 'ASN no autorizado' );
    }
}, 1 );
