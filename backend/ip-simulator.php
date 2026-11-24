<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_simulate_ip_rules( $ip ) {
    $ip = trim( (string) $ip );
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return new WP_Error( 'invalid_ip', 'Introduce una dirección IPv4 o IPv6 válida.' );
    }

    $is_server  = bap_is_server_ip( $ip );
    $country    = $is_server ? 'SERVIDOR LOCAL' : ( function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO' );
    $asn_code   = '';
    $asn_label  = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip, $asn_code ) : 'N/D';
    $abuse_risk = function_exists( 'bap_get_abuseipdb_score' ) ? bap_get_abuseipdb_score( $ip ) : null;
    $is_tor     = function_exists( 'bap_is_tor_exit_node' ) ? bap_is_tor_exit_node( $ip ) : false;
    $firehol    = function_exists( 'bap_is_in_firehol' ) ? bap_is_in_firehol( $ip ) : false;

    $whitelist = bap_parse_list( get_option( 'bap_whitelist_ips', '' ) );
    $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ) );
    $in_white  = $is_server || bap_ip_in_list( $ip, $whitelist );
    $in_black  = bap_ip_in_list( $ip, $blacklist );

    // 1. Evaluar veredicto para Panel Admin
    $admin_status = 'allow';
    $admin_reason = 'Cumple todas las reglas de acceso al Panel Admin';

    if ( $in_white ) {
        $admin_reason = $is_server ? 'IP local / del servidor (exenta)' : 'Coincide con Lista Blanca';
    } elseif ( $in_black ) {
        $admin_status = 'block';
        $admin_reason = 'IP o rango CIDR en Lista Negra';
    } else {
        $min_risk = (int) get_option( 'bap_abuseipdb_min_risk', 90 );
        $asn_mode = (string) get_option( 'bap_asn_mode', 'allow' );
        $asn_list = bap_parse_list( get_option( ( 'block' === $asn_mode ) ? 'bap_blocked_asns' : 'bap_allowed_asns', '' ), true );
        $c_mode   = (string) get_option( 'bap_country_mode', 'allow' );
        $c_list   = bap_parse_list( get_option( ( 'block' === $c_mode ) ? 'bap_blocked_countries' : 'bap_allowed_countries', ( 'allow' === $c_mode ? 'ES' : '' ) ), true );

        if ( $min_risk > 0 && null !== $abuse_risk && $abuse_risk >= $min_risk ) {
            $admin_status = 'block';
            $admin_reason = "Riesgo AbuseIPDB ({$abuse_risk}% >= {$min_risk}%)";
        } elseif ( '1' === (string) get_option( 'bap_block_tor', '1' ) && $is_tor ) {
            $admin_status = 'block';
            $admin_reason = 'Nodo de salida Tor bloqueado';
        } elseif ( '1' === (string) get_option( 'bap_block_firehol', '1' ) && false !== $firehol ) {
            $admin_status = 'block';
            $admin_reason = "Subred maliciosa FireHOL ({$firehol})";
        } elseif ( ! empty( $asn_list ) && 'block' === $asn_mode && '' !== $asn_code && in_array( $asn_code, $asn_list, true ) ) {
            $admin_status = 'block';
            $admin_reason = "ASN bloqueado ({$asn_label})";
        } elseif ( ! empty( $asn_list ) && 'allow' === $asn_mode && '' !== $asn_code && in_array( $asn_code, $asn_list, true ) ) {
            $admin_reason = "ASN autorizado directamente ({$asn_label})";
        } elseif ( ! empty( $c_list ) ) {
            if ( 'block' === $c_mode && in_array( $country, $c_list, true ) ) {
                $admin_status = 'block';
                $admin_reason = "País bloqueado ({$country})";
            } elseif ( 'allow' === $c_mode && ! in_array( $country, $c_list, true ) ) {
                $admin_status = 'block';
                $admin_reason = "País no incluido en permitidos ({$country})";
            }
        } elseif ( 'allow' === $asn_mode && ! empty( $asn_list ) ) {
            $admin_status = 'block';
            $admin_reason = 'ASN fuera de la lista de permitidos';
        }
    }

    // 2. Evaluar veredicto para Web Pública
    $web_enabled = ( '1' === (string) get_option( 'bap_web_enabled', '0' ) );
    $web_status  = 'allow';
    $web_reason  = $web_enabled ? 'Cumple las reglas de la Web Pública' : 'Bloqueo Web desactivado';

    if ( $web_enabled ) {
        if ( $in_white ) {
            $web_reason = $is_server ? 'IP local / del servidor (exenta)' : 'Coincide con Lista Blanca';
        } elseif ( function_exists( 'bap_get_country_redirect_url' ) && '' !== ( $c_redir = bap_get_country_redirect_url( $country ) ) ) {
            $web_status = 'redirect';
            $web_reason = 'Redirigida por país (' . $country . ') a ' . $c_redir;
        } elseif ( $in_black ) {
            $web_status = 'block';
            $web_reason = 'IP o rango CIDR en Lista Negra';
        } else {
            $w_risk  = (int) get_option( 'bap_web_abuse_risk', 0 );
            $w_amode = (string) get_option( 'bap_web_asn_mode', 'block' );
            $w_alist = bap_parse_list( get_option( ( 'block' === $w_amode ) ? 'bap_web_blocked_asns' : 'bap_web_allowed_asns', '' ), true );
            $w_cmode = (string) get_option( 'bap_web_country_mode', 'allow' );
            $w_clist = bap_parse_list( get_option( ( 'block' === $w_cmode ) ? 'bap_web_blocked_countries' : 'bap_web_allowed_countries', '' ), true );

            if ( $w_risk > 0 && null !== $abuse_risk && $abuse_risk >= $w_risk ) {
                $web_status = 'block';
                $web_reason = "Riesgo AbuseIPDB ({$abuse_risk}% >= {$w_risk}%)";
            } elseif ( '1' === (string) get_option( 'bap_web_block_tor', '0' ) && $is_tor ) {
                $web_status = 'block';
                $web_reason = 'Nodo de salida Tor bloqueado';
            } elseif ( '1' === (string) get_option( 'bap_web_block_firehol', '0' ) && false !== $firehol ) {
                $web_status = 'block';
                $web_reason = "Subred maliciosa FireHOL ({$firehol})";
            } elseif ( ! empty( $w_alist ) && 'block' === $w_amode && '' !== $asn_code && in_array( $asn_code, $w_alist, true ) ) {
                $web_status = 'block';
                $web_reason = "ASN bloqueado ({$asn_label})";
            } elseif ( ! empty( $w_alist ) && 'allow' === $w_amode && '' !== $asn_code && in_array( $asn_code, $w_alist, true ) ) {
                $web_reason = "ASN autorizado directamente ({$asn_label})";
            } elseif ( ! empty( $w_clist ) ) {
                if ( 'block' === $w_cmode && in_array( $country, $w_clist, true ) ) {
                    $web_status = 'block';
                    $web_reason = "País bloqueado ({$country})";
                } elseif ( 'allow' === $w_cmode && ! in_array( $country, $w_clist, true ) ) {
                    $web_status = 'block';
                    $web_reason = "País no incluido en permitidos ({$country})";
                }
            } elseif ( 'allow' === $w_amode && ! empty( $w_alist ) ) {
                $web_status = 'block';
                $web_reason = 'ASN fuera de la lista de permitidos';
            }
        }
    }

    return [
        'ip'           => $ip,
        'country'      => $country,
        'asn'          => $asn_label,
        'abuse_risk'   => $abuse_risk,
        'is_tor'       => $is_tor,
        'firehol'      => $firehol,
        'admin_status' => $admin_status,
        'admin_reason' => $admin_reason,
        'web_status'   => $web_status,
        'web_reason'   => $web_reason,
    ];
}

function bap_render_ip_simulator_box() {
    $sim_ip  = isset( $_POST['bap_sim_ip'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['bap_sim_ip'] ) ) ) : '';
    $sim_res = null;
    if ( '' !== $sim_ip && check_admin_referer( 'bap_sim_ip_action', 'bap_sim_nonce' ) ) {
        $sim_res = bap_simulate_ip_rules( $sim_ip );
    }
    require plugin_dir_path( __FILE__ ) . 'ip-simulator-view.php';
}
