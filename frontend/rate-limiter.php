<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_rl_is_exempt( $ip ) {
    if ( is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() ) {
        return true;
    }
    if ( '' === $ip || bap_is_server_ip( $ip ) ) {
        return true;
    }
    if ( bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return true;
    }
    if ( '1' === (string) get_option( 'bap_web_allow_seo_bots', '1' ) && function_exists( 'bap_is_verified_seo_bot' ) && bap_is_verified_seo_bot( $ip ) ) {
        return true;
    }
    return false;
}

function bap_rl_increment_window( $bucket, $ip ) {
    $key  = 'bap_rl_' . $bucket . '_' . md5( $ip );
    $now  = time();
    $data = get_transient( $key );

    if ( ! is_array( $data ) || ( $now - (int) ( $data['start'] ?? 0 ) ) >= 60 ) {
        $data = [ 'start' => $now, 'count' => 1 ];
        set_transient( $key, $data, 65 );
        return 1;
    }

    $data['count'] = (int) $data['count'] + 1;
    $ttl           = max( 5, 60 - ( $now - (int) $data['start'] ) );
    set_transient( $key, $data, $ttl );
    return $data['count'];
}

function bap_rl_trigger_block( $ip, $reason ) {
    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    bap_log_blocked_attempt( $ip, $country, $reason );

    if ( '1' === (string) get_option( 'bap_rl_autoban', '0' ) ) {
        $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
        if ( ! in_array( $ip, $blacklist, true ) ) {
            $blacklist[] = $ip;
            update_option( 'bap_blacklist_ips', implode( "\n", $blacklist ) );

            $duration_hours = (int) get_option( 'bap_auto_ban_duration', 24 );
            if ( $duration_hours > 0 ) {
                $expirations        = get_option( 'bap_auto_ban_expirations', [] );
                $expirations[ $ip ] = time() + ( $duration_hours * HOUR_IN_SECONDS );
                update_option( 'bap_auto_ban_expirations', $expirations, false );
            }
        }
    }

    $msg = (string) get_option( 'bap_web_block_message', 'Demasiadas peticiones en poco tiempo. Acceso temporalmente restringido.' );
    if ( function_exists( 'bap_render_custom_403_page' ) ) {
        bap_render_custom_403_page( 'Límite de peticiones excedido', $msg, $ip, $country, $reason );
    }
    wp_die( '<h1>Acceso restringido</h1><p>' . esc_html( $msg ) . '</p>', 'Rate Limit Excedido', [ 'response' => 429 ] );
}

// 1. Comprobar límite global de peticiones por minuto
add_action( 'init', function() {
    $max_req = (int) get_option( 'bap_rl_max_req_min', 0 );
    if ( $max_req <= 0 ) {
        return;
    }

    $ip = bap_get_user_ip();
    if ( bap_rl_is_exempt( $ip ) ) {
        return;
    }

    $hits = bap_rl_increment_window( 'req', $ip );
    if ( $hits > $max_req ) {
        bap_rl_trigger_block( $ip, "Web: Rate Limit ({$hits}/{$max_req} peticiones/min)" );
    }
}, 2 );

// 2. Comprobar límite de errores 404 por minuto (escáneres de rutas/vulnerabilidades)
add_action( 'template_redirect', function() {
    if ( ! is_404() ) {
        return;
    }
    $max_404 = (int) get_option( 'bap_rl_max_404_min', 0 );
    if ( $max_404 <= 0 ) {
        return;
    }

    $ip = bap_get_user_ip();
    if ( bap_rl_is_exempt( $ip ) ) {
        return;
    }

    $hits = bap_rl_increment_window( '404', $ip );
    if ( $hits > $max_404 ) {
        bap_rl_trigger_block( $ip, "Web: Rate Limit 404 ({$hits}/{$max_404} errores 404/min)" );
    }
}, 5 );
