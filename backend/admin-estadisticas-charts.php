<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_estadisticas_charts( $table, $total_blocks, $top_countries ) {
    global $wpdb;

    $since = gmdate( 'Y-m-d 00:00:00', strtotime( '-13 days' ) );
    $rows  = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DATE(created_at) AS day, COUNT(*) AS total
             FROM `{$table}`
             WHERE created_at >= %s
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
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
        $days[] = [
            'date'  => $d,
            'label' => gmdate( 'd/m', strtotime( $d ) ),
            'count' => $cnt,
        ];
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
                        <div style="width:100%;max-width:26px;height:<?php echo (int) $pct; ?>%;background:<?php echo esc_attr( $bg ); ?>;border-radius:3px 3px 0 0;transition:height 0.2s;"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:6px;padding:4px 6px 0;">
                <?php foreach ( $days as $idx => $item ) : ?>
                    <div style="flex:1;text-align:center;font-size:10px;color:#646970;">
                        <?php echo ( 0 === $idx % 2 || 13 === $idx ) ? esc_html( $item['label'] ) : ''; ?>
                    </div>
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
}
