<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Detectar si el acceso actual usa la Llave Secreta URL y marcar la sesión
 */
add_action( 'init', function() {
    if ( bap_has_valid_secret_bypass( false ) ) {
        if ( ! defined( 'BAP_USED_SECRET_KEY' ) ) {
            define( 'BAP_USED_SECRET_KEY', true );
        }
        if ( is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), '_bap_secret_key_session', '1' );
        }
    }
}, 2 );

add_action( 'wp_login', function( $user_login, $user ) {
    if ( ! ( $user instanceof WP_User ) ) {
        return;
    }
    if ( defined( 'BAP_USED_SECRET_KEY' ) && BAP_USED_SECRET_KEY ) {
        update_user_meta( $user->ID, '_bap_secret_key_session', '1' );
    } else {
        delete_user_meta( $user->ID, '_bap_secret_key_session' );
    }
}, 5, 2 );

add_action( 'wp_logout', function( $user_id ) {
    if ( $user_id > 0 ) {
        delete_user_meta( $user_id, '_bap_secret_key_session' );
    }
} );

/**
 * 2. Cambiar el fondo de wp-admin a amarillo si se inició sesión con la Llave Secreta
 */
add_action( 'admin_head', function() {
    if ( ! is_user_logged_in() ) {
        return;
    }
    $uid        = get_current_user_id();
    $via_secret = ( defined( 'BAP_USED_SECRET_KEY' ) && BAP_USED_SECRET_KEY )
                  || ( '1' === (string) get_user_meta( $uid, '_bap_secret_key_session', true ) );

    if ( $via_secret ) {
        echo '<style>
            html, body.wp-admin, #wpwrap, #wpcontent, #wpbody, #wpbody-content {
                background-color: #fef08a !important;
            }
            #wpadminbar {
                background: #ca8a04 !important;
            }
        </style>';
    }
} );

/**
 * 3. Detectar cambio de ASN/ISP al iniciar sesión y guardar datos comparativos
 */
add_action( 'wp_login', function( $user_login, $user ) {
    if ( ! ( $user instanceof WP_User ) || ! function_exists( 'bap_get_user_ip' ) ) {
        return;
    }

    $ip      = bap_get_user_ip();
    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'N/D';
    $asn_raw = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : 'N/D';

    $is_valid = ( '' !== $asn_raw && 'Sin base de datos ASN' !== $asn_raw && 'IP inválida' !== $asn_raw && 'N/D' !== $asn_raw );
    if ( ! $is_valid ) {
        return;
    }

    $parts   = explode( ' — ', $asn_raw, 2 );
    $cur_asn = trim( $parts[0] ?? $asn_raw );
    $cur_isp = trim( $parts[1] ?? $asn_raw );

    $prev = get_user_meta( $user->ID, '_bap_prev_session_net', true );
    $cur  = [
        'asn'     => $cur_asn,
        'isp'     => $cur_isp,
        'full'    => $asn_raw,
        'country' => $country,
        'ip'      => $ip,
        'time'    => current_time( 'mysql' ),
    ];

    if ( is_array( $prev ) && ! empty( $prev['asn'] ) && $prev['asn'] !== $cur_asn ) {
        update_user_meta( $user->ID, '_bap_asn_mismatch_alert', [
            'prev' => $prev,
            'curr' => $cur,
        ] );
    }

    update_user_meta( $user->ID, '_bap_prev_session_net', $cur );
}, 15, 2 );

/**
 * 4. Procesar botón "OK" (descartar banner) y mostrar banner rojo en wp-admin
 */
add_action( 'admin_init', function() {
    if ( isset( $_POST['bap_dismiss_asn_alert'] ) && is_user_logged_in() && check_admin_referer( 'bap_dismiss_asn_nonce' ) ) {
        delete_user_meta( get_current_user_id(), '_bap_asn_mismatch_alert' );
        wp_safe_redirect( remove_query_arg( '_wp_http_referer' ) );
        exit;
    }
} );

add_action( 'admin_notices', function() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $uid   = get_current_user_id();
    $alert = get_user_meta( $uid, '_bap_asn_mismatch_alert', true );
    if ( ! is_array( $alert ) || empty( $alert['prev'] ) || empty( $alert['curr'] ) ) {
        return;
    }

    $prev        = $alert['prev'];
    $curr        = $alert['curr'];
    $profile_url = esc_url( get_edit_profile_url( $uid ) . '#password' );
    ?>
    <div class="notice" style="background:#991b1b;color:#ffffff;border:2px solid #ef4444;border-left-width:8px;border-radius:6px;padding:16px 20px;margin:16px 20px 12px 0;box-shadow:0 6px 18px rgba(0,0,0,0.35);">
        <h2 style="margin:0 0 10px 0;color:#ffffff;font-size:18px;font-weight:800;letter-spacing:0.3px;">
            &#9888;&#65039; AVISO: Tu ISP / ASN actual difiere de los registros anteriores
        </h2>

        <div style="background:rgba(0,0,0,0.28);border:1px solid rgba(255,255,255,0.2);border-radius:4px;padding:10px 14px;margin-bottom:14px;font-size:13px;line-height:1.6;">
            <div>
                <strong style="color:#fca5a5;">Sesión anterior:</strong>
                ASN: <code><?php echo esc_html( $prev['asn'] ); ?></code> |
                ISP: <strong><?php echo esc_html( $prev['isp'] ); ?></strong> |
                País: <strong><?php echo esc_html( $prev['country'] ); ?></strong>
                <small style="color:#fecaca;">(IP: <?php echo esc_html( $prev['ip'] ); ?> · <?php echo esc_html( $prev['time'] ); ?>)</small>
            </div>
            <div style="margin-top:4px;">
                <strong style="color:#fde047;">Sesión actual:</strong>
                ASN: <code><?php echo esc_html( $curr['asn'] ); ?></code> |
                ISP: <strong><?php echo esc_html( $curr['isp'] ); ?></strong> |
                País: <strong><?php echo esc_html( $curr['country'] ); ?></strong>
                <small style="color:#fecaca;">(IP: <?php echo esc_html( $curr['ip'] ); ?>)</small>
            </div>
        </div>

        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <form method="post" style="margin:0;">
                <?php wp_nonce_field( 'bap_dismiss_asn_nonce' ); ?>
                <button type="submit" name="bap_dismiss_asn_alert" class="button" style="background:#ffffff;color:#991b1b;border-color:#ffffff;font-weight:700;padding:2px 16px;">
                    OK
                </button>
            </form>
            <a href="<?php echo $profile_url; ?>" class="button" style="background:#dc2626;color:#ffffff;border-color:#fca5a5;font-weight:700;">
                Cambiar contraseña
            </a>
        </div>
    </div>
    <?php
} );