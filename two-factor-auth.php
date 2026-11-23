<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_2fa_generate_secret( $length = 16 ) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = '';
    for ( $i = 0; $i < $length; $i++ ) {
        $secret .= $alphabet[ random_int( 0, 31 ) ];
    }
    return $secret;
}

function bap_2fa_base32_decode( $secret ) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = strtoupper( preg_replace( '/[^A-Z2-7]/', '', (string) $secret ) );
    $bits     = '';
    for ( $i = 0, $len = strlen( $secret ); $i < $len; $i++ ) {
        $val = strpos( $alphabet, $secret[ $i ] );
        if ( false !== $val ) {
            $bits .= str_pad( decbin( $val ), 5, '0', STR_PAD_LEFT );
        }
    }
    $binary = '';
    for ( $i = 0, $len = strlen( $bits ); $i + 8 <= $len; $i += 8 ) {
        $binary .= chr( bindec( substr( $bits, $i, 8 ) ) );
    }
    return $binary;
}

function bap_2fa_verify_totp( $secret, $code, $window = 1 ) {
    $code = preg_replace( '/\s+/', '', (string) $code );
    if ( ! preg_match( '/^\d{6}$/', $code ) ) {
        return false;
    }
    $key       = bap_2fa_base32_decode( $secret );
    $timestamp = floor( time() / 30 );

    for ( $i = -$window; $i <= $window; $i++ ) {
        $counter = pack( 'N*', 0 ) . pack( 'N*', $timestamp + $i );
        $hash    = hash_hmac( 'sha1', $counter, $key, true );
        $offset  = ord( substr( $hash, -1 ) ) & 0x0F;
        $value   = (
            ( ( ord( $hash[ $offset ] ) & 0x7F ) << 24 ) |
            ( ( ord( $hash[ $offset + 1 ] ) & 0xFF ) << 16 ) |
            ( ( ord( $hash[ $offset + 2 ] ) & 0xFF ) << 8 ) |
            ( ord( $hash[ $offset + 3 ] ) & 0xFF )
        ) % 1000000;

        if ( hash_equals( str_pad( (string) $value, 6, '0', STR_PAD_LEFT ), $code ) ) {
            return true;
        }
    }
    return false;
}

function bap_2fa_generate_backup_codes( $user_id ) {
    $plain  = [];
    $hashed = [];
    for ( $i = 0; $i < 8; $i++ ) {
        $code     = strtoupper( bin2hex( random_bytes( 4 ) ) );
        $plain[]  = substr( $code, 0, 4 ) . '-' . substr( $code, 4, 4 );
        $hashed[] = wp_hash_password( $code );
    }
    update_user_meta( $user_id, '_bap_2fa_backup_codes', $hashed );
    return $plain;
}

function bap_2fa_verify_backup_code( $user_id, $input ) {
    $clean  = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $input ) );
    $stored = get_user_meta( $user_id, '_bap_2fa_backup_codes', true );
    if ( '' === $clean || ! is_array( $stored ) ) {
        return false;
    }
    foreach ( $stored as $idx => $hash ) {
        if ( wp_check_password( $clean, $hash ) ) {
            unset( $stored[ $idx ] );
            update_user_meta( $user_id, '_bap_2fa_backup_codes', array_values( $stored ) );
            return true;
        }
    }
    return false;
}

function bap_2fa_send_email_code( $user ) {
    $code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
    set_transient( 'bap_2fa_mail_' . $user->ID, [
        'hash'    => wp_hash_password( $code ),
        'expires' => time() + 600,
    ], 600 );

    $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $subject = sprintf( '[%s] Tu código de verificación: %s', $site, $code );
    $body    = "Hola {$user->display_name},\n\n"
             . "Tu código de verificación en dos pasos es:\n\n"
             . "    {$code}\n\n"
             . "Caduca en 10 minutos.\n";

    wp_mail( $user->user_email, $subject, $body );
}

function bap_2fa_user_requires_2fa( $user ) {
    if ( '1' !== (string) get_option( 'bap_2fa_enabled', '0' ) ) {
        return false;
    }
    $method = get_user_meta( $user->ID, '_bap_2fa_method', true );
    if ( in_array( $method, [ 'totp', 'email' ], true ) ) {
        return true;
    }
    if ( '1' === (string) get_option( 'bap_2fa_enforce_admins', '0' ) && user_can( $user, 'manage_options' ) ) {
        return true;
    }
    return false;
}

function bap_2fa_is_trusted_device( $user_id ) {
    if ( '1' !== (string) get_option( 'bap_2fa_remember_device', '1' ) ) {
        return false;
    }
    $cookie_name = 'bap_2fa_trust_' . $user_id;
    if ( empty( $_COOKIE[ $cookie_name ] ) ) {
        return false;
    }
    $raw_cookie = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
    $tokens     = get_user_meta( $user_id, '_bap_2fa_trusted_devices', true );
    if ( ! is_array( $tokens ) ) {
        return false;
    }
    $now = time();
    foreach ( $tokens as $item ) {
        if ( ! empty( $item['hash'] ) && ! empty( $item['expires'] ) && $now < (int) $item['expires'] ) {
            if ( hash_equals( $item['hash'], hash( 'sha256', $raw_cookie . AUTH_SALT ) ) ) {
                return true;
            }
        }
    }
    return false;
}

function bap_2fa_trust_current_device( $user_id ) {
    $raw_token = bin2hex( random_bytes( 24 ) );
    $expires   = time() + ( 30 * DAY_IN_SECONDS );
    $tokens    = get_user_meta( $user_id, '_bap_2fa_trusted_devices', true );
    if ( ! is_array( $tokens ) ) {
        $tokens = [];
    }
    $now      = time();
    $tokens   = array_values( array_filter( $tokens, fn( $t ) => ! empty( $t['expires'] ) && $now < (int) $t['expires'] ) );
    $tokens[] = [
        'hash'    => hash( 'sha256', $raw_token . AUTH_SALT ),
        'expires' => $expires,
    ];
    if ( count( $tokens ) > 10 ) {
        $tokens = array_slice( $tokens, -10 );
    }
    update_user_meta( $user_id, '_bap_2fa_trusted_devices', $tokens );

    if ( ! headers_sent() ) {
        setcookie( 'bap_2fa_trust_' . $user_id, $raw_token, $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
    }
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
        bap_2fa_send_email_code( $user );
        $info = 'Se ha enviado un nuevo código de 6 dígitos a tu correo electrónico.';
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

function bap_2fa_render_profile_fields( $user ) {
    if ( '1' !== (string) get_option( 'bap_2fa_enabled', '0' ) ) {
        return;
    }
    wp_enqueue_script( 'bap-local-qr', plugin_dir_url( __FILE__ ) . 'bap-qr.js', [], '1.0.4', true );

    $method = get_user_meta( $user->ID, '_bap_2fa_method', true );
    if ( ! in_array( $method, [ 'totp', 'email' ], true ) ) {
        $method = 'off';
    }
    $secret = get_user_meta( $user->ID, '_bap_2fa_totp_secret', true );
    if ( '' === $secret ) {
        $secret = bap_2fa_generate_secret();
        update_user_meta( $user->ID, '_bap_2fa_totp_secret', $secret );
    }

    $issuer  = rawurlencode( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
    $account = rawurlencode( $user->user_email );
    $scheme  = 'otpauth' . '://totp/';
    $otp_uri = $scheme . $issuer . ':' . $account . '?secret=' . $secret . '&issuer=' . $issuer;

    $new_backups = get_transient( 'bap_2fa_new_backups_' . $user->ID );
    if ( is_array( $new_backups ) ) {
        delete_transient( 'bap_2fa_new_backups_' . $user->ID );
    }
    $stored_backups = get_user_meta( $user->ID, '_bap_2fa_backup_codes', true );
    $remaining      = is_array( $stored_backups ) ? count( $stored_backups ) : 0;

    $trusted     = get_user_meta( $user->ID, '_bap_2fa_trusted_devices', true );
    $trusted_cnt = is_array( $trusted ) ? count( array_filter( $trusted, fn( $t ) => ! empty( $t['expires'] ) && time() < (int) $t['expires'] ) ) : 0;

    echo '<h2>Autenticación en Dos Factores (2FA)</h2><table class="form-table">';
    echo '<tr><th>Método de verificación</th><td><select name="bap_2fa_user_method">';
    echo '<option value="off"' . selected( $method, 'off', false ) . '>Desactivado</option>';
    echo '<option value="totp"' . selected( $method, 'totp', false ) . '>App Autenticadora (Google Authenticator, Authy, Bitwarden...)</option>';
    echo '<option value="email"' . selected( $method, 'email', false ) . '>Código temporal por Email (' . esc_html( $user->user_email ) . ')</option>';
    echo '</select></td></tr>';

    echo '<tr><th>Configurar App Autenticadora (TOTP)</th><td>';
    echo '<canvas class="bap-local-qr" data-otp-uri="' . esc_attr( $otp_uri ) . '" style="border:4px solid #fff;border-radius:4px;margin-bottom:8px;display:block;background:#fff;"></canvas>';
    echo '<p><strong>Clave secreta manual:</strong> <code>' . esc_html( $secret ) . '</code></p>';
    echo '<p class="description">Escanea el QR (generado 100 % en local) con tu app y, si activas el método App Autenticadora, introduce abajo un código de 6 dígitos para confirmar antes de guardar.</p>';
    echo '<input type="text" name="bap_2fa_verify_setup" placeholder="Código de 6 dígitos para confirmar" class="regular-text" autocomplete="off" style="margin-top:6px;" />';
    echo '</td></tr>';

    echo '<tr><th>Códigos de respaldo (un solo uso)</th><td>';
    if ( is_array( $new_backups ) ) {
        echo '<div style="background:#1d2327;color:#72aee6;padding:12px 16px;border-radius:4px;max-width:340px;margin-bottom:8px;font-family:monospace;font-size:14px;">';
        echo '<strong style="color:#fff;display:block;margin-bottom:6px;">Guarda estos 8 códigos en un lugar seguro:</strong>';
        echo esc_html( implode( '   ', $new_backups ) );
        echo '</div>';
    } else {
        echo '<p>Códigos disponibles restantes: <strong>' . (int) $remaining . ' / 8</strong></p>';
    }
    echo '<label><input type="checkbox" name="bap_2fa_regen_backups" value="1" /> Generar 8 nuevos códigos de respaldo al guardar el perfil</label>';
    echo '</td></tr>';

    echo '<tr><th>Dispositivos de confianza</th><td>';
    echo '<p>Dispositivos recordados actualmente: <strong>' . (int) $trusted_cnt . '</strong></p>';
    if ( $trusted_cnt > 0 ) {
        echo '<label><input type="checkbox" name="bap_2fa_revoke_devices" value="1" /> Cerrar confianza en todos los dispositivos guardados al guardar el perfil</label>';
    }
    echo '</td></tr></table>';
}
add_action( 'show_user_profile', 'bap_2fa_render_profile_fields' );
add_action( 'edit_user_profile', 'bap_2fa_render_profile_fields' );

function bap_2fa_save_profile_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['bap_2fa_user_method'] ) ) {
        return;
    }
    if ( ! empty( $_POST['bap_2fa_revoke_devices'] ) ) {
        delete_user_meta( $user_id, '_bap_2fa_trusted_devices' );
        if ( get_current_user_id() === (int) $user_id && ! headers_sent() ) {
            setcookie( 'bap_2fa_trust_' . $user_id, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
    }

    $chosen = sanitize_key( $_POST['bap_2fa_user_method'] );
    $secret = (string) get_user_meta( $user_id, '_bap_2fa_totp_secret', true );

    if ( 'totp' === $chosen ) {
        $current_method = get_user_meta( $user_id, '_bap_2fa_method', true );
        $verify_code    = isset( $_POST['bap_2fa_verify_setup'] ) ? sanitize_text_field( wp_unslash( $_POST['bap_2fa_verify_setup'] ) ) : '';
        if ( 'totp' !== $current_method && ! bap_2fa_verify_totp( $secret, $verify_code ) ) {
            add_action( 'user_profile_update_errors', function( $errors ) {
                $errors->add( 'bap_2fa_err', '<strong>2FA no activado:</strong> Introduce el código de 6 dígitos generado por tu App Autenticadora para verificar que está bien sincronizada.' );
            });
            return;
        }
        update_user_meta( $user_id, '_bap_2fa_method', 'totp' );
    } elseif ( 'email' === $chosen ) {
        update_user_meta( $user_id, '_bap_2fa_method', 'email' );
    } else {
        delete_user_meta( $user_id, '_bap_2fa_method' );
    }

    $existing = get_user_meta( $user_id, '_bap_2fa_backup_codes', true );
    if ( ! empty( $_POST['bap_2fa_regen_backups'] ) || ( in_array( $chosen, [ 'totp', 'email' ], true ) && empty( $existing ) ) ) {
        $codes = bap_2fa_generate_backup_codes( $user_id );
        set_transient( 'bap_2fa_new_backups_' . $user_id, $codes, 120 );
    }
}
add_action( 'personal_options_update', 'bap_2fa_save_profile_fields' );
add_action( 'edit_user_profile_update', 'bap_2fa_save_profile_fields' );
