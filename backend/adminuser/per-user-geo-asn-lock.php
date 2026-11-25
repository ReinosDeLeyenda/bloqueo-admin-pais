<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_render_per_user_geo_asn_fields( $user ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $countries = (string) get_user_meta( $user->ID, '_bap_user_allowed_countries', true );
    $asns      = (string) get_user_meta( $user->ID, '_bap_user_allowed_asns', true );
    ?>
    <h2>Restricción de Red por Cuenta (País y ASN)</h2>
    <table class="form-table">
        <tr>
            <th><label for="bap_user_allowed_countries">Países permitidos (ISO)</label></th>
            <td>
                <input type="text" id="bap_user_allowed_countries" name="bap_user_allowed_countries" value="<?php echo esc_attr( $countries ); ?>" class="regular-text" placeholder="Ej: ES, FR (vacío = sin restricción individual)" />
                <p class="description">Si indicas códigos ISO, esta cuenta solo podrá iniciar sesión desde esos países (salvo usando la <strong>Llave Secreta URL</strong>).</p>
            </td>
        </tr>
        <tr>
            <th><label for="bap_user_allowed_asns">ASNs permitidos</label></th>
            <td>
                <input type="text" id="bap_user_allowed_asns" name="bap_user_allowed_asns" value="<?php echo esc_attr( $asns ); ?>" class="regular-text code" placeholder="Ej: AS57269, AS3352 (vacío = sin restricción individual)" />
                <p class="description">Si indicas ASNs, esta cuenta solo podrá iniciar sesión desde esos operadores/redes.</p>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'bap_render_per_user_geo_asn_fields', 26 );
add_action( 'edit_user_profile', 'bap_render_per_user_geo_asn_fields', 26 );

function bap_save_per_user_geo_asn_fields( $user_id ) {
    if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }
    if ( isset( $_POST['bap_user_allowed_countries'] ) ) {
        $raw_c = wp_unslash( $_POST['bap_user_allowed_countries'] );
        $clean_c = implode( ', ', array_filter( bap_parse_list( $raw_c, true ), fn( $c ) => (bool) preg_match( '/^[A-Z]{2}$/', $c ) ) );
        if ( '' !== $clean_c ) {
            update_user_meta( $user_id, '_bap_user_allowed_countries', $clean_c );
        } else {
            delete_user_meta( $user_id, '_bap_user_allowed_countries' );
        }
    }
    if ( isset( $_POST['bap_user_allowed_asns'] ) ) {
        $raw_a   = wp_unslash( $_POST['bap_user_allowed_asns'] );
        $clean_a = function_exists( 'bap_sanitize_asn_list' ) ? bap_sanitize_asn_list( $raw_a ) : implode( ', ', bap_parse_list( $raw_a, true ) );
        if ( '' !== $clean_a ) {
            update_user_meta( $user_id, '_bap_user_allowed_asns', $clean_a );
        } else {
            delete_user_meta( $user_id, '_bap_user_allowed_asns' );
        }
    }
}
add_action( 'personal_options_update', 'bap_save_per_user_geo_asn_fields', 26 );
add_action( 'edit_user_profile_update', 'bap_save_per_user_geo_asn_fields', 26 );

add_filter( 'authenticate', function( $user, $username, $password ) {
    if ( ! ( $user instanceof WP_User ) ) {
        return $user;
    }
    if ( function_exists( 'bap_vacation_has_secret_bypass' ) && bap_vacation_has_secret_bypass( $user->ID ) ) {
        return $user;
    }

    $ip = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
    if ( '' === $ip || bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, bap_parse_list( get_option( 'bap_whitelist_ips', '' ) ) ) ) {
        return $user;
    }

    $allowed_c = bap_parse_list( (string) get_user_meta( $user->ID, '_bap_user_allowed_countries', true ), true );
    $allowed_a = bap_parse_list( (string) get_user_meta( $user->ID, '_bap_user_allowed_asns', true ), true );

    if ( empty( $allowed_c ) && empty( $allowed_a ) ) {
        return $user;
    }

    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';

    if ( ! empty( $allowed_c ) && ! in_array( $country, $allowed_c, true ) ) {
        if ( function_exists( 'bap_log_blocked_attempt' ) ) {
            bap_log_blocked_attempt( $ip, $country, "Bloqueo por Cuenta ({$user->user_login}): País no autorizado ({$country})" );
        }
        return new WP_Error( 'bap_user_geo_lock', '<strong>Acceso denegado:</strong> Tu cuenta no tiene permiso para iniciar sesión desde este país (' . esc_html( $country ) . ').' );
    }

    if ( ! empty( $allowed_a ) && function_exists( 'bap_get_asn_by_ip' ) ) {
        $asn_code  = '';
        $asn_label = bap_get_asn_by_ip( $ip, $asn_code );
        if ( '' === $asn_code || ! in_array( $asn_code, $allowed_a, true ) ) {
            if ( function_exists( 'bap_log_blocked_attempt' ) ) {
                bap_log_blocked_attempt( $ip, $country, "Bloqueo por Cuenta ({$user->user_login}): ASN no autorizado (" . ( $asn_label ?: 'N/D' ) . ')' );
            }
            return new WP_Error( 'bap_user_asn_lock', '<strong>Acceso denegado:</strong> Tu cuenta no tiene permiso para iniciar sesión desde este operador/red (' . esc_html( $asn_label ?: 'N/D' ) . ').' );
        }
    }

    return $user;
}, 31, 3 );