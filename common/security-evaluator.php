<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_default_web_rule( $enabled = '0' ) {
    return [
        'enabled'       => $enabled,
        'country_mode'  => 'allow',
        'countries'     => '',
        'op_1'          => 'AND', // Conector País -> ASN
        'asn_mode'      => 'block',
        'asns'          => '',
        'op_2'          => 'AND', // Conector ASN -> AbuseIPDB
        'abuse_risk'    => 0,
        'op_3'          => 'AND', // Conector AbuseIPDB -> Tor
        'block_tor'     => '0',
        'op_4'          => 'AND', // Conector Tor -> FireHOL
        'block_firehol' => '0',
    ];
}

function bap_get_web_rules() {
    $rules = get_option( 'bap_web_rules', null );
    if ( is_array( $rules ) && isset( $rules['__all__'] ) ) {
        return $rules;
    }

    $old_enabled = (string) get_option( 'bap_web_enabled', '0' );
    $old_scope   = (string) get_option( 'bap_web_scope', 'all' );
    $old_cmode   = (string) get_option( 'bap_web_country_mode', 'allow' );
    $old_amode   = (string) get_option( 'bap_web_asn_mode', 'block' );

    $migrated = wp_parse_args( [
        'enabled'       => $old_enabled,
        'country_mode'  => $old_cmode,
        'countries'     => (string) get_option( 'block' === $old_cmode ? 'bap_web_blocked_countries' : 'bap_web_allowed_countries', '' ),
        'asn_mode'      => $old_amode,
        'asns'          => (string) get_option( 'block' === $old_amode ? 'bap_web_blocked_asns' : 'bap_web_allowed_asns', '' ),
        'abuse_risk'    => (int) get_option( 'bap_web_abuse_risk', 0 ),
        'block_tor'     => (string) get_option( 'bap_web_block_tor', '0' ),
        'block_firehol' => (string) get_option( 'bap_web_block_firehol', '0' ),
    ], bap_get_default_web_rule( '0' ) );

    $rules = [ '__all__' => bap_get_default_web_rule( '0' ) ];
    if ( 'paths' === $old_scope ) {
        foreach ( bap_parse_list( get_option( 'bap_web_paths', '' ), false ) as $p ) {
            $rules[ $p ] = $migrated;
        }
    } else {
        $rules['__all__'] = $migrated;
    }

    update_option( 'bap_web_rules', $rules );
    return $rules;
}

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
 * Evalúa secuencialmente de arriba abajo los filtros activos combinados con Y (AND) / O (OR).
 */
function bap_evaluate_rule_profile( $ip, $country, array $profile, $auto_ban_abuse = false, $ok_msg = 'Cumple las reglas' ) {
    // La Lista Negra global siempre bloquea de inmediato
    if ( bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_blacklist_ips', '' ) ) ) ) {
        return [ 'status' => 'block', 'reason' => 'IP/Rango en lista negra' ];
    }

    $checks = [];

    // 1. País
    $ctry_list = bap_parse_list( $profile['countries'] ?? '', true );
    if ( ! empty( $ctry_list ) ) {
        $ctry_mode = (string) ( $profile['country_mode'] ?? 'allow' );
        $in_ctry   = in_array( $country, $ctry_list, true );
        $pass      = ( 'allow' === $ctry_mode ) ? $in_ctry : ! $in_ctry;
        $checks[]  = [
            'pass'   => $pass,
            'op'     => null,
            'reason' => ( 'allow' === $ctry_mode ) ? "País no permitido ({$country})" : "País bloqueado ({$country})",
        ];
    }

    // 2. ASN
    $asn_list = bap_parse_list( $profile['asns'] ?? '', true );
    if ( ! empty( $asn_list ) && function_exists( 'bap_get_asn_by_ip' ) ) {
        $asn_mode  = (string) ( $profile['asn_mode'] ?? 'block' );
        $asn_code  = '';
        $asn_label = bap_get_asn_by_ip( $ip, $asn_code );
        $in_asn    = ( '' !== $asn_code && in_array( $asn_code, $asn_list, true ) );
        $pass      = ( 'allow' === $asn_mode ) ? $in_asn : ! $in_asn;
        $checks[]  = [
            'pass'   => $pass,
            'op'     => ( 'OR' === ( $profile['op_1'] ?? 'AND' ) ) ? 'OR' : 'AND',
            'reason' => ( 'allow' === $asn_mode ) ? 'ASN no autorizado (' . ( $asn_label ?: 'N/D' ) . ')' : "ASN bloqueado ({$asn_label})",
        ];
    }

    // 3. AbuseIPDB
    $min_risk = (int) ( $profile['abuse_risk'] ?? 0 );
    if ( $min_risk > 0 && function_exists( 'bap_get_abuseipdb_score' ) ) {
        $score = bap_get_abuseipdb_score( $ip );
        $fail  = ( null !== $score && $score >= $min_risk );
        if ( $fail && $auto_ban_abuse ) {
            $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
            if ( ! in_array( $ip, $blacklist, true ) ) {
                $blacklist[] = $ip;
                update_option( 'bap_blacklist_ips', implode( "\n", $blacklist ) );
            }
        }
        $checks[] = [
            'pass'   => ! $fail,
            'op'     => ( 'OR' === ( $profile['op_2'] ?? 'AND' ) ) ? 'OR' : 'AND',
            'reason' => "Riesgo AbuseIPDB ({$score}% >= {$min_risk}%)",
        ];
    }

    // 4. Tor
    if ( '1' === (string) ( $profile['block_tor'] ?? '0' ) && function_exists( 'bap_is_tor_exit_node' ) ) {
        $is_tor   = bap_is_tor_exit_node( $ip );
        $checks[] = [
            'pass'   => ! $is_tor,
            'op'     => ( 'OR' === ( $profile['op_3'] ?? 'AND' ) ) ? 'OR' : 'AND',
            'reason' => 'Nodo de salida Tor',
        ];
    }

    // 5. FireHOL
    if ( '1' === (string) ( $profile['block_firehol'] ?? '0' ) && function_exists( 'bap_is_in_firehol' ) ) {
        $cidr_hit = bap_is_in_firehol( $ip );
        $checks[] = [
            'pass'   => ( false === $cidr_hit ),
            'op'     => ( 'OR' === ( $profile['op_4'] ?? 'AND' ) ) ? 'OR' : 'AND',
            'reason' => "Red maliciosa FireHOL ({$cidr_hit})",
        ];
    }

    if ( empty( $checks ) ) {
        return [ 'status' => 'allow', 'reason' => $ok_msg ];
    }

    // Evaluación secuencial izquierda -> derecha (arriba -> abajo)
    $acc_pass    = $checks[0]['pass'];
    $last_reason = $checks[0]['pass'] ? '' : $checks[0]['reason'];

    for ( $i = 1, $total = count( $checks ); $i < $total; $i++ ) {
        $step = $checks[ $i ];
        if ( 'OR' === $step['op'] ) {
            $acc_pass = ( $acc_pass || $step['pass'] );
            if ( ! $acc_pass && ! $step['pass'] ) {
                $last_reason = $step['reason'];
            }
        } else {
            if ( $acc_pass && ! $step['pass'] ) {
                $last_reason = $step['reason'];
            }
            $acc_pass = ( $acc_pass && $step['pass'] );
        }
    }

    if ( ! $acc_pass ) {
        return [
            'status' => 'block',
            'reason' => '' !== $last_reason ? $last_reason : 'No cumple la combinación de filtros Y/O',
        ];
    }

    return [ 'status' => 'allow', 'reason' => $ok_msg ];
}

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
        'op_1'          => 'AND',
        'asn_mode'      => $a_mode,
        'asns'          => (string) get_option( 'block' === $a_mode ? 'bap_blocked_asns' : 'bap_allowed_asns', '' ),
        'op_2'          => 'AND',
        'abuse_risk'    => (int) get_option( 'bap_abuseipdb_min_risk', 90 ),
        'op_3'          => 'AND',
        'block_tor'     => (string) get_option( 'bap_block_tor', '1' ),
        'op_4'          => 'AND',
        'block_firehol' => (string) get_option( 'bap_block_firehol', '1' ),
    ];

    return bap_evaluate_rule_profile( $ip, $country, $profile, $auto_ban_abuse, 'Cumple todas las reglas de acceso al Panel Admin' );
}
