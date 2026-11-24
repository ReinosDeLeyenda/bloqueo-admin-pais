<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_default_web_rule( $enabled = '0' ) {
    return [
        'enabled'       => $enabled,
        'country_mode'  => 'allow',
        'countries'     => '',
        'asn_mode'      => 'block',
        'asns'          => '',
        'abuse_risk'    => 0,
        'block_tor'     => '0',
        'block_firehol' => '0',
    ];
}

/**
 * Obtiene todas las reglas web ('__all__' y rutas específicas), migrando ajustes antiguos si existen.
 */
function bap_get_web_rules() {
    $rules = get_option( 'bap_web_rules', null );
    if ( is_array( $rules ) && isset( $rules['__all__'] ) ) {
        return $rules;
    }

    // Migración automática desde el formato antiguo
    $old_enabled = (string) get_option( 'bap_web_enabled', '0' );
    $old_scope   = (string) get_option( 'bap_web_scope', 'all' );
    $old_cmode   = (string) get_option( 'bap_web_country_mode', 'allow' );
    $old_amode   = (string) get_option( 'bap_web_asn_mode', 'block' );

    $migrated_rule = [
        'enabled'       => $old_enabled,
        'country_mode'  => $old_cmode,
        'countries'     => (string) get_option( 'block' === $old_cmode ? 'bap_web_blocked_countries' : 'bap_web_allowed_countries', '' ),
        'asn_mode'      => $old_amode,
        'asns'          => (string) get_option( 'block' === $old_amode ? 'bap_web_blocked_asns' : 'bap_web_allowed_asns', '' ),
        'abuse_risk'    => (int) get_option( 'bap_web_abuse_risk', 0 ),
        'block_tor'     => (string) get_option( 'bap_web_block_tor', '0' ),
        'block_firehol' => (string) get_option( 'bap_web_block_firehol', '0' ),
    ];

    $rules = [ '__all__' => bap_get_default_web_rule( '0' ) ];

    if ( 'paths' === $old_scope ) {
        $old_paths = bap_parse_list( get_option( 'bap_web_paths', '' ), false );
        foreach ( $old_paths as $p ) {
            $rules[ $p ] = $migrated_rule;
        }
    } else {
        $rules['__all__'] = $migrated_rule;
    }

    update_option( 'bap_web_rules', $rules );
    return $rules;
}

/**
 * Busca qué regla aplica a una URI/Path concreto.
 * Prioridad: 1º URLs específicas -> 2º Regla global '__all__'.
 */
function bap_match_web_rule_for_uri( $uri, $path = '' ) {
    $rules = bap_get_web_rules();
    $uri   = strtolower( (string) $uri );
    $path  = '' !== $path ? strtolower( (string) $path ) : (string) wp_parse_url( $uri, PHP_URL_PATH );

    foreach ( $rules as $pattern => $rule ) {
        if ( '__all__' === $pattern ) {
            continue;
        }
        $regex = '#^' . str_replace( '\*', '.*', preg_quote( strtolower( trim( $pattern ) ), '#' ) ) . '$#i';
        if ( preg_match( $regex, $path ) || preg_match( $regex, $uri ) ) {
            return [
                'scope' => $pattern,
                'rule'  => wp_parse_args( $rule, bap_get_default_web_rule( '0' ) ),
            ];
        }
    }

    return [
        'scope' => '__all__',
        'rule'  => wp_parse_args( $rules['__all__'] ?? [], bap_get_default_web_rule( '0' ) ),
    ];
}

/**
 * Evalúa una IP frente a un perfil de regla concreto.
 */
function bap_evaluate_rule_profile( $ip, $country, array $profile, $auto_ban_abuse = false, $ok_msg = 'Cumple las reglas' ) {
    // 1. Lista Negra global
    if ( bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_blacklist_ips', '' ) ) ) ) {
        return [ 'status' => 'block', 'reason' => 'IP/Rango en lista negra' ];
    }

    // 2. Riesgo AbuseIPDB
    $min_risk = (int) ( $profile['abuse_risk'] ?? 0 );
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
            return [ 'status' => 'block', 'reason' => 'Riesgo AbuseIPDB: ' . $score . '%' ];
        }
    }

    // 3. Nodos Tor
    if ( '1' === (string) ( $profile['block_tor'] ?? '0' ) && function_exists( 'bap_is_tor_exit_node' ) && bap_is_tor_exit_node( $ip ) ) {
        return [ 'status' => 'block', 'reason' => 'Nodo de salida Tor' ];
    }

    // 4. FireHOL Level 1
    if ( '1' === (string) ( $profile['block_firehol'] ?? '0' ) && function_exists( 'bap_is_in_firehol' ) ) {
        $cidr_hit = bap_is_in_firehol( $ip );
        if ( false !== $cidr_hit ) {
            return [ 'status' => 'block', 'reason' => 'Red maliciosa FireHOL (' . $cidr_hit . ')' ];
        }
    }

    // 5. ASN
    $asn_mode = (string) ( $profile['asn_mode'] ?? 'block' );
    $asn_list = bap_parse_list( $profile['asns'] ?? '', true );
    if ( ! empty( $asn_list ) && function_exists( 'bap_get_asn_by_ip' ) ) {
        $asn_code  = '';
        $asn_label = bap_get_asn_by_ip( $ip, $asn_code );
        if ( 'block' === $asn_mode && '' !== $asn_code && in_array( $asn_code, $asn_list, true ) ) {
            return [ 'status' => 'block', 'reason' => 'ASN bloqueado: ' . $asn_label ];
        }
        if ( 'allow' === $asn_mode && ( '' === $asn_code || ! in_array( $asn_code, $asn_list, true ) ) ) {
            return [ 'status' => 'block', 'reason' => 'ASN no autorizado: ' . ( $asn_label ?: 'N/D' ) ];
        }
    }

    // 6. País
    $ctry_mode = (string) ( $profile['country_mode'] ?? 'allow' );
    $ctry_list = bap_parse_list( $profile['countries'] ?? '', true );
    if ( ! empty( $ctry_list ) ) {
        if ( 'block' === $ctry_mode && in_array( $country, $ctry_list, true ) ) {
            return [ 'status' => 'block', 'reason' => 'País bloqueado: ' . $country ];
        }
        if ( 'allow' === $ctry_mode && ! in_array( $country, $ctry_list, true ) ) {
            return [ 'status' => 'block', 'reason' => 'País no permitido: ' . $country ];
        }
    }

    return [ 'status' => 'allow', 'reason' => $ok_msg ];
}

/**
 * Compatibilidad con el Firewall Admin y el Simulador.
 */
function bap_evaluate_ip_access( $ip, $country, $context = 'admin', $auto_ban_abuse = false, $uri = '' ) {
    if ( 'web' === $context ) {
        $matched = bap_match_web_rule_for_uri( $uri );
        $rule    = $matched['rule'];
        $label   = ( '__all__' === $matched['scope'] ) ? 'Global' : $matched['scope'];

        if ( '1' !== (string) ( $rule['enabled'] ?? '0' ) ) {
            return [
                'status' => 'allow',
                'reason' => 'Sin bloqueo activo para [' . $label . ']',
                'scope'  => $matched['scope'],
            ];
        }

        $res          = bap_evaluate_rule_profile( $ip, $country, $rule, false, 'Cumple reglas Web [' . $label . ']' );
        $res['scope'] = $matched['scope'];
        return $res;
    }

    $c_mode  = (string) get_option( 'bap_country_mode', 'allow' );
    $a_mode  = (string) get_option( 'bap_asn_mode', 'allow' );
    $profile = [
        'country_mode'  => $c_mode,
        'countries'     => (string) get_option( 'block' === $c_mode ? 'bap_blocked_countries' : 'bap_allowed_countries', 'allow' === $c_mode ? 'ES' : '' ),
        'asn_mode'      => $a_mode,
        'asns'          => (string) get_option( 'block' === $a_mode ? 'bap_blocked_asns' : 'bap_allowed_asns', '' ),
        'abuse_risk'    => (int) get_option( 'bap_abuseipdb_min_risk', 90 ),
        'block_tor'     => (string) get_option( 'bap_block_tor', '1' ),
        'block_firehol' => (string) get_option( 'bap_block_firehol', '1' ),
    ];

    return bap_evaluate_rule_profile( $ip, $country, $profile, $auto_ban_abuse, 'Cumple todas las reglas de acceso al Panel Admin' );
}
