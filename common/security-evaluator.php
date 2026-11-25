<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_build_single_condition_check( $key, $ip, $country, array $profile, $auto_ban_abuse ) {
    if ( 'tor' === $key ) {
        if ( '1' !== (string) ( $profile['block_tor'] ?? '0' ) || ! function_exists( 'bap_is_tor_exit_node' ) ) {
            return null;
        }
        return [
            'pass'   => ! bap_is_tor_exit_node( $ip ),
            'reason' => 'Nodo de salida Tor',
        ];
    }

    if ( 'firehol' === $key ) {
        if ( '1' !== (string) ( $profile['block_firehol'] ?? '0' ) || ! function_exists( 'bap_is_in_firehol' ) ) {
            return null;
        }
        $cidr_hit = bap_is_in_firehol( $ip );
        return [
            'pass'   => ( false === $cidr_hit ),
            'reason' => "Red maliciosa FireHOL ({$cidr_hit})",
        ];
    }

    if ( 'abuse' === $key ) {
        $min_risk = (int) ( $profile['abuse_risk'] ?? 0 );
        if ( $min_risk <= 0 || ! function_exists( 'bap_get_abuseipdb_score' ) ) {
            return null;
        }
        $score = bap_get_abuseipdb_score( $ip );
        $fail  = ( null !== $score && $score >= $min_risk );
        if ( $fail && $auto_ban_abuse ) {
            $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
            if ( ! in_array( $ip, $blacklist, true ) ) {
                $blacklist[] = $ip;
                update_option( 'bap_blacklist_ips', implode( "\n", $blacklist ) );

                $duration_hours = (int) get_option( 'bap_auto_ban_duration', 24 );
                if ( $duration_hours > 0 ) {
                    $expirations        = get_option( 'bap_auto_ban_expirations', [] );
                    $expirations[ $ip ] = time() + ( $duration_hours * HOUR_IN_SECONDS );
                    update_option( 'bap_auto_ban_expirations', $expirations, false );
                }
            }
        }
        return [
            'pass'   => ! $fail,
            'reason' => "Riesgo AbuseIPDB ({$score}% >= {$min_risk}%)",
        ];
    }

    if ( 'country' === $key ) {
        $ctry_list = bap_parse_list( $profile['countries'] ?? '', true );
        if ( empty( $ctry_list ) ) {
            return null;
        }
        $ctry_mode = (string) ( $profile['country_mode'] ?? 'allow' );
        $in_ctry   = in_array( $country, $ctry_list, true );
        return [
            'pass'   => ( 'allow' === $ctry_mode ) ? $in_ctry : ! $in_ctry,
            'reason' => ( 'allow' === $ctry_mode ) ? "País no permitido ({$country})" : "País bloqueado ({$country})",
        ];
    }

    if ( 'asn' === $key ) {
        $asn_list = bap_parse_list( $profile['asns'] ?? '', true );
        if ( empty( $asn_list ) || ! function_exists( 'bap_get_asn_by_ip' ) ) {
            return null;
        }
        $asn_mode  = (string) ( $profile['asn_mode'] ?? 'block' );
        $asn_code  = '';
        $asn_label = bap_get_asn_by_ip( $ip, $asn_code );
        $in_asn    = ( '' !== $asn_code && in_array( $asn_code, $asn_list, true ) );
        return [
            'pass'   => ( 'allow' === $asn_mode ) ? $in_asn : ! $in_asn,
            'reason' => ( 'allow' === $asn_mode ) ? 'ASN no autorizado (' . ( $asn_label ?: 'N/D' ) . ')' : "ASN bloqueado ({$asn_label})",
        ];
    }

    return null;
}

function bap_evaluate_rule_profile( $ip, $country, array $profile, $auto_ban_abuse = false, $ok_msg = 'Cumple las reglas' ) {
    if ( bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_blacklist_ips', '' ) ) ) ) {
        return [ 'status' => 'block', 'reason' => 'IP/Rango en lista negra' ];
    }

    $norm   = bap_normalize_web_rule( $profile );
    $order  = $norm['order'];
    $ops    = $norm['ops'];
    $checks = [];

    foreach ( $order as $pos => $key ) {
        $item = bap_build_single_condition_check( $key, $ip, $country, $norm, $auto_ban_abuse );
        if ( null !== $item ) {
            $item['op'] = ( $pos > 0 ) ? ( $ops[ $pos - 1 ] ?? 'AND' ) : null;
            $checks[]   = $item;
        }
    }

    if ( empty( $checks ) ) {
        return [ 'status' => 'allow', 'reason' => $ok_msg ];
    }

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
    if ( function_exists( 'bap_match_ip_in_custom_dbs' ) ) {
        $cdb_hit = bap_match_ip_in_custom_dbs( $ip, $context );
        if ( is_array( $cdb_hit ) ) {
            if ( 'allow' === $cdb_hit['action'] ) {
                return [
                    'status' => 'allow',
                    'reason' => 'Lista blanca DB personalizada (' . $cdb_hit['name'] . ')',
                ];
            }
            return [
                'status' => 'block',
                'reason' => 'DB personalizada (' . $cdb_hit['name'] . ')',
            ];
        }
    }

    if ( 'web' === $context && function_exists( 'bap_match_web_rule_for_uri' ) ) {
        $matched = bap_match_web_rule_for_uri( '' !== $uri ? $uri : '/' );
        $rule    = $matched['rule'];
        if ( '1' !== (string) ( $rule['enabled'] ?? '0' ) ) {
            return [
                'status' => 'allow',
                'reason' => 'Regla web desactivada en este ámbito',
            ];
        }
        $scope_lbl = ( '__all__' === $matched['scope'] ) ? 'Web Global' : 'Web (' . $matched['scope'] . ')';
        return bap_evaluate_rule_profile( $ip, $country, $rule, $auto_ban_abuse, 'Cumple las reglas de ' . $scope_lbl );
    }

    $c_mode  = (string) get_option( 'bap_country_mode', 'allow' );
    $a_mode  = (string) get_option( 'bap_asn_mode', 'allow' );
    $profile = [
        'order'         => [ 'tor', 'firehol', 'abuse', 'country', 'asn' ],
        'ops'           => [ 'AND', 'AND', 'AND', 'AND' ],
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
