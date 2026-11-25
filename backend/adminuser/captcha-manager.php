<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_captcha_get_provider() {
    $allowed = [ 'none', 'math', 'altcha', 'recaptcha_v3', 'recaptcha_v2', 'turnstile', 'hcaptcha', 'friendly', 'mtcaptcha' ];
    $prov    = (string) get_option( 'bap_captcha_provider', 'none' );
    return in_array( $prov, $allowed, true ) ? $prov : 'none';
}

function bap_captcha_is_local_provider( $prov ) {
    return in_array( $prov, [ 'math', 'altcha' ], true );
}

function bap_captcha_is_enabled_for( $context ) {
    $prov = bap_captcha_get_provider();
    if ( 'none' === $prov ) {
        return false;
    }
    if ( ! bap_captcha_is_local_provider( $prov ) ) {
        $site_key   = trim( (string) get_option( 'bap_captcha_site_key', '' ) );
        $secret_key = trim( (string) get_option( 'bap_captcha_secret_key', '' ) );
        if ( '' === $site_key || '' === $secret_key ) {
            return false;
        }
    }
    $scopes = (array) get_option( 'bap_captcha_scopes', [ 'login', 'register', 'lostpassword' ] );
    return in_array( $context, $scopes, true );
}

function bap_captcha_render_widget( $context ) {
    if ( ! bap_captcha_is_enabled_for( $context ) ) {
        return;
    }
    if ( 'comments' === $context && is_user_logged_in() ) {
        return;
    }

    $prov     = bap_captcha_get_provider();
    $site_key = trim( (string) get_option( 'bap_captcha_site_key', '' ) );

    if ( 'math' === $prov ) {
        $a     = random_int( 1, 12 );
        $b     = random_int( 1, 9 );
        $token = bin2hex( random_bytes( 12 ) );
        set_transient( 'bap_cap_math_' . $token, wp_hash_password( (string) ( $a + $b ) ), 600 );
        ?>
        <p class="bap-captcha-box" style="margin:12px 0;">
            <label for="bap_captcha_math_ans"><strong>Verificación de seguridad:</strong> ¿Cuánto es <code><?php echo (int) $a; ?> + <?php echo (int) $b; ?></code>?</label>
            <input type="number" name="bap_captcha_math_ans" id="bap_captcha_math_ans" class="input" required autocomplete="off" style="margin-top:4px;" />
            <input type="hidden" name="bap_captcha_math_token" value="<?php echo esc_attr( $token ); ?>" />
        </p>
        <?php
        return;
    }

    if ( 'altcha' === $prov ) {
        $salt   = bin2hex( random_bytes( 12 ) );
        $secret = random_int( 15000, 45000 );
        $target = hash( 'sha256', $salt . ':' . $secret );
        $sig    = hash_hmac( 'sha256', $salt . '|' . $target, AUTH_SALT );
        ?>
        <div class="bap-captcha-box" style="margin:12px 0;padding:10px 12px;border:1px solid #c3c4c7;border-radius:4px;background:#f6f7f7;color:#1d2327;font-size:13px;">
            <span class="bap-altcha-status">⏳ Verificando navegador automáticamente...</span>
            <input type="hidden" name="bap_altcha_salt" value="<?php echo esc_attr( $salt ); ?>" />
            <input type="hidden" name="bap_altcha_target" value="<?php echo esc_attr( $target ); ?>" />
            <input type="hidden" name="bap_altcha_sig" value="<?php echo esc_attr( $sig ); ?>" />
            <input type="hidden" name="bap_altcha_sol" class="bap-altcha-sol" value="" />
        </div>
        <script>
        (async function(){
            var salt = <?php echo wp_json_encode( $salt ); ?>;
            var target = <?php echo wp_json_encode( $target ); ?>;
            var enc = new TextEncoder();
            for (var n = 0; n <= 100000; n++) {
                var buf = await crypto.subtle.digest('SHA-256', enc.encode(salt + ':' + n));
                var hex = Array.from(new Uint8Array(buf)).map(function(b){ return b.toString(16).padStart(2,'0'); }).join('');
                if (hex === target) {
                    document.querySelectorAll('.bap-altcha-sol').forEach(function(el){ el.value = String(n); });
                    document.querySelectorAll('.bap-altcha-status').forEach(function(el){ el.textContent = '✅ Verificación anti-bot completada'; });
                    break;
                }
            }
        })();
        </script>
        <?php
        return;
    }

    if ( 'recaptcha_v3' === $prov ) {
        $api_url = 'https://' . implode( '.', [ 'www', 'google', 'com' ] ) . '/recaptcha/api.js?render=' . rawurlencode( $site_key );
        ?>
        <input type="hidden" name="bap_g_recaptcha_v3_token" class="bap-recaptcha-v3-token" value="" />
        <script src="<?php echo esc_url( $api_url ); ?>"></script>
        <script>
        (function(){
            var sk = <?php echo wp_json_encode( $site_key ); ?>;
            var act = <?php echo wp_json_encode( 'bap_' . $context ); ?>;
            function refreshBapV3(){
                if (typeof grecaptcha !== 'undefined' && grecaptcha.ready) {
                    grecaptcha.ready(function(){
                        grecaptcha.execute(sk, {action: act}).then(function(tok){
                            document.querySelectorAll('.bap-recaptcha-v3-token').forEach(function(el){ el.value = tok; });
                        });
                    });
                }
            }
            refreshBapV3();
            setInterval(refreshBapV3, 90000);
        })();
        </script>
        <?php
        return;
    }

    if ( 'recaptcha_v2' === $prov ) {
        $api_url = 'https://' . implode( '.', [ 'www', 'google', 'com' ] ) . '/recaptcha/api.js';
        ?>
        <div class="bap-captcha-box" style="margin:12px 0;">
            <script src="<?php echo esc_url( $api_url ); ?>" async defer></script>
            <div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
        </div>
        <?php
        return;
    }

    if ( 'turnstile' === $prov ) {
        $api_url = 'https://' . implode( '.', [ 'challenges', 'cloudflare', 'com' ] ) . '/turnstile/v0/api.js';
        ?>
        <div class="bap-captcha-box" style="margin:12px 0;">
            <script src="<?php echo esc_url( $api_url ); ?>" async defer></script>
            <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
        </div>
        <?php
        return;
    }

    if ( 'hcaptcha' === $prov ) {
        $api_url = 'https://' . implode( '.', [ 'js', 'hcaptcha', 'com' ] ) . '/1/api.js';
        ?>
        <div class="bap-captcha-box" style="margin:12px 0;">
            <script src="<?php echo esc_url( $api_url ); ?>" async defer></script>
            <div class="h-captcha" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
        </div>
        <?php
        return;
    }

    if ( 'friendly' === $prov ) {
        $api_url = 'https://' . implode( '.', [ 'cdn', 'jsdelivr', 'net' ] ) . '/npm/friendly-challenge@0.9.16/widget.module.min.js';
        ?>
        <div class="bap-captcha-box" style="margin:12px 0;">
            <script type="module" src="<?php echo esc_url( $api_url ); ?>" async defer></script>
            <div class="frc-captcha" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-lang="es"></div>
        </div>
        <?php
        return;
    }

    if ( 'mtcaptcha' === $prov ) {
        $s1 = 'https://' . implode( '.', [ 'service', 'mtcaptcha', 'com' ] ) . '/mtcv1/client/mtcaptcha.min.js';
        $s2 = 'https://' . implode( '.', [ 'service2', 'mtcaptcha', 'com' ] ) . '/mtcv1/client/mtcaptcha2.min.js';
        ?>
        <div class="bap-captcha-box" style="margin:12px 0;">
            <div class="mtcaptcha"></div>
            <script>var mtcaptchaConfig = { "sitekey": <?php echo wp_json_encode( $site_key ); ?>, "lang": "es" };</script>
            <script src="<?php echo esc_url( $s1 ); ?>" async defer></script>
            <script src="<?php echo esc_url( $s2 ); ?>" async defer></script>
        </div>
        <?php
    }
}

function bap_captcha_verify_submission( $context ) {
    if ( ! bap_captcha_is_enabled_for( $context ) ) {
        return true;
    }

    $ip = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
    if ( '' !== $ip && ( bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) ) {
        return true;
    }

    $prov = bap_captcha_get_provider();

    if ( 'math' === $prov ) {
        $ans   = trim( sanitize_text_field( wp_unslash( $_POST['bap_captcha_math_ans'] ?? '' ) ) );
        $token = trim( sanitize_text_field( wp_unslash( $_POST['bap_captcha_math_token'] ?? '' ) ) );
        $hash  = ( '' !== $token ) ? get_transient( 'bap_cap_math_' . $token ) : false;
        if ( '' !== $token ) {
            delete_transient( 'bap_cap_math_' . $token );
        }
        if ( false === $hash || '' === $ans || ! wp_check_password( $ans, $hash ) ) {
            return new WP_Error( 'bap_captcha_fail', '<strong>Verificación fallida:</strong> La respuesta matemática del CAPTCHA es incorrecta o ha caducado.' );
        }
        return true;
    }

    if ( 'altcha' === $prov ) {
        $salt   = trim( sanitize_text_field( wp_unslash( $_POST['bap_altcha_salt'] ?? '' ) ) );
        $target = trim( sanitize_text_field( wp_unslash( $_POST['bap_altcha_target'] ?? '' ) ) );
        $sig    = trim( sanitize_text_field( wp_unslash( $_POST['bap_altcha_sig'] ?? '' ) ) );
        $sol    = trim( sanitize_text_field( wp_unslash( $_POST['bap_altcha_sol'] ?? '' ) ) );

        if ( '' === $salt || '' === $target || '' === $sig || '' === $sol || ! ctype_digit( $sol ) ) {
            return new WP_Error( 'bap_captcha_fail', '<strong>Verificación fallida:</strong> No se completó la prueba anti-bot del navegador.' );
        }
        if ( ! hash_equals( hash_hmac( 'sha256', $salt . '|' . $target, AUTH_SALT ), $sig ) ) {
            return new WP_Error( 'bap_captcha_fail', '<strong>Verificación fallida:</strong> Firma de seguridad inválida.' );
        }
        if ( ! hash_equals( $target, hash( 'sha256', $salt . ':' . $sol ) ) ) {
            return new WP_Error( 'bap_captcha_fail', '<strong>Verificación fallida:</strong> Solución criptográfica incorrecta.' );
        }
        return true;
    }

    $secret   = trim( (string) get_option( 'bap_captcha_secret_key', '' ) );
    $site_key = trim( (string) get_option( 'bap_captcha_site_key', '' ) );

    $field_map = [
        'recaptcha_v3' => 'bap_g_recaptcha_v3_token',
        'recaptcha_v2' => 'g-recaptcha-response',
        'turnstile'    => 'cf-turnstile-response',
        'hcaptcha'     => 'h-captcha-response',
        'friendly'     => 'frc-captcha-solution',
        'mtcaptcha'    => 'mtcaptcha-verifiedtoken',
    ];

    $field          = $field_map[ $prov ] ?? '';
    $response_token = trim( sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) ) );
    if ( '' === $response_token ) {
        return new WP_Error( 'bap_captcha_missing', '<strong>Verificación requerida:</strong> Completa el CAPTCHA antes de continuar.' );
    }

    if ( 'mtcaptcha' === $prov ) {
        $mt_url = 'https://' . implode( '.', [ 'service', 'mtcaptcha', 'com' ] ) . '/mtcv1/api/checktoken?privatekey=' . rawurlencode( $secret ) . '&token=' . rawurlencode( $response_token );
        $res    = wp_remote_get( $mt_url, [ 'timeout' => 8 ] );
        if ( is_wp_error( $res ) ) {
            return true;
        }
        $data = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( empty( $data['success'] ) ) {
            return new WP_Error( 'bap_captcha_invalid', '<strong>Verificación MTCaptcha fallida:</strong> Token inválido o caducado.' );
        }
        return true;
    }

    $endpoints = [
        'recaptcha_v3' => 'https://' . implode( '.', [ 'www', 'google', 'com' ] ) . '/recaptcha/api/siteverify',
        'recaptcha_v2' => 'https://' . implode( '.', [ 'www', 'google', 'com' ] ) . '/recaptcha/api/siteverify',
        'turnstile'    => 'https://' . implode( '.', [ 'challenges', 'cloudflare', 'com' ] ) . '/turnstile/v0/siteverify',
        'hcaptcha'     => 'https://' . implode( '.', [ 'api', 'hcaptcha', 'com' ] ) . '/siteverify',
        'friendly'     => 'https://' . implode( '.', [ 'api', 'friendlycaptcha', 'com' ] ) . '/api/v1/siteverify',
    ];

    $body_args = [
        'secret'   => $secret,
        'response' => $response_token,
        'remoteip' => $ip,
    ];
    if ( 'friendly' === $prov ) {
        $body_args = [
            'secret'   => $secret,
            'solution' => $response_token,
            'sitekey'  => $site_key,
        ];
    }

    $res = wp_remote_post( $endpoints[ $prov ], [
        'timeout' => 8,
        'body'    => $body_args,
    ] );

    if ( is_wp_error( $res ) ) {
        return true;
    }

    $data = json_decode( wp_remote_retrieve_body( $res ), true );
    if ( empty( $data['success'] ) ) {
        return new WP_Error( 'bap_captcha_invalid', '<strong>Verificación CAPTCHA fallida:</strong> No se pudo validar que seas humano.' );
    }

    if ( 'recaptcha_v3' === $prov ) {
        $min_score = max( 0.1, min( 0.9, (float) get_option( 'bap_recaptcha_v3_score', 0.5 ) ) );
        $score     = (float) ( $data['score'] ?? 0.0 );
        if ( $score < $min_score ) {
            return new WP_Error( 'bap_captcha_score', sprintf( '<strong>Acceso denegado por reCAPTCHA v3:</strong> Puntuación de riesgo sospechosa (%.1f < %.1f).', $score, $min_score ) );
        }
    }

    return true;
}

function bap_captcha_log_failure( $context ) {
    if ( ! function_exists( 'bap_log_blocked_attempt' ) || ! function_exists( 'bap_get_user_ip' ) ) {
        return;
    }
    $ip      = bap_get_user_ip();
    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    $prov    = strtoupper( bap_captcha_get_provider() );
    $prefix  = ( 'comments' === $context ) ? 'Web: ' : '';
    bap_log_blocked_attempt( $ip, $country, "{$prefix}CAPTCHA fallido ({$prov} en {$context})" );
}

add_action( 'login_form', fn() => bap_captcha_render_widget( 'login' ) );
add_filter( 'authenticate', function( $user, $username, $password ) {
    if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || '' === trim( (string) $username ) ) {
        return $user;
    }
    if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
        return $user;
    }
    $check = bap_captcha_verify_submission( 'login' );
    if ( is_wp_error( $check ) ) {
        bap_captcha_log_failure( 'login' );
        return $check;
    }
    return $user;
}, 20, 3 );

add_action( 'register_form', fn() => bap_captcha_render_widget( 'register' ) );
add_filter( 'registration_errors', function( $errors ) {
    if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
        return $errors;
    }
    $check = bap_captcha_verify_submission( 'register' );
    if ( is_wp_error( $check ) ) {
        bap_captcha_log_failure( 'register' );
        $errors->add( $check->get_error_code(), $check->get_error_message() );
    }
    return $errors;
}, 5 );

add_action( 'lostpassword_form', fn() => bap_captcha_render_widget( 'lostpassword' ) );
add_action( 'lostpassword_post', function( $errors ) {
    if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
        return;
    }
    $check = bap_captcha_verify_submission( 'lostpassword' );
    if ( is_wp_error( $check ) ) {
        bap_captcha_log_failure( 'lostpassword' );
        $errors->add( $check->get_error_code(), $check->get_error_message() );
    }
}, 5 );

add_action( 'comment_form_after_fields', fn() => bap_captcha_render_widget( 'comments' ) );
add_filter( 'preprocess_comment', function( $commentdata ) {
    if ( is_user_logged_in() ) {
        return $commentdata;
    }
    $check = bap_captcha_verify_submission( 'comments' );
    if ( is_wp_error( $check ) ) {
        bap_captcha_log_failure( 'comments' );
        wp_die( wp_kses_post( $check->get_error_message() ), 'Verificación CAPTCHA', [ 'response' => 403, 'back_link' => true ] );
    }
    return $commentdata;
}, 2 );
