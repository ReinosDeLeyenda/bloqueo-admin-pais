<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$raw_emails = (string) get_option( 'bap_notify_emails', '' );
$recipients = [];

if ( '' !== trim( $raw_emails ) ) {
    foreach ( preg_split( '/[\r\n,;]+/', $raw_emails ) as $email ) {
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
    $source = (string) get_option( 'bap_ip_source', 'remote_addr' );
    $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
    $ip     = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : 'Desconocida';

    if ( 'cloudflare' === $source && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $cf_ip = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) );
        if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
            $ip = $cf_ip;
        }
    } elseif ( 'x_forwarded_for' === $source && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $forwarded = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
        $fwd_ip    = trim( reset( $forwarded ) );
        if ( filter_var( $fwd_ip, FILTER_VALIDATE_IP ) ) {
            $ip = $fwd_ip;
        }
    } elseif ( 'x_real_ip' === $source && ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
        $real_ip = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) );
        if ( filter_var( $real_ip, FILTER_VALIDATE_IP ) ) {
            $ip = $real_ip;
        }
    }

    $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
    $site_url  = home_url();
    $user      = wp_get_current_user();
    $user_info = ( $user && $user->exists() ) ? $user->user_login . ' (' . $user->user_email . ')' : 'Desconocido / WP-CLI';

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

global $wpdb;

// 1. Eliminar tabla local de registros
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}bap_blocked_logs`" );

// 2. Eliminar todas las opciones y transients del plugin
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE 'bap\_%' OR option_name LIKE '\_transient\_bap\_%' OR option_name LIKE '\_transient\_timeout\_bap\_%'" );

// 3. Eliminar metadatos de usuario (2FA, dispositivos de confianza, avisos HIBP)
$wpdb->query( "DELETE FROM `{$wpdb->usermeta}` WHERE meta_key LIKE '\_bap\_%'" );

// 4. Eliminar carpeta de bases de datos locales en uploads/bap-geo
$upload  = wp_upload_dir();
$geo_dir = trailingslashit( $upload['basedir'] ) . 'bap-geo';
if ( is_dir( $geo_dir ) ) {
    $files = glob( $geo_dir . '/{,.}*', GLOB_BRACE );
    if ( is_array( $files ) ) {
        foreach ( $files as $f ) {
            if ( is_file( $f ) ) {
                @unlink( $f );
            }
        }
    }
    @rmdir( $geo_dir );
}

$mu_loader = ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( WP_CONTENT_DIR . '/mu-plugins' ) ) . '/bap-emergency-loader.php';
if ( file_exists( $mu_loader ) ) { @unlink( $mu_loader ); }

$user_ini_file = ABSPATH . '.user.ini';
if ( file_exists( $user_ini_file ) && is_writable( $user_ini_file ) ) {
    $ini_raw   = (string) @file_get_contents( $user_ini_file );
    $ini_clean = preg_replace( '/; BEGIN Bloqueo Admin Early WAF.*?; END Bloqueo Admin Early WAF\s*/s', '', $ini_raw );
    if ( '' === trim( $ini_clean ) ) {
        @unlink( $user_ini_file );
    } else {
        @file_put_contents( $user_ini_file, $ini_clean, LOCK_EX );
    }
}