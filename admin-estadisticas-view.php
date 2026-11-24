<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-admin-estadisticas' ); ?>
    <?php echo $notice; ?>

    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
        <div class="card" style="flex:1;min-width:180px;max-width:240px;margin:0;text-align:center;">
            <h3 style="margin:0 0 8px;">Intentos Bloqueados</h3>
            <p style="font-size:28px;font-weight:bold;color:#d63638;margin:0;"><?php echo number_format_i18n( (int) $total_hits ); ?></p>
        </div>
        <div class="card" style="flex:1;min-width:180px;max-width:240px;margin:0;text-align:center;">
            <h3 style="margin:0 0 8px;">IPs Únicas Detectadas</h3>
            <p style="font-size:28px;font-weight:bold;color:#1d2327;margin:0;"><?php echo number_format_i18n( (int) ( $unique_ips ?? count( $logs ) ) ); ?></p>
        </div>
        <div class="card" style="flex:1;min-width:180px;max-width:240px;margin:0;text-align:center;">
            <h3 style="margin:0 0 8px;">IPs en Lista Negra</h3>
            <p style="font-size:28px;font-weight:bold;color:#2271b1;margin:0;"><?php echo number_format_i18n( count( $blacklist ) ); ?></p>
        </div>
    </div>

    <?php if ( ! empty( $logs ) ) : ?>
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
            <div class="card" style="flex:1;min-width:280px;max-width:450px;margin:0;">
                <h2>Top Países Bloqueados</h2>
                <ul style="list-style:disc;margin-left:20px;">
                    <?php foreach ( array_slice( $country_rank, 0, 8, true ) as $c_code => $c_hits ) : ?>
                        <li><strong><?php echo esc_html( $c_code ); ?></strong>: <?php echo number_format_i18n( (int) $c_hits ); ?> intentos</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card" style="flex:1;min-width:280px;max-width:560px;margin:0;">
                <h2>Top ASNs / Proveedores</h2>
                <ul style="list-style:disc;margin-left:20px;">
                    <?php foreach ( array_slice( $asn_rank, 0, 8, true ) as $a_name => $a_hits ) : ?>
                        <li><strong><?php echo esc_html( $a_name ); ?></strong>: <?php echo number_format_i18n( (int) $a_hits ); ?> intentos</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width:1120px;padding:12px 18px;margin-bottom:20px;">
        <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0;">
            <?php wp_nonce_field( 'bap_logs_action' ); ?>
            <label for="bap_logs_retention_days"><strong>Retención automática de registros en MySQL:</strong></label>
            <select id="bap_logs_retention_days" name="bap_logs_retention_days">
                <?php
                $ret_opts = [
                    7   => '7 días',
                    15  => '15 días',
                    30  => '30 días',
                    60  => '60 días',
                    90  => '90 días (recomendado)',
                    180 => '180 días (6 meses)',
                    365 => '365 días (1 año)',
                    0   => 'Conservar siempre (sin purga automática)',
                ];
                $cur_ret = (int) ( $retention_days ?? 90 );
                foreach ( $ret_opts as $val => $text ) :
                ?>
                    <option value="<?php echo (int) $val; ?>" <?php selected( $cur_ret, $val ); ?>><?php echo esc_html( $text ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="bap_save_retention" value="1" class="button button-secondary">Guardar y purgar ahora</button>
        </form>
    </div>

    <h2>Historial Detallado de IPs Bloqueadas</h2>
    <?php if ( ! empty( $logs ) || '' !== ( $ip2loc_tbl ?? '' ) ) : ?>
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap;">
            <?php if ( '' !== ( $ip2loc_tbl ?? '' ) ) : ?>
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field( 'bap_logs_action' ); ?>
                    <input type="submit" name="bap_import_ip2loc" class="button button-primary" value="Importar estadísticas de IP2Location (MySQL)" onclick="return confirm('Se importarán los registros detectados en la tabla <?php echo esc_js( $ip2loc_tbl ); ?>. ¿Continuar?');" />
                </form>
            <?php endif; ?>
            <?php if ( ! empty( $logs ) ) : ?>
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field( 'bap_logs_action' ); ?>
                    <input type="submit" name="bap_export_logs_csv" class="button button-secondary" value="Exportar todos los ataques (CSV)" />
                </form>
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field( 'bap_logs_action' ); ?>
                    <input type="submit" name="bap_export_asn_csv" class="button button-secondary" value="Exportar resumen por ASN (CSV)" />
                </form>
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field( 'bap_logs_action' ); ?>
                    <input type="submit" name="bap_clear_logs" class="button button-secondary" value="Vaciar historial" onclick="return confirm('¿Seguro que quieres borrar todo el historial?');" />
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php require plugin_dir_path( __FILE__ ) . 'admin-estadisticas-table.php'; ?>
</div>
