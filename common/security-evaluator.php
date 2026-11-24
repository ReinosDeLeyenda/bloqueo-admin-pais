<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Evalúa una IP frente a todas las reglas activas ('admin' o 'web').
 *
 * @param string $ip             Dirección IPv4 o IPv6 a evaluar.
 * @param string $country        Código ISO de país (2 letras) o estado local.
 * @param string $context        'admin' o 'web'.
 * @param bool   $auto_ban_abuse Si es true, añade la IP a bap_blacklist_ips al superar el umbral AbuseIPDB.
 * @return array                 [ 'status' => 'allow'|'block', 'reason' => string ]
 */
function bap_evaluate_ip_access( $ip, $country, $context = 'admin', $auto_ban_abuse = false ) {
    $is_web = ( 'web' === $context );

    // 1. Lista Negra global
    if ( bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_blacklist_ips', '' ) ) ) ) {
        return [
            'status' => 'block',
            'reason' => 'IP/Rango en lista negra',
        ];
    }

    // 2. Riesgo AbuseIPDB
    $min_risk = (int) get_option( $is_web ? 'bap_web_abuse_risk' : 'bap_abuseipdb_min_risk', $is_web ? 0 : 90 );
    if ( $min_risk > 0 && function_exists( 'bap_get_abuseipdb_score' ) ) {
        $score = bap_get_abuseipdb_score( $ip );
        if ( null !== $score && $score >= $min_risk ) {
            if ( $auto_ban_abuse ) {
                $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
                if ( ! in_array( $ip, $blacklist, true ) ) {
                    $blacklist[] = $ip;
                    update_option( 'bap_blacklist_ips', implode( "\n", $blacklist ) );
                }
            }
            return [
                'status' => 'block',
                'reason' => 'Riesgo AbuseIPDB: ' . $score . '%',
            ];
        }
    }

    // 3. Nodos de salida Tor
    $block_tor = ( '1' === (string) get_option( $is_web ? 'bap_web_block_tor' : 'bap_block_tor', $is_web ? '0' : '1' ) );
    if ( $block_tor && function_exists( 'bap_is_tor_exit_node' ) && bap_is_tor_exit_node( $ip ) ) {
        return [
            'status' => 'block',
            'reason' => 'Nodo de salida Tor',
        ];
    }

    // 4. Redes maliciosas FireHOL Level 1
    $block_fh = ( '1' === (string) get_option( $is_web ? 'bap_web_block_firehol' : 'bap_block_firehol', $is_web ? '0' : '1' ) );
    if ( $block_fh && function_exists( 'bap_is_in_firehol' ) ) {
        $cidr_hit = bap_is_in_firehol( $ip );
        if ( false !== $cidr_hit ) {
            return [
                'status' => 'block',
                'reason' => 'Red maliciosa FireHOL (' . $cidr_hit . ')',
            ];
        }
    }

    // 5. Evaluar ASN de forma independiente
    $asn_opt_mode = $is_web ? 'bap_web_asn_mode' : 'bap_asn_mode';
    $asn_mode     = (string) get_option( $asn_opt_mode, $is_web ? 'block' : 'allow' );
    $asn_key      = ( 'block' === $asn_mode )
        ? ( $is_web ? 'bap_web_blocked_asns' : 'bap_blocked_asns' )
        : ( $is_web ? 'bap_web_allowed_asns' : 'bap_allowed_asns' );
    $asn_list     = bap_parse_list( get_option( $asn_key, '' ), true );

    if ( ! empty( $asn_list ) && function_exists( 'bap_get_asn_by_ip' ) ) {
        $asn_code  = '';
        $asn_label = bap_get_asn_by_ip( $ip, $asn_code );
        if ( 'block' === $asn_mode && '' !== $asn_code && in_array( $asn_code, $asn_list, true ) ) {
            return [
                'status' => 'block',
                'reason' => 'ASN bloqueado: ' . $asn_label,
            ];
        }
        if ( 'allow' === $asn_mode && ( '' === $asn_code || ! in_array( $asn_code, $asn_list, true ) ) ) {
            return [
                'status' => 'block',
                'reason' => 'ASN no autorizado: ' . ( $asn_label ?: 'N/D' ),
            ];
        }
    }

    // 6. Evaluar País de forma independiente
    $ctry_opt_mode = $is_web ? 'bap_web_country_mode' : 'bap_country_mode';
    $ctry_mode     = (string) get_option( $ctry_opt_mode, 'allow' );
    $ctry_default  = ( ! $is_web && 'allow' === $ctry_mode ) ? 'ES' : '';
    $ctry_key      = ( 'block' === $ctry_mode )
        ? ( $is_web ? 'bap_web_blocked_countries' : 'bap_blocked_countries' )
        : ( $is_web ? 'bap_web_allowed_countries' : 'bap_allowed_countries' );
    $ctry_list     = bap_parse_list( get_option( $ctry_key, $ctry_default ), true );

    if ( ! empty( $ctry_list ) ) {
        if ( 'block' === $ctry_mode && in_array( $country, $ctry_list, true ) ) {
            return [
                'status' => 'block',
                'reason' => 'País bloqueado: ' . $country,
            ];
        }
        if ( 'allow' === $ctry_mode && ! in_array( $country, $ctry_list, true ) ) {
            return [
                'status' => 'block',
                'reason' => 'País no permitido: ' . $country,
            ];
        }
    }

    return [
        'status' => 'allow',
        'reason' => $is_web ? 'Cumple las reglas de la Web Pública' : 'Cumple todas las reglas de acceso al Panel Admin',
    ];
}
