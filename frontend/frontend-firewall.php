<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
    if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
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
        if ( function_exists( 'bap_render_custom_403_page' ) ) {
            bap_render_custom_403_page( 'Acceso restringido', $msg, $ip, $country, $reason );
        }
        wp_die( '<h1>Acceso restringido</h1><p>' . esc_html( $msg ) . '</p>', 'Acceso Restringido', [ 'response' => 403 ] );
    };

    if ( function_exists( 'bap_get_country_redirect_url' ) ) {
        $custom_redir = bap_get_country_redirect_url( $country );
        if ( '' !== $custom_redir ) {
            wp_redirect( esc_url_raw( $custom_redir ), 302 );
            exit;
        }
    }

    if ( bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_blacklist_ips', '' ) ) ) ) {
        $deny_fn( 'IP/Rango en lista negra' );
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

    $amode  = get_option( 'bap_web_asn_mode', 'block' );
    $alist  = bap_parse_list( get_option( ( 'block' === $amode ) ? 'bap_web_blocked_asns' : 'bap_web_allowed_asns', '' ), true );
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
