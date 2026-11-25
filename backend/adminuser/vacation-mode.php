<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_get_user_vacation_window( $user_id ) {
    $start = (int) get_user_meta( $user_id, '_bap_vacation_start', true );
    $end   = (int) get_user_meta( $user_id, '_bap_vacation_end', true );
    if ( $start <= 0 || $end <= 0 || $end <= $start ) {
        return null;
    }
    return [ 'start' => $start, 'end' => $end ];
}

function bap_is_user_on_vacation( $user_id ) {
    $win = bap_get_user_vacation_window( $user_id );
    if ( null === $win ) {
        return false;
    }
    $now = time();
    if ( $now >= $win['end'] ) {
        delete_user_meta( $user_id, '_bap_vacation_start' );
        delete_user_meta( $user_id, '_bap_vacation_end' );
        delete_user_meta( $user_id, '_bap_vac_emergency_started' );
        return false;
    }
    return ( $now >= $win['start'] && $now < $win['end'] );
}

function bap_vacation_has_secret_bypass( $user_id = 0 ) {
    if ( defined( 'BAP_USED_SECRET_KEY' ) && BAP_USED_SECRET_KEY ) {
        return true;
    }
    if ( $user_id > 0 && '1' === (string) get_user_meta( $user_id, '_bap_secret_key_session', true ) ) {
        return true;
    }
    return function_exists( 'bap_has_valid_secret_bypass' ) && bap_has_valid_secret_bypass( false );
}

// Limitar cookie de WordPress a 30 minutos (1800s) si entra en vacaciones con Llave Secreta
add_filter( 'auth_cookie_expiration', function( $length, $user_id, $remember ) {
    if ( bap_is_user_on_vacation( $user_id ) && bap_vacation_has_secret_bypass( $user_id ) ) {
        return 30 * MINUTE_IN_SECONDS;
    }
    return $length;
}, 999, 3 );

// Marcar inicio de sesión de mantenimiento de emergencia (máx. 30 min)
add_action( 'wp_login', function( $user_login, $user ) {
    if ( ! ( $user instanceof WP_User ) ) {
        return;
    }
    if ( bap_is_user_on_vacation( $user->ID ) && bap_vacation_has_secret_bypass( $user->ID ) ) {
        update_user_meta( $user->ID, '_bap_secret_key_session', '1' );
        update_user_meta( $user->ID, '_bap_vac_emergency_started', time() );
        $secret = trim( (string) get_option( 'bap_secret_key', '' ) );
        if ( '' !== $secret && ! headers_sent() ) {
            $cname = 'bap_bypass_' . md5( $secret );
            $token = hash( 'sha256', $secret . AUTH_SALT );
            setcookie( $cname, $token, time() + ( 30 * MINUTE_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
    } else {
        delete_user_meta( $user->ID, '_bap_vac_emergency_started' );
    }
}, 6, 2 );

add_action( 'wp_logout', function( $user_id ) {
    if ( $user_id > 0 ) {
        delete_user_meta( $user_id, '_bap_vac_emergency_started' );
    }
} );

add_filter( 'authenticate', function( $user, $username, $password ) {
    if ( ! ( $user instanceof WP_User ) ) {
        return $user;
    }
    if ( bap_is_user_on_vacation( $user->ID ) ) {
        if ( bap_vacation_has_secret_bypass( $user->ID ) ) {
            if ( ! defined( 'BAP_USED_SECRET_KEY' ) ) {
                define( 'BAP_USED_SECRET_KEY', true );
            }
            return $user;
        }
        $win   = bap_get_user_vacation_window( $user->ID );
        $until = $win ? wp_date( 'd/m/Y H:i', $win['end'] ) : '';
        if ( function_exists( 'bap_log_blocked_attempt' ) && function_exists( 'bap_get_user_ip' ) ) {
            $ip      = bap_get_user_ip();
            $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
            bap_log_blocked_attempt( $ip, $country, 'Modo Vacaciones activo (' . $user->user_login . ')' );
        }
        return new WP_Error(
            'bap_user_vacation',
            sprintf( '<strong>Cuenta en Modo Vacaciones:</strong> El acceso para <code>%s</code> está suspendido temporalmente hasta el <strong>%s</strong>.', esc_html( $user->user_login ), esc_html( $until ) )
        );
    }
    return $user;
}, 32, 3 );

add_action( 'init', function() {
    if ( ! is_user_logged_in() ) {
        return;
    }
    $uid = get_current_user_id();
    if ( bap_is_user_on_vacation( $uid ) ) {
        if ( ! bap_vacation_has_secret_bypass( $uid ) ) {
            wp_logout();
            wp_safe_redirect( add_query_arg( 'bap_vacation_out', '1', wp_login_url() ) );
            exit;
        }
        if ( ! defined( 'BAP_USED_SECRET_KEY' ) ) {
            define( 'BAP_USED_SECRET_KEY', true );
        }
        update_user_meta( $uid, '_bap_secret_key_session', '1' );
        $started = (int) get_user_meta( $uid, '_bap_vac_emergency_started', true );
        if ( $started <= 0 ) {
            $started = time();
            update_user_meta( $uid, '_bap_vac_emergency_started', $started );
        }
        if ( ( time() - $started ) >= ( 30 * MINUTE_IN_SECONDS ) ) {
            delete_user_meta( $uid, '_bap_vac_emergency_started' );
            delete_user_meta( $uid, '_bap_secret_key_session' );
            $secret = trim( (string) get_option( 'bap_secret_key', '' ) );
            if ( '' !== $secret && ! headers_sent() ) {
                setcookie( 'bap_bypass_' . md5( $secret ), '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            }
            wp_logout();
            wp_safe_redirect( add_query_arg( 'bap_vacation_expired', '1', wp_login_url() ) );
            exit;
        }
    }
}, 2 );

add_action( 'admin_notices', function() {
    if ( ! is_user_logged_in() ) {
        return;
    }
    $uid = get_current_user_id();
    if ( bap_is_user_on_vacation( $uid ) && bap_vacation_has_secret_bypass( $uid ) ) {
        $started  = (int) get_user_meta( $uid, '_bap_vac_emergency_started', true );
        $rem_secs = max( 0, ( 30 * MINUTE_IN_SECONDS ) - ( time() - $started ) );
        $rem_mins = (int) ceil( $rem_secs / 60 );
        echo '<div class="notice notice-warning" style="border-left-color:#ca8a04;background:#fef9c3;color:#713f12;"><p>'
           . '<strong>Sesión de Mantenimiento de Emergencia (Modo Vacaciones):</strong> Has accedido mediante la <strong>Llave Secreta URL</strong>. '
           . 'Por seguridad, esta sesión caducará automáticamente en <strong>' . (int) $rem_mins . ' minuto(s)</strong>.'
           . '</p></div>';
    }
} );