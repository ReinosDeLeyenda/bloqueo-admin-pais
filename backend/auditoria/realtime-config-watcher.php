<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_dispatch_critical_file_tamper_alert( array $changed_labels ) {
    $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $user    = wp_get_current_user();
    $actor   = ( $user && $user->exists() ) ? $user->user_login . ' (' . $user->user_email . ')' : 'Proceso externo / SSH / FTP / Cron';
    $ip      = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : 'N/D';
    $time    = current_time( 'mysql' );
    $files_s = implode( ', ', $changed_labels );

    $subject = "[{$site}] ALERTA CRÍTICA: Archivo alterado ({$files_s})";
    $body    = "Se ha detectado una modificación no aprobada en archivos críticos de {$site}:\n\n"
             . "• Archivo(s) alterado(s): {$files_s}\n"
             . "• Sesión activa detectada: {$actor}\n"
             . "• Dirección IP: {$ip}\n"
             . "• Fecha / Hora: {$time}\n\n"
             . "Revisa el contenido de estos archivos y aprueba la nueva huella SHA-256 en:\n"
             . admin_url( 'admin.php?page=bap-auditoria&subtab=hardening' ) . "\n";

        $e    = fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    $rich = "<h2>Alerta Critica: Archivo Alterado &mdash; {$e( $site )}</h2><p><b>Archivos:</b> <code>{$e( $files_s )}</code></p><p><b>Usuario:</b> {$e( $actor )} | <b>IP:</b> <code>{$e( $ip )}</code> | <b>Hora:</b> <code>{$e( $time )}</code></p>";

    bap_send_security_alert_both_channels( $subject, $body, $rich );
}

add_action( 'admin_init', function() {
    if ( wp_doing_ajax() || ! function_exists( 'bap_verify_critical_config_hashes' ) ) {
        return;
    }
    if ( false !== get_transient( 'bap_rt_cfg_watch_tick' ) ) {
        return;
    }
    set_transient( 'bap_rt_cfg_watch_tick', '1', 60 );

    $changed = [];
    foreach ( bap_verify_critical_config_hashes() as $row ) {
        if ( ! empty( $row['changed'] ) ) {
            $changed[] = $row['label'];
        }
    }

    if ( empty( $changed ) ) {
        delete_option( 'bap_rt_cfg_alerted_sig' );
        return;
    }

    $sig = md5( implode( '|', $changed ) );
    if ( get_option( 'bap_rt_cfg_alerted_sig', '' ) !== $sig ) {
        update_option( 'bap_rt_cfg_alerted_sig', $sig, false );
        bap_dispatch_critical_file_tamper_alert( $changed );
    }
}, 20 );

add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'bap_verify_critical_config_hashes' ) ) {
        return;
    }
    $changed = [];
    foreach ( bap_verify_critical_config_hashes() as $row ) {
        if ( ! empty( $row['changed'] ) ) {
            $changed[] = $row['label'];
        }
    }
    if ( ! empty( $changed ) ) {
        $url = esc_url( admin_url( 'admin.php?page=bap-auditoria&subtab=hardening' ) );
        echo '<div class="notice notice-error"><p>'
           . '<strong>Alerta de Integridad (SHA-256):</strong> Se han detectado cambios no aprobados en <code>'
           . esc_html( implode( ', ', $changed ) )
           . '</code>. <a href="' . $url . '">Revisar y aprobar huella aquí</a>.'
           . '</p></div>';
    }
} );