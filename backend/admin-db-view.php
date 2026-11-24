<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once plugin_dir_path( __FILE__ ) . 'admin-db-cards.php';

$ext_test_notice = '';
if ( isset( $_POST['bap_test_ext_db'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_ext_db_test_action', 'bap_ext_db_nonce' ) ) {
    $err    = '';
    $ext_db = function_exists( 'bap_get_external_wpdb' ) ? bap_get_external_wpdb( $err ) : null;
    if ( $ext_db ) {
        $tbl = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) get_option( 'bap_ext_db_table', 'bap_global_blocked_logs' ) );
        $ok  = function_exists( 'bap_ensure_external_logs_table' ) ? bap_ensure_external_logs_table( $ext_db, $tbl ) : true;
        $ext_test_notice = $ok
            ? '<div class="notice notice-success is-dismissible"><p><strong>Conexión exitosa:</strong> Servidor MySQL conectado y tabla <code>' . esc_html( $tbl ) . '</code> verificada.</p></div>'
            : '<div class="notice notice-warning is-dismissible"><p>Conectado al servidor, pero no se pudo crear/verificar la tabla <code>' . esc_html( $tbl ) . '</code>.</p></div>';
    } else {
        $ext_test_notice = '<div class="notice notice-error is-dismissible"><p><strong>Error de conexión:</strong> ' . esc_html( $err ?: 'Activa primero MySQL externo y guarda los datos.' ) . '</p></div>';
    }
}
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-admin-db' ); ?>
    <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php echo $ext_test_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <div class="card" style="max-width:1180px;padding:14px 20px;margin-bottom:20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <strong>Acciones masivas:</strong>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field( 'bap_db_action' ); ?>
            <input type="submit" name="bap_update_all_dbs" class="button button-primary" value="Descargar / Actualizar TODAS las bases de datos" />
        </form>
        <form method="post" style="display:inline;" onsubmit="return confirm('¿Seguro que quieres borrar TODAS las bases de datos locales?');">
            <?php wp_nonce_field( 'bap_db_action' ); ?>
            <input type="submit" name="bap_delete_all_dbs" class="button button-secondary" style="color:#d63638;border-color:#d63638;" value="Borrar TODAS las bases de datos" />
        </form>
    </div>

    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;max-width:1200px;">
        <?php
        bap_render_single_db_card( '1. Base de Datos Países (.mmdb)', file_exists( $mmdb ), file_exists( $mmdb ) ? size_format( filesize( $mmdb ), 2 ) : '', $country_updated, 'bap_update_country_db', 'Descargar / Actualizar Países', 'bap_up_country', 'bap_mmdb_file', '.mmdb,.gz', 'bap_upload_country_db', 'country', '¿Borrar DB de Países?' );
        bap_render_single_db_card( '2. Base de Datos ASN (.mmdb)', file_exists( $asn_mmdb ), file_exists( $asn_mmdb ) ? size_format( filesize( $asn_mmdb ), 2 ) : '', $asn_updated, 'bap_update_asn_db', 'Descargar / Actualizar ASN', 'bap_up_asn', 'bap_asn_file', '.mmdb,.gz', 'bap_upload_asn_db', 'asn', '¿Borrar DB de ASN?' );
        bap_render_single_db_card( '3. Base de Datos AbuseIPDB', file_exists( $abuse_file ), (int) get_option( 'bap_abuseipdb_count', 0 ) . ' IPs', get_option( 'bap_abuseipdb_last_updated', '' ), 'bap_update_abuse_db', 'Descargar / Actualizar AbuseIPDB', 'bap_up_abuse', 'bap_abuse_file', '.json,.txt,.csv,.ipv4,.gz', 'bap_upload_abuse_db', 'abuse', '¿Borrar DB de AbuseIPDB?' );
        bap_render_single_db_card( '4. Nodos de Salida Tor', file_exists( $tor_file ), (int) get_option( 'bap_tor_count', 0 ) . ' nodos', get_option( 'bap_tor_last_updated', '' ), 'bap_update_tor_db', 'Descargar / Actualizar Tor', 'bap_up_tor', 'bap_tor_file', '.txt,.lst,.json', 'bap_upload_tor_db', 'tor', '¿Borrar lista Tor?' );
        bap_render_single_db_card( '5. FireHOL Level 1 (Spamhaus/CIDR)', file_exists( $firehol_file ), (int) get_option( 'bap_firehol_count', 0 ) . ' redes', get_option( 'bap_firehol_last_updated', '' ), 'bap_update_firehol_db', 'Descargar / Actualizar FireHOL', 'bap_up_firehol', 'bap_firehol_file', '.netset,.txt,.json', 'bap_upload_firehol_db', 'firehol', '¿Borrar lista FireHOL?' );
        ?>
    </div>

    <div class="card" style="max-width:760px;padding:16px 24px;margin-top:12px;">
        <h2>Configuración de Bases de Datos y MySQL Externo</h2>
        <form method="post" action="options.php">
            <?php settings_fields( 'bap_db_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th>API Key AbuseIPDB (Opcional)</th>
                    <td>
                        <input type="password" name="bap_abuseipdb_key" value="<?php echo esc_attr( get_option( 'bap_abuseipdb_key', '' ) ); ?>" class="regular-text code" placeholder="Sin clave usa espejo público >=90%" />
                        <p class="description">Permite descargar listas con puntuación exacta (0-100 %) y consultar IPs en vivo.</p>
                    </td>
                </tr>
                <tr>
                    <th>Actualización automática de DBs</th>
                    <td>
                        <?php
                        $db_freq   = (string) get_option( 'bap_db_auto_update_freq', 'none' );
                        $next_cron = wp_next_scheduled( 'bap_auto_update_dbs_event' );
                        ?>
                        <select name="bap_db_auto_update_freq">
                            <option value="none" <?php selected( $db_freq, 'none' ); ?>>Desactivada</option>
                            <option value="daily" <?php selected( $db_freq, 'daily' ); ?>>Diaria (cada 24 horas)</option>
                            <option value="weekly" <?php selected( $db_freq, 'weekly' ); ?>>Semanal (cada 7 días)</option>
                            <option value="2weeks" <?php selected( $db_freq, '2weeks' ); ?>>Cada 2 semanas (cada 14 días)</option>
                            <option value="monthly" <?php selected( $db_freq, 'monthly' ); ?>>Mensual (cada 30 días)</option>
                        </select>
                        <?php if ( $next_cron ) : ?>
                            <p class="description" style="color:#008a20;">Próxima actualización automática programada: <code><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $next_cron ) ); ?></code></p>
                        <?php else : ?>
                            <p class="description">Descarga y actualiza automáticamente las 5 bases de datos en segundo plano mediante WP-Cron.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
                if ( function_exists( 'bap_render_external_db_fields' ) ) {
                    bap_render_external_db_fields();
                }
                ?>
            </table>
            <p class="submit" style="display:flex;gap:10px;align-items:center;">
                <input type="submit" name="submit" class="button button-primary" value="Guardar configuración de Base de Datos" />
                <button type="submit" form="bap_test_ext_db_form" name="bap_test_ext_db" class="button button-secondary">Probar conexión MySQL</button>
            </p>
        </form>
        <form id="bap_test_ext_db_form" method="post" style="display:none;"><?php wp_nonce_field( 'bap_ext_db_test_action', 'bap_ext_db_nonce' ); ?></form>
    </div>

    <?php bap_render_json_backup_card(); ?>

    <?php
    if ( function_exists( 'bap_render_ip_simulator_box' ) ) {
        bap_render_ip_simulator_box();
    }
    ?>
</div>
