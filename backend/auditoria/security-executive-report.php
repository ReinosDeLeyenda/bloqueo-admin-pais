<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_calculate_security_score_summary() {
    $checks = [
        [ 'label' => 'Bases de datos de inteligencia locales (Países / ASN / Amenazas)', 'ok' => ( file_exists( bap_get_mmdb_path() ) && file_exists( bap_get_asn_mmdb_path() ) ), 'pts' => 15 ],
        [ 'label' => 'Protección perimetral Backend (País / ASN / Lista Blanca)', 'ok' => ( '' !== trim( (string) get_option( 'bap_allowed_countries', '' ) ) || '' !== trim( (string) get_option( 'bap_allowed_asns', '' ) ) || '' !== trim( (string) get_option( 'bap_whitelist_ips', '' ) ) ), 'pts' => 15 ],
        [ 'label' => 'Cortafuegos WAF / Early WAF (.user.ini)', 'ok' => ( '1' === (string) get_option( 'bap_waf_enabled', '0' ) || ( function_exists( 'bap_is_early_waf_installed' ) && bap_is_early_waf_installed() ) ), 'pts' => 10 ],
        [ 'label' => 'Autenticación en Dos Factores (2FA)', 'ok' => ( '1' === (string) get_option( 'bap_2fa_enabled', '0' ) ), 'pts' => 15 ],
        [ 'label' => 'Escudo Anti-Secuestro de Sesión (Cookie Binding)', 'ok' => ( '1' === (string) get_option( 'bap_session_hijack_shield', '0' ) ), 'pts' => 10 ],
        [ 'label' => 'Bloqueo de XML-RPC y Enumeración de Usuarios', 'ok' => ( '1' === (string) get_option( 'bap_block_xmlrpc', '1' ) && '1' === (string) get_option( 'bap_block_user_enum', '1' ) ), 'pts' => 10 ],
        [ 'label' => 'Endurecimiento Hardening (Editor desactivado + PHP bloqueado en Uploads)', 'ok' => ( '1' === (string) get_option( 'bap_harden_disable_file_edit', '0' ) && '1' === (string) get_option( 'bap_harden_block_uploads_php', '0' ) ), 'pts' => 10 ],
        [ 'label' => 'Verificación Magic Bytes / SVG en Subidas', 'ok' => ( '1' === (string) get_option( 'bap_harden_upload_magic_bytes', '0' ) ), 'pts' => 5 ],
        [ 'label' => 'Modo Emergencia Anti-Crash (mu-plugins)', 'ok' => ( function_exists( 'bap_is_mu_emergency_loader_installed' ) && bap_is_mu_emergency_loader_installed() ), 'pts' => 5 ],
        [ 'label' => 'Auditoría Programada sin Alertas Pendientes', 'ok' => empty( get_option( 'bap_last_auto_scan_alerts', [] ) ), 'pts' => 5 ],
    ];

    $score = 0;
    foreach ( $checks as $c ) {
        if ( $c['ok'] ) { $score += (int) $c['pts']; }
    }

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
                            <td><?php echo (int) $c['pts']; ?> pts</td>
                            <td><strong style="color:<?php echo $c['ok'] ? '#16a34a' : '#d97706'; ?>;"><?php echo $c['ok'] ? 'ACTIVO' : 'PENDIENTE'; ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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