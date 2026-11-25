<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

    $site_short = mb_substr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), 0, 20 );
    $issuer     = rawurlencode( $site_short );
    $account    = rawurlencode( $user->user_email );
    $scheme     = 'otpauth' . '://totp/';
    $otp_uri    = $scheme . $issuer . ':' . $account . '?secret=' . $secret . '&issuer=' . $issuer;

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

add_action( 'user_profile_update_errors', function( $errors, $update, $user ) {
    if ( ! $update || empty( $user->ID ) || ! isset( $_POST['bap_2fa_user_method'] ) ) {
        return;
    }
    $chosen         = sanitize_key( $_POST['bap_2fa_user_method'] );
    $current_method = get_user_meta( $user->ID, '_bap_2fa_method', true );

    if ( 'totp' === $chosen && 'totp' !== $current_method ) {
        $secret      = (string) get_user_meta( $user->ID, '_bap_2fa_totp_secret', true );
        $verify_code = isset( $_POST['bap_2fa_verify_setup'] ) ? sanitize_text_field( wp_unslash( $_POST['bap_2fa_verify_setup'] ) ) : '';
        if ( ! bap_2fa_verify_totp( $secret, $verify_code ) ) {
            $errors->add(
                'bap_2fa_err',
                '<strong>2FA no activado:</strong> Introduce el código de 6 dígitos generado por tu App Autenticadora para verificar que está bien sincronizada.'
            );
        }
    }
}, 10, 3 );

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
