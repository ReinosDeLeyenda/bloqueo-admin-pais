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
    $ts        = (string) time();
    $sig       = hash_hmac( 'sha256', $ts, AUTH_SALT );
    $js_proof  = substr( hash_hmac( 'sha256', 'human_js|' . $ts, AUTH_SALT ), 0, 24 );
    ?>
    <p class="bap-hp-wrap" style="position:absolute !important;left:-9999px !important;top:-9999px !important;width:1px !important;height:1px !important;overflow:hidden !important;opacity:0 !important;" aria-hidden="true">
        <label for="bap_hp_website_url">Dejar este campo en blanco</label>
        <input type="text" name="bap_hp_website_url" id="bap_hp_website_url" value="" tabindex="-1" autocomplete="off" />
        <input type="hidden" name="bap_hp_ts" value="<?php echo esc_attr( $ts ); ?>" />
        <input type="hidden" name="bap_hp_sig" value="<?php echo esc_attr( $sig ); ?>" />
        <input type="hidden" name="bap_hp_js_proof" class="bap-hp-js-proof" data-proof="<?php echo esc_attr( $js_proof ); ?>" value="" />
    </p>
    <script>
    (function(){
        var marked = false;
        function markHuman(){
            if (marked) return;
            marked = true;
            document.querySelectorAll('.bap-hp-js-proof').forEach(function(el){
                el.value = el.getAttribute('data-proof') || '';
            });
        }
        ['pointermove','keydown','touchstart','scroll'].forEach(function(ev){
            window.addEventListener(ev, markHuman, { once: true, passive: true });
        });
    })();
    </script>
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

    // 1. Campo trampa oculto relleno
    $trap = isset( $_POST['bap_hp_website_url'] ) ? trim( (string) wp_unslash( $_POST['bap_hp_website_url'] ) ) : '';
    if ( '' !== $trap ) {
        return 'Web: Honeypot activado (campo trampa relleno)';
    }

    // 2. Token temporal y firma HMAC
    $ts  = isset( $_POST['bap_hp_ts'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['bap_hp_ts'] ) ) ) : '';
    $sig = isset( $_POST['bap_hp_sig'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['bap_hp_sig'] ) ) ) : '';

    if ( '' === $ts || ! ctype_digit( $ts ) || '' === $sig || ! hash_equals( hash_hmac( 'sha256', $ts, AUTH_SALT ), $sig ) ) {
        return 'Web: Honeypot activado (envío directo sin token)';
    }

    // 3. Tiempo mínimo de rellenado (2 segundos)
    if ( ( time() - (int) $ts ) < 2 ) {
        return 'Web: Honeypot activado (envío instantáneo < 2s)';
    }

    // 4. Prueba de interacción humana por JavaScript
    $js_val      = isset( $_POST['bap_hp_js_proof'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['bap_hp_js_proof'] ) ) ) : '';
    $expected_js = substr( hash_hmac( 'sha256', 'human_js|' . $ts, AUTH_SALT ), 0, 24 );
    if ( '' === $js_val || ! hash_equals( $expected_js, $js_val ) ) {
        return 'Web: Honeypot activado (sin interacción humana JS)';
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

function bap_hp_has_valid_local_referer() {
    $ref = isset( $_SERVER['HTTP_REFERER'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
    if ( '' === $ref ) {
        return false;
    }
    $ref_host  = strtolower( (string) wp_parse_url( $ref, PHP_URL_HOST ) );
    $site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    return ( '' !== $ref_host && '' !== $site_host && $ref_host === $site_host );
}

// 1. Comentarios nativos de WordPress
add_action( 'comment_form_after_fields', 'bap_hp_render_fields' );
add_filter( 'preprocess_comment', function( $commentdata ) {
    if ( bap_hp_is_enabled() && ! is_user_logged_in() ) {
        $ip        = bap_get_user_ip();
        $is_exempt = ( '' === $ip || bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) );
        if ( ! $is_exempt ) {
            if ( ! bap_hp_has_valid_local_referer() ) {
                bap_hp_block_and_log( 'Web: Comentario directo sin Referer local válido' );
                wp_die( '<strong>Bloqueo anti-spam:</strong> Envío de comentario no válido (origen no verificado).', 'Spam Bloqueado', [ 'response' => 403 ] );
            }
            $max_links = (int) get_option( 'bap_hp_max_comment_links', 0 );
            if ( $max_links > 0 ) {
                $content    = (string) ( $commentdata['comment_content'] ?? '' );
                $link_count = preg_match_all( '/https?:\/\//i', $content );
                if ( $link_count > $max_links ) {
                    bap_hp_block_and_log( "Web: Spam en comentario ({$link_count} enlaces > máx. {$max_links})" );
                    wp_die( '<strong>Bloqueo anti-spam:</strong> Tu comentario contiene demasiados enlaces externos.', 'Spam Bloqueado', [ 'response' => 403 ] );
                }
            }
        }
    }

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

// 3. Contact Form 7
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

// 4. WPForms
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