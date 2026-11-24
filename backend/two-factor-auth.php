<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_login', function( $user_login, $user ) {
    if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
        return;
    }
    if ( ! bap_2fa_user_requires_2fa( $user ) || bap_2fa_is_trusted_device( $user->ID ) ) {
        return;
    }

    wp_clear_auth_cookie();

    $method = get_user_meta( $user->ID, '_bap_2fa_method', true );
    if ( ! in_array( $method, [ 'totp', 'email' ], true ) ) {
        $method = 'email';
    }

    $token = bin2hex( random_bytes( 24 ) );
    set_transient( 'bap_2fa_login_' . $token, [
        'user_id'     => $user->ID,
        'method'      => $method,
        'remember'    => ! empty( $_POST['rememberme'] ),
        'redirect_to' => isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : admin_url(),
        'attempts'    => 0,
        'last_sent'   => time(),
    ], 600 );

    if ( 'email' === $method ) {
        bap_2fa_send_email_code( $user );
    }

    wp_safe_redirect( add_query_arg( [ 'action' => 'bap_2fa', 'bap_token' => $token ], wp_login_url() ) );
    exit;
}, 10, 2 );

add_action( 'login_form_bap_2fa', function() {
    $token = isset( $_REQUEST['bap_token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bap_token'] ) ) : '';
    $state = ( '' !== $token ) ? get_transient( 'bap_2fa_login_' . $token ) : false;

    if ( ! is_array( $state ) || empty( $state['user_id'] ) ) {
        wp_safe_redirect( wp_login_url() );
        exit;
    }

    $user = get_user_by( 'id', (int) $state['user_id'] );
    if ( ! $user ) {
        wp_safe_redirect( wp_login_url() );
        exit;
    }

    $error = '';
    $info  = '';

    if ( isset( $_GET['resend'] ) && 'email' === $state['method'] ) {
        $elapsed = time() - (int) ( $state['last_sent'] ?? 0 );
        if ( $elapsed < 30 ) {
            $error = 'Espera ' . ( 30 - $elapsed ) . ' segundos antes de solicitar un nuevo código por email.';
        } else {
            bap_2fa_send_email_code( $user );
            $state['last_sent'] = time();
            set_transient( 'bap_2fa_login_' . $token, $state, 600 );
            $info = 'Se ha enviado un nuevo código de 6 dígitos a tu correo electrónico.';
        }
    }

    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['bap_2fa_code'] ) ) {
        $code  = sanitize_text_field( wp_unslash( $_POST['bap_2fa_code'] ) );
        $valid = false;

        if ( 'totp' === $state['method'] ) {
            $secret = (string) get_user_meta( $user->ID, '_bap_2fa_totp_secret', true );
            $valid  = bap_2fa_verify_totp( $secret, $code );
        } elseif ( 'email' === $state['method'] ) {
            $mail_data = get_transient( 'bap_2fa_mail_' . $user->ID );
            if ( is_array( $mail_data ) && time() <= (int) $mail_data['expires'] && wp_check_password( preg_replace( '/\s+/', '', $code ), $mail_data['hash'] ) ) {
                $valid = true;
                delete_transient( 'bap_2fa_mail_' . $user->ID );
            }
        }

        if ( ! $valid ) {
            $valid = bap_2fa_verify_backup_code( $user->ID, $code );
        }

        if ( $valid ) {
            delete_transient( 'bap_2fa_login_' . $token );
            if ( ! empty( $_POST['bap_2fa_trust'] ) && '1' === (string) get_option( 'bap_2fa_remember_device', '1' ) ) {
                bap_2fa_trust_current_device( $user->ID );
            }
            wp_set_auth_cookie( $user->ID, ! empty( $state['remember'] ) );
            wp_safe_redirect( $state['redirect_to'] );
            exit;
        }

        $state['attempts'] = (int) $state['attempts'] + 1;
        if ( $state['attempts'] >= 5 ) {
            delete_transient( 'bap_2fa_login_' . $token );
            wp_safe_redirect( add_query_arg( 'bap_2fa_locked', '1', wp_login_url() ) );
            exit;
        }
        set_transient( 'bap_2fa_login_' . $token, $state, 600 );
        $error = 'Código incorrecto o caducado. Intento ' . $state['attempts'] . ' de 5.';
    }

    login_header( 'Verificación en dos pasos', '', '' );
    if ( '' !== $error ) {
        echo '<div id="login_error">' . esc_html( $error ) . '</div>';
    }
    if ( '' !== $info ) {
        echo '<p class="message">' . esc_html( $info ) . '</p>';
    }
    echo '<form method="post" action="' . esc_url( add_query_arg( [ 'action' => 'bap_2fa', 'bap_token' => $token ], wp_login_url() ) ) . '">';
    echo '<p style="margin-bottom:14px;">';
    echo ( 'totp' === $state['method'] )
        ? 'Introduce el código de <strong>6 dígitos</strong> de tu aplicación autenticadora o un <strong>código de respaldo</strong>.'
        : 'Hemos enviado un código de <strong>6 dígitos</strong> a tu email registrado (o usa un <strong>código de respaldo</strong>).';
    echo '</p>';
    echo '<p><label for="bap_2fa_code">Código de verificación</label>';
    echo '<input type="text" name="bap_2fa_code" id="bap_2fa_code" class="input" size="20" autocomplete="one-time-code" autofocus required style="text-align:center;letter-spacing:4px;font-size:20px;" /></p>';
    if ( '1' === (string) get_option( 'bap_2fa_remember_device', '1' ) ) {
        echo '<p style="margin-bottom:14px;"><label><input type="checkbox" name="bap_2fa_trust" value="1" /> No volver a pedir en este dispositivo durante 30 días</label></p>';
    }
    echo '<p class="submit"><input type="submit" class="button button-primary button-large" value="Verificar acceso" /></p>';
    if ( 'email' === $state['method'] ) {
        echo '<p style="text-align:center;margin-top:12px;"><a href="' . esc_url( add_query_arg( [ 'action' => 'bap_2fa', 'bap_token' => $token, 'resend' => '1' ], wp_login_url() ) ) . '">Reenviar código por email</a></p>';
    }
    echo '</form>';
    login_footer();
    exit;
});
