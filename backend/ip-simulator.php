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
    $asn_label  = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : 'N/D';
    $abuse_risk = function_exists( 'bap_get_abuseipdb_score' ) ? bap_get_abuseipdb_score( $ip ) : null;
    $is_tor     = function_exists( 'bap_is_tor_exit_node' ) ? bap_is_tor_exit_node( $ip ) : false;
    $firehol    = function_exists( 'bap_is_in_firehol' ) ? bap_is_in_firehol( $ip ) : false;
    $in_white   = $is_server || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) );

    // 1. Veredicto Panel Admin
    if ( $in_white ) {
        $admin_status = 'allow';
        $admin_reason = $is_server ? 'IP local / del servidor (exenta)' : 'Coincide con Lista Blanca';
    } else {
        $admin_eval   = bap_evaluate_ip_access( $ip, $country, 'admin', false );
        $admin_status = $admin_eval['status'];
        $admin_reason = $admin_eval['reason'];
    }

    // 2. Veredicto Web Pública
    $web_enabled = ( '1' === (string) get_option( 'bap_web_enabled', '0' ) );
    if ( ! $web_enabled ) {
        $web_status = 'allow';
        $web_reason = 'Bloqueo Web desactivado';
    } elseif ( $in_white ) {
        $web_status = 'allow';
        $web_reason = $is_server ? 'IP local / del servidor (exenta)' : 'Coincide con Lista Blanca';
    } elseif ( function_exists( 'bap_get_country_redirect_url' ) && '' !== ( $c_redir = bap_get_country_redirect_url( $country ) ) ) {
        $web_status = 'redirect';
        $web_reason = 'Redirigida por país (' . $country . ') a ' . $c_redir;
    } else {
        $web_eval   = bap_evaluate_ip_access( $ip, $country, 'web', false );
        $web_status = $web_eval['status'];
        $web_reason = $web_eval['reason'];
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
