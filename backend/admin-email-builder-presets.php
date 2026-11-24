<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_available_email_modules() {
    return [
        'cards'          => 'Tarjetas Resumen (Total Bloqueados, IPs Únicas, Top Países, Top ASNs)',
        'countries_bars' => 'Gráfico de Barras por País (Top 5)',
        'top_uris'       => 'Gráfico de Barras URLs Atacadas (Top 5)',
        'hourly_chart'   => 'Gráfico de Actividad por Hora (00h – 23h)',
        'events_table'   => 'Tabla de Intentos Bloqueados (Agrupados)',
    ];
}

function bap_get_builtin_email_presets() {
    return [
        'full'  => [
            'name'        => 'Completa (Por defecto)',
            'desc'        => 'Tarjetas + Barras de Países + Todos los eventos agrupados',
            'format'      => 'html_dark',
            'table_limit' => 0,
            'modules'     => [ 'cards', 'countries_bars', 'events_table' ],
            'sizes'       => [ 'cards' => '100', 'countries_bars' => '100', 'events_table' => '100' ],
        ],
        'top10' => [
            'name'        => 'Top 10',
            'desc'        => 'Tarjetas + Barras de Países + Top 10 bloqueos',
            'format'      => 'html_dark',
            'table_limit' => 10,
            'modules'     => [ 'cards', 'countries_bars', 'events_table' ],
            'sizes'       => [ 'cards' => '100', 'countries_bars' => '100', 'events_table' => '100' ],
        ],
        'top5'  => [
            'name'        => 'Top 5',
            'desc'        => 'Tarjetas + Barras de Países + Top 5 bloqueos',
            'format'      => 'html_dark',
            'table_limit' => 5,
            'modules'     => [ 'cards', 'countries_bars', 'events_table' ],
            'sizes'       => [ 'cards' => '100', 'countries_bars' => '100', 'events_table' => '100' ],
        ],
        'text'  => [
            'name'        => 'Solo texto plano',
            'desc'        => 'Resumen agrupado ligero sin HTML',
            'format'      => 'text',
            'table_limit' => 0,
            'modules'     => [ 'cards', 'events_table' ],
            'sizes'       => [],
        ],
    ];
}

function bap_sanitize_custom_template_data( array $raw ) {
    $all_mods = array_keys( bap_get_available_email_modules() );
    $modules  = [];
    foreach ( (array) ( $raw['modules'] ?? [] ) as $m ) {
        $m = sanitize_key( $m );
        if ( in_array( $m, $all_mods, true ) && ! in_array( $m, $modules, true ) ) {
            $modules[] = $m;
        }
    }
    if ( empty( $modules ) ) {
        $modules = [ 'cards', 'events_table' ];
    }

    $sizes = [];
    foreach ( $all_mods as $k ) {
        $val         = (string) ( $raw['sizes'][ $k ] ?? '100' );
        $sizes[ $k ] = in_array( $val, [ '100', '50', '33' ], true ) ? $val : '100';
    }

    $fmt = (string) ( $raw['format'] ?? 'html_dark' );
    if ( ! in_array( $fmt, [ 'html_dark', 'html_light', 'text' ], true ) ) {
        $fmt = 'html_dark';
    }

    $name = trim( sanitize_text_field( (string) ( $raw['name'] ?? '' ) ) );
    if ( '' === $name ) {
        $name = 'Plantilla personalizada';
    }

    return [
        'name'        => mb_substr( $name, 0, 60 ),
        'format'      => $fmt,
        'table_limit' => max( 0, min( 200, (int) ( $raw['table_limit'] ?? 10 ) ) ),
        'modules'     => $modules,
        'sizes'       => $sizes,
    ];
}

function bap_get_email_preview_sample_queue() {
    global $wpdb;
    $table = function_exists( 'bap_get_logs_table_name' ) ? bap_get_logs_table_name() : $wpdb->prefix . 'bap_blocked_logs';
    $rows  = $wpdb->get_results( "SELECT created_at, ip_address, country_code, asn, reason, request_uri FROM `{$table}` ORDER BY id DESC LIMIT 40", ARRAY_A );

    if ( ! empty( $rows ) ) {
        $queue = [];
        foreach ( array_reverse( $rows ) as $r ) {
            $risk    = function_exists( 'bap_get_abuseipdb_score' ) ? bap_get_abuseipdb_score( $r['ip_address'] ) : null;
            $queue[] = [
                'time'    => $r['created_at'],
                'ip'      => $r['ip_address'],
                'country' => $r['country_code'] ?: 'DESCONOCIDO',
                'asn'     => $r['asn'] ?: 'N/D',
                'risk'    => ( null === $risk ) ? 'N/D' : $risk . '%',
                'reason'  => $r['reason'],
                'uri'     => $r['request_uri'] ?: '/',
            ];
        }
        return $queue;
    }

    $now = current_time( 'mysql' );
    return [
        [ 'time' => $now, 'ip' => '34.140.28.27', 'country' => 'BE', 'asn' => 'AS396982 — Google LLC', 'risk' => '95%', 'reason' => 'Web Global: IP/Rango en lista negra', 'uri' => '/wp-login.php' ],
        [ 'time' => $now, 'ip' => '34.140.28.27', 'country' => 'BE', 'asn' => 'AS396982 — Google LLC', 'risk' => '95%', 'reason' => 'Web Global: IP/Rango en lista negra', 'uri' => '/wp-login.php' ],
        [ 'time' => $now, 'ip' => '185.220.101.4', 'country' => 'DE', 'asn' => 'AS205100 — F3 Netze e.V.', 'risk' => '100%', 'reason' => 'Nodo de salida Tor', 'uri' => '/xmlrpc.php' ],
    ];
}
