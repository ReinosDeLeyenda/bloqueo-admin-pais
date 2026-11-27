<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_has_local_databases_ready() {
    $upload_dir = wp_upload_dir();
    $base       = trailingslashit( $upload_dir['basedir'] ) . 'bloqueo-admin-pais/';
    if ( ! is_dir( $base ) ) { return false; }
    $files = @glob( $base . '*.{mmdb,bin,csv,txt,dat,json}', GLOB_BRACE );
    return ! empty( $files );
}

function bap_render_post_db_recommendations_box() {
    if ( ! current_user_can( 'manage_options' ) || ! bap_has_local_databases_ready() ) { return; }

    $notice = '';
    if ( isset( $_POST['bap_apply_recommended_preset'] ) && check_admin_referer( 'bap_preset_action', 'bap_preset_nonce' ) ) {
        $preset = sanitize_key( (string) wp_unslash( $_POST['bap_apply_recommended_preset'] ) );
        $me     = function_exists( 'bap_get_current_admin_network_profile' ) ? bap_get_current_admin_network_profile() : [ 'ip' => '', 'country' => '', 'asn' => '' ];
        $my_ip  = (string) ( $me['ip'] ?? '' );
        $my_cc  = strtoupper( (string) ( $me['country'] ?? '' ) );

        if ( '' !== $my_ip && filter_var( $my_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            $wl = trim( (string) get_option( 'bap_whitelist_ips', '' ) );
            if ( false === strpos( $wl, $my_ip ) ) {
                update_option( 'bap_whitelist_ips', ( '' !== $wl ? $wl . "\n" : '' ) . $my_ip . ' # Admin protegido (Asistente)' );
            }
        }

        if ( 'backend_country' === $preset && '' !== $my_cc && 'XX' !== $my_cc ) {
            update_option( 'bap_country_mode', 'allow' );
            update_option( 'bap_countries', [ $my_cc ] );
            $notice = 'Backend restringido exclusivamente a tu pais actual (<strong>' . esc_html( $my_cc ) . '</strong>) con tu IP blindada en Lista Blanca.';
        } elseif ( 'threat_lists' === $preset ) {
            update_option( 'bap_block_tor', '1' );
            update_option( 'bap_block_hosting', '1' );
            update_option( 'bap_block_proxies', '1' );
            update_option( 'bap_fe_block_tor', '1' );
            update_option( 'bap_fe_form_antispam_enabled', '1' );
            $notice = 'Activado el bloqueo contra nodos Tor, redes de hosting/bots (ASN), proxies y escudo anti-spam en formularios.';
        }
    }

    $me    = function_exists( 'bap_get_current_admin_network_profile' ) ? bap_get_current_admin_network_profile() : [ 'ip' => '', 'country' => '', 'asn' => '' ];
    $my_ip = (string) ( $me['ip'] ?? '' );
    $my_cc = strtoupper( (string) ( $me['country'] ?? '' ) );
    $be_on = ( 'off' !== (string) get_option( 'bap_country_mode', 'off' ) && ! empty( get_option( 'bap_countries', [] ) ) );
    $th_on = ( '1' === (string) get_option( 'bap_block_tor', '0' ) || '1' === (string) get_option( 'bap_block_hosting', '0' ) );

    echo '<div class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;border-left:4px solid #00a32a;">';
    echo '<h2 style="margin-top:0;">Sugerencias de Proteccion Recomendadas (Elige una opcion)</h2>';
    if ( '' !== $notice ) {
        echo '<div class="notice notice-success inline"><p>' . wp_kses_post( $notice ) . '</p></div>';
    }
    echo '<p class="description">Las bases de datos locales ya estan instaladas. Elige <strong>una de estas dos modalidades</strong> (tu IP <code>' . esc_html( $my_ip ?: 'actual' ) . '</code> se blinda automaticamente en Lista Blanca):</p>';
    echo '<form method="post" style="margin-top:14px;">';
    wp_nonce_field( 'bap_preset_action', 'bap_preset_nonce' );
    echo '<div style="border:1px solid #cbd5e1;border-radius:12px;padding:16px;background:#f1f5f9;">';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:16px;align-items:stretch;">';

    echo '<div style="border:2px solid #2271b1;border-radius:10px;padding:16px 18px;background:#fff;display:flex;flex-direction:column;justify-content:space-between;">';
    echo '<div><span style="display:inline-block;background:#2271b1;color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;margin-bottom:8px;">OPCION A &middot; RECOMENDADA PARA WEBS LOCALES</span>';
    echo '<h3 style="margin:4px 0 8px 0;font-size:15px;">Restringir Panel Admin solo a tu pais ' . ( '' !== $my_cc && 'XX' !== $my_cc ? '(<code>' . esc_html( $my_cc ) . '</code>)' : '' ) . '</h3>';
    echo '<p style="margin:0 0 14px 0;color:#475569;font-size:13px;">Bloquea todos los intentos a <code>wp-login.php</code> y <code>wp-admin</code> desde el extranjero sin afectar a los visitantes de tu web.</p></div>';
    echo '<div>' . ( $be_on ? '<span style="color:#00a32a;font-weight:700;">&#10003; Opcion A activa</span>' : '<button type="submit" name="bap_apply_recommended_preset" value="backend_country" class="button button-primary">Elegir Opcion A (Solo mi pais)</button>' ) . '</div>';
    echo '</div>';

    echo '<div style="border:2px solid #64748b;border-radius:10px;padding:16px 18px;background:#fff;display:flex;flex-direction:column;justify-content:space-between;">';
    echo '<div><span style="display:inline-block;background:#475569;color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;margin-bottom:8px;">OPCION B &middot; ALTERNATIVA GLOBAL (SIN BLOQUEO PAIS)</span>';
    echo '<h3 style="margin:4px 0 8px 0;font-size:15px;">Bloquear redes automatizadas (Tor, Hosting/ASN y Proxies)</h3>';
    echo '<p style="margin:0 0 14px 0;color:#475569;font-size:13px;">Ideal si administras la web desde varios paises: permite cualquier pais pero frena bots de datacenter, nodos Tor y proxies.</p></div>';
    echo '<div>' . ( $th_on ? '<span style="color:#00a32a;font-weight:700;">&#10003; Opcion B activa</span>' : '<button type="submit" name="bap_apply_recommended_preset" value="threat_lists" class="button button-secondary">Elegir Opcion B (Bots y ASN)</button>' ) . '</div>';
    echo '</div>';

    echo '</div></div></form></div>';
}