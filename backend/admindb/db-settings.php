<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_init', function() {
    register_setting( 'bap_db_settings_group', 'bap_abuseipdb_key', [
        'sanitize_callback' => function( $i ) {
            $lines = array_filter( array_map( 'trim', preg_split( '/[\r\n,;]+/', (string) $i ) ) );
            return implode( "\n", array_unique( array_map( 'sanitize_text_field', $lines ) ) );
        },
        'default'           => '',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_db_auto_update_freq', [
        'sanitize_callback' => function( $i ) {
            $allowed = [ 'none', 'daily', 'weekly', '2weeks', 'monthly' ];
            return in_array( $i, $allowed, true ) ? $i : 'none';
        },
        'default' => 'none',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_enabled', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '0',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_host', [
        'sanitize_callback' => fn( $i ) => trim( sanitize_text_field( (string) $i ) ),
        'default'           => '',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_port', [
        'sanitize_callback' => fn( $i ) => max( 1, min( 65535, (int) $i ) ),
        'default'           => 3306,
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_name', [
        'sanitize_callback' => fn( $i ) => trim( sanitize_text_field( (string) $i ) ),
        'default'           => '',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_user', [
        'sanitize_callback' => fn( $i ) => trim( sanitize_text_field( (string) $i ) ),
        'default'           => '',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_pass', [
        'sanitize_callback' => function( $i ) {
            $raw = (string) $i;
            if ( '' === trim( $raw ) ) {
                return get_option( 'bap_ext_db_pass', '' );
            }
            return function_exists( 'bap_encrypt_ext_pass' ) ? bap_encrypt_ext_pass( $raw ) : $raw;
        },
        'default' => '',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_table', [
        'sanitize_callback' => fn( $i ) => preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $i ) ?: 'bap_global_blocked_logs',
        'default'           => 'bap_global_blocked_logs',
    ]);
});

add_action( 'update_option_bap_db_auto_update_freq', function( $old_val, $new_val ) {
    if ( function_exists( 'bap_reschedule_db_cron' ) ) {
        bap_reschedule_db_cron( $new_val );
    }
}, 10, 2 );

add_action( 'bap_auto_update_dbs_event', 'bap_run_all_db_updates' );

function bap_render_external_db_fields() {
    ?>
    <tr><th colspan="2" style="padding-bottom:0;"><h3 style="margin:0;">Servidor MySQL Externo (Logs Centralizados)</h3></th></tr>
    <tr>
        <th scope="row">Activar MySQL externo</th>
        <td>
            <label>
                <input type="checkbox" name="bap_ext_db_enabled" value="1" <?php checked( get_option( 'bap_ext_db_enabled', '0' ), '1' ); ?> />
                Guardar registros de ataques en servidor MySQL remoto (si falla, guarda en local)
            </label>
        </td>
    </tr>
    <tr>
        <th scope="row">Host y Puerto</th>
        <td>
            <input type="text" name="bap_ext_db_host" value="<?php echo esc_attr( get_option( 'bap_ext_db_host', '' ) ); ?>" class="regular-text" placeholder="ej. ://tudominio.com o 192.168.1.50" />
            <input type="number" name="bap_ext_db_port" value="<?php echo (int) get_option( 'bap_ext_db_port', 3306 ); ?>" min="1" max="65535" style="width:85px;" />
        </td>
    </tr>
    <tr>
        <th scope="row">Base de Datos</th>
        <td><input type="text" name="bap_ext_db_name" value="<?php echo esc_attr( get_option( 'bap_ext_db_name', '' ) ); ?>" class="regular-text" placeholder="nombre_bd" /></td>
    </tr>
    <tr>
        <th scope="row">Usuario</th>
        <td><input type="text" name="bap_ext_db_user" value="<?php echo esc_attr( get_option( 'bap_ext_db_user', '' ) ); ?>" class="regular-text" placeholder="usuario_mysql" /></td>
    </tr>
    <tr>
        <th scope="row">Contraseña</th>
        <td><input type="password" name="bap_ext_db_pass" value="" class="regular-text" placeholder="<?php echo '' !== get_option( 'bap_ext_db_pass', '' ) ? '•••••••• (dejar vacío para mantener)' : 'Contraseña MySQL'; ?>" autocomplete="new-password" /></td>
    </tr>
    <tr>
        <th scope="row">Nombre de la tabla</th>
        <td><input type="text" name="bap_ext_db_table" value="<?php echo esc_attr( get_option( 'bap_ext_db_table', 'bap_global_blocked_logs' ) ); ?>" class="regular-text code" /></td>
    </tr>
    <?php
}
