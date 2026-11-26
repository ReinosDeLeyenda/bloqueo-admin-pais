<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_render_estadisticas_summary_cards( $total_blocks, $top_countries, $top_asns, $top_uris, $stats_base_url ) {
    ?>
    <div style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap;">
        <div class="card" style="flex:1;min-width:170px;margin:0;">
            <h3>Total Bloqueados</h3>
            <p style="font-size:28px;font-weight:bold;color:#d63638;margin:0;"><?php echo number_format_i18n( $total_blocks ); ?></p>
        </div>
        <div class="card" style="flex:1;min-width:190px;margin:0;">
            <h3>Top Paises</h3>
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

function bap_render_estadisticas_toolbar( array $filter_rows, $filter_type, $stats_base_url, $filter_abuse = 'all' ) {
    if ( empty( $filter_rows ) ) { $filter_rows = [ [ 'uri' => '', 'q' => '', 'mode' => 'include' ] ]; }
    $has_filters = ( 'all' !== $filter_type || 'all' !== $filter_abuse );
    foreach ( $filter_rows as $fr ) {
        if ( '' !== ( $fr['uri'] ?? '' ) || '' !== ( $fr['q'] ?? '' ) ) { $has_filters = true; break; }
    }
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin:14px 0 12px 0;flex-wrap:wrap;gap:10px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <form method="post" style="display:inline-flex;gap:6px;flex-wrap:wrap;align-items:center;margin:0;">
                <?php wp_nonce_field( 'bap_logs_action' ); ?>
                <input type="hidden" name="bap_export_filter_type" value="<?php echo esc_attr( $filter_type ); ?>" />
                <button type="submit" name="bap_export_logs_csv" class="button button-secondary">Exportar Logs CSV</button>
                <button type="submit" name="bap_export_asn_csv" class="button button-secondary">Exportar Resumen ASN</button>
                <?php if ( function_exists( 'bap_find_ip2location_log_table' ) && '' !== bap_find_ip2location_log_table() ) : ?>
                    <button type="submit" name="bap_import_ip2location" class="button button-secondary" onclick="return confirm('Importar historial desde IP2Location?');">Importar de IP2Location</button>
                <?php endif; ?>
                <?php if ( function_exists( 'bap_find_wordfence_log_table' ) && '' !== bap_find_wordfence_log_table() ) : ?>
                    <button type="submit" name="bap_import_wordfence" class="button button-secondary" onclick="return confirm('Importar historial desde Wordfence?');">Importar de Wordfence</button>
                <?php endif; ?>
                <button type="submit" name="bap_clear_logs" class="button button-link-delete" style="margin-left:4px;" onclick="return confirm('Vaciar todos los registros?');">Vaciar historial</button>
            </form>
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline-flex;align-items:center;gap:6px;margin-left:6px;">
                <input type="hidden" name="page" value="bap-admin-estadisticas" />
                <select name="filter_type" onchange="this.form.submit();" style="min-width:130px;font-weight:600;">
                    <option value="all" <?php selected( $filter_type, 'all' ); ?>>Todo</option>
                    <option value="admin" <?php selected( $filter_type, 'admin' ); ?>>Backend</option>
                    <option value="web" <?php selected( $filter_type, 'web' ); ?>>Frontend</option>
                </select>
            </form>
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;flex-wrap:wrap;gap:12px;background:#fff;border:1px solid #c3c4c7;padding:12px 14px;border-radius:4px;">
        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap;flex:1;">
            <input type="hidden" name="page" value="bap-admin-estadisticas" />
            <input type="hidden" name="filter_type" value="<?php echo esc_attr( $filter_type ); ?>" />
            <div id="bap-filter-rows-wrap" style="display:flex;flex-direction:column;gap:8px;">
                <?php foreach ( $filter_rows as $fr ) :
                    $is_inc = ( 'exclude' !== ( $fr['mode'] ?? 'include' ) );
                ?>
                    <div class="bap-filter-row" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <input type="text" name="f_uri[]" value="<?php echo esc_attr( $fr['uri'] ?? '' ); ?>" placeholder="Filtrar por ruta..." class="regular-text" style="width:190px;" />
                        <input type="text" name="f_q[]" value="<?php echo esc_attr( $fr['q'] ?? '' ); ?>" placeholder="Buscar IP, pais, ASN o motivo..." class="regular-text" style="width:215px;" />
                        <input type="hidden" name="f_mode[]" value="<?php echo $is_inc ? 'include' : 'exclude'; ?>" class="bap-fmode-val" />
                        <button type="button" class="bap-fmode-switch" style="display:inline-flex;align-items:center;gap:6px;height:30px;padding:0 10px;border-radius:15px;border:1px solid rgba(0,0,0,0.2);background:<?php echo $is_inc ? '#2271b1' : '#d63638'; ?>;color:#fff;font-weight:700;font-size:12px;cursor:pointer;">
                            <span class="bap-fmode-dot" style="width:12px;height:12px;border-radius:50%;background:#fff;display:inline-block;order:<?php echo $is_inc ? '2' : '1'; ?>;"></span>
                            <span class="bap-fmode-txt" style="order:<?php echo $is_inc ? '1' : '2'; ?>;"><?php echo $is_inc ? 'Incluir' : 'Excluir'; ?></span>
                        </button>
                        <button type="button" class="button button-secondary bap-add-frow" style="font-weight:700;padding:0 9px;">+</button>
                        <button type="button" class="button button-secondary bap-del-frow" style="color:#d63638;font-weight:700;padding:0 9px;">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <select name="filter_abuse">
                    <option value="all" <?php selected( $filter_abuse, 'all' ); ?>>AbuseIPDB: Todos</option>
                    <option value="listed" <?php selected( $filter_abuse, 'listed' ); ?>>En AbuseIPDB</option>
                    <option value="unlisted" <?php selected( $filter_abuse, 'unlisted' ); ?>>No en AbuseIPDB</option>
                </select>
                <button type="submit" class="button button-primary">Filtrar</button>
                <?php if ( $has_filters ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-estadisticas' ) ); ?>" class="button button-secondary">Limpiar</a><?php endif; ?>
            </div>
        </form>
        <form method="post" style="display:flex;align-items:center;gap:6px;">
            <?php wp_nonce_field( 'bap_logs_action' ); ?>
            <label for="bap_logs_retention_days">Retencion:</label>
            <input type="number" id="bap_logs_retention_days" name="bap_logs_retention_days" value="<?php echo (int) get_option( 'bap_logs_retention_days', 30 ); ?>" min="0" max="3650" style="width:65px;" /> dias
            <button type="submit" name="bap_save_retention" class="button button-secondary">Guardar</button>
        </form>
    </div>
    <?php
}