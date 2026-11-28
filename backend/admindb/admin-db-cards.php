<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_db_status_state( $is_active, $updated_str, $freq ) {
    if ( ! $is_active ) {
        return 'missing';
    }

    $intervals = [
        'daily'   => DAY_IN_SECONDS,
        'weekly'  => WEEK_IN_SECONDS,
        '2weeks'  => 2 * WEEK_IN_SECONDS,
        'monthly' => 30 * DAY_IN_SECONDS,
    ];

    if ( isset( $intervals[ $freq ] ) && '' !== trim( (string) $updated_str ) ) {
        $ts = strtotime( (string) $updated_str );
        if ( false !== $ts && ( time() - $ts ) > $intervals[ $freq ] ) {
            return 'outdated';
        }
    }

    return 'ok';
}

function bap_render_single_db_card( $title, $is_active, $info_line, $updated, $update_btn_name, $update_btn_label, $form_id, $file_input_name, $accept, $upload_btn_name, $del_type, $del_confirm, $state = 'ok' ) {
    $card_style = 'flex:1;min-width:300px;max-width:380px;margin:0;transition:background-color 0.2s,border-color 0.2s;';

    if ( 'missing' === $state ) {
        $card_style .= 'background-color:rgba(214,54,56,0.16);border:2px solid #d63638;';
    } elseif ( 'outdated' === $state ) {
        $card_style .= 'background-color:rgba(219,166,23,0.18);border:2px solid #dba617;';
    }
    ?>
    <div class="card" style="<?php echo esc_attr( $card_style ); ?>">
        <h2><?php echo esc_html( $title ); ?></h2>
        <p><strong>Estado:</strong>
            <?php if ( $is_active ) : ?>
                <?php if ( 'outdated' === $state ) : ?>
                    <span style="color:#dba617;font-weight:bold;">Obsoleta (Requiere actualización)</span> (<?php echo esc_html( $info_line ); ?>)<br />
                <?php else : ?>
                    <span style="color:#00a32a;font-weight:bold;">Activa</span> (<?php echo esc_html( $info_line ); ?>)<br />
                <?php endif; ?>
                <small style="color:#8c8f94;">Actualizada: <code><?php echo esc_html( $updated ?: 'Desconocida' ); ?></code></small>
            <?php else : ?>
                <span style="color:#f86368;font-weight:bold;">No instalada (Sin cargar)</span>
            <?php endif; ?>
        </p>
        <form method="post" style="margin-bottom:10px;">
            <?php wp_nonce_field( 'bap_db_action' ); ?>
            <input type="submit" name="<?php echo esc_attr( $update_btn_name ); ?>" class="button button-primary" value="<?php echo esc_attr( $update_btn_label ); ?>" />
        </form>
        <form id="<?php echo esc_attr( $form_id ); ?>" method="post" enctype="multipart/form-data" style="margin-bottom:8px;">
            <?php wp_nonce_field( 'bap_db_action' ); ?>
            <input type="file" name="<?php echo esc_attr( $file_input_name ); ?>" accept="<?php echo esc_attr( $accept ); ?>" required />
        </form>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="submit" form="<?php echo esc_attr( $form_id ); ?>" name="<?php echo esc_attr( $upload_btn_name ); ?>" class="button button-secondary" value="Subir manual" />
            <?php if ( $is_active ) : ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( $del_confirm ); ?>');">
                    <?php wp_nonce_field( 'bap_db_action' ); ?>
                    <input type="hidden" name="bap_delete_db" value="<?php echo esc_attr( $del_type ); ?>" />
                    <input type="submit" class="button button-secondary" style="color:#f86368;" value="Borrar DB" />
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function bap_render_json_backup_card() {
    ?>
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
}

function bap_render_db_settings_card( $db_freq ) {
    ?>
    <div class="card" style="max-width:760px;padding:16px 24px;margin-top:12px;">
        <h2>Configuración de Bases de Datos y MySQL Externo</h2>
        <form method="post" action="options.php">
            <?php settings_fields( 'bap_db_settings_group' ); ?>
            <table class="form-table">
                                <tr>
                    <th>API Key(s) AbuseIPDB (Opcional)</th>
                    <td>
                        <?php
                        $ab_keys   = function_exists( 'bap_get_abuseipdb_keys' ) ? bap_get_abuseipdb_keys() : [];
                        $ab_stats  = function_exists( 'bap_get_abuseipdb_usage_stats' ) ? bap_get_abuseipdb_usage_stats() : [ 'used' => 0, 'exhausted' => [] ];
                        $key_count = count( $ab_keys );
                        $daily_cap = max( 1000, $key_count * 1000 );
                        $used_cnt  = (int) ( $ab_stats['used'] ?? 0 );
                        $exh_list  = (array) ( $ab_stats['exhausted'] ?? [] );
                        $pct_used  = $key_count > 0 ? min( 100, round( ( $used_cnt / $daily_cap ) * 100, 1 ) ) : 0;
                        $bar_color = ( $pct_used >= 90 ) ? '#d63638' : ( ( $pct_used >= 65 ) ? '#dba617' : '#00a32a' );
                        ?>
                        <textarea name="bap_abuseipdb_key" rows="3" class="regular-text code" placeholder="Una o varias API Keys (una por linea). Sin clave usa espejo publico >=90%"><?php echo esc_textarea( get_option( 'bap_abuseipdb_key', '' ) ); ?></textarea>
                        <p class="description">Puedes anadir <strong>varias claves gratuitas (una por linea)</strong>: si una agota sus 1,000 consultas diarias, rotara automaticamente a la siguiente.</p>
                        <?php if ( $key_count > 0 ) : ?>
                            <div style="margin-top:10px;padding:10px 12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;max-width:460px;">
                                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px;">
                                    <span><strong>Consumo hoy (UTC):</strong> <?php echo number_format_i18n( $used_cnt ); ?> / <?php echo number_format_i18n( $daily_cap ); ?> consultas</span>
                                    <strong style="color:<?php echo esc_attr( $bar_color ); ?>;"><?php echo esc_html( $pct_used ); ?>%</strong>
                                </div>
                                <div style="width:100%;height:8px;background:#dcdcde;border-radius:4px;overflow:hidden;margin-bottom:6px;">
                                    <div style="width:<?php echo esc_attr( $pct_used ); ?>%;height:100%;background:<?php echo esc_attr( $bar_color ); ?>;"></div>
                                </div>
                                <small style="color:#50575e;">
                                    Claves configuradas: <strong><?php echo (int) $key_count; ?></strong> |
                                    Agotadas hoy: <strong><?php echo count( $exh_list ); ?></strong> |
                                    Reinicio diario: <code>00:00 UTC</code>
                                </small>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Actualización automática de DBs</th>
                    <td>
                        <?php $next_cron = wp_next_scheduled( 'bap_auto_update_dbs_event' ); ?>
                        <select name="bap_db_auto_update_freq">
                            <option value="none" <?php selected( $db_freq, 'none' ); ?>>Desactivada</option>
                            <option value="daily" <?php selected( $db_freq, 'daily' ); ?>>Diaria (cada 24 horas)</option>
                            <option value="weekly" <?php selected( $db_freq, 'weekly' ); ?>>Semanal (cada 7 días)</option>
                            <option value="2weeks" <?php selected( $db_freq, '2weeks' ); ?>>Cada 2 semanas (cada 14 días)</option>
                            <option value="monthly" <?php selected( $db_freq, 'monthly' ); ?>>Mensual (cada 30 días)</option>
                        </select>
                        <?php if ( $next_cron ) : ?>
                            <p class="description" style="color:#008a20;">Próxima actualización automática: <code><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $next_cron ) ); ?></code></p>
                        <?php else : ?>
                            <p class="description">Descarga y actualiza automáticamente las 5 bases de datos mediante WP-Cron.</p>
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
        <form id="bap_test_ext_db_form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-db&subtab=configuracion' ) ); ?>" style="display:none;"><?php wp_nonce_field( 'bap_ext_db_test_action', 'bap_ext_db_nonce' ); ?></form>
    </div>
    <?php
}
