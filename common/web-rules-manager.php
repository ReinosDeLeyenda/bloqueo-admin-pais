<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_default_web_rule( $enabled = '0' ) {
    return [
        'enabled'       => $enabled,
        'order'         => [ 'tor', 'firehol', 'abuse', 'country', 'asn' ],
        'ops'           => [ 'AND', 'AND', 'AND', 'AND' ],
        'country_mode'  => 'allow',
        'countries'     => '',
        'asn_mode'      => 'block',
        'asns'          => '',
        'abuse_risk'    => 0,
        'block_tor'     => '0',
        'block_firehol' => '0',
    ];
}

function bap_normalize_web_rule( array $rule ) {
    $def   = bap_get_default_web_rule( '0' );
    $valid = [ 'tor', 'firehol', 'abuse', 'country', 'asn' ];
    $order = [];

    if ( ! empty( $rule['order'] ) && is_array( $rule['order'] ) ) {
        foreach ( $rule['order'] as $k ) {
            if ( in_array( $k, $valid, true ) && ! in_array( $k, $order, true ) ) {
                $order[] = $k;
            }
        }
    }
    foreach ( $valid as $k ) {
        if ( ! in_array( $k, $order, true ) ) {
            $order[] = $k;
        }
    }

    $ops = [];
    if ( ! empty( $rule['ops'] ) && is_array( $rule['ops'] ) ) {
        for ( $i = 0; $i < 4; $i++ ) {
            $ops[ $i ] = ( 'OR' === strtoupper( (string) ( $rule['ops'][ $i ] ?? 'AND' ) ) ) ? 'OR' : 'AND';
        }
    } else {
        $legacy = [ $rule['op_1'] ?? 'AND', $rule['op_2'] ?? 'AND', $rule['op_3'] ?? 'AND', $rule['op_4'] ?? 'AND' ];
        for ( $i = 0; $i < 4; $i++ ) {
            $ops[ $i ] = ( 'OR' === strtoupper( (string) $legacy[ $i ] ) ) ? 'OR' : 'AND';
        }
    }

    $merged          = wp_parse_args( $rule, $def );
    $merged['order'] = $order;
    $merged['ops']   = $ops;
    return $merged;
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

    $migrated = bap_normalize_web_rule( [
        'enabled'       => $old_enabled,
        'country_mode'  => $old_cmode,
        'countries'     => (string) get_option( 'block' === $old_cmode ? 'bap_web_blocked_countries' : 'bap_web_allowed_countries', '' ),
        'asn_mode'      => $old_amode,
        'asns'          => (string) get_option( 'block' === $old_amode ? 'bap_web_blocked_asns' : 'bap_web_allowed_asns', '' ),
        'abuse_risk'    => (int) get_option( 'bap_web_abuse_risk', 0 ),
        'block_tor'     => (string) get_option( 'bap_web_block_tor', '0' ),
        'block_firehol' => (string) get_option( 'bap_web_block_firehol', '0' ),
    ] );

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
                'rule'  => bap_normalize_web_rule( (array) $rule ),
            ];
        }
    }

    return [
        'scope' => '__all__',
        'rule'  => bap_normalize_web_rule( (array) ( $rules['__all__'] ?? [] ) ),
    ];
}
