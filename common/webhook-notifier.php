<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_send_generic_webhook( $event_type, array $data, $is_test = false ) {
    $url = trim( (string) get_option( 'bap_webhook_url', '' ) );
    if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return new WP_Error( 'wh_no_url', 'No hay ninguna URL de Webhook configurada.' );
    }

    $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $ip      = (string) ( $data['ip'] ?? 'N/D' );
    $country = (string) ( $data['country'] ?? 'N/D' );
    $reason  = (string) ( $data['reason'] ?? 'Alerta de seguridad' );
    $uri     = (string) ( $data['uri'] ?? '/' );
    $badge   = $is_test ? '[PRUEBA] ' : '';

    $payload = [
        'event'     => (string) $event_type,
        'is_test'   => (bool) $is_test,
        'site'      => $site,
        'site_url'  => home_url(),
        'timestamp' => current_time( 'c' ),
        'ip'        => $ip,
        'country'   => $country,
        'asn'       => (string) ( $data['asn'] ?? 'N/D' ),
        'risk'      => (string) ( $data['risk'] ?? 'N/D' ),
        'reason'    => $reason,
        'uri'       => $uri,
        'content'   => "{$badge}**[{$site}]** `{$ip}` ({$country}) — {$reason} (`{$uri}`)",
    ];

    $json_body = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    $headers   = [
        'Content-Type' => 'application/json; charset=utf-8',
        'User-Agent'   => 'WordPress-BAP-Webhook/1.0',
        'X-BAP-Event'  => (string) $event_type,
    ];

    $secret = trim( (string) get_option( 'bap_webhook_secret', '' ) );
    if ( '' !== $secret ) {
        $headers['X-BAP-Signature'] = 'sha256=' . hash_hmac( 'sha256', (string) $json_body, $secret );
    }

    $res = wp_remote_post( $url, [
        'timeout'  => 6,
        'blocking' => $is_test,
        'headers'  => $headers,
        'body'     => $json_body,
    ] );

    if ( is_wp_error( $res ) ) {
        return $res;
    }

    if ( $is_test ) {
        $code = wp_remote_retrieve_response_code( $res );
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'wh_http', 'El endpoint devolvió HTTP ' . $code );
        }
    }

    return true;
}