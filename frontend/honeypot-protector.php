<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_hp_is_enabled() {
    return ( '1' === (string) get_option( 'bap_honeypot_enabled', '0' ) );
}

function bap_hp_render_fields() {
    if ( ! bap_hp_is_enabled() || is_user_logged_in() ) {
        return;
    }
    $ts  = (string) time();
    $sig = hash_hmac( 'sha256', $ts, AUTH_SALT );
    ?>
    <p class="bap-hp-wrap" style="position:absolute !important;left:-9999px !important;top:-9999px !important;width:1px !important;height:1px !important;overflow:hidden !important;opacity:0 !important;" aria-hidden="true">
        <label for="bap_hp_website_url">Dejar este campo en blanco</label>
        <input type="text" name="bap_hp_website_url" id="bap_hp_website_url" value="" tabindex="-1" autocomplete="off" />
        <input type="hidden" name="bap_hp_ts" value="<?php echo esc_attr( $ts ); ?>" />
        <input type="hidden" name="bap_hp_sig" value="<?php echo esc_attr( $sig ); ?>" />
    </p>
    <?php
}

function bap_hp_detect_bot_reason() {
    if ( ! bap_hp_is_enabled() || is_user_logged_in() ) {
        return '';
    }

    $ip = bap_get_user_ip();
    if ( '' === $ip || bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return '';
    }

    // 1. Si el campo trampa oculto viene relleno, es un bot seguro
    $trap = isset( $_POST['bap_hp_website_url'] ) ? trim( (string) wp_unslash( $_POST['bap_hp_website_url'] ) ) : '';
    if ( '' !== $trap ) {
        return 'Web: Honeypot activado (campo trampa relleno)';
    }

    // 2. Comprobar token temporal y tiempo mínimo de rellenado (2 segundos)
    $ts  = isset( $_POST['bap_hp_ts'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['bap_hp_ts'] ) ) ) : '';
    $sig = isset( $_POST['bap_hp_sig'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['bap_hp_sig'] ) ) ) : '';

    if ( '' === $ts || ! ctype_digit( $ts ) || '' === $sig || ! hash_equals( hash_hmac( 'sha256', $ts, AUTH_SALT ), $sig ) ) {
        return 'Web: Honeypot activado (envío directo sin token)';
    }

    $elapsed = time() - (int) $ts;
    if ( $elapsed < 2 ) {
        return 'Web: Honeypot activado (envío instantáneo < 2s)';
    }

    return '';
}

function bap_hp_block_and_log( $reason ) {
    $ip      = bap_get_user_ip();
    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    bap_log_blocked_attempt( $ip, $country, $reason );

    if ( '1' === (string) get_option( 'bap_hp_autoban', '0' ) ) {
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
}

// 1. Comentarios nativos de WordPress
add_action( 'comment_form_after_fields', 'bap_hp_render_fields' );
add_filter( 'preprocess_comment', function( $commentdata ) {
    $reason = bap_hp_detect_bot_reason();
    if ( '' !== $reason ) {
        bap_hp_block_and_log( $reason );
        wp_die( '<strong>Bloqueo anti-spam:</strong> Tu envío ha sido identificado como automatizado.', 'Spam Bloqueado', [ 'response' => 403 ] );
    }
    return $commentdata;
}, 1 );

// 2. Registro de nuevos usuarios y recuperación de contraseña
add_action( 'register_form', 'bap_hp_render_fields' );
add_action( 'lostpassword_form', 'bap_hp_render_fields' );

add_filter( 'registration_errors', function( $errors ) {
    $reason = bap_hp_detect_bot_reason();
    if ( '' !== $reason ) {
        bap_hp_block_and_log( $reason );
        $errors->add( 'bap_honeypot_spam', '<strong>Seguridad:</strong> Envío bloqueado por detección anti-bot.' );
    }
    return $errors;
}, 1 );

add_action( 'lostpassword_post', function( $errors ) {
    $reason = bap_hp_detect_bot_reason();
    if ( '' !== $reason ) {
        bap_hp_block_and_log( $reason );
        $errors->add( 'bap_honeypot_spam', '<strong>Seguridad:</strong> Envío bloqueado por detección anti-bot.' );
    }
}, 1 );

// 3. Integración automática con Contact Form 7 (si está instalado)
add_filter( 'wpcf7_form_elements', function( $html ) {
    if ( ! bap_hp_is_enabled() || is_user_logged_in() ) {
        return $html;
    }
    ob_start();
    bap_hp_render_fields();
    return $html . ob_get_clean();
} );

add_filter( 'wpcf7_spam', function( $spam ) {
    if ( $spam ) {
        return $spam;
    }
    $reason = bap_hp_detect_bot_reason();
    if ( '' !== $reason ) {
        bap_hp_block_and_log( $reason );
        return true;
    }
    return false;
}, 10 );

// 4. Integración automática con WPForms (si está instalado)
add_action( 'wpforms_display_submit_before', 'bap_hp_render_fields' );
add_filter( 'wpforms_process_initial_errors', function( $errors, $form_data ) {
    $reason = bap_hp_detect_bot_reason();
    if ( '' !== $reason ) {
        bap_hp_block_and_log( $reason );
        $form_id = (int) ( $form_data['id'] ?? 0 );
        $errors[ $form_id ]['header'] = 'Envío bloqueado por protección anti-spam.';
    }
    return $errors;
}, 10, 2 );
