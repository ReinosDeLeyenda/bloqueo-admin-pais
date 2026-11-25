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

$db_freq  = (string) get_option( 'bap_db_auto_update_freq', 'none' );
$db_items = [
    [ 'title' => '1. Base de Datos Países (.mmdb)', 'active' => file_exists( $mmdb ), 'info' => file_exists( $mmdb ) ? size_format( filesize( $mmdb ), 2 ) : '', 'updated' => $country_updated, 'up_btn' => 'bap_update_country_db', 'up_lbl' => 'Descargar / Actualizar Países', 'form_id' => 'bap_up_country', 'f_input' => 'bap_mmdb_file', 'accept' => '.mmdb,.gz', 'ul_btn' => 'bap_upload_country_db', 'del_t' => 'country', 'del_c' => '¿Borrar DB de Países?' ],
    [ 'title' => '2. Base de Datos ASN (.mmdb)', 'active' => file_exists( $asn_mmdb ), 'info' => file_exists( $asn_mmdb ) ? size_format( filesize( $asn_mmdb ), 2 ) : '', 'updated' => $asn_updated, 'up_btn' => 'bap_update_asn_db', 'up_lbl' => 'Descargar / Actualizar ASN', 'form_id' => 'bap_up_asn', 'f_input' => 'bap_asn_file', 'accept' => '.mmdb,.gz', 'ul_btn' => 'bap_upload_asn_db', 'del_t' => 'asn', 'del_c' => '¿Borrar DB de ASN?' ],
    [ 'title' => '3. Base de Datos AbuseIPDB', 'active' => file_exists( $abuse_file ), 'info' => (int) get_option( 'bap_abuseipdb_count', 0 ) . ' IPs', 'updated' => (string) get_option( 'bap_abuseipdb_last_updated', '' ), 'up_btn' => 'bap_update_abuse_db', 'up_lbl' => 'Descargar / Actualizar AbuseIPDB', 'form_id' => 'bap_up_abuse', 'f_input' => 'bap_abuse_file', 'accept' => '.json,.txt,.csv,.ipv4,.gz', 'ul_btn' => 'bap_upload_abuse_db', 'del_t' => 'abuse', 'del_c' => '¿Borrar DB de AbuseIPDB?' ],
    [ 'title' => '4. Nodos de Salida Tor', 'active' => file_exists( $tor_file ), 'info' => (int) get_option( 'bap_tor_count', 0 ) . ' nodos', 'updated' => (string) get_option( 'bap_tor_last_updated', '' ), 'up_btn' => 'bap_update_tor_db', 'up_lbl' => 'Descargar / Actualizar Tor', 'form_id' => 'bap_up_tor', 'f_input' => 'bap_tor_file', 'accept' => '.txt,.lst,.json', 'ul_btn' => 'bap_upload_tor_db', 'del_t' => 'tor', 'del_c' => '¿Borrar lista Tor?' ],
    [ 'title' => '5. FireHOL Level 1 (Spamhaus/CIDR)', 'active' => file_exists( $firehol_file ), 'info' => (int) get_option( 'bap_firehol_count', 0 ) . ' redes', 'updated' => (string) get_option( 'bap_firehol_last_updated', '' ), 'up_btn' => 'bap_update_firehol_db', 'up_lbl' => 'Descargar / Actualizar FireHOL', 'form_id' => 'bap_up_firehol', 'f_input' => 'bap_firehol_file', 'accept' => '.netset,.txt,.json', 'ul_btn' => 'bap_upload_firehol_db', 'del_t' => 'firehol', 'del_c' => '¿Borrar lista FireHOL?' ],
];

$missing_count  = 0;
$outdated_count = 0;
foreach ( $db_items as &$item ) {
    $item['state'] = bap_get_db_status_state( $item['active'], $item['updated'], $db_freq );
    if ( 'missing' === $item['state'] ) {
        $missing_count++;
    } elseif ( 'outdated' === $item['state'] ) {
        $outdated_count++;
    }
}
unset( $item );

$all_missing     = ( count( $db_items ) === $missing_count );
$container_style = 'display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;max-width:1200px;';
if ( $all_missing ) {
    $container_style .= 'background-color:rgba(214,54,56,0.22);border:2px solid #d63638;border-radius:6px;padding:16px;';
}
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-admin-db' ); ?>
    <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php echo $ext_test_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <?php if ( $missing_count > 0 || $outdated_count > 0 ) : ?>
        <div class="notice <?php echo $missing_count > 0 ? 'notice-error' : 'notice-warning'; ?>" style="max-width:1180px;margin:14px 0;">
            <p>
                <strong>Atención — Seguridad degradada:</strong>
                <?php if ( $all_missing ) : ?>
                    No hay <strong>ninguna base de datos cargada</strong>. Los filtros por País, ASN, AbuseIPDB, Tor y FireHOL no podrán bloquear amenazas hasta que las descargues.
                <?php else : ?>
                    <?php if ( $missing_count > 0 ) : ?>Hay <strong><?php echo (int) $missing_count; ?></strong> base(s) de datos sin cargar. <?php endif; ?>
                    <?php if ( $outdated_count > 0 ) : ?>Hay <strong><?php echo (int) $outdated_count; ?></strong> base(s) de datos obsoletas respecto a la frecuencia de actualización. <?php endif; ?>
                    La protección del sitio se verá degradada hasta que las descargues o actualices.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width:1180px;padding:14px 20px;margin-bottom:20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <strong>Acciones masivas:</strong>
        <form method="post" style="display:inline;"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="submit" name="bap_update_all_dbs" class="button button-primary" value="Descargar / Actualizar TODAS las bases de datos" /></form>
        <form method="post" style="display:inline;" onsubmit="return confirm('¿Seguro que quieres borrar TODAS las bases de datos locales?');"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="submit" name="bap_delete_all_dbs" class="button button-secondary" style="color:#d63638;border-color:#d63638;" value="Borrar TODAS las bases de datos" /></form>
    </div>

    <div style="<?php echo esc_attr( $container_style ); ?>">
        <?php foreach ( $db_items as $db ) : ?>
            <?php bap_render_single_db_card( $db['title'], $db['active'], $db['info'], $db['updated'], $db['up_btn'], $db['up_lbl'], $db['form_id'], $db['f_input'], $db['accept'], $db['ul_btn'], $db['del_t'], $db['del_c'], $db['state'] ); ?>
        <?php endforeach; ?>
    </div>

    <?php if ( function_exists( 'bap_render_custom_dbs_section' ) ) { bap_render_custom_dbs_section(); } ?>
    <?php bap_render_db_settings_card( $db_freq ); ?>
    <?php bap_render_json_backup_card(); ?>
    <?php if ( function_exists( 'bap_render_ip_simulator_box' ) ) { bap_render_ip_simulator_box(); } ?>
    <?php if ( function_exists( 'bap_render_core_integrity_box' ) ) { bap_render_core_integrity_box(); } ?>
    <?php if ( function_exists( 'bap_render_malware_scanner_box' ) ) { bap_render_malware_scanner_box(); } ?>
    <?php if ( function_exists( 'bap_render_hardening_box' ) ) { bap_render_hardening_box(); } ?>
</div>
