<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Comprueba si una contraseña está filtrada usando HIBP Pwned Passwords (k-Anonymity).
 * Devuelve el número de veces filtrada (int > 0) o 0 si es segura / error de red.
 */
function bap_check_pwned_password( $password ) {
    $password = (string) $password;
    if ( '' === $password ) {
        return 0;
    }

    $sha1   = strtoupper( sha1( $password ) );
    $prefix = substr( $sha1, 0, 5 );
    $suffix = substr( $sha1, 5 );

    $cache_key = 'bap_hibp_' . $prefix;
    $body      = get_transient( $cache_key );

    if ( false === $body ) {
        $host = 'api' . '.pwnedpasswords.com';
        $url  = 'https://' . $host . '/range/' . $prefix;
        $res  = wp_remote_get( $url, [
            'timeout' => 5,
            'headers' => [
                'Add-Padding' => 'true',
                'User-Agent'  => 'WordPress-BAP-Security',
            ],
        ] );

        if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
            return 0; // Fail-open: no bloquea si la API de HIBP cae
        }

        $body = wp_remote_retrieve_body( $res );
        set_transient( $cache_key, $body, DAY_IN_SECONDS );
    }

    foreach ( explode( "\n", $body ) as $line ) {
        $parts = explode( ':', trim( $line ) );
        if ( isset( $parts[0], $parts[1] ) && hash_equals( $suffix, strtoupper( trim( $parts[0] ) ) ) ) {
            return (int) trim( $parts[1] );
        }
    }

    return 0;
}

// 1. Bloquear contraseñas filtradas al actualizar perfil o crear usuario
add_action( 'user_profile_update_errors', function( $errors, $update, $user ) {
    if ( '1' !== (string) get_option( 'bap_hibp_enabled', '1' ) ) {
        return;
    }
    if ( ! empty( $_POST['pass1'] ) ) {
        $pass  = wp_unslash( $_POST['pass1'] );
        $count = bap_check_pwned_password( $pass );
        if ( $count > 0 ) {
            $errors->add(
                'bap_pwned_password',
                sprintf(
                    '<strong>Seguridad:</strong> Esta contraseña aparece en <strong>%s</strong> filtraciones de datos públicas (Have I Been Pwned). Elige una contraseña única.',
                    number_format_i18n( $count )
                )
            );
        }
    }
}, 10, 3 );

// 2. Bloquear contraseñas filtradas al restablecer contraseña (wp-login.php?action=rp)
add_action( 'validate_password_reset', function( $errors, $user ) {
    if ( '1' !== (string) get_option( 'bap_hibp_enabled', '1' ) ) {
        return;
    }
    if ( ! empty( $_POST['pass1'] ) ) {
        $pass  = wp_unslash( $_POST['pass1'] );
        $count = bap_check_pwned_password( $pass );
        if ( $count > 0 ) {
            $errors->add(
                'bap_pwned_password',
                sprintf(
                    '<strong>Seguridad:</strong> Esta contraseña ha sido expuesta en <strong>%s</strong> brechas de seguridad. Por favor, usa otra diferente.',
                    number_format_i18n( $count )
                )
            );
        }
    }
}, 10, 2 );

// 3. Comprobar al iniciar sesión (opcional: bloquear o registrar aviso)
add_filter( 'authenticate', function( $user, $username, $password ) {
    if ( ! ( $user instanceof WP_User ) || '' === (string) $password ) {
        return $user;
    }
    if ( '1' !== (string) get_option( 'bap_hibp_check_login', '1' ) ) {
        return $user;
    }

    $count = bap_check_pwned_password( $password );
    if ( $count > 0 ) {
        update_user_meta( $user->ID, '_bap_pwned_login_count', $count );

        if ( 'block' === get_option( 'bap_hibp_login_action', 'warn' ) ) {
            return new WP_Error(
                'bap_pwned_login',
                sprintf(
                    '<strong>Acceso bloqueado por seguridad:</strong> Tu contraseña aparece en %s filtraciones públicas. Usa "¿Olvidaste tu contraseña?" para establecer una nueva.',
                    number_format_i18n( $count )
                )
            );
        }
    } else {
        delete_user_meta( $user->ID, '_bap_pwned_login_count' );
    }

    return $user;
}, 40, 3 );

// Mostrar aviso en el escritorio de WordPress si el usuario entró con clave filtrada
add_action( 'admin_notices', function() {
    $uid   = get_current_user_id();
    $count = (int) get_user_meta( $uid, '_bap_pwned_login_count', true );
    if ( $count > 0 ) {
        $profile_url = esc_url( get_edit_profile_url( $uid ) );
        echo '<div class="notice notice-error"><p>'
           . '<strong>Alerta de Seguridad (Have I Been Pwned):</strong> La contraseña de tu cuenta aparece en <strong>'
           . esc_html( number_format_i18n( $count ) )
           . '</strong> brechas de datos conocidas. <a href="' . $profile_url . '">Cámbiala inmediatamente en tu perfil</a>.'
           . '</p></div>';
    }
});
