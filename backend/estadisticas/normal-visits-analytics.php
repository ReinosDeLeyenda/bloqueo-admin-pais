<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_parse_visit_ua_info( $ua ) {
    $ua_s = (string) $ua;
    if ( '' === trim( $ua_s ) ) {
        return [ 'device' => 'Desconocido', 'browser' => 'Desconocido', 'os' => 'Desconocido' ];
    }

    // Dispositivo
    if ( preg_match( '/ipad|tablet|playbook|silk|(android(?!.*mobi))/i', $ua_s ) ) {
        $device = 'Tablet';
    } elseif ( preg_match( '/mobile|iphone|ipod|android.*mobi|windows phone|blackberry/i', $ua_s ) ) {
        $device = 'Móvil';
    } else {
        $device = 'Escritorio';
    }

    // Navegador
    if ( preg_match( '/Edg\//i', $ua_s ) ) {
        $browser = 'Edge';
    } elseif ( preg_match( '/OPR\/|Opera/i', $ua_s ) ) {
        $browser = 'Opera';
    } elseif ( preg_match( '/Firefox\//i', $ua_s ) ) {
        $browser = 'Firefox';
    } elseif ( preg_match( '/Chrome\//i', $ua_s ) && ! preg_match( '/Chromium/i', $ua_s ) ) {
        $browser = 'Chrome';
    } elseif ( preg_match( '/Safari\//i', $ua_s ) && ! preg_match( '/Chrome|Chromium/i', $ua_s ) ) {
        $browser = 'Safari';
    } else {
        $browser = 'Otros';
    }

    // Sistema Operativo
    if ( preg_match( '/Windows NT/i', $ua_s ) ) {
        $os = 'Windows';
    } elseif ( preg_match( '/Android/i', $ua_s ) ) {
        $os = 'Android';
    } elseif ( preg_match( '/iPhone|iPad|iPod/i', $ua_s ) ) {
        $os = 'iOS / iPadOS';
    } elseif ( preg_match( '/Mac OS X|Macintosh/i', $ua_s ) ) {
        $os = 'macOS';
    } elseif ( preg_match( '/Linux/i', $ua_s ) ) {
        $os = 'Linux';
    } else {
        $os = 'Otros';
    }

    return [ 'device' => $device, 'browser' => $browser, 'os' => $os ];
}

function bap_classify_visit_referer( $referer, $site_host ) {
    $ref = trim( (string) $referer );
    if ( '' === $ref ) {
        return [ 'channel' => 'Directo', 'domain' => '(Entrada directa)' ];
    }
    $host = strtolower( (string) wp_parse_url( $ref, PHP_URL_HOST ) );
    $host = preg_replace( '/^www\./', '', $host );
    if ( '' === $host || $host === $site_host ) {
        return [ 'channel' => 'Directo / Interno', 'domain' => '(Navegación interna / directa)' ];
    }
    if ( preg_match( '/(?:google\.|bing\.|duckduckgo\.|yahoo\.|ecosia\.|yandex\.|baidu\.|brave\.com)/i', $host ) ) {
        return [ 'channel' => 'Buscadores', 'domain' => $host ];
    }
    if ( preg_match( '/(?:t\.co|twitter\.com|x\.com|facebook\.com|fb\.com|instagram\.com|youtube\.com|youtu\.be|discord\.com|discordapp\.com|reddit\.com|tiktok\.com|linkedin\.com|telegram\.org|t\.me)/i', $host ) ) {
        return [ 'channel' => 'Redes Sociales', 'domain' => $host ];
    }
    return [ 'channel' => 'Webs Externas', 'domain' => $host ];
}

function bap_build_visits_analytics_breakdown( $table, $where_sql, array $params ) {
    global $wpdb;
    $sql  = "SELECT request_uri, referer, user_agent FROM `{$table}` {$where_sql} ORDER BY id DESC LIMIT 3000";
    $rows = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

    $site_host = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
    $channels  = [];
    $domains   = [];
    $utms      = [];
    $devices   = [];
    $browsers  = [];
    $oses      = [];

    foreach ( (array) $rows as $r ) {
        $ref_info = bap_classify_visit_referer( $r['referer'] ?? '', $site_host );
        $channels[ $ref_info['channel'] ] = ( $channels[ $ref_info['channel'] ] ?? 0 ) + 1;
        if ( 0 !== strpos( $ref_info['domain'], '(' ) ) {
            $domains[ $ref_info['domain'] ] = ( $domains[ $ref_info['domain'] ] ?? 0 ) + 1;
        }

        $ua_info = bap_parse_visit_ua_info( $r['user_agent'] ?? '' );
        $devices[ $ua_info['device'] ]   = ( $devices[ $ua_info['device'] ] ?? 0 ) + 1;
        $browsers[ $ua_info['browser'] ] = ( $browsers[ $ua_info['browser'] ] ?? 0 ) + 1;
        $oses[ $ua_info['os'] ]           = ( $oses[ $ua_info['os'] ] ?? 0 ) + 1;

        $q_str = (string) wp_parse_url( (string) ( $r['request_uri'] ?? '' ), PHP_URL_QUERY );
        if ( '' !== $q_str && false !== stripos( $q_str, 'utm_' ) ) {
            parse_str( $q_str, $q_vars );
            $src = sanitize_text_field( (string) ( $q_vars['utm_source'] ?? '' ) );
            $med = sanitize_text_field( (string) ( $q_vars['utm_medium'] ?? '' ) );
            $cmp = sanitize_text_field( (string) ( $q_vars['utm_campaign'] ?? '' ) );
            if ( '' !== $src || '' !== $cmp ) {
                $lbl          = trim( ( $src ?: 'sin_source' ) . ( '' !== $med ? ' / ' . $med : '' ) . ( '' !== $cmp ? ' (' . $cmp . ')' : '' ) );
                $utms[ $lbl ] = ( $utms[ $lbl ] ?? 0 ) + 1;
            }
        }
    }

    arsort( $channels );
    arsort( $domains );
    arsort( $utms );
    arsort( $devices );
    arsort( $browsers );
    arsort( $oses );

    return [
        'sample'   => count( (array) $rows ),
        'channels' => array_slice( $channels, 0, 5, true ),
        'domains'  => array_slice( $domains, 0, 5, true ),
        'utms'     => array_slice( $utms, 0, 5, true ),
        'devices'  => array_slice( $devices, 0, 5, true ),
        'browsers' => array_slice( $browsers, 0, 5, true ),
        'oses'     => array_slice( $oses, 0, 5, true ),
    ];
}
function bap_render_visits_mini_bar_card( $title, array $items, $total, $empty_msg, $palette ) {
    ?>
    <div class="card" style="flex:1;min-width:260px;margin:0;padding:15px 18px;">
        <h3 style="margin-top:0;margin-bottom:10px;font-size:14px;"><?php echo esc_html( $title ); ?></h3>
        <?php if ( empty( $items ) || $total <= 0 ) : ?>
            <p class="description"><?php echo esc_html( $empty_msg ); ?></p>
        <?php else : ?>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <?php $i = 0; foreach ( $items as $lbl => $cnt ) :
                    $share = round( ( $cnt / $total ) * 100, 1 );
                    $bar_w = max( 4, min( 100, $share ) );
                    $color = $palette[ $i % count( $palette ) ];
                    $i++;
                ?>
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:2px;">
                            <strong style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:68%;" title="<?php echo esc_attr( $lbl ); ?>"><?php echo esc_html( $lbl ); ?></strong>
                            <span><?php echo number_format_i18n( $cnt ); ?> (<?php echo esc_html( $share ); ?>%)</span>
                        </div>
                        <div style="width:100%;height:7px;background:#f0f0f1;border-radius:4px;overflow:hidden;">
                            <div style="width:<?php echo esc_attr( $bar_w ); ?>%;height:100%;background:<?php echo esc_attr( $color ); ?>;border-radius:4px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function bap_render_visits_analytics_cards( array $data ) {
    $tot = max( 1, (int) $data['sample'] );
    $p1  = [ '#00a32a', '#2271b1', '#d97706', '#8b5cf6', '#646970' ];
    $p2  = [ '#38bdf8', '#10b981', '#f59e0b', '#ec4899', '#6366f1' ];
    ?>
    <div style="display:flex;gap:16px;margin:0 0 18px 0;flex-wrap:wrap;">
        <?php bap_render_visits_mini_bar_card( 'Canales de Adquisición', $data['channels'], $tot, 'Sin datos de tráfico todavía.', $p1 ); ?>
        <?php bap_render_visits_mini_bar_card( 'Dominios Referer (Top 5)', $data['domains'], $tot, 'Aún no hay visitas desde enlaces externos.', $p1 ); ?>
        <?php bap_render_visits_mini_bar_card( 'Campañas UTM (Top 5)', $data['utms'], $tot, 'Sin parámetros ?utm_source= detectados.', $p1 ); ?>
    </div>

    <div style="display:flex;gap:16px;margin:0 0 20px 0;flex-wrap:wrap;">
        <?php bap_render_visits_mini_bar_card( 'Dispositivos', $data['devices'], $tot, 'Sin datos de dispositivos.', $p2 ); ?>
        <?php bap_render_visits_mini_bar_card( 'Navegadores', $data['browsers'], $tot, 'Sin datos de navegadores.', $p2 ); ?>
        <?php bap_render_visits_mini_bar_card( 'Sistemas Operativos', $data['oses'], $tot, 'Sin datos de sistema operativo.', $p2 ); ?>
    </div>
    <?php
}
function bap_calculate_visits_session_metrics( $table, $where_sql, array $params ) {
    global $wpdb;
    $sql  = "SELECT ip_address, UNIX_TIMESTAMP(created_at) AS ts FROM `{$table}` {$where_sql} ORDER BY ip_address ASC, created_at ASC LIMIT 25000";
    $rows = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

    if ( empty( $rows ) ) {
        return [ 'sessions' => 0, 'pages_per_session' => 0.0, 'bounce_rate' => 0.0 ];
    }

    $sessions       = 0;
    $bounces        = 0;
    $total_pages    = 0;
    $prev_ip        = '';
    $prev_ts        = 0;
    $cur_sess_pages = 0;

    foreach ( $rows as $r ) {
        $ip = (string) ( $r['ip_address'] ?? '' );
        $ts = (int) ( $r['ts'] ?? 0 );

        if ( $ip !== $prev_ip || ( $ts - $prev_ts ) > 1800 ) {
            if ( $cur_sess_pages > 0 ) {
                $sessions++;
                $total_pages += $cur_sess_pages;
                if ( 1 === $cur_sess_pages ) {
                    $bounces++;
                }
            }
            $cur_sess_pages = 1;
        } else {
            $cur_sess_pages++;
        }

        $prev_ip = $ip;
        $prev_ts = $ts;
    }

    if ( $cur_sess_pages > 0 ) {
        $sessions++;
        $total_pages += $cur_sess_pages;
        if ( 1 === $cur_sess_pages ) {
            $bounces++;
        }
    }

    return [
        'sessions'          => $sessions,
        'pages_per_session' => $sessions > 0 ? round( $total_pages / $sessions, 2 ) : 0.0,
        'bounce_rate'       => $sessions > 0 ? round( ( $bounces / $sessions ) * 100, 1 ) : 0.0,
    ];
}