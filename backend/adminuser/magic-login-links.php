<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_magic_link_is_enabled() {
    return ( '1' === (string) get_option( 'bap_magic_login_enabled', '0' ) );
}

function bap_send_user_magic_link( WP_User $user ) {
    $token = bin2hex( random_bytes( 24 ) );
    $ip    = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';

    set_transient( 'bap_magic_' . $token, [
        'user_id' => (int) $user->ID,
        'ip'      => $ip,
    ], 10 * MINUTE_IN_SECONDS );

    $magic_url = add_query_arg( [
        'action'    => 'bap_magic_auth',
        'bap_magic' => $token,
    ], wp_login_url() );

    $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'N/D';
    $sent_any = false;

    // 1. Enviar por Telegram si está configurado
    $tg_token = trim( (string) get_option( 'bap_tg_bot_token', '' ) );
    if ( '' !== $tg_token && function_exists( 'bap_send_telegram_rich_article' ) ) {
        $custom_chats = function_exists( 'bap_get_login_routes_for_user' ) ? bap_get_login_routes_for_user( $user->ID, 'telegram' ) : [];
        $target_chats = ! empty( $custom_chats ) ? implode( ', ', $custom_chats ) : trim( (string) get_option( 'bap_tg_chat_ids', '' ) );
        if ( '' !== $target_chats ) {
            $e    = fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
            $rich = "<h2>🔗 Enlace Mágico de Acceso — {$e( $site )}</h2>"
                  . "<p>Hola <b>{$e( $user->user_login )}</b>, usa este enlace de un solo uso (válido 10 min) para entrar sin contraseña:</p>"
                  . "<p><a href=\"{$e( $magic_url )}\">{$e( $magic_url )}</a></p>"
                  . "<blockquote expandable><b>IP solicitante:</b> <code>{$e( $ip )}</code> ({$e( $country )})</blockquote>";
            $fb   = "🔗 Enlace Mágico — {$site}\nUsuario: {$user->user_login}\nIP: {$ip} ({$country})\nVálido 10 min:\n{$magic_url}";
            $tg_r = bap_send_telegram_rich_article( $rich, $fb, $target_chats );
            if ( true === $tg_r ) {
                $sent_any = true;
            }
        }
    }

    // 2. Enviar por Email al correo del usuario
    $subject = "[{$site}] Tu enlace mágico de acceso (válido 10 minutos)";
    $body    = "Hola {$user->display_name},\n\n"
             . "Has solicitado un enlace de acceso sin contraseña en {$site}.\n"
             . "Haz clic en el siguiente enlace (un solo uso, caduca en 10 minutos):\n\n"
             . "{$magic_url}\n\n"
             . "IP solicitante: {$ip} ({$country})\n"
             . "Si no lo has solicitado tú, ignora este mensaje.\n";

    $mail_ok = function_exists( 'bap_send_mail' )
        ? bap_send_mail( $user->user_email, $subject, $body )
        : wp_mail( $user->user_email, $subject, $body );

    return ( $sent_any || $mail_ok );
}

// Botón en el formulario de login
add_action( 'login_form', function() {
    if ( ! bap_magic_link_is_enabled() ) {
        return;
    }
    $url = add_query_arg( 'action', 'bap_magic_req', wp_login_url() );
    echo '<p style="margin:10px 0 14px 0;text-align:center;">'
       . '<a href="' . esc_url( $url ) . '" class="button button-secondary" style="width:100%;text-align:center;">🔗 Entrar con Enlace Mágico (sin contraseña)</a>'
       . '</p>';
} );

// Formulario de solicitud del enlace mágico (?action=bap_magic_req)
add_action( 'login_form_bap_magic_req', function() {
    if ( ! bap_magic_link_is_enabled() ) {
        wp_safe_redirect( wp_login_url() );
        exit;
    }

    $msg = '';
    $err = '';

    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['bap_magic_user'] ) ) {
        if ( function_exists( 'bap_captcha_verify_submission' ) ) {
            $cap = bap_captcha_verify_submission( 'login' );
            if ( is_wp_error( $cap ) ) {
                $err = $cap->get_error_message();
            }
        }

        if ( '' === $err ) {
            $raw  = trim( sanitize_text_field( wp_unslash( $_POST['bap_magic_user'] ) ) );
            $user = is_email( $raw ) ? get_user_by( 'email', $raw ) : get_user_by( 'login', $raw );

            if ( $user instanceof WP_User ) {
                if ( function_exists( 'bap_is_user_on_vacation' ) && bap_is_user_on_vacation( $user->ID ) && ! bap_vacation_has_secret_bypass( $user->ID ) ) {
                    $err = 'Esta cuenta se encuentra en Modo Vacaciones.';
                } else {
                    bap_send_user_magic_link( $user );
                }
            } elseif ( '1' === (string) get_option( 'bap_block_nonexistent_users', '1' ) && function_exists( 'bap_deny_access' ) ) {
                $ip      = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
                $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
                bap_deny_access( $ip, $country, 'Magic Link: Usuario inexistente' );
            }

            if ( '' === $err ) {
                $msg = 'Si la cuenta existe, se ha enviado un enlace mágico de un solo uso a tu Email y Telegram.';
            }
        }
    }

    login_header( 'Acceso con Enlace Mágico', '', '' );
    if ( '' !== $err ) {
        echo '<div id="login_error">' . wp_kses_post( $err ) . '</div>';
    }
    if ( '' !== $msg ) {
        echo '<p class="message">' . esc_html( $msg ) . '</p>';
    }
    echo '<form method="post" action="' . esc_url( add_query_arg( 'action', 'bap_magic_req', wp_login_url() ) ) . '">';
    echo '<p><label for="bap_magic_user">Nombre de usuario o correo electrónico</label>';
    echo '<input type="text" name="bap_magic_user" id="bap_magic_user" class="input" required autofocus /></p>';
    if ( function_exists( 'bap_captcha_render_widget' ) ) {
        bap_captcha_render_widget( 'login' );
    }
    echo '<p class="submit"><input type="submit" class="button button-primary button-large" value="Enviar enlace mágico" /></p>';
    echo '</form>';
    echo '<p id="nav"><a href="' . esc_url( wp_login_url() ) . '">&larr; Volver al acceso con contraseña</a></p>';
    login_footer();
    exit;
} );

// Validar enlace mágico (?action=bap_magic_auth&bap_magic=TOKEN)
add_action( 'login_form_bap_magic_auth', function() {
    if ( ! bap_magic_link_is_enabled() ) {
        wp_safe_redirect( wp_login_url() );
        exit;
    }

    $token = isset( $_GET['bap_magic'] ) ? sanitize_key( wp_unslash( $_GET['bap_magic'] ) ) : '';
    $data  = ( '' !== $token ) ? get_transient( 'bap_magic_' . $token ) : false;
    if ( '' !== $token ) {
        delete_transient( 'bap_magic_' . $token );
    }

    if ( ! is_array( $data ) || empty( $data['user_id'] ) ) {
        wp_die( '<strong>Enlace mágico inválido o caducado.</strong> Solicita uno nuevo desde la pantalla de acceso.', 'Enlace Caducado', [ 'response' => 403 ] );
    }

    $user = get_user_by( 'id', (int) $data['user_id'] );
    if ( ! ( $user instanceof WP_User ) ) {
        wp_safe_redirect( wp_login_url() );
        exit;
    }

    if ( function_exists( 'bap_is_user_on_vacation' ) && bap_is_user_on_vacation( $user->ID ) && ! bap_vacation_has_secret_bypass( $user->ID ) ) {
        wp_die( '<strong>Cuenta en Modo Vacaciones.</strong> El acceso está suspendido temporalmente.', 'Acceso Suspendido', [ 'response' => 403 ] );
    }

    wp_set_current_user( $user->ID, $user->user_login );
    wp_set_auth_cookie( $user->ID, false );
    do_action( 'wp_login', $user->user_login, $user );

    wp_safe_redirect( admin_url() );
    exit;
} );