<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_get_sender_intel_summary( $ip = '' ) {
    if ( '' === $ip ) {
        $ip = function_exists( 'bap_get_client_ip' ) ? (string) bap_get_client_ip() : (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
    }
    if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return [ 'ip' => 'Desconocida', 'country' => 'XX', 'asn' => 'Desconocido', 'abuse' => 'N/D' ];
    }
    $cc = function_exists( 'bap_get_country_by_ip' ) ? strtoupper( (string) bap_get_country_by_ip( $ip ) ) : 'XX';
    $asn_txt = 'Desconocido';
    if ( function_exists( 'bap_get_asn_by_ip' ) ) {
        $raw_asn = bap_get_asn_by_ip( $ip );
        if ( is_array( $raw_asn ) ) {
            $num = preg_replace( '/[^0-9]/', '', (string) ( $raw_asn['asn'] ?? '' ) );
            $org = trim( (string) ( $raw_asn['org'] ?? $raw_asn['name'] ?? '' ) );
            $asn_txt = ( '' !== $num ? 'AS' . $num : '' ) . ( '' !== $org ? ' (' . $org . ')' : '' );
        } elseif ( '' !== (string) $raw_asn ) {
            $asn_txt = (string) $raw_asn;
        }
    }
    $abuse_txt = 'Sin consultar / Local';
    if ( function_exists( 'bap_check_abuseipdb_score' ) ) {
        $sc = bap_check_abuseipdb_score( $ip );
        if ( is_numeric( $sc ) ) {
            $abuse_txt = (int) $sc . '% riesgo';
        }
    } elseif ( function_exists( 'bap_is_ip_in_abuseipdb' ) ) {
        $abuse_txt = bap_is_ip_in_abuseipdb( $ip ) ? 'Listada (Alto riesgo)' : 'Limpia (0% local)';
    }
    return [ 'ip' => $ip, 'country' => $cc ?: 'XX', 'asn' => $asn_txt ?: 'Desconocido', 'abuse' => $abuse_txt ];
}

function bap_detect_form_spam_reason( array $post_data, $host ) {
    $self_url_hits = 0;
    $id_keys       = [ 'nombre', 'name', 'personaje', 'character', 'cuenta', 'account', 'usuario', 'user', 'author', 'asunto', 'subject' ];

    foreach ( $post_data as $key => $val ) {
        if ( is_array( $val ) ) { $val = implode( ' ', array_map( 'strval', $val ) ); }
        $str = trim( (string) $val );
        if ( '' === $str || 0 === strpos( (string) $key, '_' ) ) { continue; }
        $k_low = strtolower( (string) $key );

        if ( preg_match( '/:https?:\/\/[^\s:]+:/i', $str ) ) {
            return 'Firma de bot detectada (:http...: en campo ' . $key . ')';
        }
        if ( '' !== $host && false !== stripos( $str, $host ) && false !== stripos( $str, 'http' ) ) {
            $self_url_hits++;
        }
        foreach ( $id_keys as $ik ) {
            if ( false !== strpos( $k_low, $ik ) && preg_match( '/https?:\/\/|www\./i', $str ) ) {
                return 'URL inyectada en campo de identidad/asunto (' . $key . ')';
            }
        }
    }
    if ( $self_url_hits >= 2 ) {
        return 'URL propia repetida en ' . $self_url_hits . ' campos del formulario (Bot XRumer/GSA)';
    }
    return '';
}

// 1. Bloqueo preventivo de bots en envios POST publicos (formularios y comentarios)
add_action( 'init', function() {
    if ( '1' !== (string) get_option( 'bap_fe_form_antispam_enabled', '1' ) ) { return; }
    if ( is_admin() || wp_doing_cron() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { return; }
    if ( is_user_logged_in() || empty( $_POST ) ) { return; }
    $uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
    if ( false !== strpos( $uri, 'wp-login.php' ) || false !== strpos( $uri, 'xmlrpc.php' ) ) { return; }

    $host   = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    $reason = bap_detect_form_spam_reason( wp_unslash( $_POST ), $host );
    if ( '' !== $reason ) {
        $ip = function_exists( 'bap_get_client_ip' ) ? (string) bap_get_client_ip() : (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
        if ( function_exists( 'bap_log_blocked_attempt' ) ) {
            bap_log_blocked_attempt( $ip, 'Anti-Spam Formulario: ' . $reason, 'frontend' );
        }
        status_header( 403 );
        nocache_headers();
        wp_die( '<h1>Envio bloqueado</h1><p>Tu mensaje ha sido rechazado por el filtro anti-spam automatico.</p>', 'Bloqueo Anti-Spam', [ 'response' => 403 ] );
    }
}, 1 );

// 2. Adjuntar IP, Pais, ASN y AbuseIPDB en los correos enviados desde formularios publicos
add_filter( 'wp_mail', function( $args ) {
    if ( is_admin() || wp_doing_cron() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        return $args;
    }
    $msg = (string) ( $args['message'] ?? '' );
    if ( '' === $msg || false !== strpos( $msg, 'AbuseIPDB:' ) ) {
        return $args;
    }
    $intel   = bap_get_sender_intel_summary();
    $headers = is_array( $args['headers'] ?? null ) ? implode( "\n", $args['headers'] ) : (string) ( $args['headers'] ?? '' );
    $is_html = ( false !== stripos( $headers, 'text/html' ) || false !== strpos( $msg, '<html' ) || false !== strpos( $msg, '<br' ) || false !== strpos( $msg, '<p' ) );

    if ( $is_html ) {
        $footer = '<hr style="border:0;border-top:1px solid #ddd;margin:18px 0 10px;">'
                . '<p style="font-size:12px;color:#555;font-family:sans-serif;line-height:1.5;">'
                . '<strong>Auditoría de Red del Remitente (Bloqueo Admin):</strong><br>'
                . 'IP: <code>' . esc_html( $intel['ip'] ) . '</code> &middot; '
                . 'País: <strong>' . esc_html( $intel['country'] ) . '</strong> &middot; '
                . 'ASN: <code>' . esc_html( $intel['asn'] ) . '</code> &middot; '
                . 'AbuseIPDB: <strong>' . esc_html( $intel['abuse'] ) . '</strong>'
                . '</p>';
        $args['message'] = $msg . "\n" . $footer;
    } else {
        $footer = "\n\n----------------------------------------\n"
                . "Auditoria de Red del Remitente:\n"
                . "IP: " . $intel['ip'] . "\n"
                . "Pais: " . $intel['country'] . "\n"
                . "ASN: " . $intel['asn'] . "\n"
                . "AbuseIPDB: " . $intel['abuse'];
        $args['message'] = $msg . $footer;
    }
    return $args;
}, 20 );