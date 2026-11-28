<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_render_estadisticas_summary_cards( $total_blocks, $top_countries, $top_asns, $top_uris, $stats_base_url, $excluded_count = 0 ) {
    $ab_keys    = function_exists( 'bap_get_abuseipdb_keys' ) ? bap_get_abuseipdb_keys() : [];
    $key_count  = count( $ab_keys );
    $is_exhaust = false;
    $used_cnt   = 0;
    $daily_cap  = 1000;
    $pct_used   = 0;
    $num_color  = '#00a32a';

    if ( $key_count > 0 ) {
        $ab_stats   = function_exists( 'bap_get_abuseipdb_usage_stats' ) ? bap_get_abuseipdb_usage_stats() : [ 'used' => 0, 'exhausted' => [] ];
        $daily_cap  = $key_count * 1000;
        $used_cnt   = (int) ( $ab_stats['used'] ?? 0 );
        $exh_cnt    = count( (array) ( $ab_stats['exhausted'] ?? [] ) );
        $is_exhaust = ( $exh_cnt >= $key_count || $used_cnt >= $daily_cap );
        $pct_used   = min( 100, round( ( $used_cnt / $daily_cap ) * 100, 1 ) );
        $num_color  = $is_exhaust ? '#d63638' : ( $pct_used >= 75 ? '#dba617' : '#00a32a' );
    }

    $api_card_css = $is_exhaust
        ? 'margin:0;padding:12px 16px;border:2px solid #d63638;background-color:rgba(214,54,56,0.12);'
        : 'margin:0;padding:12px 16px;';
    $cfg_url      = admin_url( 'admin.php?page=bap-admin-db&subtab=configuracion' );
    ?>
    <div style="display:grid;grid-template-columns:205px 125px minmax(0,1.4fr) minmax(0,1fr);gap:14px;margin:20px 0;align-items:stretch;">
        <div style="min-width:0;display:flex;flex-direction:column;gap:12px;">
            <div class="card" style="margin:0;padding:12px 16px;min-width:0;max-width:none;flex:1;">
                <h3 style="margin:0 0 6px 0;">Total Bloqueados</h3>
                <p style="font-size:26px;font-weight:bold;color:#d63638;margin:0;">
                    <?php echo number_format_i18n( $total_blocks ); ?>
                    <?php if ( $excluded_count > 0 ) : ?>
                        <span style="display:inline-block;font-size:11px;font-weight:600;color:#646970;background:#f0f0f1;padding:2px 7px;border-radius:12px;vertical-align:middle;margin-left:4px;">
                            Excluidas (<?php echo number_format_i18n( $excluded_count ); ?>)
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="card" style="min-width:0;max-width:none;<?php echo esc_attr( $api_card_css ); ?>">
                <h3 style="margin:0 0 4px 0;font-size:13px;">Cupo API AbuseIPDB</h3>
                <?php if ( $key_count > 0 ) : ?>
                    <p style="font-size:18px;font-weight:bold;color:<?php echo esc_attr( $num_color ); ?>;margin:0 0 2px 0;">
                        <?php echo number_format_i18n( $used_cnt ); ?> <span style="font-size:12px;font-weight:normal;color:#8c8f94;">/ <?php echo number_format_i18n( $daily_cap ); ?></span>
                    </p>
                    <p style="margin:0;font-size:11px;font-weight:<?php echo $is_exhaust ? '700' : 'normal'; ?>;color:<?php echo $is_exhaust ? '#d63638' : '#8c8f94'; ?>;">
                        <?php echo $is_exhaust ? 'AGOTADO (00:00 UTC)' : esc_html( $pct_used . '% usado (' . $key_count . ' clave' . ( $key_count > 1 ? 's' : '' ) . ')' ); ?>
                        &middot; <a href="<?php echo esc_url( $cfg_url ); ?>" style="color:inherit;text-decoration:underline;">Configurar</a>
                    </p>
                <?php else : ?>
                    <p style="margin:0;font-size:12px;color:#8c8f94;">Espejo local activo &middot; <a href="<?php echo esc_url( $cfg_url ); ?>">Añadir API Key</a></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card" style="min-width:0;max-width:none;margin:0;padding:14px 16px;text-align:left;">
            <h3 style="margin-top:0;">Top Paises</h3>
            <?php if ( empty( $top_countries ) ) : ?><p>Sin datos.</p><?php else : ?>
                <ul style="margin:0;padding:0;list-style:none;text-align:left;"><?php foreach ( $top_countries as $tc ) : ?>
                    <li style="margin-bottom:4px;"><strong><?php echo esc_html( $tc->country_code ); ?></strong>: <?php echo (int) $tc->total; ?></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <div class="card" style="min-width:0;max-width:none;margin:0;padding:14px 18px;">
            <h3 style="margin-top:0;">Top ASNs / Proveedores</h3>
            <?php if ( empty( $top_asns ) ) : ?><p>Sin datos.</p><?php else : ?>
                <ul style="margin:0;"><?php foreach ( $top_asns as $ta ) : ?>
                    <li><small><strong><?php echo esc_html( $ta->asn ); ?></strong>: <?php echo (int) $ta->total; ?></small></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <div class="card" style="min-width:0;max-width:none;margin:0;padding:14px 18px;">
            <h3 style="margin-top:0;">Top Rutas Atacadas</h3>
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

function bap_render_estadisticas_toolbar( array $filter_rows, $filter_type, $stats_base_url ) {
    if ( empty( $filter_rows ) ) { $filter_rows = [ [ 'val' => '', 'field' => 'any', 'mode' => 'include' ] ]; }
    $has_filters = ( 'all' !== $filter_type );
    foreach ( $filter_rows as $fr ) {
        if ( '' !== ( $fr['val'] ?? '' ) || 'abuseipdb' === ( $fr['field'] ?? '' ) ) { $has_filters = true; break; }
    }
    $fields = [
        'any'       => 'Cualquier campo',
        'uri'       => 'Ruta (URL)',
        'ip'        => 'Direccion IP',
        'country'   => 'Pais (ISO)',
        'asn'       => 'ASN / Proveedor',
        'reason'    => 'Motivo',
        'abuseipdb' => 'AbuseIPDB (% min)',
    ];
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
                    $cur_f  = (string) ( $fr['field'] ?? 'any' );
                ?>
                    <div class="bap-filter-row" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <input type="text" name="f_val[]" value="<?php echo esc_attr( $fr['val'] ?? '' ); ?>" placeholder="Valor (ej. aowow, 8.8.8.8, 90...)" class="regular-text bap-fval-inp" style="width:225px;" />
                        <select name="f_field[]" class="bap-ffield-sel" style="width:155px;">
                            <?php foreach ( $fields as $fk => $flbl ) : ?>
                                <option value="<?php echo esc_attr( $fk ); ?>" <?php selected( $cur_f, $fk ); ?>><?php echo esc_html( $flbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
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
            <div style="display:flex;align-items:center;gap:6px;">
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
