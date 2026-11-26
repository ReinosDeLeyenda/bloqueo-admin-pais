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

function bap_render_estadisticas_toolbar( $filter_uri, $filter_q, $filter_type, $stats_base_url, $filter_abuse = 'all' ) {
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin:14px 0 12px 0;flex-wrap:wrap;gap:10px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <form method="post" style="display:inline-flex;gap:6px;flex-wrap:wrap;align-items:center;margin:0;">
                <?php wp_nonce_field( 'bap_logs_action' ); ?>
                <input type="hidden" name="bap_export_filter_type" value="<?php echo esc_attr( $filter_type ); ?>" />
                <button type="submit" name="bap_export_logs_csv" class="button button-secondary">Exportar Logs CSV</button>
                <button type="submit" name="bap_export_asn_csv" class="button button-secondary">Exportar Resumen ASN</button>
                <?php if ( function_exists( 'bap_find_ip2location_log_table' ) && '' !== bap_find_ip2location_log_table() ) : ?>
                    <button type="submit" name="bap_import_ip2location" class="button button-secondary" onclick="return confirm('¿Importar todo el historial de bloqueos desde la tabla de IP2Location?');">Importar de IP2Location</button>
                <?php endif; ?>
                <?php if ( function_exists( 'bap_find_wordfence_log_table' ) && '' !== bap_find_wordfence_log_table() ) : ?>
                    <button type="submit" name="bap_import_wordfence" class="button button-secondary" onclick="return confirm('¿Importar todo el historial de bloqueos desde las tablas de Wordfence?');">Importar de Wordfence</button>
                <?php endif; ?>
                <button type="submit" name="bap_clear_logs" class="button button-link-delete" style="margin-left:4px;" onclick="return confirm('¿Vaciar todos los registros?');">Vaciar historial</button>
            </form>

            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline-flex;align-items:center;gap:6px;margin-left:6px;">
                <input type="hidden" name="page" value="bap-admin-estadisticas" />
                <?php if ( '' !== $filter_uri ) : ?>
                    <input type="hidden" name="filter_uri" value="<?php echo esc_attr( $filter_uri ); ?>" />
                <?php endif; ?>
                <?php if ( '' !== $filter_q ) : ?>
                    <input type="hidden" name="filter_q" value="<?php echo esc_attr( $filter_q ); ?>" />
                <?php endif; ?>
                <select name="filter_type" onchange="this.form.submit();" style="min-width:130px;font-weight:600;" aria-label="Filtrar por entorno">
                    <option value="all" <?php selected( $filter_type, 'all' ); ?>>Todo</option>
                    <option value="admin" <?php selected( $filter_type, 'admin' ); ?>>Backend</option>
                    <option value="web" <?php selected( $filter_type, 'web' ); ?>>Frontend</option>
                </select>
            </form>
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;background:#fff;border:1px solid #c3c4c7;padding:12px 14px;border-radius:4px;">
        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <input type="hidden" name="page" value="bap-admin-estadisticas" />
            <input type="hidden" name="filter_type" value="<?php echo esc_attr( $filter_type ); ?>" />
            <input type="text" name="filter_uri" value="<?php echo esc_attr( $filter_uri ); ?>" placeholder="Ruta (ej. /aowow -/admin)" class="regular-text" style="width:195px;" title="Usa -termino para excluir" />
            <input type="text" name="filter_q" value="<?php echo esc_attr( $filter_q ); ?>" placeholder="Buscar (ej. aowow -Tor)" class="regular-text" style="width:215px;" title="Separa terminos por espacios; antepon - para excluir" />
            <select name="filter_abuse" aria-label="Filtrar por AbuseIPDB">
                <option value="all" <?php selected( $filter_abuse, 'all' ); ?>>AbuseIPDB: Todos</option>
                <option value="listed" <?php selected( $filter_abuse, 'listed' ); ?>>En AbuseIPDB</option>
                <option value="unlisted" <?php selected( $filter_abuse, 'unlisted' ); ?>>No en AbuseIPDB</option>
            </select>
            <button type="submit" class="button button-primary">Filtrar</button>
            <?php if ( '' !== $filter_uri || '' !== $filter_q || 'all' !== $filter_type || 'all' !== $filter_abuse ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-estadisticas' ) ); ?>" class="button button-secondary">Limpiar</a>
            <?php endif; ?>
        </form>
        <form method="post" style="display:flex;align-items:center;gap:6px;">
            <?php wp_nonce_field( 'bap_logs_action' ); ?>
            <label for="bap_logs_retention_days">Retención:</label>
            <input type="number" id="bap_logs_retention_days" name="bap_logs_retention_days" value="<?php echo (int) get_option( 'bap_logs_retention_days', 30 ); ?>" min="0" max="3650" style="width:65px;" /> días
            <button type="submit" name="bap_save_retention" class="button button-secondary">Guardar</button>
        </form>
    </div>
    <?php
}