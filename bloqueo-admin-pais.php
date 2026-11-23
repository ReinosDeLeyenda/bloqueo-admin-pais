<?php
/**
 * Plugin Name: Bloqueo Admin por País e IP
 * Plugin URI: https://github.com
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
    'cidr-helper.php',
    'logs-db-manager.php',
    'seo-bots-helper.php',
    'htaccess-manager.php',
    'frontend-firewall.php',
    'ip-simulator.php',
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

register_activation_hook( __FILE__, 'bap_maybe_create_logs_table' );

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

add_action( 'wp_login_failed', function( $username ) {
    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
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
    if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
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

register_deactivation_hook( __FILE__, function() {
    if ( ! function_exists( 'insert_with_markers' ) ) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }
    $htaccess_file = ABSPATH . '.htaccess';
    if ( file_exists( $htaccess_file ) && is_writable( $htaccess_file ) ) {
        insert_with_markers( $htaccess_file, 'Bloqueo Admin Pais', [] );
    }
    wp_clear_scheduled_hook( 'bap_auto_update_dbs_event' );

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
    if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return;
    }

    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';

    if ( bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_blacklist_ips', '' ) ) ) ) {
        bap_deny_access( $ip, $country, 'IP/Rango en lista negra' );
    }

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

    if ( '1' === (string) get_option( 'bap_block_tor', '1' ) && function_exists( 'bap_is_tor_exit_node' ) && bap_is_tor_exit_node( $ip ) ) {
        bap_deny_access( $ip, $country, 'Nodo de salida Tor' );
    }
    if ( '1' === (string) get_option( 'bap_block_firehol', '1' ) && function_exists( 'bap_is_in_firehol' ) ) {
        $cidr_hit = bap_is_in_firehol( $ip );
        if ( false !== $cidr_hit ) {
            bap_deny_access( $ip, $country, 'Red maliciosa FireHOL (' . $cidr_hit . ')' );
        }
    }

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
