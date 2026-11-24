<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_hourly_line_chart( $table ) {
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT HOUR(created_at) AS hr, COUNT(*) AS total FROM `{$table}` GROUP BY HOUR(created_at)", ARRAY_A );

    $hours = array_fill( 0, 24, 0 );
    foreach ( (array) $rows as $r ) {
        $h = (int) $r['hr'];
        if ( $h >= 0 && $h <= 23 ) {
            $hours[ $h ] = (int) $r['total'];
        }
    }

    $max_val  = max( 1, max( $hours ) );
    $peak_hr  = array_search( max( $hours ), $hours, true );
    $w        = 820;
    $h_svg    = 145;
    $pad_x    = 26;
    $pad_top  = 18;
    $pad_bot  = 24;
    $plot_w   = $w - ( $pad_x * 2 );
    $plot_h   = $h_svg - $pad_top - $pad_bot;
    $pts      = [];
    $coords   = [];

    for ( $i = 0; $i < 24; $i++ ) {
        $x        = round( $pad_x + ( $i / 23 ) * $plot_w, 1 );
        $y        = round( $pad_top + $plot_h - ( ( $hours[ $i ] / $max_val ) * $plot_h ), 1 );
        $pts[]    = $x . ',' . $y;
        $coords[] = [ 'h' => $i, 'cnt' => $hours[ $i ], 'x' => $x, 'y' => $y ];
    }

    $line_pts = implode( ' ', $pts );
    $area_pts = $pad_x . ',' . ( $pad_top + $plot_h ) . ' ' . $line_pts . ' ' . ( $pad_x + $plot_w ) . ',' . ( $pad_top + $plot_h );
    $ns       = 'http://' . 'www.w3.org/2000/svg';
    ?>
    <div class="card" style="margin:0 0 20px 0;padding:16px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
            <h3 style="margin:0;">Distribución horaria de bloqueos (00:00 – 23:59)</h3>
            <?php if ( max( $hours ) > 0 ) : ?>
                <span style="font-size:12px;color:#8c8f94;">Hora punta: <strong style="color:#f86368;"><?php printf( '%02d:00h', (int) $peak_hr ); ?></strong> (<?php echo number_format_i18n( $hours[ $peak_hr ] ); ?> bloqueos)</span>
            <?php endif; ?>
        </div>
        <svg viewBox="0 0 <?php echo (int) $w; ?> <?php echo (int) $h_svg; ?>" style="width:100%;height:165px;display:block;overflow:visible;" xmlns="<?php echo esc_attr( $ns ); ?>">
            <defs>
                <linearGradient id="bapHourGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#2271b1" stop-opacity="0.35" />
                    <stop offset="100%" stop-color="#2271b1" stop-opacity="0.02" />
                </linearGradient>
            </defs>
            <line x1="<?php echo (int) $pad_x; ?>" y1="<?php echo (int) ( $pad_top + $plot_h ); ?>" x2="<?php echo (int) ( $pad_x + $plot_w ); ?>" y2="<?php echo (int) ( $pad_top + $plot_h ); ?>" stroke="#3c434a" stroke-width="1" />
            <line x1="<?php echo (int) $pad_x; ?>" y1="<?php echo (int) ( $pad_top + ( $plot_h / 2 ) ); ?>" x2="<?php echo (int) ( $pad_x + $plot_w ); ?>" y2="<?php echo (int) ( $pad_top + ( $plot_h / 2 ) ); ?>" stroke="#2c3338" stroke-dasharray="4 4" stroke-width="1" />
            <polygon points="<?php echo esc_attr( $area_pts ); ?>" fill="url(#bapHourGrad)" />
            <polyline fill="none" stroke="#38bdf8" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" points="<?php echo esc_attr( $line_pts ); ?>" />
            <?php foreach ( $coords as $c ) : ?>
                <g>
                    <title><?php printf( '%02d:00h — %s bloqueos', (int) $c['h'], number_format_i18n( $c['cnt'] ) ); ?></title>
                    <circle cx="<?php echo esc_attr( $c['x'] ); ?>" cy="<?php echo esc_attr( $c['y'] ); ?>" r="3.5" fill="<?php echo $c['cnt'] === max( $hours ) && $c['cnt'] > 0 ? '#f86368' : '#38bdf8'; ?>" stroke="#0f172a" stroke-width="1.5" />
                    <?php if ( 0 === $c['h'] % 2 || 23 === $c['h'] ) : ?>
                        <text x="<?php echo esc_attr( $c['x'] ); ?>" y="<?php echo (int) ( $h_svg - 4 ); ?>" text-anchor="middle" fill="#8c8f94" font-size="10" font-family="sans-serif"><?php printf( '%02dh', (int) $c['h'] ); ?></text>
                    <?php endif; ?>
                </g>
            <?php endforeach; ?>
        </svg>
    </div>
    <?php
}

function bap_render_estadisticas_charts( $table, $total_blocks, $top_countries ) {
    global $wpdb;

    $since = gmdate( 'Y-m-d 00:00:00', strtotime( '-13 days' ) );
    $rows  = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM `{$table}` WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC",
            $since
        ),
        ARRAY_A
    );

    $by_day = [];
    foreach ( (array) $rows as $r ) {
        $by_day[ $r['day'] ] = (int) $r['total'];
    }

    $days    = [];
    $max_day = 1;
    for ( $i = 13; $i >= 0; $i-- ) {
        $d   = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
        $cnt = $by_day[ $d ] ?? 0;
        if ( $cnt > $max_day ) {
            $max_day = $cnt;
        }
        $days[] = [ 'date' => $d, 'label' => gmdate( 'd/m', strtotime( $d ) ), 'count' => $cnt ];
    }
    ?>
    <div style="display:flex;gap:16px;margin:0 0 20px 0;flex-wrap:wrap;">
        <div class="card" style="flex:1.5;min-width:340px;margin:0;padding:16px 20px;">
            <h3 style="margin-top:0;margin-bottom:12px;">Evolución de bloqueos (Últimos 14 días)</h3>
            <div style="display:flex;align-items:flex-end;gap:6px;height:130px;padding:10px 6px 4px;border-bottom:1px solid #dcdcde;background:#f9f9f9;border-radius:4px 4px 0 0;">
                <?php foreach ( $days as $item ) :
                    $pct = $item['count'] > 0 ? max( 6, (int) round( ( $item['count'] / $max_day ) * 100 ) ) : 2;
                    $bg  = $item['count'] > 0 ? '#2271b1' : '#dcdcde';
                ?>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;" title="<?php echo esc_attr( $item['date'] . ': ' . $item['count'] . ' bloqueos' ); ?>">
                        <?php if ( $item['count'] > 0 ) : ?>
                            <span style="font-size:10px;color:#50575e;margin-bottom:2px;font-weight:600;"><?php echo (int) $item['count']; ?></span>
                        <?php endif; ?>
                        <div style="width:100%;max-width:26px;height:<?php echo (int) $pct; ?>%;background:<?php echo esc_attr( $bg ); ?>;border-radius:3px 3px 0 0;"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:6px;padding:4px 6px 0;">
                <?php foreach ( $days as $idx => $item ) : ?>
                    <div style="flex:1;text-align:center;font-size:10px;color:#646970;"><?php echo ( 0 === $idx % 2 || 13 === $idx ) ? esc_html( $item['label'] ) : ''; ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card" style="flex:1;min-width:280px;margin:0;padding:16px 20px;">
            <h3 style="margin-top:0;margin-bottom:12px;">Distribución por País (Top 5)</h3>
            <?php if ( empty( $top_countries ) || $total_blocks <= 0 ) : ?>
                <p class="description">Aún no hay suficientes datos para mostrar la distribución.</p>
            <?php else :
                $palette = [ '#d63638', '#2271b1', '#d97706', '#00a32a', '#646970' ];
            ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ( $top_countries as $i => $tc ) :
                        $cnt   = (int) $tc->total;
                        $share = round( ( $cnt / $total_blocks ) * 100, 1 );
                        $bar_w = max( 3, min( 100, $share ) );
                        $color = $palette[ $i % count( $palette ) ];
                    ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                                <strong><?php echo esc_html( $tc->country_code ); ?></strong>
                                <span><?php echo number_format_i18n( $cnt ); ?> (<?php echo esc_html( $share ); ?>%)</span>
                            </div>
                            <div style="width:100%;height:8px;background:#f0f0f1;border-radius:4px;overflow:hidden;">
                                <div style="width:<?php echo esc_attr( $bar_w ); ?>%;height:100%;background:<?php echo esc_attr( $color ); ?>;border-radius:4px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    bap_render_hourly_line_chart( $table );
}
