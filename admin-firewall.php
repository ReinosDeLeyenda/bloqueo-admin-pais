<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
