<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Purgar IPs autobaneadas caducadas mediante WP-Cron (evita carga en cada petición)
function bap_purge_expired_autobans() {
    $duration_hours = (int) get_option( 'bap_auto_ban_duration', 24 );
    if ( $duration_hours <= 0 ) {
        return;
    }

    $expirations = get_option( 'bap_auto_ban_expirations', [] );
    if ( ! is_array( $expirations ) || empty( $expirations ) ) {
        return;
    }

    $now     = time();
    $expired = [];

    foreach ( $expirations as $ip => $expires_at ) {
        if ( $now >= (int) $expires_at ) {
            $expired[] = $ip;
            unset( $expirations[ $ip ] );
        }
    }

    if ( ! empty( $expired ) ) {
        $bl_ips   = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
        $filtered = array_diff( $bl_ips, $expired );

        update_option( 'bap_blacklist_ips', implode( "\n", $filtered ) );
        update_option( 'bap_auto_ban_expirations', $expirations, false );
    }
}
add_action( 'bap_purge_autobans_event', 'bap_purge_expired_autobans' );

add_action( 'init', function() {
    if ( false === get_transient( 'bap_autoban_cron_checked' ) ) {
        if ( ! wp_next_scheduled( 'bap_purge_autobans_event' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'bap_purge_autobans_event' );
        }
        set_transient( 'bap_autoban_cron_checked', '1', 12 * HOUR_IN_SECONDS );
    }
}, 5 );

// Bloqueo de XML-RPC
if ( '1' === (string) get_option( 'bap_block_xmlrpc', '1' ) ) {
    add_filter( 'xmlrpc_enabled', '__return_false' );
    add_filter( 'xmlrpc_methods', '__return_empty_array' );

    add_action( 'init', function() {
        $script = isset( $_SERVER['SCRIPT_FILENAME'] ) ? basename( $_SERVER['SCRIPT_FILENAME'] ) : '';
        $uri    = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

        if ( 'xmlrpc.php' === $script || false !== stripos( $uri, 'xmlrpc.php' ) ) {
            $ip = bap_get_user_ip();
            if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
                return;
            }
            $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
            bap_deny_access( $ip, $country, 'Acceso bloqueado a XML-RPC' );
        }
    }, 1 );
}

// Bloqueo de enumeración de usuarios (Author scan y REST API)
if ( '1' === (string) get_option( 'bap_block_user_enum', '1' ) ) {
    add_action( 'template_redirect', function() {
        if ( ! is_user_logged_in() && ( is_author() || isset( $_GET['author'] ) ) ) {
            $ip = bap_get_user_ip();
            if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
                return;
            }
            $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
            bap_deny_access( $ip, $country, 'Intento de enumeración de usuarios (?author)' );
        }
    }, 1 );

    add_filter( 'rest_endpoints', function( $endpoints ) {
        if ( ! is_user_logged_in() ) {
            unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
        }
        return $endpoints;
    } );
}

add_action( 'wp_login_failed', function( $username ) {
    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return;
    }
    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    bap_log_blocked_attempt( $ip, $country, 'Login fallido (' . sanitize_user( $username ) . ')' );
});

add_filter( 'authenticate', function( $user, $username, $password ) {
    if ( '' === trim( (string) $username ) || '1' !== (string) get_option( 'bap_block_nonexistent_users', '1' ) ) {
        return $user;
    }

    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return $user;
    }

    $clean_user = sanitize_text_field( wp_unslash( $username ) );
    $exists     = username_exists( $clean_user ) || ( is_email( $clean_user ) && email_exists( $clean_user ) );

    if ( ! $exists ) {
        $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
        $label   = mb_substr( sanitize_user( $clean_user, true ), 0, 40 );
        bap_deny_access( $ip, $country, 'Usuario inexistente: ' . ( '' !== $label ? $label : 'desconocido' ) );
    }

    return $user;
}, 30, 3 );

add_action( 'init', function() {
    if ( ! bap_is_protected_request() ) {
        if ( function_exists( 'bap_check_frontend_rules' ) ) {
            bap_check_frontend_rules();
        }
        return;
    }

    $secret = trim( (string) get_option( 'bap_secret_key', '' ) );
    if ( '' !== $secret ) {
        $cname = 'bap_bypass_' . md5( $secret );
        $token = hash( 'sha256', $secret . AUTH_SALT );

        // Comprobar parámetro GET exacto (?MI_LLAVE o ?MI_LLAVE=1)
        $has_valid_param = array_key_exists( $secret, $_GET );

        // Si viene dentro de redirect_to (ej. tras login), parsear sus parámetros GET reales
        if ( ! $has_valid_param && ! empty( $_GET['redirect_to'] ) ) {
            $redir_query = (string) wp_parse_url( wp_unslash( $_GET['redirect_to'] ), PHP_URL_QUERY );
            if ( '' !== $redir_query ) {
                parse_str( $redir_query, $redir_params );
                $has_valid_param = array_key_exists( $secret, $redir_params );
            }
        }

        if ( $has_valid_param ) {
            if ( ! headers_sent() ) {
                setcookie( $cname, $token, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            }
            return;
        }
        if ( isset( $_COOKIE[ $cname ] ) && hash_equals( $token, $_COOKIE[ $cname ] ) ) {
            return;
        }
    }

    $ip = bap_get_user_ip();
    if ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return;
    }

function bap_is_away_mode_active() {
    if ( '1' !== (string) get_option( 'bap_away_mode_enabled', '0' ) ) {
        return false;
    }

    $days = (array) get_option( 'bap_away_mode_days', [] );
    $dow  = (string) current_time( 'N' ); // 1 (Lun) .. 7 (Dom)
    if ( ! in_array( $dow, $days, true ) ) {
        return false;
    }

    $start_str = (string) get_option( 'bap_away_mode_start', '23:00' );
    $end_str   = (string) get_option( 'bap_away_mode_end', '07:00' );
    if ( ! preg_match( '/^(\d{2}):(\d{2})$/', $start_str, $ms ) || ! preg_match( '/^(\d{2}):(\d{2})$/', $end_str, $me ) ) {
        return false;
    }

    $now_str   = (string) current_time( 'H:i' );
    list( $nh, $nm ) = array_map( 'intval', explode( ':', $now_str ) );

    $cur_mins   = ( $nh * 60 ) + $nm;
    $start_mins = ( (int) $ms[1] * 60 ) + (int) $ms[2];
    $end_mins   = ( (int) $me[1] * 60 ) + (int) $me[2];

    if ( $start_mins === $end_mins ) {
        return false;
    }
    if ( $start_mins < $end_mins ) {
        return ( $cur_mins >= $start_mins && $cur_mins < $end_mins );
    }
    // Franja nocturna que cruza la medianoche (ej. 23:00 a 07:00)
    return ( $cur_mins >= $start_mins || $cur_mins < $end_mins );
}

    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    if ( bap_is_away_mode_active() ) {
        $s_h = (string) get_option( 'bap_away_mode_start', '23:00' );
        $e_h = (string) get_option( 'bap_away_mode_end', '07:00' );
        bap_deny_access( $ip, $country, "Modo Ausente activo ({$s_h} - {$e_h})" );
    }
    $verdict = bap_evaluate_ip_access( $ip, $country, 'admin', true );

    if ( 'block' === $verdict['status'] ) {
        bap_deny_access( $ip, $country, $verdict['reason'] );
    }
}, 1 );
