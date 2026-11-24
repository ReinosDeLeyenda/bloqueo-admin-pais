<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-admin-db' ); ?>
    <?php echo $notice; ?>

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
        <div class="card" style="flex:1;min-width:300px;max-width:380px;margin:0;">
            <h2>1. Base de Datos Países (.mmdb)</h2>
            <p><strong>Estado:</strong>
                <?php if ( file_exists( $mmdb ) ) : ?>
                    <span style="color:#008a20;font-weight:bold;">Activa</span> (<?php echo esc_html( size_format( filesize( $mmdb ), 2 ) ); ?>)<br />
                    <small style="color:#50575e;">Actualizada: <code><?php echo esc_html( $country_updated ); ?></code></small>
                <?php else : ?>
                    <span style="color:#d63638;font-weight:bold;">No instalada</span>
                <?php endif; ?>
            </p>
            <form method="post" style="margin-bottom:10px;"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="submit" name="bap_update_country_db" class="button button-primary" value="Descargar / Actualizar Países" /></form>
            <form id="bap_up_country" method="post" enctype="multipart/form-data" style="margin-bottom:8px;"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="file" name="bap_mmdb_file" accept=".mmdb,.gz" required /></form>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <input type="submit" form="bap_up_country" name="bap_upload_country_db" class="button button-secondary" value="Subir manual" />
                <?php if ( file_exists( $mmdb ) ) : ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Borrar DB de Países?');"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="hidden" name="bap_delete_db" value="country" /><input type="submit" class="button button-secondary" style="color:#d63638;" value="Borrar DB" /></form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="flex:1;min-width:300px;max-width:380px;margin:0;">
            <h2>2. Base de Datos ASN (.mmdb)</h2>
            <p><strong>Estado:</strong>
                <?php if ( file_exists( $asn_mmdb ) ) : ?>
                    <span style="color:#008a20;font-weight:bold;">Activa</span> (<?php echo esc_html( size_format( filesize( $asn_mmdb ), 2 ) ); ?>)<br />
                    <small style="color:#50575e;">Actualizada: <code><?php echo esc_html( $asn_updated ); ?></code></small>
                <?php else : ?>
                    <span style="color:#d63638;font-weight:bold;">No instalada</span>
                <?php endif; ?>
            </p>
            <form method="post" style="margin-bottom:10px;"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="submit" name="bap_update_asn_db" class="button button-primary" value="Descargar / Actualizar ASN" /></form>
            <form id="bap_up_asn" method="post" enctype="multipart/form-data" style="margin-bottom:8px;"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="file" name="bap_asn_file" accept=".mmdb,.gz" required /></form>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <input type="submit" form="bap_up_asn" name="bap_upload_asn_db" class="button button-secondary" value="Subir manual" />
                <?php if ( file_exists( $asn_mmdb ) ) : ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Borrar DB de ASN?');"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="hidden" name="bap_delete_db" value="asn" /><input type="submit" class="button button-secondary" style="color:#d63638;" value="Borrar DB" /></form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="flex:1;min-width:300px;max-width:380px;margin:0;">
            <h2>3. Base de Datos AbuseIPDB</h2>
            <p><strong>Estado:</strong>
                <?php if ( file_exists( $abuse_file ) ) : ?>
                    <span style="color:#008a20;font-weight:bold;">Activa</span> (<?php echo (int) get_option( 'bap_abuseipdb_count', 0 ); ?> IPs)<br />
                    <small style="color:#50575e;">Actualizada: <code><?php echo esc_html( get_option( 'bap_abuseipdb_last_updated', '' ) ); ?></code></small>
                <?php else : ?>
                    <span style="color:#d63638;font-weight:bold;">No instalada</span>
                <?php endif; ?>
            </p>
            <form method="post" style="margin-bottom:10px;"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="submit" name="bap_update_abuse_db" class="button button-primary" value="Descargar / Actualizar AbuseIPDB" /></form>
            <form id="bap_up_abuse" method="post" enctype="multipart/form-data" style="margin-bottom:8px;"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="file" name="bap_abuse_file" accept=".json,.txt,.csv,.ipv4,.gz" required /></form>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <input type="submit" form="bap_up_abuse" name="bap_upload_abuse_db" class="button button-secondary" value="Subir manual" />
                <?php if ( file_exists( $abuse_file ) ) : ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Borrar DB de AbuseIPDB?');"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="hidden" name="bap_delete_db" value="abuse" /><input type="submit" class="button button-secondary" style="color:#d63638;" value="Borrar DB" /></form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="flex:1;min-width:300px;max-width:380px;margin:0;">
            <h2>4. Nodos de Salida Tor</h2>
            <p><strong>Estado:</strong>
                <?php if ( file_exists( $tor_file ) ) : ?>
                    <span style="color:#008a20;font-weight:bold;">Activa</span> (<?php echo (int) get_option( 'bap_tor_count', 0 ); ?> nodos)<br />
                    <small style="color:#50575e;">Actualizada: <code><?php echo esc_html( get_option( 'bap_tor_last_updated', '' ) ); ?></code></small>
                <?php else : ?>
                    <span style="color:#d63638;font-weight:bold;">No instalada</span>
                <?php endif; ?>
            </p>
            <form method="post" style="margin-bottom:10px;"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="submit" name="bap_update_tor_db" class="button button-primary" value="Descargar / Actualizar Tor" /></form>
            <form id="bap_up_tor" method="post" enctype="multipart/form-data" style="margin-bottom:8px;"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="file" name="bap_tor_file" accept=".txt,.lst,.json" required /></form>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <input type="submit" form="bap_up_tor" name="bap_upload_tor_db" class="button button-secondary" value="Subir manual" />
                <?php if ( file_exists( $tor_file ) ) : ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Borrar lista Tor?');"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="hidden" name="bap_delete_db" value="tor" /><input type="submit" class="button button-secondary" style="color:#d63638;" value="Borrar DB" /></form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="flex:1;min-width:300px;max-width:380px;margin:0;">
            <h2>5. FireHOL Level 1 (Spamhaus/CIDR)</h2>
            <p><strong>Estado:</strong>
                <?php if ( file_exists( $firehol_file ) ) : ?>
                    <span style="color:#008a20;font-weight:bold;">Activa</span> (<?php echo (int) get_option( 'bap_firehol_count', 0 ); ?> redes)<br />
                    <small style="color:#50575e;">Actualizada: <code><?php echo esc_html( get_option( 'bap_firehol_last_updated', '' ) ); ?></code></small>
                <?php else : ?>
                    <span style="color:#d63638;font-weight:bold;">No instalada</span>
                <?php endif; ?>
            </p>
            <form method="post" style="margin-bottom:10px;"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="submit" name="bap_update_firehol_db" class="button button-primary" value="Descargar / Actualizar FireHOL" /></form>
            <form id="bap_up_firehol" method="post" enctype="multipart/form-data" style="margin-bottom:8px;"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="file" name="bap_firehol_file" accept=".netset,.txt,.json" required /></form>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <input type="submit" form="bap_up_firehol" name="bap_upload_firehol_db" class="button button-secondary" value="Subir manual" />
                <?php if ( file_exists( $firehol_file ) ) : ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Borrar lista FireHOL?');"><?php wp_nonce_field( 'bap_db_action' ); ?><input type="hidden" name="bap_delete_db" value="firehol" /><input type="submit" class="button button-secondary" style="color:#d63638;" value="Borrar DB" /></form>
                <?php endif; ?>
            </div>
        </div>
    </div>

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
        </table>
        <?php submit_button( 'Guardar configuración de Base de Datos' ); ?>
    </form>

    <div class="card" style="max-width:760px;padding:16px 24px;margin-top:24px;">
        <h2>Exportar / Importar Configuración (.json)</h2>
        <p class="description" style="margin-bottom:14px;">Guarda una copia de seguridad de todas tus reglas de <strong>Bloqueos Admin</strong> y <strong>Bloqueos Web</strong> o clónalas en otro sitio WordPress.</p>

        <form method="post" style="margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid #3c434a;">
            <?php wp_nonce_field( 'bap_db_action' ); ?>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_export_secrets" value="1" />
                Incluir también la <strong>Llave secreta URL</strong> y la <strong>API Key AbuseIPDB</strong> en el archivo exportado
            </label>
            <input type="submit" name="bap_export_json" class="button button-primary" value="Descargar configuración (.json)" />
        </form>

        <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_db_action' ); ?>
            <input type="file" name="bap_json_file" accept=".json" required />
            <input type="submit" name="bap_import_json" class="button button-secondary" value="Importar configuración (.json)" onclick="return confirm('Esto sobrescribirá tus reglas actuales con las del archivo JSON. ¿Continuar?');" />
        </form>

    </div>
    <?php
    if ( function_exists( 'bap_render_external_db_settings_box' ) ) {
        bap_render_external_db_settings_box();
    }
    if ( function_exists( 'bap_render_ip_simulator_box' ) ) {
        bap_render_ip_simulator_box();
    }
    ?>
</div>
