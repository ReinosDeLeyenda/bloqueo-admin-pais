<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_custom_template_text( array $tpl, array $data, $site_name, $is_test ) {
    $modules = $tpl['modules'] ?? [];
    $limit   = (int) ( $tpl['table_limit'] ?? 10 );
    $lines   = [];

    if ( $is_test ) {
        $lines[] = '*** CORREO DE PRUEBA — Plantilla: ' . strtoupper( (string) ( $tpl['name'] ?? 'Personalizada' ) ) . ' ***';
        $lines[] = '';
    }
    $lines[] = "Resumen de Seguridad — {$site_name}";
    $lines[] = str_repeat( '=', 72 );

    foreach ( $modules as $mod ) {
        if ( 'cards' === $mod ) {
            $lines[] = sprintf( 'Total Bloqueados: %d | IPs Únicas: %d | Eventos Únicos: %d', $data['total_hits'], $data['unique_ips'], count( $data['grouped'] ) );
            $lines[] = '';
        } elseif ( 'countries_bars' === $mod && ! empty( $data['top_countries'] ) ) {
            $lines[] = 'TOP PAÍSES:';
            foreach ( $data['top_countries'] as $cc => $cnt ) {
                $lines[] = " - {$cc}: {$cnt}";
            }
            $lines[] = '';
        } elseif ( 'hourly_chart' === $mod && ! empty( $data['by_hour'] ) ) {
            $lines[] = 'DISTRIBUCIÓN HORARIA (Horas con bloqueos):';
            foreach ( $data['by_hour'] as $hr => $cnt ) {
                if ( $cnt > 0 ) {
                    $lines[] = sprintf( ' - %02d:00h: %d', $hr, $cnt );
                }
            }
            $lines[] = '';
        } elseif ( 'top_uris' === $mod && ! empty( $data['top_uris'] ) ) {
            $lines[] = 'TOP 5 RUTAS ATACADAS:';
            foreach ( $data['top_uris'] as $u => $cnt ) {
                $lines[] = " - {$u}: {$cnt}";
            }
            $lines[] = '';
        } elseif ( 'events_table' === $mod ) {
            $rows    = ( $limit > 0 ) ? array_slice( $data['grouped'], 0, $limit, true ) : $data['grouped'];
            $lines[] = 'DETALLE DE BLOQUEOS:';
            foreach ( $rows as $g ) {
                $lines[] = sprintf( '(x%d) [%s] IP: %s | País: %s | ASN: %s | Riesgo: %s | Motivo: %s', $g['count'], $g['first_time'], $g['ip'], $g['country'], $g['asn'], $g['risk'], $g['reason'] );
            }
            $lines[] = '';
        }
    }

    return implode( "\n", $lines );
}

function bap_render_custom_single_module_html( $mod, array $data, $bg_card, $bd_color, $txt_main, $txt_sub, $limit ) {
    $palette = [ '#d63638', '#2271b1', '#d97706', '#00a32a', '#646970' ];
    ob_start();
    if ( 'cards' === $mod ) : ?>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:8px;margin:0 -8px;">
            <tr>
                <td width="28%" valign="top" style="background:<?php echo esc_attr( $bg_card ); ?>;border:1px solid <?php echo esc_attr( $bd_color ); ?>;border-radius:6px;padding:12px;">
                    <div style="font-size:12px;font-weight:700;color:<?php echo esc_attr( $txt_sub ); ?>;margin-bottom:6px;">Total Bloqueados</div>
                    <div style="font-size:26px;font-weight:800;color:#d63638;line-height:1.1;"><?php echo esc_html( number_format_i18n( $data['total_hits'] ) ); ?></div>
                    <div style="font-size:11px;color:<?php echo esc_attr( $txt_sub ); ?>;margin-top:6px;">IPs únicas: <strong style="color:<?php echo esc_attr( $txt_main ); ?>;"><?php echo (int) $data['unique_ips']; ?></strong></div>
                </td>
                <td width="32%" valign="top" style="background:<?php echo esc_attr( $bg_card ); ?>;border:1px solid <?php echo esc_attr( $bd_color ); ?>;border-radius:6px;padding:12px;">
                    <div style="font-size:12px;font-weight:700;color:<?php echo esc_attr( $txt_sub ); ?>;margin-bottom:6px;">Top Países</div>
                    <?php foreach ( $data['top_countries'] as $cc => $cnt ) : ?>
                        <div style="font-size:12px;margin-bottom:2px;"><strong><?php echo esc_html( $cc ); ?></strong>: <?php echo (int) $cnt; ?></div>
                    <?php endforeach; ?>
                </td>
                <td width="40%" valign="top" style="background:<?php echo esc_attr( $bg_card ); ?>;border:1px solid <?php echo esc_attr( $bd_color ); ?>;border-radius:6px;padding:12px;">
                    <div style="font-size:12px;font-weight:700;color:<?php echo esc_attr( $txt_sub ); ?>;margin-bottom:6px;">Top ASNs / Proveedores</div>
                    <?php foreach ( $data['top_asns'] as $an => $cnt ) : ?>
                        <div style="font-size:11px;margin-bottom:2px;"><strong><?php echo esc_html( $an ); ?></strong>: <?php echo (int) $cnt; ?></div>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
    <?php elseif ( 'countries_bars' === $mod && ! empty( $data['top_countries'] ) && $data['total_hits'] > 0 ) : ?>
        <div style="background:<?php echo esc_attr( $bg_card ); ?>;border:1px solid <?php echo esc_attr( $bd_color ); ?>;border-radius:6px;padding:14px;">
            <div style="font-size:14px;font-weight:700;margin-bottom:10px;">Distribución por País (Top 5)</div>
            <?php $idx = 0; foreach ( $data['top_countries'] as $cc => $cnt ) :
                $share = round( ( $cnt / $data['total_hits'] ) * 100, 1 );
                $bar_w = max( 4, min( 100, (int) round( $share ) ) );
                $color = $palette[ $idx % count( $palette ) ];
                $idx++;
            ?>
                <div style="margin-bottom:8px;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:2px;">
                        <tr>
                            <td style="font-size:12px;font-weight:700;"><?php echo esc_html( $cc ); ?></td>
                            <td align="right" style="font-size:12px;color:<?php echo esc_attr( $txt_sub ); ?>;"><?php echo esc_html( number_format_i18n( $cnt ) . ' (' . $share . '%)' ); ?></td>
                        </tr>
                    </table>
                    <div style="width:100%;height:8px;background:<?php echo esc_attr( $bd_color ); ?>;border-radius:4px;overflow:hidden;">
                        <div style="width:<?php echo (int) $bar_w; ?>%;height:8px;background-color:<?php echo esc_attr( $color ); ?>;border-radius:4px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ( 'top_uris' === $mod && ! empty( $data['top_uris'] ) && $data['total_hits'] > 0 ) : ?>
        <div style="background:<?php echo esc_attr( $bg_card ); ?>;border:1px solid <?php echo esc_attr( $bd_color ); ?>;border-radius:6px;padding:14px;">
            <div style="font-size:14px;font-weight:700;margin-bottom:10px;">Distribución URLs Atacadas (Top 5)</div>
            <?php $idx = 0; foreach ( $data['top_uris'] as $u => $cnt ) :
                $share = round( ( $cnt / $data['total_hits'] ) * 100, 1 );
                $bar_w = max( 4, min( 100, (int) round( $share ) ) );
                $color = $palette[ $idx % count( $palette ) ];
                $idx++;
            ?>
                <div style="margin-bottom:8px;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:2px;">
                        <tr>
                            <td style="font-size:11px;word-break:break-all;"><code style="color:#38bdf8;"><?php echo esc_html( $u ); ?></code></td>
                            <td align="right" style="font-size:11px;color:<?php echo esc_attr( $txt_sub ); ?>;white-space:nowrap;padding-left:8px;"><?php echo esc_html( number_format_i18n( $cnt ) . ' (' . $share . '%)' ); ?></td>
                        </tr>
                    </table>
                    <div style="width:100%;height:8px;background:<?php echo esc_attr( $bd_color ); ?>;border-radius:4px;overflow:hidden;">
                        <div style="width:<?php echo (int) $bar_w; ?>%;height:8px;background-color:<?php echo esc_attr( $color ); ?>;border-radius:4px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ( 'hourly_chart' === $mod && ! empty( $data['by_hour'] ) ) :
        $max_h = max( 1, max( $data['by_hour'] ) );
    ?>
        <div style="background:<?php echo esc_attr( $bg_card ); ?>;border:1px solid <?php echo esc_attr( $bd_color ); ?>;border-radius:6px;padding:14px;">
            <div style="font-size:14px;font-weight:700;margin-bottom:10px;">Actividad por Hora del Día (00h – 23h)</div>
            <table width="100%" cellpadding="0" cellspacing="2" style="height:75px;">
                <tr valign="bottom">
                    <?php for ( $h = 0; $h < 24; $h++ ) :
                        $v   = (int) ( $data['by_hour'][ $h ] ?? 0 );
                        $bar = $v > 0 ? max( 6, (int) round( ( $v / $max_h ) * 60 ) ) : 2;
                        $col = $v > 0 ? '#38bdf8' : $bd_color;
                    ?>
                        <td align="center" title="<?php printf( '%02dh: %d', $h, $v ); ?>">
                            <div style="width:100%;height:<?php echo (int) $bar; ?>px;background:<?php echo esc_attr( $col ); ?>;border-radius:2px 2px 0 0;"></div>
                        </td>
                    <?php endfor; ?>
                </tr>
                <tr>
                    <?php for ( $h = 0; $h < 24; $h++ ) : ?>
                        <td align="center" style="font-size:9px;color:<?php echo esc_attr( $txt_sub ); ?>;"><?php echo ( 0 === $h % 3 ) ? sprintf( '%02d', $h ) : ''; ?></td>
                    <?php endfor; ?>
                </tr>
            </table>
        </div>
    <?php elseif ( 'events_table' === $mod ) :
        $rows = ( $limit > 0 ) ? array_slice( $data['grouped'], 0, $limit, true ) : $data['grouped'];
    ?>
        <div style="background:<?php echo esc_attr( $bg_card ); ?>;border:1px solid <?php echo esc_attr( $bd_color ); ?>;border-radius:6px;padding:14px;">
            <div style="font-size:14px;font-weight:700;margin-bottom:10px;"><?php echo esc_html( $limit > 0 ? "Top {$limit} intentos bloqueados" : 'Detalle de intentos bloqueados' ); ?></div>
            <table width="100%" cellpadding="7" cellspacing="0" style="border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr style="border-bottom:1px solid <?php echo esc_attr( $bd_color ); ?>;text-align:left;">
                        <th>Fecha</th>
                        <th>IP</th>
                        <th>Riesgo</th>
                        <th>País</th>
                        <th>ASN</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $g ) :
                        $last_short = substr( $g['last_time'], 11 ) ?: $g['last_time'];
                        $time_str   = ( $g['first_time'] !== $g['last_time'] && '' !== $g['last_time'] ) ? $g['first_time'] . ' → ' . $last_short : $g['first_time'];
                    ?>
                        <tr style="border-bottom:1px solid <?php echo esc_attr( $bd_color ); ?>;">
                            <td style="white-space:nowrap;"><?php echo esc_html( $time_str ); ?></td>
                            <td style="white-space:nowrap;">
                                <span style="display:inline-block;min-width:20px;text-align:center;background:#d63638;color:#fff;font-weight:700;font-size:11px;line-height:18px;padding:0 5px;border-radius:4px;margin-right:4px;"><?php echo (int) $g['count']; ?></span>
                                <code style="color:#2271b1;"><?php echo esc_html( $g['ip'] ); ?></code>
                            </td>
                            <td><strong><?php echo esc_html( $g['risk'] ); ?></strong></td>
                            <td><strong><?php echo esc_html( $g['country'] ); ?></strong></td>
                            <td style="color:<?php echo esc_attr( $txt_sub ); ?>;"><?php echo esc_html( $g['asn'] ); ?></td>
                            <td><?php echo esc_html( $g['reason'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif;
    return (string) ob_get_clean();
}

function bap_render_custom_template_html( array $tpl, array $data, $site_name, $stats_url, $is_test ) {
    $is_light = ( 'html_light' === ( $tpl['format'] ?? 'html_dark' ) );
    $bg_body  = $is_light ? '#f3f4f6' : '#0a0a0a';
    $bg_wrap  = $is_light ? '#ffffff' : '#0f0f0f';
    $bg_card  = $is_light ? '#f9fafb' : '#161616';
    $bd_color = $is_light ? '#e5e7eb' : '#2a2a2a';
    $txt_main = $is_light ? '#111827' : '#ffffff';
    $txt_sub  = $is_light ? '#4b5563' : '#9ca3af';
    $modules  = $tpl['modules'] ?? [];
    $sizes    = $tpl['sizes'] ?? [];
    $limit    = (int) ( $tpl['table_limit'] ?? 10 );

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head><meta charset="UTF-8" /></head>
    <body style="margin:0;padding:20px;background-color:<?php echo esc_attr( $bg_body ); ?>;color:<?php echo esc_attr( $txt_main ); ?>;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
        <div style="max-width:920px;margin:0 auto;background-color:<?php echo esc_attr( $bg_wrap ); ?>;border:1px solid <?php echo esc_attr( $bd_color ); ?>;border-radius:8px;padding:22px;">
            <?php if ( $is_test ) : ?>
                <div style="background:#2d2206;border:1px solid #d97706;color:#fbbf24;padding:8px 12px;border-radius:4px;font-size:12px;font-weight:700;margin-bottom:14px;">
                    CORREO DE PRUEBA — Plantilla: <?php echo esc_html( (string) ( $tpl['name'] ?? 'Personalizada' ) ); ?>
                </div>
            <?php endif; ?>

            <h1 style="margin:0 0 4px 0;font-size:20px;color:<?php echo esc_attr( $txt_main ); ?>;">Control de Acceso al Panel — <?php echo esc_html( $site_name ); ?></h1>
            <p style="margin:0 0 18px 0;font-size:13px;color:<?php echo esc_attr( $txt_sub ); ?>;">Resumen modular de seguridad (<?php echo count( $data['grouped'] ); ?> eventos únicos agrupados)</p>

            <div style="font-size:0;margin:0 -6px;">
                <?php foreach ( $modules as $mod ) :
                    $w_pct = in_array( (string) ( $sizes[ $mod ] ?? '100' ), [ '100', '50', '33' ], true ) ? (string) $sizes[ $mod ] : '100';
                    $width_css = ( '33' === $w_pct ) ? '33.33%' : $w_pct . '%';
                    $inner = bap_render_custom_single_module_html( $mod, $data, $bg_card, $bd_color, $txt_main, $txt_sub, $limit );
                    if ( '' === trim( $inner ) ) {
                        continue;
                    }
                ?>
                    <div style="display:inline-block;vertical-align:top;width:<?php echo esc_attr( $width_css ); ?>;box-sizing:border-box;padding:0 6px 14px 6px;font-size:13px;">
                        <?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <p style="margin:8px 0 0 0;text-align:right;font-size:12px;">
                <a href="<?php echo esc_url( $stats_url ); ?>" style="color:#2271b1;text-decoration:none;">Ver panel completo de Estadísticas en WordPress &rarr;</a>
            </p>
        </div>
    </body>
    </html>
    <?php
    return (string) ob_get_clean();
}
