<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_pwd_policy_applies_to_user( $user ) {
    if ( ! ( $user instanceof WP_User ) ) {
        return false;
    }
    $scope = (string) get_option( 'bap_pwd_policy_scope', 'admins_editors' );
    if ( 'all' === $scope ) {
        return true;
    }
    if ( 'admins_editors' === $scope ) {
        return user_can( $user, 'edit_others_posts' );
    }
    return user_can( $user, 'manage_options' );
}

function bap_pwd_is_in_history( $password, $user ) {
    if ( ! ( $user instanceof WP_User ) || empty( $user->ID ) ) {
        return false;
    }
    if ( wp_check_password( $password, $user->user_pass, $user->ID ) ) {
        return true;
    }
    $history = get_user_meta( $user->ID, '_bap_pwd_history', true );
    if ( is_array( $history ) ) {
        foreach ( $history as $old_hash ) {
            if ( is_string( $old_hash ) && '' !== $old_hash && wp_check_password( $password, $old_hash, $user->ID ) ) {
                return true;
            }
        }
    }
    return false;
}

function bap_pwd_record_history( $user_id, $old_hash ) {
    $old_hash = (string) $old_hash;
    if ( '' === $old_hash ) {
        return;
    }
    $history = get_user_meta( $user_id, '_bap_pwd_history', true );
    if ( ! is_array( $history ) ) {
        $history = [];
    }
    array_unshift( $history, $old_hash );
    $history = array_slice( array_values( array_unique( $history ) ), 0, 5 );
    update_user_meta( $user_id, '_bap_pwd_history', $history );
}

function bap_validate_strong_password_rules( $password, $user = null ) {
    $min_len  = max( 8, min( 64, (int) get_option( 'bap_pwd_min_length', 12 ) ) );
    $req_mix  = ( '1' === (string) get_option( 'bap_pwd_require_complexity', '1' ) );
    $no_reuse = ( '1' === (string) get_option( 'bap_pwd_prevent_reuse', '1' ) );

    if ( mb_strlen( $password, 'UTF-8' ) < $min_len ) {
        return sprintf( 'La contraseña debe tener al menos <strong>%d caracteres</strong>.', $min_len );
    }

    if ( $req_mix ) {
        if ( ! preg_match( '/\p{Lu}/u', $password ) || ! preg_match( '/\p{Ll}/u', $password ) || ! preg_match( '/[0-9]/', $password ) || ! preg_match( '/[^a-zA-Z0-9]/', $password ) ) {
            return 'La contraseña debe incluir al menos una <strong>mayúscula</strong>, una <strong>minúscula</strong>, un <strong>número</strong> y un <strong>símbolo</strong>.';
        }
    }

    if ( $user instanceof WP_User && ! empty( $user->ID ) ) {
        if ( false !== stripos( $password, (string) $user->user_login ) ) {
            return 'La contraseña no puede contener tu nombre de usuario.';
        }
        if ( $no_reuse && bap_pwd_is_in_history( $password, $user ) ) {
            return 'No puedes reutilizar ninguna de tus últimas 5 contraseñas. Elige una nueva.';
        }
    }

    return '';
}

add_action( 'user_profile_update_errors', function( $errors, $update, $user ) {
    if ( '1' !== (string) get_option( 'bap_pwd_policy_enabled', '0' ) ) {
        return;
    }
    if ( empty( $_POST['pass1'] ) ) {
        return;
    }

    $target_user = $update ? get_user_by( 'id', (int) $user->ID ) : null;
    if ( $update && ! bap_pwd_policy_applies_to_user( $target_user ) ) {
        return;
    }
    if ( ! $update ) {
        $role  = sanitize_key( $_POST['role'] ?? 'subscriber' );
        $scope = (string) get_option( 'bap_pwd_policy_scope', 'admins_editors' );
        if ( 'admins' === $scope && 'administrator' !== $role ) {
            return;
        }
        if ( 'admins_editors' === $scope && ! in_array( $role, [ 'administrator', 'editor', 'shop_manager' ], true ) ) {
            return;
        }
    }

    $err = bap_validate_strong_password_rules( (string) wp_unslash( $_POST['pass1'] ), $target_user );
    if ( '' !== $err ) {
        $errors->add( 'bap_pwd_policy_err', '<strong>Política de Contraseñas:</strong> ' . $err );
    }
}, 15, 3 );

add_action( 'validate_password_reset', function( $errors, $user ) {
    if ( '1' !== (string) get_option( 'bap_pwd_policy_enabled', '0' ) || ! bap_pwd_policy_applies_to_user( $user ) ) {
        return;
    }
    if ( ! empty( $_POST['pass1'] ) ) {
        $err = bap_validate_strong_password_rules( (string) wp_unslash( $_POST['pass1'] ), $user );
        if ( '' !== $err ) {
            $errors->add( 'bap_pwd_policy_err', '<strong>Política de Contraseñas:</strong> ' . $err );
        }
    }
}, 15, 2 );

add_action( 'profile_update', function( $user_id, $old_user_data ) {
    $new_user = get_user_by( 'id', $user_id );
    if ( $new_user && $old_user_data && $new_user->user_pass !== $old_user_data->user_pass ) {
        bap_pwd_record_history( $user_id, $old_user_data->user_pass );
        update_user_meta( $user_id, '_bap_pwd_changed_at', time() );
        delete_user_meta( $user_id, '_bap_pwd_force_expire' );
    }
}, 10, 2 );

add_action( 'password_reset', function( $user, $new_pass ) {
    if ( $user instanceof WP_User ) {
        bap_pwd_record_history( $user->ID, $user->user_pass );
        update_user_meta( $user->ID, '_bap_pwd_changed_at', time() );
        delete_user_meta( $user->ID, '_bap_pwd_force_expire' );
    }
}, 10, 2 );
add_filter( 'authenticate', function( $user, $username, $password ) {
    if ( ! ( $user instanceof WP_User ) ) {
        return $user;
    }

    $forced   = ( '1' === (string) get_user_meta( $user->ID, '_bap_pwd_force_expire', true ) );
    $max_days = (int) get_option( 'bap_pwd_expiry_days', 0 );
    $expired  = false;

    if ( $forced ) {
        $expired = true;
    } elseif ( $max_days > 0 && bap_pwd_policy_applies_to_user( $user ) ) {
        $changed_at = (int) get_user_meta( $user->ID, '_bap_pwd_changed_at', true );
        if ( 0 === $changed_at ) {
            $changed_at = strtotime( (string) $user->user_registered ) ?: time();
            update_user_meta( $user->ID, '_bap_pwd_changed_at', $changed_at );
        }
        if ( ( time() - $changed_at ) >= ( $max_days * DAY_IN_SECONDS ) ) {
            $expired = true;
        }
    }

    if ( $expired ) {
        $reset_key = get_password_reset_key( $user );
        if ( ! is_wp_error( $reset_key ) ) {
            $rp_url = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $reset_key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
            $msg    = $forced
                ? '<strong>Cambio de contraseña obligatorio:</strong> Un administrador ha solicitado que renueves tu contraseña. <a href="%s">Haz clic aquí para establecer una nueva</a>.'
                : sprintf( '<strong>Contraseña caducada:</strong> Han pasado más de %d días desde tu último cambio de clave. <a href="%%s">Haz clic aquí para establecer una nueva contraseña</a>.', $max_days );

            return new WP_Error( 'bap_pwd_expired', sprintf( $msg, esc_url( $rp_url ) ) );
        }
    }

    return $user;
}, 35, 3 );

add_action( 'admin_notices', function() {
    $max_days = (int) get_option( 'bap_pwd_expiry_days', 0 );
    if ( $max_days <= 0 ) {
        return;
    }
    $user = wp_get_current_user();
    if ( ! bap_pwd_policy_applies_to_user( $user ) ) {
        return;
    }

    $changed_at = (int) get_user_meta( $user->ID, '_bap_pwd_changed_at', true );
    if ( $changed_at <= 0 ) {
        return;
    }

    $expires_at = $changed_at + ( $max_days * DAY_IN_SECONDS );
    $days_left  = (int) ceil( ( $expires_at - time() ) / DAY_IN_SECONDS );

    if ( $days_left > 0 && $days_left <= 7 ) {
        $profile_url = esc_url( get_edit_profile_url( $user->ID ) . '#password' );
        printf(
            '<div class="notice notice-warning"><p><strong>Rotación de Contraseña:</strong> Tu contraseña caducará en <strong>%d día(s)</strong>. <a href="%s">Actualízala ahora en tu perfil</a>.</p></div>',
            $days_left,
            $profile_url
        );
    }
} );

function bap_pwd_render_user_expiry_field( $user ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $changed_at = (int) get_user_meta( $user->ID, '_bap_pwd_changed_at', true );
    $is_forced  = ( '1' === (string) get_user_meta( $user->ID, '_bap_pwd_force_expire', true ) );
    $hist       = get_user_meta( $user->ID, '_bap_pwd_history', true );
    $hist_count = is_array( $hist ) ? count( $hist ) : 0;
    ?>
    <h2>Seguridad y Rotación de Contraseña</h2>
    <table class="form-table">
        <tr>
            <th>Estado de la contraseña</th>
            <td>
                <p style="margin-bottom:8px;">
                    <strong>Último cambio registrado:</strong>
                    <code><?php echo $changed_at > 0 ? esc_html( wp_date( 'Y-m-d H:i:s', $changed_at ) ) : 'Sin cambios registrados desde el alta'; ?></code>
                    &nbsp;|&nbsp;
                    <strong>Historial guardado:</strong> <code><?php echo (int) $hist_count; ?> / 5</code> claves anteriores
                </p>
                <label>
                    <input type="checkbox" name="bap_pwd_force_expire_user" value="1" <?php checked( $is_forced ); ?> />
                    <strong>Forzar caducidad inmediata:</strong> Obligar a este usuario a crear una nueva contraseña en su próximo inicio de sesión
                </label>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'bap_pwd_render_user_expiry_field', 25 );
add_action( 'edit_user_profile', 'bap_pwd_render_user_expiry_field', 25 );

function bap_pwd_save_user_expiry_field( $user_id ) {
    if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }
    if ( ! empty( $_POST['bap_pwd_force_expire_user'] ) ) {
        update_user_meta( $user_id, '_bap_pwd_force_expire', '1' );
        if ( class_exists( 'WP_Session_Tokens' ) && get_current_user_id() !== (int) $user_id ) {
            WP_Session_Tokens::get_instance( $user_id )->destroy_all();
        }
    } else {
        delete_user_meta( $user_id, '_bap_pwd_force_expire' );
    }
}
add_action( 'personal_options_update', 'bap_pwd_save_user_expiry_field', 25 );
add_action( 'edit_user_profile_update', 'bap_pwd_save_user_expiry_field', 25 );
