<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$raw_emails = (string) get_option( 'bap_notify_emails', '' );
$recipients = [];

if ( '' !== trim( $raw_emails ) ) {
    foreach ( preg_split( '/[\r\n,]+/', $raw_emails ) as $email ) {
        $clean = sanitize_email( trim( $email ) );
        if ( is_email( $clean ) ) {
            $recipients[] = $clean;
        }
    }
}

if ( empty( $recipients ) ) {
    $admin_email = sanitize_email( (string) get_option( 'admin_email', '' ) );
    if ( is_email( $admin_email ) ) {
        $recipients[] = $admin_email;
    }
}

if ( ! empty( $recipients ) ) {
    $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
    $site_url  = home_url();
    $user      = wp_get_current_user();
    $user_info = ( $user && $user->exists() ) ? $user->user_login . ' (' . $user->user_email . ')' : 'Desconocido / WP-CLI';
    $ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'Desconocida';

    $subject = sprintf( '[%s] Alerta de seguridad: Plugin Bloqueo Admin desinstalado', $site_name );
    $body    = "Hola,\n\n"
             . "Te informamos de que el plugin \"Bloqueo Admin por País e IP\" acaba de ser DESINSTALADO y eliminado de tu sitio WordPress.\n\n"
             . "Detalles del evento:\n"
             . "- Sitio web: {$site_name} ({$site_url})\n"
             . "- Fecha y hora: " . current_time( 'Y-m-d H:i:s' ) . "\n"
             . "- Usuario responsable: {$user_info}\n"
             . "- Dirección IP: {$ip}\n\n"
             . "Si no has realizado esta acción tú mismo, revisa inmediatamente los accesos de administrador de tu WordPress.\n";

    wp_mail( array_unique( $recipients ), $subject, $body );
}
