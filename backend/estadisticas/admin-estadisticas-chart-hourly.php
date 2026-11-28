<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_hourly_line_chart( $table, $type_where_sql = '', $mode = 'blocks' ) {
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT HOUR(created_at) AS hr, COUNT(*) AS total FROM `{$table}` WHERE 1=1{$type_where_sql} GROUP BY HOUR(created_at)", ARRAY_A );

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
    <div class="card" style="flex:1.2;min-width:320px;margin:0;padding:16px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
            <h3 style="margin:0;"><?php echo esc_html( 'visits' === $mode ? 'Distribución horaria de visitas (00:00 – 23:59)' : 'Distribución horaria de bloqueos (00:00 – 23:59)' ); ?></h3>
            <?php if ( max( $hours ) > 0 ) : ?>
                <span style="font-size:12px;color:#8c8f94;">Hora punta: <strong style="color:#f86368;"><?php printf( '%02d:00h', (int) $peak_hr ); ?></strong> (<?php echo number_format_i18n( $hours[ $peak_hr ] ); ?> <?php echo esc_html( 'visits' === $mode ? 'visitas' : 'bloqueos' ); ?>)</span>
            <?php endif; ?>
        </div>
        <svg viewBox="0 0 <?php echo (int) $w; ?> <?php echo (int) $h_svg; ?>" style="width:100%;height:155px;display:block;overflow:visible;" xmlns="<?php echo esc_attr( $ns ); ?>">
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
