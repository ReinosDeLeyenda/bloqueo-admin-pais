<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_email_html_header( $site_name, array $data, $is_test, $tpl_label ) {
    $palette = [ '#d63638', '#2271b1', '#d97706', '#00a32a', '#646970' ];
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head><meta charset="UTF-8" /></head>
    <body style="margin:0;padding:20px;background-color:#0a0a0a;color:#f0f0f1;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
        <div style="max-width:920px;margin:0 auto;background-color:#0f0f0f;border:1px solid #262626;border-radius:8px;padding:22px;">
            <?php if ( $is_test ) : ?>
                <div style="background:#2d2206;border:1px solid #d97706;color:#fbbf24;padding:8px 12px;border-radius:4px;font-size:12px;font-weight:700;margin-bottom:14px;">
                    CORREO DE PRUEBA — Plantilla: <?php echo esc_html( $tpl_label ); ?>
                </div>
            <?php endif; ?>

            <h1 style="margin:0 0 4px 0;font-size:20px;color:#ffffff;">Control de Acceso al Panel — <?php echo esc_html( $site_name ); ?></h1>
            <p style="margin:0 0 18px 0;font-size:13px;color:#9ca3af;">Resumen de actividad bloqueada (<?php echo count( $data['grouped'] ); ?> eventos únicos agrupados)</p>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:10px;margin:0 -10px 16px -10px;">
                <tr>
                    <td width="28%" valign="top" style="background:#161616;border:1px solid #2a2a2a;border-radius:6px;padding:14px;">
                        <div style="font-size:13px;font-weight:700;color:#e5e7eb;margin-bottom:8px;">Total Bloqueados</div>
                        <div style="font-size:30px;font-weight:800;color:#f87171;line-height:1.1;"><?php echo esc_html( number_format_i18n( $data['total_hits'] ) ); ?></div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:6px;">IPs únicas: <strong style="color:#fff;"><?php echo (int) $data['unique_ips']; ?></strong></div>
                    </td>
                    <td width="32%" valign="top" style="background:#161616;border:1px solid #2a2a2a;border-radius:6px;padding:14px;">
                        <div style="font-size:13px;font-weight:700;color:#e5e7eb;margin-bottom:8px;">Top Países</div>
                        <?php foreach ( $data['top_countries'] as $c_code => $c_cnt ) : ?>
                            <div style="font-size:12px;color:#d1d5db;margin-bottom:3px;"><strong style="color:#ffffff;"><?php echo esc_html( $c_code ); ?></strong>: <?php echo (int) $c_cnt; ?></div>
                        <?php endforeach; ?>
                    </td>
                    <td width="40%" valign="top" style="background:#161616;border:1px solid #2a2a2a;border-radius:6px;padding:14px;">
                        <div style="font-size:13px;font-weight:700;color:#e5e7eb;margin-bottom:8px;">Top ASNs / Proveedores</div>
                        <?php foreach ( $data['top_asns'] as $a_name => $a_cnt ) : ?>
                            <div style="font-size:11px;color:#d1d5db;margin-bottom:3px;"><strong style="color:#ffffff;"><?php echo esc_html( $a_name ); ?></strong>: <?php echo (int) $a_cnt; ?></div>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>

            <?php if ( ! empty( $data['top_countries'] ) && $data['total_hits'] > 0 ) : ?>
                <div style="background:#161616;border:1px solid #2a2a2a;border-radius:6px;padding:14px 16px;margin-bottom:18px;">
                    <div style="font-size:14px;font-weight:700;color:#ffffff;margin-bottom:12px;">Distribución por País (Top 5)</div>
                    <?php $idx = 0; foreach ( $data['top_countries'] as $c_code => $c_cnt ) :
                        $share = round( ( $c_cnt / $data['total_hits'] ) * 100, 1 );
                        $bar_w = max( 4, min( 100, (int) round( $share ) ) );
                        $color = $palette[ $idx % count( $palette ) ];
                        $idx++;
                    ?>
                        <div style="margin-bottom:9px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:3px;">
                                <tr>
                                    <td style="font-size:12px;font-weight:700;color:#ffffff;"><?php echo esc_html( $c_code ); ?></td>
                                    <td align="right" style="font-size:12px;color:#d1d5db;"><?php echo esc_html( number_format_i18n( $c_cnt ) . ' (' . $share . '%)' ); ?></td>
                                </tr>
                            </table>
                            <div style="width:100%;height:8px;background:#262626;border-radius:4px;overflow:hidden;">
                                <div style="width:<?php echo (int) $bar_w; ?>%;height:8px;background-color:<?php echo esc_attr( $color ); ?>;border-radius:4px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
    <?php
}

function bap_email_html_table_and_footer( array $rows, $title, $stats_url ) {
    ?>
            <div style="font-size:14px;font-weight:700;color:#ffffff;margin-bottom:10px;"><?php echo esc_html( $title ); ?></div>
            <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;background:#141414;border:1px solid #2a2a2a;font-size:12px;">
                <thead>
                    <tr style="background:#1c1c1c;color:#e5e7eb;text-align:left;border-bottom:1px solid #333;">
                        <th>Fecha / Rango</th>
                        <th>Dirección IP</th>
                        <th>Riesgo</th>
                        <th>País</th>
                        <th>ASN / Proveedor</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $g ) :
                        $last_short = substr( $g['last_time'], 11 ) ?: $g['last_time'];
                        $time_str   = ( $g['first_time'] !== $g['last_time'] && '' !== $g['last_time'] ) ? $g['first_time'] . ' → ' . $last_short : $g['first_time'];
                        $risk_num   = (int) rtrim( $g['risk'], '%' );
                        $risk_color = ( 'N/D' === $g['risk'] ) ? '#8c8f94' : ( $risk_num >= 75 ? '#f87171' : ( $risk_num >= 25 ? '#fbbf24' : '#4ade80' ) );
                    ?>
                        <tr style="border-bottom:1px solid #242424;">
                            <td style="color:#d1d5db;white-space:nowrap;"><?php echo esc_html( $time_str ); ?></td>
                            <td style="white-space:nowrap;">
                                <span style="display:inline-block;min-width:22px;text-align:center;background:#d63638;color:#ffffff;font-weight:700;font-size:11px;line-height:18px;padding:0 5px;border-radius:4px;margin-right:5px;"><?php echo (int) $g['count']; ?></span>
                                <code style="color:#72aee6;font-family:monospace;"><?php echo esc_html( $g['ip'] ); ?></code>
                            </td>
                            <td style="font-weight:700;color:<?php echo esc_attr( $risk_color ); ?>;"><?php echo esc_html( $g['risk'] ); ?></td>
                            <td style="font-weight:700;color:#ffffff;"><?php echo esc_html( $g['country'] ); ?></td>
                            <td style="color:#9ca3af;"><?php echo esc_html( $g['asn'] ); ?></td>
                            <td style="color:#e5e7eb;"><?php echo esc_html( $g['reason'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin:16px 0 0 0;text-align:right;font-size:12px;">
                <a href="<?php echo esc_url( $stats_url ); ?>" style="color:#72aee6;text-decoration:none;">Ver panel completo de Estadísticas en WordPress &rarr;</a>
            </p>
        </div>
    </body>
    </html>
    <?php
}
