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

function bap_get_login_routes_for_user( $user_id, $type ) {
    $routes  = get_option( 'bap_login_notify_routes', [] );
    $targets = [];

    if ( is_array( $routes ) ) {
        foreach ( $routes as $r ) {
            $r_uid  = (int) ( $r['user_id'] ?? 0 );
            $r_type = (string) ( $r['type'] ?? '' );
            $r_tgt  = trim( (string) ( $r['target'] ?? '' ) );

            if ( '' !== $r_tgt && $r_type === $type && ( 0 === $r_uid || $r_uid === (int) $user_id ) ) {
                $targets[] = $r_tgt;
            }
        }
    }

    return array_values( array_unique( $targets ) );
}

function bap_send_successful_login_alert( WP_User $user, $is_test = false, $single_route = null ) {
    if ( is_array( $single_route ) ) {
        $channel = ( 'email' === ( $single_route['type'] ?? '' ) ) ? 'email' : 'telegram';
    } else {
        $channel = (string) get_option( 'bap_login_notify_channel', 'none' );
        if ( ! $is_test && ( 'none' === $channel || '' === $channel ) ) {
            return false;
        }
        if ( $is_test && ( 'none' === $channel || '' === $channel ) ) {
            $channel = 'both';
        }
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

    if ( in_array( $channel, [ 'email', 'both' ], true ) ) {
        if ( is_array( $single_route ) && 'email' === ( $single_route['type'] ?? '' ) ) {
            $clean_mail    = sanitize_email( (string) ( $single_route['target'] ?? '' ) );
            $custom_emails = is_email( $clean_mail ) ? [ $clean_mail ] : [];
        } else {
            $custom_emails = bap_get_login_routes_for_user( $user->ID, 'email' );
        }
        $recipients = ! empty( $custom_emails )
            ? $custom_emails
            : ( function_exists( 'bap_get_notify_recipients' ) ? bap_get_notify_recipients() : [ get_option( 'admin_email' ) ] );

        $prefix  = $asn_check['changed'] ? '[Aviso ASN] ' : '';
        $t_badge = $is_test ? '(Verificación) ' : '';
        $subject = "{$prefix}{$t_badge}[{$site_name}] Inicio de sesión en el panel: {$user->user_login}";

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

        $sent = function_exists( 'bap_send_mail' ) ? bap_send_mail( $recipients, $subject, $body ) : wp_mail( $recipients, $subject, $body );
        if ( ! $sent ) {
            $ok = false;
        }
    }

    if ( in_array( $channel, [ 'telegram', 'both' ], true ) && function_exists( 'bap_send_telegram_rich_article' ) ) {
        $e           = fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        $t_tag       = $is_test ? '🧪 <b>PRUEBA</b> · ' : '';
        $status_icon = $asn_check['changed'] ? '⚠️' : '✅';
        $asn_alert   = $asn_check['changed']
            ? '⚠️ ATENCIÓN — CAMBIO DE OPERADOR / ASN DETECTADO: ' . $e( $asn_check['summary'] )
            : 'Inicio de sesión verificado desde la red habitual.';

        if ( function_exists( 'bap_render_custom_tg_template' ) ) {
            $rendered = bap_render_custom_tg_template( 'login', [
                '{status_icon}' => $status_icon,
                '{badge}'       => $t_tag,
                '{site_name}'   => $e( $site_name ),
                '{asn_alert}'   => $asn_alert,
                '{user_login}'  => $e( $user->user_login ),
                '{user_email}'  => $e( $user->user_email ),
                '{ip}'          => $e( $ip ),
                '{country}'     => $e( $country ),
                '{asn}'         => $e( $asn ),
                '{last_asn}'    => $e( $asn_check['last_asn'] ),
                '{active_asns}' => $e( $asn_check['active_asns'] ),
                '{time}'        => $e( $time ),
                '{user_agent}'  => $e( $ua ?: 'No registrado' ),
            ] );
            $rich = $rendered['rich'];
            $fb   = $rendered['fallback'];
        } else {
            $rich = "<h2>{$status_icon} {$t_tag}Conexión Exitosa — {$e( $site_name )}</h2><p>{$asn_alert}</p>";
            $fb   = strip_tags( $rich );
        }

        if ( is_array( $single_route ) && 'telegram' === ( $single_route['type'] ?? '' ) ) {
            $clean_chat   = preg_replace( '/[^0-9\-]/', '', (string) ( $single_route['target'] ?? '' ) );
            $custom_chats = '' !== $clean_chat ? [ $clean_chat ] : [];
        } else {
            $custom_chats = bap_get_login_routes_for_user( $user->ID, 'telegram' );
        }
        $tg_res = bap_send_telegram_rich_article( $rich, $fb, implode( ', ', $custom_chats ) );
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
}, 20, 2 );
