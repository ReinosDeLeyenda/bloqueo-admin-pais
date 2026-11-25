<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_estadisticas_summary_cards( $total_blocks, $top_countries, $top_asns, $top_uris, $stats_base_url ) {
    ?>
    <div style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap;">
        <div class="card" style="flex:1;min-width:170px;margin:0;">
            <h3>Total Bloqueados</h3>
            <p style="font-size:28px;font-weight:bold;color:#d63638;margin:0;"><?php echo number_format_i18n( $total_blocks ); ?></p>
        </div>
        <div class="card" style="flex:1;min-width:190px;margin:0;">
            <h3>Top Países</h3>
            <?php if ( empty( $top_countries ) ) : ?><p>Sin datos.</p><?php else : ?>
                <ul style="margin:0;"><?php foreach ( $top_countries as $tc ) : ?>
                    <li><strong><?php echo esc_html( $tc->country_code ); ?></strong>: <?php echo (int) $tc->total; ?></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <div class="card" style="flex:1;min-width:220px;margin:0;">
            <h3>Top ASNs / Proveedores</h3>
            <?php if ( empty( $top_asns ) ) : ?><p>Sin datos.</p><?php else : ?>
                <ul style="margin:0;"><?php foreach ( $top_asns as $ta ) : ?>
                    <li><small><strong><?php echo esc_html( $ta->asn ); ?></strong>: <?php echo (int) $ta->total; ?></small></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <div class="card" style="flex:1.4;min-width:260px;margin:0;">
            <h3>Top Rutas Atacadas</h3>
            <?php if ( empty( $top_uris ) ) : ?><p>Sin datos.</p><?php else : ?>
                <ul style="margin:0;"><?php foreach ( $top_uris as $tu ) :
                    $uri_link = add_query_arg( 'filter_uri', rawurlencode( $tu->request_uri ), $stats_base_url );
                ?>
                    <li style="margin-bottom:4px;word-break:break-all;">
                        <a href="<?php echo esc_url( $uri_link ); ?>" style="text-decoration:none;"><code><?php echo esc_html( $tu->request_uri ); ?></code></a>: <strong><?php echo (int) $tu->total; ?></strong>
                    </li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function bap_render_estadisticas_toolbar( $filter_uri, $filter_q, $stats_base_url ) {
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;background:#fff;border:1px solid #c3c4c7;padding:12px 14px;border-radius:4px;">
        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <input type="hidden" name="page" value="bap-admin-estadisticas" />
            <input type="text" name="filter_uri" value="<?php echo esc_attr( $filter_uri ); ?>" placeholder="Filtrar por ruta..." class="regular-text" style="width:210px;" />
            <input type="text" name="filter_q" value="<?php echo esc_attr( $filter_q ); ?>" placeholder="Buscar IP, país, ASN o motivo..." class="regular-text" style="width:220px;" />
            <button type="submit" class="button button-primary">Filtrar</button>
            <?php if ( '' !== $filter_uri || '' !== $filter_q ) : ?>
                <a href="<?php echo esc_url( $stats_base_url ); ?>" class="button button-secondary">Limpiar</a>
            <?php endif; ?>
        </form>
        <form method="post" style="display:flex;align-items:center;gap:6px;">
            <?php wp_nonce_field( 'bap_logs_action' ); ?>
            <label for="bap_logs_retention_days">Retención:</label>
            <input type="number" id="bap_logs_retention_days" name="bap_logs_retention_days" value="<?php echo (int) get_option( 'bap_logs_retention_days', 30 ); ?>" min="0" max="3650" style="width:65px;" /> días
            <button type="submit" name="bap_save_retention" class="button button-secondary">Guardar</button>
        </form>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;">Últimos 100 intentos bloqueados</h2>
        <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_logs_action' ); ?>
            <button type="submit" name="bap_export_logs_csv" class="button button-secondary">Exportar Logs CSV</button>
            <button type="submit" name="bap_export_asn_csv" class="button button-secondary">Exportar Resumen ASN</button>
            <?php if ( function_exists( 'bap_find_ip2location_log_table' ) && '' !== bap_find_ip2location_log_table() ) : ?>
                <button type="submit" name="bap_import_ip2location" class="button button-secondary" onclick="return confirm('¿Importar todo el historial de bloqueos desde la tabla de IP2Location?');">Importar de IP2Location</button>
            <?php endif; ?>
            <?php if ( function_exists( 'bap_find_wordfence_log_table' ) && '' !== bap_find_wordfence_log_table() ) : ?>
                <button type="submit" name="bap_import_wordfence" class="button button-secondary" onclick="return confirm('¿Importar todo el historial de bloqueos desde las tablas de Wordfence?');">Importar de Wordfence</button>
            <?php endif; ?>
            <button type="submit" name="bap_clear_logs" class="button button-link-delete" style="margin-left:6px;" onclick="return confirm('¿Vaciar todos los registros?');">Vaciar historial</button>
        </form>
    </div>
    <?php
}
