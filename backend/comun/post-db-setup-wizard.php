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
    echo '<h2 style="margin-top:0;">Sugerencias de Proteccion Recomendadas (Bases de Datos Listas)</h2>';
    if ( '' !== $notice ) {
        echo '<div class="notice notice-success inline"><p>' . wp_kses_post( $notice ) . '</p></div>';
    }
    echo '<p class="description">Las bases de datos locales ya estan instaladas. Puedes aplicar estas protecciones seguras en 1 clic sin riesgo de autobloqueo (tu IP <code>' . esc_html( $my_ip ?: 'actual' ) . '</code> se anadira automaticamente a la Lista Blanca):</p>';
    echo '<form method="post" style="margin-top:12px;">';
    wp_nonce_field( 'bap_preset_action', 'bap_preset_nonce' );
    echo '<table class="widefat striped"><thead><tr><th>Recomendacion</th><th>Impacto</th><th style="width:210px;">Accion</th></tr></thead><tbody>';
    echo '<tr><td><strong>1. Restringir acceso al Panel Admin solo a tu pais ' . ( '' !== $my_cc && 'XX' !== $my_cc ? '(<code>' . esc_html( $my_cc ) . '</code>)' : '' ) . '</strong></td>';
    echo '<td>Bloquea ataques a <code>wp-login.php</code> y <code>wp-admin</code> desde el extranjero sin afectar a los visitantes de tu web.</td>';
    echo '<td>' . ( $be_on ? '<span style="color:#00a32a;font-weight:700;">&#10003; Ya configurado</span>' : '<button type="submit" name="bap_apply_recommended_preset" value="backend_country" class="button button-primary">Aplicar en Backend</button>' ) . '</td></tr>';
    echo '<tr><td><strong>2. Bloquear redes automatizadas (Tor, Hosting/Datacenters ASN y Proxies)</strong></td>';
    echo '<td>Detiene bots de fuerza bruta y spam que operan desde servidores cloud y nodos de salida Tor.</td>';
    echo '<td>' . ( $th_on ? '<span style="color:#00a32a;font-weight:700;">&#10003; Ya activo</span>' : '<button type="submit" name="bap_apply_recommended_preset" value="threat_lists" class="button button-secondary">Activar Proteccion Bots/ASN</button>' ) . '</td></tr>';
    echo '</tbody></table></form></div>';
}