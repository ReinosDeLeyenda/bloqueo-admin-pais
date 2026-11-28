<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_top_bars_row( $total_blocks, $top_countries, $top_uris, $stats_base_url, $mode = 'blocks' ) {
    $palette = [ '#d63638', '#2271b1', '#d97706', '#00a32a', '#646970' ];
    ?>
    <div style="display:flex;gap:16px;margin:0 0 20px 0;flex-wrap:wrap;">
        <div class="card" style="flex:1;min-width:280px;margin:0;padding:16px 20px;">
            <h3 style="margin-top:0;margin-bottom:12px;">Distribución por País (Top 5)</h3>
            <?php if ( empty( $top_countries ) || $total_blocks <= 0 ) : ?>
                <p class="description">Aún no hay suficientes datos.</p>
            <?php else : ?>
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

        <div class="card" style="flex:1;min-width:280px;margin:0;padding:16px 20px;">
            <h3 style="margin-top:0;margin-bottom:12px;"><?php echo esc_html( 'visits' === $mode ? 'Distribución Páginas Visitadas (Top 5)' : 'Distribución URLs Atacadas (Top 5)' ); ?></h3>
            <?php if ( empty( $top_uris ) || $total_blocks <= 0 ) : ?>
                <p class="description">Aún no hay suficientes datos.</p>
            <?php else : ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ( $top_uris as $i => $tu ) :
                        $cnt      = (int) $tu->total;
                        $share    = round( ( $cnt / $total_blocks ) * 100, 1 );
                        $bar_w    = max( 3, min( 100, $share ) );
                        $color    = $palette[ $i % count( $palette ) ];
                        $uri_link = '' !== $stats_base_url ? add_query_arg( 'visits' === $mode ? 'v_q' : 'filter_uri', rawurlencode( $tu->request_uri ), $stats_base_url ) : '#';
                    ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;gap:8px;font-size:12px;margin-bottom:3px;">
                                <span role="link" tabindex="0" onclick="window.location.href='<?php echo esc_js( $uri_link ); ?>';" style="cursor:pointer;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:68%;" title="<?php echo esc_attr( $tu->request_uri ); ?>">
                                    <code><?php echo esc_html( $tu->request_uri ); ?></code>
                                </span>
                                <span style="white-space:nowrap;"><?php echo number_format_i18n( $cnt ); ?> (<?php echo esc_html( $share ); ?>%)</span>
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
