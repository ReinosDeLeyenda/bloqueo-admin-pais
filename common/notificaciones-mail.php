<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['bap_3hours']  = [ 'interval' => 3 * HOUR_IN_SECONDS, 'display' => 'Cada 3 horas' ];
    $schedules['bap_6hours']  = [ 'interval' => 6 * HOUR_IN_SECONDS, 'display' => 'Cada 6 horas' ];
    $schedules['bap_12hours'] = [ 'interval' => 12 * HOUR_IN_SECONDS, 'display' => 'Cada 12 horas' ];
    $schedules['bap_2weeks']  = [ 'interval' => 14 * DAY_IN_SECONDS, 'display' => 'Cada 2 semanas' ];
    $schedules['bap_monthly'] = [ 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Cada mes' ];
    return $schedules;
});

function bap_reschedule_mail_cron( $freq ) {
    wp_clear_scheduled_hook( 'bap_send_digest_email_event' );
    $map = [
        'hourly'  => 'hourly',
        '3hours'  => 'bap_3hours',
        '6hours'  => 'bap_6hours',
        '12hours' => 'bap_12hours',
        'daily'   => 'daily',
        'weekly'  => 'weekly',
        '2weeks'  => 'bap_2weeks',
        'monthly' => 'bap_monthly',
    ];
    if ( isset( $map[ $freq ] ) ) {
        $recurrence = $map[ $freq ];
        $intervals  = [
            'hourly'      => HOUR_IN_SECONDS,
            'bap_3hours'  => 3 * HOUR_IN_SECONDS,
            'bap_6hours'  => 6 * HOUR_IN_SECONDS,
            'bap_12hours' => 12 * HOUR_IN_SECONDS,
            'daily'       => DAY_IN_SECONDS,
            'weekly'      => WEEK_IN_SECONDS,
            'bap_2weeks'  => 14 * DAY_IN_SECONDS,
            'bap_monthly' => 30 * DAY_IN_SECONDS,
        ];
        wp_schedule_event( time() + $intervals[ $recurrence ], $recurrence, 'bap_send_digest_email_event' );
    }
}

add_action( 'update_option_bap_notify_freq', function( $old_val, $new_val ) {
    bap_reschedule_mail_cron( $new_val );
}, 10, 2 );

add_action( 'bap_send_digest_email_event', 'bap_flush_email_digest' );

function bap_get_notify_recipients() {
    $raw = trim( (string) get_option( 'bap_notify_emails', '' ) );
    if ( '' === $raw ) {
        return [ get_option( 'admin_email' ) ];
    }
    $valid = [];
    foreach ( preg_split( '/[\r\n,;]+/', $raw ) as $mail ) {
        $m = sanitize_email( trim( $mail ) );
        if ( is_email( $m ) ) {
            $valid[] = $m;
        }
    }
    return ! empty( $valid ) ? array_unique( $valid ) : [ get_option( 'admin_email' ) ];
}

function bap_queue_or_send_notification( $ip, $country, $reason ) {
    $channel = (string) get_option( 'bap_notify_channel', 'none' );
    if ( 'none' === $channel || '' === $channel ) {
        return;
    }

    $freq = (string) get_option( 'bap_notify_freq', 'daily' );
    $asn  = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : 'N/D';
    $risk = function_exists( 'bap_get_abuseipdb_score' ) ? bap_get_abuseipdb_score( $ip ) : null;
    $item = [
        'time'    => current_time( 'mysql' ),
        'ip'      => $ip,
        'country' => $country,
        'asn'     => $asn,
        'risk'    => ( null === $risk ) ? 'N/D' : $risk . '%',
        'reason'  => $reason,
        'uri'     => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
    ];

    if ( in_array( $channel, [ 'telegram', 'both' ], true ) && function_exists( 'bap_queue_or_send_telegram' ) ) {
        bap_queue_or_send_telegram( $item, $freq );
    }

    if ( ! in_array( $channel, [ 'email', 'both' ], true ) ) {
        return;
    }

    if ( 'instant' === $freq ) {
        $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $subject = '[' . $site . '] Intento bloqueado: ' . $ip . ' (' . $country . ')';
        $body    = "Se ha bloqueado un intento de acceso al panel de administración:\n\n"
                 . "Fecha/Hora: {$item['time']}\n"
                 . "IP: {$item['ip']}\n"
                 . "País: {$item['country']}\n"
                 . "ASN / Proveedor: {$item['asn']}\n"
                 . "Riesgo AbuseIPDB: {$item['risk']}\n"
                 . "Motivo: {$item['reason']}\n"
                 . "URL solicitada: {$item['uri']}\n";
        wp_mail( bap_get_notify_recipients(), $subject, $body );
        return;
    }

    $queue = get_option( 'bap_email_digest_queue', [] );
    if ( ! is_array( $queue ) ) {
        $queue = [];
    }
    $queue[] = $item;
    if ( count( $queue ) > 2000 ) {
        $queue = array_slice( $queue, -2000 );
    }
    update_option( 'bap_email_digest_queue', $queue, false );

    if ( ! wp_next_scheduled( 'bap_send_digest_email_event' ) ) {
        bap_reschedule_mail_cron( $freq );
    }
}

function bap_flush_email_digest() {
    $queue = get_option( 'bap_email_digest_queue', [] );
    if ( empty( $queue ) || ! is_array( $queue ) ) {
        return;
    }
    delete_option( 'bap_email_digest_queue' );

    $channel = (string) get_option( 'bap_notify_channel', 'none' );
    if ( ! in_array( $channel, [ 'email', 'both' ], true ) ) {
        return;
    }

    $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $total   = count( $queue );
    $subject = '[' . $site . '] Resumen de seguridad: ' . $total . ' intentos bloqueados';
    $tpl     = (string) get_option( 'bap_email_template', 'full' );
    $mail    = bap_render_digest_by_template( $queue, false, $tpl );

    wp_mail( bap_get_notify_recipients(), $subject, $mail['body'], $mail['headers'] );
}

function bap_send_test_digest_email() {
    $queue = get_option( 'bap_email_digest_queue', [] );

    if ( empty( $queue ) || ! is_array( $queue ) ) {
        global $wpdb;
        $table = function_exists( 'bap_get_logs_table_name' ) ? bap_get_logs_table_name() : $wpdb->prefix . 'bap_blocked_logs';
        $rows  = $wpdb->get_results( "SELECT created_at, ip_address, country_code, asn, reason FROM `{$table}` ORDER BY id DESC LIMIT 50", ARRAY_A );

        $queue = [];
        if ( ! empty( $rows ) ) {
            foreach ( array_reverse( $rows ) as $r ) {
                $risk    = function_exists( 'bap_get_abuseipdb_score' ) ? bap_get_abuseipdb_score( $r['ip_address'] ) : null;
                $queue[] = [
                    'time'    => $r['created_at'],
                    'ip'      => $r['ip_address'],
                    'country' => $r['country_code'] ?: 'DESCONOCIDO',
                    'asn'     => $r['asn'] ?: 'N/D',
                    'risk'    => ( null === $risk ) ? 'N/D' : $risk . '%',
                    'reason'  => $r['reason'],
                ];
            }
        } else {
            $now   = current_time( 'mysql' );
            $queue = [
                [ 'time' => $now, 'ip' => '34.140.28.27', 'country' => 'BE', 'asn' => 'AS396982 — Google LLC', 'risk' => '95%', 'reason' => 'Web Global: IP/Rango en lista negra' ],
                [ 'time' => $now, 'ip' => '34.140.28.27', 'country' => 'BE', 'asn' => 'AS396982 — Google LLC', 'risk' => '95%', 'reason' => 'Web Global: IP/Rango en lista negra' ],
                [ 'time' => $now, 'ip' => '185.220.101.4', 'country' => 'DE', 'asn' => 'AS205100 — F3 Netze e.V.', 'risk' => '100%', 'reason' => 'Nodo de salida Tor' ],
            ];
        }
    }

    $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $subject = '[' . $site . '] Prueba de resumen agrupado (' . count( $queue ) . ' eventos)';
    $tpl     = (string) get_option( 'bap_email_template', 'full' );
    $mail    = bap_render_digest_by_template( $queue, true, $tpl );

    return wp_mail( bap_get_notify_recipients(), $subject, $mail['body'], $mail['headers'] );
}
