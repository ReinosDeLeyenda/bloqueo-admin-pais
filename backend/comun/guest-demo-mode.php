<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_guest_cookie_name() {
    return 'bap_guest_demo_' . substr( hash( 'sha256', AUTH_SALT ), 0, 10 );
}

function bap_guest_is_enabled() {
    $cfg = get_option( 'bap_guest_demo_cfg', [] );
    if ( ! is_array( $cfg ) || empty( $cfg['enabled'] ) || empty( $cfg['token'] ) ) { return false; }
    $exp = (int) ( $cfg['expires'] ?? 0 );
    if ( $exp > 0 && time() >= $exp ) { return false; }
    return true;
}

function bap_is_guest_demo_session() {
    if ( is_user_logged_in() || ! bap_guest_is_enabled() ) { return false; }
    $cfg   = get_option( 'bap_guest_demo_cfg', [] );
    $cname = bap_guest_cookie_name();
    if ( empty( $_COOKIE[ $cname ] ) ) { return false; }
    $expected = hash_hmac( 'sha256', (string) $cfg['token'], AUTH_SALT );
    return hash_equals( $expected, (string) $_COOKIE[ $cname ] );
}

// 1. Capturar enlace o escaneo de QR (?bap_guest_token=...) y abrir sesion invitado
add_action( 'init', function() {
    if ( ! isset( $_GET['bap_guest_token'] ) || ! bap_guest_is_enabled() ) { return; }
    $cfg   = get_option( 'bap_guest_demo_cfg', [] );
    $given = sanitize_text_field( wp_unslash( $_GET['bap_guest_token'] ) );
    if ( '' !== $given && hash_equals( (string) $cfg['token'], $given ) ) {
        $cname = bap_guest_cookie_name();
        $val   = hash_hmac( 'sha256', (string) $cfg['token'], AUTH_SALT );
        $exp   = ( (int) ( $cfg['expires'] ?? 0 ) > 0 ) ? (int) $cfg['expires'] : ( time() + 6 * HOUR_IN_SECONDS );
        if ( ! headers_sent() ) {
            setcookie( $cname, $val, $exp, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
        $_COOKIE[ $cname ] = $val;
        wp_safe_redirect( admin_url( 'admin.php?page=bap-admin-estadisticas' ) );
        exit;
    }
}, 0 );

// 2. Permitir acceso exclusivamente a las pantallas del plugin en modo solo lectura
add_filter( 'user_has_cap', function( $allcaps, $caps ) {
    if ( ! bap_is_guest_demo_session() ) { return $allcaps; }
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $allowed_pages = [ 'bap-admin-db', 'bap-bloqueos', 'bap-auditoria', 'bap-plantillas', 'bap-notificaciones', 'bap-admin-estadisticas' ];
    if ( is_admin() && in_array( $page, $allowed_pages, true ) && in_array( 'manage_options', $caps, true ) ) {
        $allcaps['manage_options'] = true;
        $allcaps['read']           = true;
    }
    return $allcaps;
}, 10, 2 );

// 3. Bloquear cualquier modificacion POST o descarga en modo invitado
add_action( 'admin_init', function() {
    if ( ! bap_is_guest_demo_session() ) { return; }
    if ( isset( $_GET['bap_exit_guest'] ) ) {
        if ( ! headers_sent() ) { setcookie( bap_guest_cookie_name(), '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true ); }
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $allowed_pages = [ 'bap-admin-db', 'bap-bloqueos', 'bap-auditoria', 'bap-plantillas', 'bap-notificaciones', 'bap-admin-estadisticas' ];
    if ( ! wp_doing_ajax() && ! in_array( $page, $allowed_pages, true ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=bap-admin-estadisticas' ) );
        exit;
    }
    if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        wp_die( '<h2>Modo Invitado (Solo Lectura)</h2><p>Esta accion esta deshabilitada en la demostracion publica.</p><p><a href="javascript:history.back()">&larr; Volver</a></p>', 'Modo Invitado', [ 'response' => 403 ] );
    }
}, 0 );

// 4. Ocultar todas las claves, tokens, IPs privadas y datos confidenciales en pantalla
foreach ( [
    'bap_secret_key', 'bap_abuseipdb_key', 'bap_tg_bot_token', 'bap_tg_chat_ids',
    'bap_notify_emails', 'bap_webhook_url', 'bap_webhook_secret', 'bap_custom_login_slug',
    'bap_captcha_site_key', 'bap_captcha_secret_key', 'bap_ext_db_host', 'bap_ext_db_user', 'bap_ext_db_pass',
] as $bap_secret_opt ) {
    add_filter( 'option_' . $bap_secret_opt, function( $val ) {
        if ( ! bap_is_guest_demo_session() || '' === (string) $val ) { return $val; }
        return '•••••••••••• (Oculto en Modo Invitado)';
    }, 999 );
}

foreach ( [ 'bap_whitelist_ips', 'bap_blacklist_ips' ] as $bap_list_opt ) {
    add_filter( 'option_' . $bap_list_opt, function( $val ) {
        if ( ! bap_is_guest_demo_session() || '' === trim( (string) $val ) ) { return $val; }
        return "# Listado oculto por privacidad en Modo Invitado\n203.0.113.10\n198.51.100.0/24";
    }, 999 );
}

add_filter( 'option_bap_login_notify_routes', function( $val ) {
    if ( ! bap_is_guest_demo_session() ) { return $val; }
    return [ [ 'user_id' => 0, 'target' => '•••••• (Oculto)', 'type' => 'telegram' ] ];
}, 999 );

add_action( 'admin_notices', function() {
    if ( ! bap_is_guest_demo_session() ) { return; }
    $exit_url = esc_url( add_query_arg( 'bap_exit_guest', '1' ) );
    echo '<div class="notice notice-info" style="border-left-width:6px;border-left-color:#2271b1;"><p><strong>MODO INVITADO (DEMO SOLO LECTURA):</strong> Puedes explorar todas las pestanas del plugin. Las claves, tokens, IPs reales y cambios estan ocultos o bloqueados. <a href="' . $exit_url . '" class="button button-small" style="margin-left:10px;">Salir de la demo</a></p></div>';
}, 1 );

function bap_render_guest_demo_card() {
    if ( ! current_user_can( 'manage_options' ) || bap_is_guest_demo_session() ) { return; }
    $notice = '';
    if ( isset( $_POST['bap_save_guest_demo'] ) && check_admin_referer( 'bap_guest_demo_action', 'bap_guest_demo_nonce' ) ) {
        $enable = ! empty( $_POST['bap_guest_enabled'] );
        $hours  = (int) ( $_POST['bap_guest_hours'] ?? 24 );
        $cur    = get_option( 'bap_guest_demo_cfg', [] );
        $token  = ( ! empty( $_POST['bap_guest_regen'] ) || empty( $cur['token'] ) ) ? bin2hex( random_bytes( 14 ) ) : (string) $cur['token'];
        $exp    = ( $hours > 0 ) ? ( time() + $hours * HOUR_IN_SECONDS ) : 0;
        update_option( 'bap_guest_demo_cfg', [ 'enabled' => $enable ? 1 : 0, 'token' => $token, 'expires' => $exp ], false );
        $notice = '<div class="notice notice-success inline"><p><strong>Configuracion de Modo Invitado guardada.</strong></p></div>';
    }

    $cfg    = get_option( 'bap_guest_demo_cfg', [] );
    $active = bap_guest_is_enabled();
    $token  = (string) ( $cfg['token'] ?? '' );
    $exp    = (int) ( $cfg['expires'] ?? 0 );
    $qr_url = ( $active && '' !== $token ) ? add_query_arg( 'bap_guest_token', $token, home_url( '/' ) ) : '';

    if ( '' !== $qr_url ) {
        wp_enqueue_script( 'bap-local-qr', plugins_url( 'backend/adminuser/bap-qr.js', dirname( __DIR__, 2 ) . '/bloqueo-admin-pais.php' ), [], '1.0.4', true );
    }

    echo '<div class="card" style="max-width:960px;padding:18px 24px;margin-top:20px;">';
    echo '<h2 style="margin-top:0;">Modo Invitado / Demo (Acceso por QR en Solo Lectura)</h2>';
    echo '<p class="description">Permite compartir acceso de demostracion a todas las secciones del plugin mediante enlace o codigo QR generado en local. Oculta claves, tokens, IPs privadas y bloquea cualquier cambio.</p>';
    echo wp_kses_post( $notice );
    echo '<form method="post" style="margin-top:12px;">';
    wp_nonce_field( 'bap_guest_demo_action', 'bap_guest_demo_nonce' );
    echo '<label style="display:block;margin-bottom:10px;"><input type="checkbox" name="bap_guest_enabled" value="1"' . checked( $active, true, false ) . ' /> <strong>Activar Modo Invitado (Demo publica)</strong></label>';
    echo '<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">';
    echo '<label>Duracion del enlace: <select name="bap_guest_hours"><option value="1">1 hora</option><option value="6">6 horas</option><option value="24" selected>24 horas</option><option value="168">7 dias</option><option value="0">Sin caducidad</option></select></label>';
    echo '<label><input type="checkbox" name="bap_guest_regen" value="1" /> Regenerar nuevo token QR</label></div>';
    echo '<button type="submit" name="bap_save_guest_demo" class="button button-primary">Guardar Modo Invitado</button></form>';

    if ( '' !== $qr_url ) {
        echo '<div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin-top:16px;padding:14px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;">';
        echo '<canvas class="bap-local-qr" data-otp-uri="' . esc_attr( $qr_url ) . '" style="border:4px solid #fff;border-radius:4px;background:#fff;"></canvas>';
        echo '<div style="flex:1;min-width:240px;">';
        echo '<p style="margin:0 0 6px;"><strong>Enlace directo de invitado:</strong></p>';
        echo '<input type="text" readonly value="' . esc_attr( $qr_url ) . '" class="large-text code" onclick="this.select();" style="margin-bottom:8px;" />';
        if ( $exp > 0 ) {
            echo '<p class="description" style="margin:0;">Caduca el: <code>' . esc_html( wp_date( 'Y-m-d H:i:s', $exp ) ) . '</code></p>';
        }
        echo '</div></div>';
    }
    echo '</div>';
}