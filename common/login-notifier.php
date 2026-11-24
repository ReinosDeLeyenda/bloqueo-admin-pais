<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_active_session_asns( $user_id ) {
    if ( ! class_exists( 'WP_Session_Tokens' ) ) {
        return [];
    }
    $manager  = WP_Session_Tokens::get_instance( $user_id );
    $sessions = $manager->get_all();
    $asns     = [];

    foreach ( (array) $sessions as $sess ) {
        $s_ip = $sess['ip'] ?? '';
        if ( '' !== $s_ip && function_exists( 'bap_get_asn_by_ip' ) ) {
            $s_asn = bap_get_asn_by_ip( $s_ip );
            if ( '' !== $s_asn && 'Sin base de datos ASN' !== $s_asn && 'IP inválida' !== $s_asn ) {
                $asns[] = $s_asn;
            }
        }
    }

    return array_values( array_unique( $asns ) );
}

function bap_evaluate_login_asn_change( $user_id, $current_asn ) {
    $last_asn   = (string) get_user_meta( $user_id, '_bap_last_login_asn', true );
    $active_asn = bap_get_active_session_asns( $user_id );

    $is_valid_cur = ( '' !== $current_asn && 'Sin base de datos ASN' !== $current_asn && 'IP inválida' !== $current_asn );
    $other_active = $is_valid_cur ? array_values( array_diff( $active_asn, [ $current_asn ] ) ) : [];
    $diff_last    = ( $is_valid_cur && '' !== $last_asn && $current_asn !== $last_asn );
    $diff_active  = ! empty( $other_active );

    if ( $is_valid_cur ) {
        update_user_meta( $user_id, '_bap_last_login_asn', $current_asn );
    }

    $notes = [];
    if ( $diff_last ) {
        $notes[] = "Distinto al de la última sesión ({$last_asn})";
    }
    if ( $diff_active ) {
        $notes[] = 'Distinto al de otras sesiones activas (' . implode( ', ', $other_active ) . ')';
    }

    return [
        'changed'     => ( $diff_last || $diff_active ),
        'last_asn'    => $last_asn ?: 'Primera sesión registrada',
        'active_asns' => ! empty( $active_asn ) ? implode( ', ', $active_asn ) : 'Ninguna otra activa',
        'summary'     => ! empty( $notes ) ? implode( ' | ', $notes ) : 'Mismo ASN habitual',
    ];
}

function bap_send_successful_login_alert( WP_User $user, $is_test = false ) {
    $channel = (string) get_option( 'bap_login_notify_channel', 'none' );
    if ( ! $is_test && ( 'none' === $channel || '' === $channel ) ) {
        return false;
    }
    if ( $is_test && ( 'none' === $channel || '' === $channel ) ) {
        $channel = 'both';
    }

    $ip        = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '127.0.0.1';
    $country   = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    $asn       = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : 'N/D';
    $ua        = function_exists( 'bap_get_sanitized_user_agent' ) ? bap_get_sanitized_user_agent() : '';
    $time      = current_time( 'mysql' );
    $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

    $asn_check = $is_test
        ? [
            'changed'     => true,
            'last_asn'    => 'AS3352 — Telefonica de Espana SAU',
            'active_asns' => 'AS3352 — Telefonica de Espana SAU',
            'summary'     => 'Distinto al de la última sesión y sesiones activas (Prueba)',
        ]
        : bap_evaluate_login_asn_change( $user->ID, $asn );

    $ok = true;

    if ( in_array( $channel, [ 'email', 'both' ], true ) && function_exists( 'bap_get_notify_recipients' ) ) {
        $prefix  = $asn_check['changed'] ? '⚠️ [NUEVO ASN] ' : '✅ ';
        $t_badge = $is_test ? '[PRUEBA] ' : '';
        $subject = "{$prefix}{$t_badge}[{$site_name}] Inicio de sesión exitoso: {$user->user_login}";

        $body  = "Se ha detectado un inicio de sesión exitoso en {$site_name}:\n\n";
        if ( $asn_check['changed'] ) {
            $body .= "⚠️ AVISO DE CAMBIO DE RED / ASN:\n{$asn_check['summary']}\n\n";
        }
        $body .= "• Usuario: {$user->user_login} ({$user->user_email})\n";
        $body .= "• Fecha / Hora: {$time}\n";
        $body .= "• Dirección IP: {$ip}\n";
        $body .= "• País: {$country}\n";
        $body .= "• ASN Actual: {$asn}\n";
        $body .= "• ASN Última Sesión: {$asn_check['last_asn']}\n";
        $body .= "• ASNs Sesiones Activas: {$asn_check['active_asns']}\n";
        $body .= "• Navegador / User-Agent: " . ( $ua ?: 'No registrado' ) . "\n";

        if ( ! wp_mail( bap_get_notify_recipients(), $subject, $body ) ) {
            $ok = false;
        }
    }

    if ( in_array( $channel, [ 'telegram', 'both' ], true ) && function_exists( 'bap_send_telegram_rich_article' ) ) {
        $e      = fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        $t_tag  = $is_test ? '🧪 <b>PRUEBA</b> · ' : '';
        $header = $asn_check['changed']
            ? "⚠️ {$t_tag}Conexión Exitosa desde ASN Diferente — {$e( $site_name )}"
            : "✅ {$t_tag}Conexión Exitosa al Panel — {$e( $site_name )}";

        $rich = "<h2>{$header}</h2>";
        if ( $asn_check['changed'] ) {
            $rich .= "<p><b>⚠️ ATENCIÓN — CAMBIO DE OPERADOR / ASN DETECTADO:</b><br>{$e( $asn_check['summary'] )}</p>";
        } else {
            $rich .= "<p>Inicio de sesión verificado desde la red habitual.</p>";
        }

        $rich .= "<table><tr><th>Parámetro</th><th>Detalle</th></tr>";
        $rich .= "<tr><td><b>Usuario</b></td><td><b>{$e( $user->user_login )}</b> ({$e( $user->user_email )})</td></tr>";
        $rich .= "<tr><td><b>IP / País</b></td><td><code>{$e( $ip )}</code> (<b>{$e( $country )}</b>)</td></tr>";
        $rich .= "<tr><td><b>ASN Actual</b></td><td><b>{$e( $asn )}</b></td></tr>";
        $rich .= "<tr><td><b>ASN Última Sesión</b></td><td>{$e( $asn_check['last_asn'] )}</td></tr>";
        $rich .= "<tr><td><b>ASNs Sesiones Activas</b></td><td>{$e( $asn_check['active_asns'] )}</td></tr>";
        $rich .= "<tr><td><b>Fecha / Hora</b></td><td><code>{$e( $time )}</code></td></tr>";
        $rich .= "</table>";
        $rich .= "<blockquote expandable><b>Navegador / User-Agent:</b>\n<code>{$e( $ua ?: 'No registrado' )}</code></blockquote>";

        $fb  = "<b>{$header}</b>\n\n";
        if ( $asn_check['changed'] ) {
            $fb .= "⚠️ <b>{$e( $asn_check['summary'] )}</b>\n\n";
        }
        $fb .= "• <b>Usuario:</b> {$e( $user->user_login )}\n";
        $fb .= "• <b>IP:</b> <code>{$e( $ip )}</code> ({$e( $country )})\n";
        $fb .= "• <b>ASN Actual:</b> <b>{$e( $asn )}</b>\n";
        $fb .= "• <b>ASN Anterior:</b> {$e( $asn_check['last_asn'] )}\n";
        $fb .= "<blockquote expandable><b>Sesiones activas:</b> {$e( $asn_check['active_asns'] )}\n<b>UA:</b> {$e( $ua ?: 'N/D' )}</blockquote>";

        $tg_res = bap_send_telegram_rich_article( $rich, $fb );
        if ( is_wp_error( $tg_res ) ) {
            $ok = false;
        }
    }

    return $ok;
}

add_action( 'wp_login', function( $user_login, $user ) {
    if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
        return;
    }
    if ( ! ( $user instanceof WP_User ) ) {
        return;
    }
    if ( function_exists( 'bap_2fa_user_requires_2fa' ) && bap_2fa_user_requires_2fa( $user ) && ! bap_2fa_is_trusted_device( $user->ID ) ) {
        return;
    }
    bap_send_successful_login_alert( $user, false );
}, 5, 2 );
