<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_get_current_admin_network_profile() {
    $ip = function_exists( 'bap_get_client_ip' ) ? (string) bap_get_client_ip() : (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
    $cc = '';
    $asn = '';
    if ( '' !== $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        if ( function_exists( 'bap_get_country_by_ip' ) ) {
            $cc = strtoupper( trim( (string) bap_get_country_by_ip( $ip ) ) );
        }
        if ( function_exists( 'bap_get_asn_by_ip' ) ) {
            $raw_asn = bap_get_asn_by_ip( $ip );
            $asn     = is_array( $raw_asn ) ? (string) ( $raw_asn['asn'] ?? '' ) : (string) $raw_asn;
            $asn     = preg_replace( '/[^0-9]/', '', $asn );
        }
    }
    return [ 'ip' => $ip, 'country' => $cc, 'asn' => $asn ];
}

function bap_ip_matches_list_lines( $ip, $raw_list ) {
    if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) { return false; }
    $lines = preg_split( '/\r\n|\r|\n/', (string) $raw_list );
    foreach ( (array) $lines as $ln ) {
        $clean = trim( preg_replace( '/\s*#.*$/', '', (string) $ln ) );
        if ( '' === $clean ) { continue; }
        if ( $clean === $ip ) { return true; }
        if ( false !== strpos( $clean, '/' ) && function_exists( 'bap_ip_in_cidr' ) && bap_ip_in_cidr( $ip, $clean ) ) {
            return true;
        }
    }
    return false;
}

function bap_evaluate_self_lockout_risk( array $override = [] ) {
    $me = bap_get_current_admin_network_profile();
    $ip = $me['ip'];
    if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
        return [];
    }
    $wl = array_key_exists( 'bap_whitelist_ips', $override ) ? (string) $override['bap_whitelist_ips'] : (string) get_option( 'bap_whitelist_ips', '' );
    if ( bap_ip_matches_list_lines( $ip, $wl ) ) {
        return [];
    }
    $risks = [];
    $bl = array_key_exists( 'bap_blacklist_ips', $override ) ? (string) $override['bap_blacklist_ips'] : (string) get_option( 'bap_blacklist_ips', '' );
    if ( bap_ip_matches_list_lines( $ip, $bl ) ) {
        $risks[] = 'Tu IP actual (' . $ip . ') coincide con una regla de la Lista Negra de IPs/CIDR.';
    }
    $be_asns_raw = array_key_exists( 'bap_blocked_asns', $override ) ? (string) $override['bap_blocked_asns'] : (string) get_option( 'bap_blocked_asns', '' );
    $fe_asns_raw = array_key_exists( 'bap_fe_blocked_asns', $override ) ? (string) $override['bap_fe_blocked_asns'] : (string) get_option( 'bap_fe_blocked_asns', '' );
    $all_asns    = array_filter( array_map( fn( $x ) => preg_replace( '/[^0-9]/', '', $x ), preg_split( '/[\s,;]+/', $be_asns_raw . ' ' . $fe_asns_raw ) ) );
    if ( '' !== $me['asn'] && in_array( $me['asn'], $all_asns, true ) ) {
        $risks[] = 'Tu ASN actual (AS' . $me['asn'] . ', IP ' . $ip . ') esta incluido en la lista de ASNs bloqueados.';
    }
    $cc = $me['country'];
    if ( '' !== $cc && 'XX' !== $cc ) {
        $be_mode = array_key_exists( 'bap_country_mode', $override ) ? (string) $override['bap_country_mode'] : (string) get_option( 'bap_country_mode', 'allow' );
        $be_ccs  = array_key_exists( 'bap_countries', $override ) ? (array) $override['bap_countries'] : (array) get_option( 'bap_countries', [] );
        $be_ccs  = array_map( 'strtoupper', array_filter( $be_ccs ) );
        if ( ! empty( $be_ccs ) ) {
            if ( 'allow' === $be_mode && ! in_array( $cc, $be_ccs, true ) ) {
                $risks[] = 'El modo Backend solo permite ciertos paises y tu pais actual (' . $cc . ', IP ' . $ip . ') NO esta en la lista.';
            } elseif ( 'block' === $be_mode && in_array( $cc, $be_ccs, true ) ) {
                $risks[] = 'Tu pais actual (' . $cc . ', IP ' . $ip . ') esta marcado como bloqueado en el Backend.';
            }
        }
        $fe_mode = array_key_exists( 'bap_fe_country_mode', $override ) ? (string) $override['bap_fe_country_mode'] : (string) get_option( 'bap_fe_country_mode', 'off' );
        $fe_ccs  = array_key_exists( 'bap_fe_countries', $override ) ? (array) $override['bap_fe_countries'] : (array) get_option( 'bap_fe_countries', [] );
        $fe_ccs  = array_map( 'strtoupper', array_filter( $fe_ccs ) );
        if ( ! empty( $fe_ccs ) && 'off' !== $fe_mode ) {
            if ( 'allow' === $fe_mode && ! in_array( $cc, $fe_ccs, true ) ) {
                $risks[] = 'El modo Frontend solo permite ciertos paises y tu pais actual (' . $cc . ', IP ' . $ip . ') quedaria bloqueado.';
            } elseif ( 'block' === $fe_mode && in_array( $cc, $fe_ccs, true ) ) {
                $risks[] = 'Tu pais actual (' . $cc . ', IP ' . $ip . ') esta marcado como bloqueado en el Frontend.';
            }
        }
    }
    return $risks;
}

// 1. Accion rapida en 1 clic para blindar la IP actual en Lista Blanca
add_action( 'admin_init', function() {
    if ( ! isset( $_POST['bap_quick_whitelist_my_ip'] ) || ! current_user_can( 'manage_options' ) ) { return; }
    check_admin_referer( 'bap_self_lockout_wl_action', 'bap_self_lockout_wl_nonce' );
    $me = bap_get_current_admin_network_profile();
    $ip = $me['ip'];
    if ( '' !== $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        $wl = trim( (string) get_option( 'bap_whitelist_ips', '' ) );
        if ( ! bap_ip_matches_list_lines( $ip, $wl ) ) {
            $note = '# Admin auto-protegido (' . gmdate( 'Y-m-d' ) . ')';
            $new_wl = ( '' !== $wl ? $wl . "\n" : '' ) . $ip . ' ' . $note;
            update_option( 'bap_whitelist_ips', $new_wl );
        }
    }
    wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=bap-bloqueos' ) );
    exit;
} );

// 2. Intercepcion preventiva antes de guardar reglas en options.php que bloqueen al propio admin
add_action( 'admin_init', function() {
    global $pagenow;
    if ( 'options.php' !== (string) $pagenow || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { return; }
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $opt_page = sanitize_key( (string) ( $_POST['option_page'] ?? '' ) );
    if ( 0 !== strpos( $opt_page, 'bap_' ) ) { return; }

    $override = [];
    foreach ( [ 'bap_whitelist_ips', 'bap_blacklist_ips', 'bap_blocked_asns', 'bap_fe_blocked_asns', 'bap_country_mode', 'bap_countries', 'bap_fe_country_mode', 'bap_fe_countries' ] as $k ) {
        if ( array_key_exists( $k, $_POST ) ) {
            $override[ $k ] = wp_unslash( $_POST[ $k ] );
        }
    }
    if ( empty( $override ) ) { return; }

    $risks = bap_evaluate_self_lockout_risk( $override );
    if ( ! empty( $risks ) ) {
        $me   = bap_get_current_admin_network_profile();
        $html = '<div style="max-width:680px;margin:20px auto;padding:24px;border-left:6px solid #d63638;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.12);">';
        $html .= '<h1 style="color:#d63638;margin-top:0;font-size:22px;">&#9888; GUARDADO DETENIDO: Riesgo de Autobloqueo</h1>';
        $html .= '<p style="font-size:15px;">La configuracion que intentas guardar <strong>bloquearia tu propia conexion actual</strong> y te expulsaria del panel de WordPress:</p>';
        $html .= '<ul style="font-size:14px;color:#b91c1c;margin:12px 0 18px 20px;list-style:disc;">';
        foreach ( $risks as $r ) { $html .= '<li><strong>' . esc_html( $r ) . '</strong></li>'; }
        $html .= '</ul>';
        $html .= '<p style="font-size:14px;"><strong>Como solucionarlo:</strong> Primero anade tu IP actual (<code>' . esc_html( $me['ip'] ) . '</code>) a la <strong>Lista Blanca</strong> antes de aplicar este bloqueo.</p>';
        $html .= '<p style="margin-top:20px;"><a href="javascript:history.back()" class="button button-primary button-large">&larr; Volver y revisar configuracion</a></p>';
        $html .= '</div>';
        wp_die( $html, 'Alerta de Autobloqueo - Bloqueo Admin', [ 'response' => 403 ] );
    }
}, 1 );

// 3. Banner gigante permanente en el panel de administracion si la regla actual afecta al admin
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $risks = bap_evaluate_self_lockout_risk();
    if ( empty( $risks ) ) { return; }
    $me = bap_get_current_admin_network_profile();
    echo '<div class="notice notice-error" style="border-left-width:8px;border-color:#d63638;background:#fef2f2;padding:16px 20px;margin:16px 0;">';
    echo '<h2 style="margin:0 0 8px 0;color:#991b1b;font-size:18px;">&#9888; ALERTA CRITICA: Una regla de bloqueo afecta a tu propia IP / Pais / ASN</h2>';
    echo '<ul style="margin:0 0 12px 20px;list-style:disc;color:#7f1d1d;font-size:14px;">';
    foreach ( $risks as $r ) { echo '<li><strong>' . esc_html( $r ) . '</strong></li>'; }
    echo '</ul>';
    echo '<form method="post" style="margin:0;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">';
    wp_nonce_field( 'bap_self_lockout_wl_action', 'bap_self_lockout_wl_nonce' );
    echo '<button type="submit" name="bap_quick_whitelist_my_ip" class="button button-primary button-large" style="background:#d63638;border-color:#b91c1c;">Anadir mi IP actual (' . esc_html( $me['ip'] ) . ') a la Lista Blanca ahora</button>';
    echo '<span style="color:#7f1d1d;font-size:13px;">(Aunque los administradores tienen bypass en backend, podrias quedarte bloqueado al cerrar sesion o entrar en wp-login.php)</span>';
    echo '</form></div>';
} );