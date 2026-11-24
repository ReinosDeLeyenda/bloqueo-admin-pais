<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_parse_country_redirects( $raw ) {
    $map = [];
    if ( empty( $raw ) ) {
        return $map;
    }
    foreach ( preg_split( '/[\r\n]+/', (string) $raw ) as $line ) {
        $line = trim( $line );
        if ( '' === $line || 0 === strpos( $line, '#' ) ) {
            continue;
        }
        if ( preg_match( '/^([a-zA-Z]{2})\s*(?:=>|=|\s+)\s*(https?:\/\/\S+)$/i', $line, $m ) ) {
            $code = strtoupper( $m[1] );
            $url  = esc_url_raw( trim( $m[2] ) );
            if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
                $map[ $code ] = $url;
            }
        }
    }
    return $map;
}

function bap_sanitize_country_redirects( $raw ) {
    $map = bap_parse_country_redirects( $raw );
    $out = [];
    foreach ( $map as $code => $url ) {
        $out[] = $code . ' => ' . $url;
    }
    return implode( "\n", $out );
}

function bap_get_country_redirect_url( $country ) {
    $code = strtoupper( trim( (string) $country ) );
    if ( 2 !== strlen( $code ) ) {
        return '';
    }
    $map = bap_parse_country_redirects( get_option( 'bap_web_country_redirects', '' ) );
    if ( empty( $map[ $code ] ) ) {
        return '';
    }

    $target = $map[ $code ];
    // Evitar bucle si el usuario ya está en la URL de destino
    $req_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $cur_host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
    $t_host   = strtolower( (string) wp_parse_url( $target, PHP_URL_HOST ) );
    $t_path   = (string) wp_parse_url( $target, PHP_URL_PATH );
    $c_path   = (string) wp_parse_url( $req_uri, PHP_URL_PATH );

    if ( $cur_host === $t_host && rtrim( $c_path, '/' ) === rtrim( $t_path, '/' ) ) {
        return '';
    }

    return $target;
}
