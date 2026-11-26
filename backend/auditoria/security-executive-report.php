<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_calculate_security_score_summary() {
    $db_paths  = [ bap_get_mmdb_path(), bap_get_asn_mmdb_path(), bap_get_abuseipdb_path(), bap_get_tor_list_path(), bap_get_firehol_list_path() ];
    $db_count  = count( array_filter( $db_paths, 'file_exists' ) );
    $db_pts    = $db_count * 3;

    $geo_asn_ok = ( '' !== trim( (string) get_option( 'bap_allowed_countries', '' ) ) || '' !== trim( (string) get_option( 'bap_blocked_countries', '' ) ) || '' !== trim( (string) get_option( 'bap_allowed_asns', '' ) ) || '' !== trim( (string) get_option( 'bap_blocked_asns', '' ) ) );
    $ip_list_ok = ( '' !== trim( (string) get_option( 'bap_whitelist_ips', '' ) ) || '' !== trim( (string) get_option( 'bap_blacklist_ips', '' ) ) );
    $stealth_ok = ( '' !== trim( (string) get_option( 'bap_secret_key', '' ) ) || '' !== trim( (string) get_option( 'bap_custom_login_slug', '' ) ) );
    $perim_pts  = ( $geo_asn_ok ? 5 : 0 ) + ( $ip_list_ok ? 5 : 0 ) + ( $stealth_ok ? 5 : 0 );

    $xmlrpc_ok = ( '1' === (string) get_option( 'bap_block_xmlrpc', '1' ) );
    $enum_ok   = ( '1' === (string) get_option( 'bap_block_user_enum', '1' ) );
    $edit_ok   = ( '1' === (string) get_option( 'bap_harden_disable_file_edit', '0' ) );
    $up_php_ok = ( '1' === (string) get_option( 'bap_harden_block_uploads_php', '0' ) );

    $checks = [
        [ 'label' => 'Bases de datos de inteligencia locales (Paises / ASN / Amenazas)', 'ok' => ( 5 === $db_count ), 'earned' => $db_pts, 'pts' => 15, 'url' => admin_url( 'admin.php?page=bap-admin-db' ) ],
        [ 'label' => 'Proteccion perimetral Backend (Pais-ASN / Listas IP / Hide Login-Llave)', 'ok' => ( 15 === $perim_pts ), 'earned' => $perim_pts, 'pts' => 15, 'url' => admin_url( 'admin.php?page=bap-bloqueos&subtab=backend' ) ],
        [ 'label' => 'Cortafuegos WAF / Early WAF (.user.ini)', 'ok' => ( '1' === (string) get_option( 'bap_waf_enabled', '0' ) || ( function_exists( 'bap_is_early_waf_installed' ) && bap_is_early_waf_installed() ) ), 'pts' => 10, 'url' => admin_url( 'admin.php?page=bap-bloqueos&subtab=frontend' ) ],
        [ 'label' => 'Autenticacion en Dos Factores (2FA)', 'ok' => ( '1' === (string) get_option( 'bap_2fa_enabled', '0' ) ), 'pts' => 15, 'url' => admin_url( 'admin.php?page=bap-bloqueos&subtab=backend' ) ],
        [ 'label' => 'Escudo Anti-Secuestro de Sesion (Cookie Binding)', 'ok' => ( '1' === (string) get_option( 'bap_session_hijack_shield', '0' ) ), 'pts' => 10, 'url' => admin_url( 'admin.php?page=bap-bloqueos&subtab=backend' ) ],
        [ 'label' => 'Bloqueo de XML-RPC y Enumeracion de Usuarios', 'ok' => ( $xmlrpc_ok && $enum_ok ), 'earned' => ( $xmlrpc_ok ? 5 : 0 ) + ( $enum_ok ? 5 : 0 ), 'pts' => 10, 'url' => admin_url( 'admin.php?page=bap-bloqueos&subtab=backend' ) ],
        [ 'label' => 'Endurecimiento Hardening (Editor desactivado + PHP bloqueado en Uploads)', 'ok' => ( $edit_ok && $up_php_ok ), 'earned' => ( $edit_ok ? 5 : 0 ) + ( $up_php_ok ? 5 : 0 ), 'pts' => 10, 'url' => admin_url( 'admin.php?page=bap-auditoria&subtab=hardening' ) ],
        [ 'label' => 'Verificacion Magic Bytes / SVG en Subidas', 'ok' => ( '1' === (string) get_option( 'bap_harden_upload_magic_bytes', '0' ) ), 'pts' => 5, 'url' => admin_url( 'admin.php?page=bap-auditoria&subtab=hardening' ) ],
        [ 'label' => 'Modo Emergencia Anti-Crash (mu-plugins)', 'ok' => ( function_exists( 'bap_is_mu_emergency_loader_installed' ) && bap_is_mu_emergency_loader_installed() ), 'pts' => 5, 'url' => admin_url( 'admin.php?page=bap-auditoria&subtab=hardening' ) ],
        [ 'label' => 'Auditoria Programada sin Alertas Pendientes', 'ok' => empty( get_option( 'bap_last_auto_scan_alerts', [] ) ), 'pts' => 5, 'url' => admin_url( 'admin.php?page=bap-auditoria&subtab=escaneres#bap-box-db-inj' ) ],
    ];

    $score = 0;
    foreach ( $checks as &$c ) {
        $c['earned'] = isset( $c['earned'] ) ? (int) $c['earned'] : ( $c['ok'] ? (int) $c['pts'] : 0 );
        $score      += $c['earned'];
    }
    unset( $c );

    $grade = ( $score >= 90 ) ? 'A+' : ( ( $score >= 80 ) ? 'A' : ( ( $score >= 65 ) ? 'B' : ( ( $score >= 50 ) ? 'C' : 'D' ) ) );
    $color = ( $score >= 80 ) ? '#00a32a' : ( ( $score >= 60 ) ? '#d97706' : '#d63638' );

    return [ 'score' => $score, 'grade' => $grade, 'color' => $color, 'checks' => $checks ];
}
add_action( 'admin_post_bap_executive_security_report', function() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acceso denegado', 403 ); }
    check_admin_referer( 'bap_exec_report_nonce' );

    global $wpdb, $wp_version;
    $summary = bap_calculate_security_score_summary();
    $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $table   = bap_get_logs_table_name();
    $since   = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
    $blocks  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE created_at >= %s", $since ) );
    $uniq_ip = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT ip_address) FROM `{$table}` WHERE created_at >= %s", $since ) );

    // 1. De donde proceden (Top Paises y Top ASNs)
    $top_countries = $wpdb->get_results( $wpdb->prepare( "SELECT country_code, COUNT(*) AS cnt FROM `{$table}` WHERE created_at >= %s AND country_code != '' GROUP BY country_code ORDER BY cnt DESC LIMIT 5", $since ), ARRAY_A );
    $top_asns      = $wpdb->get_results( $wpdb->prepare( "SELECT asn, COUNT(*) AS cnt FROM `{$table}` WHERE created_at >= %s AND asn != '' AND asn != 'Sin base de datos ASN' GROUP BY asn ORDER BY cnt DESC LIMIT 5", $since ), ARRAY_A );

    // 2. A donde se dirigen (Web Publica vs Backend / Servidor y Top URIs)
    $by_target  = $wpdb->get_results( $wpdb->prepare( "SELECT block_type, COUNT(*) AS cnt FROM `{$table}` WHERE created_at >= %s GROUP BY block_type ORDER BY cnt DESC", $since ), ARRAY_A );
    $top_uris   = $wpdb->get_results( $wpdb->prepare( "SELECT request_uri, COUNT(*) AS cnt FROM `{$table}` WHERE created_at >= %s AND request_uri != '' GROUP BY request_uri ORDER BY cnt DESC LIMIT 5", $since ), ARRAY_A );
    $web_hits   = 0;
    $admin_hits = 0;
    foreach ( (array) $by_target as $bt ) {
        if ( 'web' === ( $bt['block_type'] ?? '' ) ) { $web_hits += (int) $bt['cnt']; }
        else { $admin_hits += (int) $bt['cnt']; }
    }

    // 3. Que intentan atacar (Clasificacion de vectores / exploits)
    $reason_rows = $wpdb->get_results( $wpdb->prepare( "SELECT reason, COUNT(*) AS cnt FROM `{$table}` WHERE created_at >= %s GROUP BY reason", $since ), ARRAY_A );
    $vectors     = [
        'Exploits WAF / SQLi / XSS / LFI / vPatch' => 0,
        'Fuerza Bruta / Login / Usuarios / 2FA'     => 0,
        'Escaneo Rutas Trampa / .env / XML-RPC'     => 0,
        'Redes Maliciosas (Tor / FireHOL / Abuse)'  => 0,
        'Bloqueo Geografico / Pais / ASN'           => 0,
        'Bots / Scrapers / Rate Limit / Honeypot'   => 0,
        'Otros bloqueos perimetrales'               => 0,
    ];
    foreach ( (array) $reason_rows as $rr ) {
        $r_low = strtolower( (string) ( $rr['reason'] ?? '' ) );
        $c     = (int) ( $rr['cnt'] ?? 0 );
        if ( false !== strpos( $r_low, 'waf' ) || false !== strpos( $r_low, 'vpatch' ) || false !== strpos( $r_low, 'sqli' ) || false !== strpos( $r_low, 'xss' ) || false !== strpos( $r_low, 'lfi' ) || false !== strpos( $r_low, 'cve' ) ) {
            $vectors['Exploits WAF / SQLi / XSS / LFI / vPatch'] += $c;
        } elseif ( false !== strpos( $r_low, 'login' ) || false !== strpos( $r_low, 'usuario' ) || false !== strpos( $r_low, 'captcha' ) || false !== strpos( $r_low, 'magic' ) || false !== strpos( $r_low, 'vacaciones' ) || false !== strpos( $r_low, 'secuestro' ) ) {
            $vectors['Fuerza Bruta / Login / Usuarios / 2FA'] += $c;
        } elseif ( false !== strpos( $r_low, 'trampa' ) || false !== strpos( $r_low, 'xml-rpc' ) || false !== strpos( $r_low, 'xmlrpc' ) || false !== strpos( $r_low, 'enumeracion' ) || false !== strpos( $r_low, 'oculta' ) ) {
            $vectors['Escaneo Rutas Trampa / .env / XML-RPC'] += $c;
        } elseif ( false !== strpos( $r_low, 'tor' ) || false !== strpos( $r_low, 'firehol' ) || false !== strpos( $r_low, 'abuseipdb' ) || false !== strpos( $r_low, 'lista negra' ) ) {
            $vectors['Redes Maliciosas (Tor / FireHOL / Abuse)'] += $c;
        } elseif ( false !== strpos( $r_low, 'pais' ) || false !== strpos( $r_low, 'país' ) || false !== strpos( $r_low, 'asn' ) ) {
            $vectors['Bloqueo Geografico / Pais / ASN'] += $c;
        } elseif ( false !== strpos( $r_low, 'user-agent' ) || false !== strpos( $r_low, 'rate limit' ) || false !== strpos( $r_low, 'honeypot' ) || false !== strpos( $r_low, 'spam' ) ) {
            $vectors['Bots / Scrapers / Rate Limit / Honeypot'] += $c;
        } else {
            $vectors['Otros bloqueos perimetrales'] += $c;
        }
    }
    $vectors = array_filter( $vectors, fn( $v ) => $v > 0 );
    arsort( $vectors );
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <title>Informe Ejecutivo de Seguridad — <?php echo esc_html( $site ); ?></title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; background: #f8fafc; margin: 0; padding: 28px; }
            .sheet { max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 32px 36px; }
            .top { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 18px; margin-bottom: 22px; }
            .badge-score { text-align: center; border: 3px solid <?php echo esc_attr( $summary['color'] ); ?>; border-radius: 12px; padding: 10px 18px; }
            .kpis { display: flex; gap: 14px; margin-bottom: 22px; }
            .kpi { flex: 1; background: #f1f5f9; border-radius: 8px; padding: 14px; }
            table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
            th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
            th { background: #f1f5f9; }
            @media print { .no-print { display: none; } body { background: #fff; padding: 0; } .sheet { border: none; padding: 0; } }
        </style>
    </head>
    <body>
        <div class="sheet">
            <div class="no-print" style="text-align:right;margin-bottom:14px;">
                <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;padding:8px 16px;border-radius:6px;font-weight:700;cursor:pointer;">Imprimir / Guardar como PDF</button>
            </div>
            <div class="top">
                <div>
                    <h1 style="margin:0 0 6px;font-size:22px;">Informe Ejecutivo de Seguridad Web</h1>
                    <p style="margin:0;color:#475569;font-size:13px;"><strong>Sitio:</strong> <?php echo esc_html( $site ); ?> (<code><?php echo esc_html( home_url() ); ?></code>)<br><strong>Fecha de emisión:</strong> <?php echo esc_html( wp_date( 'Y-m-d H:i:s' ) ); ?> &nbsp;|&nbsp; <strong>WordPress:</strong> v<?php echo esc_html( $wp_version ); ?> &nbsp;|&nbsp; <strong>PHP:</strong> v<?php echo esc_html( PHP_VERSION ); ?></p>
                </div>
                <div class="badge-score">
                    <div style="font-size:28px;font-weight:800;color:<?php echo esc_attr( $summary['color'] ); ?>;line-height:1;"><?php echo (int) $summary['score']; ?>/100</div>
                    <div style="font-size:13px;font-weight:700;margin-top:4px;">Nota: <?php echo esc_html( $summary['grade'] ); ?></div>
                </div>
            </div>
            <div class="kpis">
                <div class="kpi"><div style="font-size:12px;color:#475569;">Ataques bloqueados (30 días)</div><div style="font-size:24px;font-weight:800;color:#dc2626;"><?php echo number_format_i18n( $blocks ); ?></div></div>
                <div class="kpi"><div style="font-size:12px;color:#475569;">IPs atacantes únicas (30 días)</div><div style="font-size:24px;font-weight:800;"><?php echo number_format_i18n( $uniq_ip ); ?></div></div>
                <div class="kpi"><div style="font-size:12px;color:#475569;">Estado de auditoría</div><div style="font-size:18px;font-weight:800;color:<?php echo empty( get_option( 'bap_last_auto_scan_alerts', [] ) ) ? '#16a34a' : '#dc2626'; ?>;"><?php echo empty( get_option( 'bap_last_auto_scan_alerts', [] ) ) ? 'Sin alertas activas' : 'Requiere revisión'; ?></div></div>
            </div>
            <h2 style="font-size:16px;margin-bottom:6px;">Desglose de Controles de Seguridad</h2>
            <table>
                <thead><tr><th>Control de seguridad</th><th style="width:90px;">Peso</th><th style="width:110px;">Estado</th></tr></thead>
                <tbody>
                    <?php foreach ( $summary['checks'] as $c ) : ?>
                        <tr>
                            <td><?php echo esc_html( $c['label'] ); ?></td>
                            <td><?php echo (int) $c['earned']; ?> / <?php echo (int) $c['pts']; ?> pts</td>
                            <td>
                                <?php if ( $c['ok'] ) : ?>
                                    <strong style="color:#16a34a;">ACTIVO</strong>
                                <?php elseif ( $c['earned'] > 0 ) : ?>
                                    <a href="<?php echo esc_url( $c['url'] ); ?>" target="_blank" rel="noopener noreferrer" style="color:#2563eb;font-weight:700;text-decoration:underline;" title="Completar la medida restante">PARCIAL &rarr;</a>
                                <?php else : ?>
                                    <a href="<?php echo esc_url( $c['url'] ); ?>" target="_blank" rel="noopener noreferrer" style="color:#d97706;font-weight:700;text-decoration:underline;" title="Ir a configurar este control">PENDIENTE &rarr;</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php $safe_total = max( 1, $blocks ); ?>
            <div style="display:flex;gap:16px;margin-top:24px;flex-wrap:wrap;">
                <div style="flex:1;min-width:260px;">
                    <h2 style="font-size:15px;margin:0 0 8px;">1. De donde proceden (Top Paises)</h2>
                    <table>
                        <thead><tr><th>Pais</th><th style="width:95px;">Bloqueos</th></tr></thead>
                        <tbody>
                            <?php if ( empty( $top_countries ) ) : ?>
                                <tr><td colspan="2">Sin datos registrados.</td></tr>
                            <?php else : ?>
                                <?php foreach ( $top_countries as $tc ) :
                                    $c_lbl = function_exists( 'bap_dashboard_country_label' ) ? bap_dashboard_country_label( $tc['country_code'] ) : $tc['country_code'];
                                    $pct   = round( ( (int) $tc['cnt'] / $safe_total ) * 100, 1 );
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $c_lbl ); ?></strong></td>
                                        <td><?php echo number_format_i18n( (int) $tc['cnt'] ); ?> <small style="color:#64748b;">(<?php echo esc_html( $pct ); ?>%)</small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="flex:1;min-width:260px;">
                    <h2 style="font-size:15px;margin:0 0 8px;">2. A donde se dirigen (Objetivo)</h2>
                    <table>
                        <thead><tr><th>Objetivo atacado</th><th style="width:110px;">Peticiones</th></tr></thead>
                        <tbody>
                            <tr>
                                <td><strong>Panel Admin / Login / API / Sistema</strong></td>
                                <td><?php echo number_format_i18n( $admin_hits ); ?> <small style="color:#64748b;">(<?php echo esc_html( round( ( $admin_hits / $safe_total ) * 100, 1 ) ); ?>%)</small></td>
                            </tr>
                            <tr>
                                <td><strong>Web Publica (Frontend)</strong></td>
                                <td><?php echo number_format_i18n( $web_hits ); ?> <small style="color:#64748b;">(<?php echo esc_html( round( ( $web_hits / $safe_total ) * 100, 1 ) ); ?>%)</small></td>
                            </tr>
                        </tbody>
                    </table>

                    <?php if ( ! empty( $top_asns ) ) : ?>
                        <h3 style="font-size:13px;margin:14px 0 6px;color:#334155;">Principales redes / ASNs de origen</h3>
                        <table>
                            <tbody>
                                <?php foreach ( $top_asns as $ta ) : ?>
                                    <tr>
                                        <td style="font-size:12px;"><code><?php echo esc_html( mb_substr( (string) $ta['asn'], 0, 42 ) ); ?></code></td>
                                        <td style="width:75px;font-weight:700;"><?php echo number_format_i18n( (int) $ta['cnt'] ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <h2 style="font-size:15px;margin:22px 0 8px;">3. Que intentan atacar (Vectores y Exploits detectados)</h2>
            <table>
                <thead><tr><th>Vector de ataque / Amenaza</th><th style="width:180px;">Distribucion</th><th style="width:110px;">Intentos</th></tr></thead>
                <tbody>
                    <?php if ( empty( $vectors ) ) : ?>
                        <tr><td colspan="3">Sin ataques registrados en los ultimos 30 dias.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $vectors as $vec_name => $vec_cnt ) :
                            $v_pct = round( ( $vec_cnt / $safe_total ) * 100, 1 );
                            $bar_w = max( 3, min( 100, (int) round( $v_pct ) ) );
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html( $vec_name ); ?></strong></td>
                                <td>
                                    <div style="width:100%;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                        <div style="width:<?php echo (int) $bar_w; ?>%;height:100%;background:#dc2626;"></div>
                                    </div>
                                </td>
                                <td><strong><?php echo number_format_i18n( (int) $vec_cnt ); ?></strong> <small style="color:#64748b;">(<?php echo esc_html( $v_pct ); ?>%)</small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( ! empty( $top_uris ) ) : ?>
                <h2 style="font-size:15px;margin:22px 0 8px;">4. Rutas y archivos mas atacados (Top 5 URLs)</h2>
                <table>
                    <thead><tr><th>Ruta / Endpoint solicitado</th><th style="width:120px;">Intentos</th></tr></thead>
                    <tbody>
                        <?php foreach ( $top_uris as $tu ) :
                            $u_pct = round( ( (int) $tu['cnt'] / $safe_total ) * 100, 1 );
                        ?>
                            <tr>
                                <td style="word-break:break-all;"><code><?php echo esc_html( (string) $tu['request_uri'] ); ?></code></td>
                                <td><strong><?php echo number_format_i18n( (int) $tu['cnt'] ); ?></strong> <small style="color:#64748b;">(<?php echo esc_html( $u_pct ); ?>%)</small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
} );

function bap_render_executive_report_card() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $sum = bap_calculate_security_score_summary();
    $url = wp_nonce_url( admin_url( 'admin-post.php?action=bap_executive_security_report' ), 'bap_exec_report_nonce' );
    ?>
    <div class="card" style="max-width:980px;padding:16px 22px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div style="display:flex;align-items:center;gap:16px;">
            <div style="border:3px solid <?php echo esc_attr( $sum['color'] ); ?>;border-radius:10px;padding:8px 14px;text-align:center;">
                <strong style="font-size:20px;color:<?php echo esc_attr( $sum['color'] ); ?>;display:block;line-height:1.1;"><?php echo (int) $sum['score']; ?>%</strong>
                <small style="font-weight:700;">Nota <?php echo esc_html( $sum['grade'] ); ?></small>
            </div>
            <div>
                <h2 style="margin:0;">Índice de Seguridad e Informe Ejecutivo</h2>
                <p class="description" style="margin:4px 0 0 0;">Evaluación global de tus defensas activas y generación de informe imprimible / PDF para auditorías.</p>
            </div>
        </div>
        <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary button-large">Abrir Informe Ejecutivo (Imprimir / PDF)</a>
    </div>
    <?php
}