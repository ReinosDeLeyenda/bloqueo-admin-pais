<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_telegram_intervals() {
    return [
        'hourly'  => [ 'recurrence' => 'hourly', 'seconds' => HOUR_IN_SECONDS, 'label' => '1 hora' ],
        '3hours'  => [ 'recurrence' => 'bap_3hours', 'seconds' => 3 * HOUR_IN_SECONDS, 'label' => '3 horas' ],
        '6hours'  => [ 'recurrence' => 'bap_6hours', 'seconds' => 6 * HOUR_IN_SECONDS, 'label' => '6 horas' ],
        '12hours' => [ 'recurrence' => 'bap_12hours', 'seconds' => 12 * HOUR_IN_SECONDS, 'label' => '12 horas' ],
        'daily'   => [ 'recurrence' => 'daily', 'seconds' => DAY_IN_SECONDS, 'label' => '24 horas' ],
        'weekly'  => [ 'recurrence' => 'weekly', 'seconds' => WEEK_IN_SECONDS, 'label' => '7 días' ],
        '2weeks'  => [ 'recurrence' => 'bap_2weeks', 'seconds' => 14 * DAY_IN_SECONDS, 'label' => '14 días' ],
        'monthly' => [ 'recurrence' => 'bap_monthly', 'seconds' => 30 * DAY_IN_SECONDS, 'label' => '1 mes' ],
    ];
}

function bap_reschedule_telegram_cron( $freq ) {
    wp_clear_scheduled_hook( 'bap_send_telegram_digest_event' );
    $map = bap_get_telegram_intervals();
    if ( isset( $map[ $freq ] ) ) {
        wp_schedule_event( time() + $map[ $freq ]['seconds'], $map[ $freq ]['recurrence'], 'bap_send_telegram_digest_event' );
    }
}

add_action( 'update_option_bap_notify_freq', function( $old_val, $new_val ) {
    bap_reschedule_telegram_cron( $new_val );
}, 10, 2 );

add_action( 'bap_send_telegram_digest_event', 'bap_flush_telegram_digest' );

function bap_tg_api_request( $token, $method, array $payload ) {
    $host = 'api' . '.telegram.org';
    $url  = 'https://' . $host . '/bot' . rawurlencode( $token ) . '/' . $method;

    $res = wp_remote_post( $url, [
        'timeout' => 12,
        'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
        'body'    => wp_json_encode( $payload ),
    ] );

    if ( is_wp_error( $res ) ) {
        return $res;
    }

    $code = wp_remote_retrieve_response_code( $res );
    $body = json_decode( wp_remote_retrieve_body( $res ), true );

    if ( 200 === $code && ! empty( $body['ok'] ) ) {
        return true;
    }

    return new WP_Error( 'tg_api_err', (string) ( $body['description'] ?? 'HTTP ' . $code ) );
}

function bap_send_telegram_rich_article( $rich_html, $fallback_html ) {
    $token   = trim( (string) get_option( 'bap_tg_bot_token', '' ) );
    $raw_ids = trim( (string) get_option( 'bap_tg_chat_ids', '' ) );

    if ( '' === $token || '' === $raw_ids ) {
        return new WP_Error( 'tg_missing_cfg', 'Falta el Token del Bot o el Chat ID de Telegram.' );
    }

    $chat_ids = array_unique( array_filter( array_map( 'trim', preg_split( '/[\r\n,;]+/', $raw_ids ) ) ) );
    $last_err = null;

    foreach ( $chat_ids as $chat_id ) {
        $rich_res = bap_tg_api_request( $token, 'sendRichMessage', [
            'chat_id'      => $chat_id,
            'rich_message' => [ 'html' => $rich_html ],
        ] );

        if ( true !== $rich_res ) {
            $fb_res = bap_tg_api_request( $token, 'sendMessage', [
                'chat_id'                  => $chat_id,
                'text'                     => mb_substr( $fallback_html, 0, 4090 ),
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ] );
            if ( true !== $fb_res ) {
                $last_err = $fb_res;
            }
        }
    }

    return null === $last_err ? true : $last_err;
}

function bap_build_telegram_single_article( array $item, $site_name, $is_test = false ) {
    $badge = $is_test ? '🧪 <b>PRUEBA DE ALERTA</b> · ' : '';
    $e     = fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

    $rich  = "<h2>🛡️ {$badge}Bloqueo de Seguridad — {$e( $site_name )}</h2>";
    $rich .= "<p>Se ha interceptado y bloqueado una petición sospechosa en tu instalación WordPress.</p>";
    $rich .= "<table>";
    $rich .= "<tr><th>Parámetro</th><th>Detalle</th></tr>";
    $rich .= "<tr><td><b>IP Atacante</b></td><td><code>{$e( $item['ip'] )}</code></td></tr>";
    $rich .= "<tr><td><b>País</b></td><td><b>{$e( $item['country'] )}</b></td></tr>";
    $rich .= "<tr><td><b>ASN / Red</b></td><td>{$e( $item['asn'] )}</td></tr>";
    $rich .= "<tr><td><b>Riesgo AbuseIPDB</b></td><td><b>{$e( $item['risk'] )}</b></td></tr>";
    $rich .= "<tr><td><b>Motivo</b></td><td>{$e( $item['reason'] )}</td></tr>";
    $rich .= "<tr><td><b>Fecha / Hora</b></td><td><code>{$e( $item['time'] )}</code></td></tr>";
    $rich .= "</table>";
    $rich .= "<blockquote expandable><b>Ruta solicitada:</b>\n<code>{$e( $item['uri'] ?: '/' )}</code></blockquote>";

    $fb  = "🛡️ <b>{$badge}Bloqueo — {$e( $site_name )}</b>\n\n";
    $fb .= "• <b>IP:</b> <code>{$e( $item['ip'] )}</code> ({$e( $item['country'] )})\n";
    $fb .= "• <b>ASN:</b> {$e( $item['asn'] )}\n";
    $fb .= "• <b>AbuseIPDB:</b> {$e( $item['risk'] )}\n";
    $fb .= "• <b>Motivo:</b> {$e( $item['reason'] )}\n";
    $fb .= "<blockquote expandable><b>Ruta:</b> <code>{$e( $item['uri'] ?: '/' )}</code>\n<b>Fecha:</b> {$e( $item['time'] )}</blockquote>";

    return [ 'rich' => $rich, 'fallback' => $fb ];
}

function bap_build_telegram_digest_article( array $queue, $site_name, $period_label, $is_test = false ) {
    $data  = bap_prepare_digest_stats( $queue );
    $e     = fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    $badge = $is_test ? '🧪 <b>PRUEBA DE RESUMEN</b> · ' : '';

    $rich  = "<h1>📊 {$badge}Informe de Seguridad ({$e( $period_label )})</h1>";
    $rich .= "<p><b>Sitio:</b> {$e( $site_name )} · <b>Total bloqueos:</b> <b>{$data['total_hits']}</b> · <b>IPs únicas:</b> <b>{$data['unique_ips']}</b></p>";

    if ( ! empty( $data['top_countries'] ) ) {
        $rich .= "<h3>🌍 Top Países Atacantes</h3><ul>";
        foreach ( $data['top_countries'] as $cc => $cnt ) {
            $pct   = round( ( $cnt / max( 1, $data['total_hits'] ) ) * 100, 1 );
            $rich .= "<li><b>{$e( $cc )}</b>: {$cnt} intentos ({$pct}%)</li>";
        }
        $rich .= "</ul>";
    }

    $top_rows = array_slice( $data['grouped'], 0, 15, true );
    $rich    .= "<h3>🚨 Eventos Bloqueados Agrupados (Top " . count( $top_rows ) . ")</h3>";
    $rich    .= "<table><tr><th>Hits</th><th>IP / País</th><th>Riesgo</th><th>Motivo</th></tr>";
    foreach ( $top_rows as $g ) {
        $rich .= "<tr>";
        $rich .= "<td><b>x{$g['count']}</b></td>";
        $rich .= "<td><code>{$e( $g['ip'] )}</code> ({$e( $g['country'] )})</td>";
        $rich .= "<td>{$e( $g['risk'] )}</td>";
        $rich .= "<td>{$e( $g['reason'] )}</td>";
        $rich .= "</tr>";
    }
    $rich .= "</table>";

    if ( ! empty( $data['top_asns'] ) ) {
        $asn_lines = [];
        foreach ( $data['top_asns'] as $an => $cnt ) {
            $asn_lines[] = "• <b>{$e( $an )}</b>: {$cnt}";
        }
        $rich .= "<blockquote expandable><b>🏢 Top ASNs / Proveedores:</b>\n" . implode( "\n", $asn_lines ) . "</blockquote>";
    }

    $fb  = "📊 <b>{$badge}Informe ({$e( $period_label )}) — {$e( $site_name )}</b>\n";
    $fb .= "Total: <b>{$data['total_hits']}</b> | IPs únicas: <b>{$data['unique_ips']}</b>\n\n";
    foreach ( array_slice( $top_rows, 0, 10, true ) as $g ) {
        $fb .= "• <b>(x{$g['count']})</b> <code>{$e( $g['ip'] )}</code> [{$e( $g['country'] )}] — {$e( $g['reason'] )}\n";
    }

    return [ 'rich' => $rich, 'fallback' => $fb ];
}

function bap_queue_or_send_telegram( array $item, $freq = '' ) {
    if ( '' === $freq ) {
        $freq = (string) get_option( 'bap_notify_freq', 'daily' );
    }

    $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

    if ( 'instant' === $freq ) {
        $msg = bap_build_telegram_single_article( $item, $site_name, false );
        bap_send_telegram_rich_article( $msg['rich'], $msg['fallback'] );
        return;
    }

    $queue = get_option( 'bap_tg_digest_queue', [] );
    if ( ! is_array( $queue ) ) {
        $queue = [];
    }
    $queue[] = $item;
    if ( count( $queue ) > 2000 ) {
        $queue = array_slice( $queue, -2000 );
    }
    update_option( 'bap_tg_digest_queue', $queue, false );

    if ( ! wp_next_scheduled( 'bap_send_telegram_digest_event' ) ) {
        bap_reschedule_telegram_cron( $freq );
    }
}

function bap_flush_telegram_digest() {
    $queue = get_option( 'bap_tg_digest_queue', [] );
    if ( empty( $queue ) || ! is_array( $queue ) ) {
        return;
    }
    delete_option( 'bap_tg_digest_queue' );

    $channel = (string) get_option( 'bap_notify_channel', 'none' );
    if ( ! in_array( $channel, [ 'telegram', 'both' ], true ) ) {
        return;
    }

    $freq      = (string) get_option( 'bap_notify_freq', 'daily' );
    $intervals = bap_get_telegram_intervals();
    $label     = $intervals[ $freq ]['label'] ?? 'Programado';
    $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $msg       = bap_build_telegram_digest_article( $queue, $site_name, $label, false );

    bap_send_telegram_rich_article( $msg['rich'], $msg['fallback'] );
}
