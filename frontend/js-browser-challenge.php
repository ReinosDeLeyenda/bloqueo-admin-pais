<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_js_challenge_cookie_name() {
    return 'bap_jsc_' . substr( hash( 'sha256', AUTH_SALT ), 0, 10 );
}

function bap_js_challenge_expected_token( $ip ) {
    $ua = substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 120 );
    $day_bucket = (string) floor( time() / ( 12 * HOUR_IN_SECONDS ) );
    return hash_hmac( 'sha256', $ip . '|' . $ua . '|' . $day_bucket, AUTH_SALT );
}

function bap_js_challenge_has_valid_cookie( $ip ) {
    $cname = bap_js_challenge_cookie_name();
    if ( empty( $_COOKIE[ $cname ] ) ) { return false; }
    $val = sanitize_text_field( wp_unslash( $_COOKIE[ $cname ] ) );
    return hash_equals( bap_js_challenge_expected_token( $ip ), $val );
}

function bap_js_challenge_matches_scope() {
    $mode = (string) get_option( 'bap_js_challenge_mode', 'off' );
    if ( 'off' === $mode || '' === $mode ) { return false; }
    if ( 'all' === $mode ) { return true; }

    $raw = trim( (string) get_option( 'bap_js_challenge_paths', "/tienda/*\n/mi-cuenta/*\n/registro*" ) );
    if ( '' === $raw ) { return false; }

    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '/';
    $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
    foreach ( bap_parse_list( $raw, false ) as $pat ) {
        $p = strtolower( trim( $pat ) );
        if ( '' === $p ) { continue; }
        $regex = '#^' . str_replace( '\*', '.*', preg_quote( $p, '#' ) ) . '$#i';
        if ( preg_match( $regex, $path ) || preg_match( $regex, $uri ) ) { return true; }
    }
    return false;
}

add_action( 'init', function() {
    if ( is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { return; }
    if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { return; }
    if ( ! bap_js_challenge_matches_scope() ) { return; }

    $ip = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
    if ( '' === $ip || bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) { return; }
    if ( '1' === (string) get_option( 'bap_web_allow_seo_bots', '1' ) && function_exists( 'bap_is_verified_seo_bot' ) && bap_is_verified_seo_bot( $ip ) ) { return; }
    if ( bap_js_challenge_has_valid_cookie( $ip ) ) { return; }

    $cname  = bap_js_challenge_cookie_name();
    $token  = bap_js_challenge_expected_token( $ip );
    $salt   = bin2hex( random_bytes( 8 ) );
    $secret = random_int( 8000, 28000 );
    $target = hash( 'sha256', $salt . ':' . $secret );

    status_header( 503 );
    nocache_headers();
    header( 'Content-Type: text/html; charset=utf-8' );
    header( 'Retry-After: 2' );
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verificando navegador...</title></head>';
    echo '<body style="background:#0f172a;color:#f8fafc;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;">';
    echo '<div style="text-align:center;border:1px solid #334155;padding:34px 28px;border-radius:14px;background:#1e293b;max-width:440px;">';
    echo '<h2 style="margin:0 0 10px;color:#38bdf8;">Verificando tu navegador...</h2>';
    echo '<p id="bap-jsc-msg" style="margin:0;color:#cbd5e1;font-size:14px;">Comprobando conexion segura antes de acceder al sitio. Espera un segundo...</p>';
    echo '<noscript><p style="color:#f87171;margin-top:12px;">Activa JavaScript en tu navegador para continuar.</p></noscript></div>';
    echo '<script>(async function(){var s=' . wp_json_encode( $salt ) . ',t=' . wp_json_encode( $target ) . ',enc=new TextEncoder();';
    echo 'for(var n=0;n<=60000;n++){var b=await crypto.subtle.digest("SHA-256",enc.encode(s+":"+n));';
    echo 'var h=Array.from(new Uint8Array(b)).map(function(x){return x.toString(16).padStart(2,"0");}).join("");';
    echo 'if(h===t){document.cookie=' . wp_json_encode( $cname . '=' . $token . '; path=/; max-age=43200; SameSite=Lax' ) . ';';
    echo 'document.getElementById("bap-jsc-msg").textContent="Verificado. Redirigiendo...";setTimeout(function(){window.location.reload();},250);break;}}})();</script></body></html>';
    exit;
}, 3 );