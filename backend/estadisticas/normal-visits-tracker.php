<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_visits_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'bap_normal_visits';
}

function bap_maybe_create_visits_table() {
    if ( '1.0' === get_option( 'bap_visits_table_ver' ) ) {
        return;
    }
    global $wpdb;
    $table   = bap_get_visits_table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `{$table}` (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        ip_address varchar(45) NOT NULL,
        country_code varchar(16) NOT NULL,
        asn varchar(190) NOT NULL DEFAULT '',
        request_uri text NOT NULL,
        referer text NOT NULL,
        user_agent text NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY idx_created_at (created_at),
        KEY idx_ip_address (ip_address)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    update_option( 'bap_visits_table_ver', '1.0' );
}
add_action( 'admin_init', 'bap_maybe_create_visits_table' );

add_action( 'admin_init', function() {
    if ( empty( $_POST['bap_export_visits_csv'] ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Acceso denegado', 403 );
    }
    check_admin_referer( 'bap_visits_action' );

    global $wpdb;
    $table = bap_get_visits_table_name();
    if ( function_exists( 'bap_send_download_headers' ) ) {
        bap_send_download_headers( 'visitas-normales-' . wp_date( 'Y-m-d-His' ) . '.csv', 'text/csv' );
    } else {
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="visitas-normales.csv"' );
    }

    $out = fopen( 'php://output', 'w' );
    fwrite( $out, "\xEF\xBB\xBF" );
    fputcsv( $out, [ 'ID', 'Fecha y Hora', 'Direccion IP', 'Pais', 'ASN / Proveedor', 'Pagina Visitada', 'Referer', 'User-Agent' ], ';' );

    $batch  = 2000;
    $offset = 0;
    while ( true ) {
        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d OFFSET %d", $batch, $offset ),
            ARRAY_A
        );
        if ( empty( $rows ) ) {
            break;
        }
        foreach ( $rows as $r ) {
            fputcsv( $out, [ $r['id'], $r['created_at'], $r['ip_address'], $r['country_code'], $r['asn'], $r['request_uri'], $r['referer'], $r['user_agent'] ], ';' );
        }
        flush();
        $offset += $batch;
    }
    fclose( $out );
    exit;
} );

add_action( 'template_redirect', function() {
    if ( '1' !== (string) get_option( 'bap_track_normal_visits', '1' ) ) {
        return;
    }
    if ( is_admin() || is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() || is_404() ) {
        return;
    }
    if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
        return;
    }
    if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
        return;
    }

    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
    $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
    if ( preg_match( '/\.(?:css|js|map|png|jpg|jpeg|gif|webp|svg|ico|woff2?|ttf|eot|xml|txt)$/i', $path ) ) {
        return;
    }

    $ip = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
    if ( '' === $ip || ( function_exists( 'bap_is_server_ip' ) && bap_is_server_ip( $ip ) ) ) {
        return;
    }
    if ( '1' === (string) get_option( 'bap_visits_exclude_seo', '1' ) && function_exists( 'bap_is_verified_seo_bot' ) && bap_is_verified_seo_bot( $ip ) ) {
        return;
    }

    bap_maybe_create_visits_table();

    global $wpdb;
    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    $asn     = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : '';
    $ua      = function_exists( 'bap_get_sanitized_user_agent' ) ? bap_get_sanitized_user_agent() : '';
    $ref     = isset( $_SERVER['HTTP_REFERER'] ) ? mb_substr( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), 0, 500 ) : '';

    $wpdb->insert(
        bap_get_visits_table_name(),
        [
            'ip_address'   => $ip,
            'country_code' => $country,
            'asn'          => $asn,
            'request_uri'  => $uri ?: '/',
            'referer'      => $ref,
            'user_agent'   => $ua,
            'created_at'   => current_time( 'mysql' ),
        ],
        [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
    );
}, 20 );

add_action( 'bap_daily_logs_cleanup', function() {
    $days = (int) get_option( 'bap_logs_retention_days', 30 );
    if ( $days <= 0 ) {
        return;
    }
    global $wpdb;
    $table  = bap_get_visits_table_name();
    $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
    $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE created_at < %s", $cutoff ) );
} );
function bap_render_normal_visits_subtab() {
    global $wpdb;
    bap_maybe_create_visits_table();
    $table  = bap_get_visits_table_name();
    $notice = '';

    if ( isset( $_POST['bap_toggle_visits_tracking'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_visits_action' ) ) {
        update_option( 'bap_track_normal_visits', ! empty( $_POST['bap_track_normal_visits'] ) ? '1' : '0' );
        update_option( 'bap_visits_exclude_seo', ! empty( $_POST['bap_visits_exclude_seo'] ) ? '1' : '0' );
        $notice = '<div class="notice notice-success is-dismissible"><p>Configuracion de visitas normales guardada.</p></div>';
    }

    if ( isset( $_POST['bap_clear_normal_visits'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_visits_action' ) ) {
        $wpdb->query( "TRUNCATE TABLE `{$table}`" );
        $notice = '<div class="notice notice-success is-dismissible"><p>Historial de visitas normales vaciado.</p></div>';
    }

    $tracking_on = ( '1' === (string) get_option( 'bap_track_normal_visits', '1' ) );
    $excl_seo_on = ( '1' === (string) get_option( 'bap_visits_exclude_seo', '1' ) );
    $hide_seo_q  = isset( $_GET['v_no_seo'] ) ? ( '1' === $_GET['v_no_seo'] ) : $excl_seo_on;
    $filter_q    = isset( $_GET['v_q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['v_q'] ) ) ) : '';

    $seo_where = $hide_seo_q ? " AND user_agent NOT REGEXP 'Googlebot|Bingbot|DuckDuckBot|Applebot|Baiduspider|YandexBot'" : '';

    $since_24h  = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
    $total_vis  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE 1=1{$seo_where}" );
    $unique_vis = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT ip_address) FROM `{$table}` WHERE 1=1{$seo_where}" );
    $vis_24h    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE created_at >= %s{$seo_where}", $since_24h ) );

    $top_countries = $wpdb->get_results( "SELECT country_code, COUNT(*) AS total FROM `{$table}` WHERE 1=1{$seo_where} GROUP BY country_code ORDER BY total DESC LIMIT 5" );
    $top_asns      = $wpdb->get_results( "SELECT asn, COUNT(*) AS total FROM `{$table}` WHERE asn != ''{$seo_where} GROUP BY asn ORDER BY total DESC LIMIT 5" );
    $top_uris      = $wpdb->get_results( "SELECT request_uri, COUNT(*) AS total FROM `{$table}` WHERE request_uri != ''{$seo_where} GROUP BY request_uri ORDER BY total DESC LIMIT 5" );

    $where  = [ '1=1' ];
    $params = [];
    if ( $hide_seo_q ) {
        $where[] = "user_agent NOT REGEXP 'Googlebot|Bingbot|DuckDuckBot|Applebot|Baiduspider|YandexBot'";
    }
    if ( '' !== $filter_q ) {
        $like     = '%' . $wpdb->esc_like( $filter_q ) . '%';
        $where[]  = '(ip_address LIKE %s OR country_code LIKE %s OR asn LIKE %s OR request_uri LIKE %s OR user_agent LIKE %s)';
        array_push( $params, $like, $like, $like, $like, $like );
    }

    $where_sql = 'WHERE ' . implode( ' AND ', $where );
    $sql       = "SELECT * FROM `{$table}` {$where_sql} ORDER BY id DESC LIMIT 100";
    $rows      = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

    if ( function_exists( 'bap_is_guest_demo_session' ) && bap_is_guest_demo_session() && ! empty( $rows ) ) {
        foreach ( $rows as $r ) {
            $raw_ip = trim( (string) ( $r->ip_address ?? '' ) );
            if ( filter_var( $raw_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                $p = explode( '.', $raw_ip );
                $r->ip_address = $p[0] . '.' . $p[1] . '.xxx.xxx';
            }
        }
    }

    echo wp_kses_post( $notice );
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin:12px 0;padding:12px 14px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;flex-wrap:wrap;gap:12px;">
        <form method="post" style="display:flex;align-items:center;gap:12px;margin:0;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_visits_action' ); ?>
            <label><input type="checkbox" name="bap_track_normal_visits" value="1" <?php checked( $tracking_on ); ?> /> <strong>Registrar visitas</strong></label>
            <label><input type="checkbox" name="bap_visits_exclude_seo" value="1" <?php checked( $excl_seo_on ); ?> /> Excluir bots SEO (Googlebot/Bingbot)</label>
            <button type="submit" name="bap_toggle_visits_tracking" class="button button-primary">Guardar</button>
            <button type="submit" name="bap_export_visits_csv" class="button button-secondary">Exportar CSV</button>
            <?php if ( $total_vis > 0 ) : ?>
                <button type="submit" name="bap_clear_normal_visits" class="button button-link-delete" onclick="return confirm('Vaciar historial de visitas normales?');">Vaciar</button>
            <?php endif; ?>
        </form>

        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;align-items:center;gap:8px;margin:0;flex-wrap:wrap;">
            <input type="hidden" name="page" value="bap-admin-estadisticas" />
            <input type="hidden" name="subtab" value="visitas" /><select name="v_range" onchange="this.form.submit();" style="font-weight:600;"><option value="today" <?php selected( $range, 'today' ); ?>>Hoy</option><option value="7d" <?php selected( $range, '7d' ); ?>>Últimos 7 días</option><option value="30d" <?php selected( $range, '30d' ); ?>>Últimos 30 días</option><option value="all" <?php selected( $range, 'all' ); ?>>Todo el historial</option></select>
            <label style="font-size:12px;"><input type="checkbox" name="v_no_seo" value="1" <?php checked( $hide_seo_q ); ?> onchange="this.form.submit();" /> Ocultar bots SEO</label>
            <input type="text" name="v_q" value="<?php echo esc_attr( $filter_q ); ?>" placeholder="Filtrar IP, pais, ASN o URL..." class="regular-text" style="width:210px;" />
            <button type="submit" class="button button-secondary">Filtrar</button>
            <?php if ( '' !== $filter_q ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-estadisticas&subtab=visitas' ) ); ?>" class="button button-secondary">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="bap-stats-top-grid" style="display:grid;grid-template-columns:310px 110px minmax(0,1.15fr) minmax(0,1fr);gap:14px;margin:16px 0;">
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="card" style="padding:12px 14px;margin:0;">
                    <h3 style="margin:0 0 6px 0;font-size:13px;">Total Visitas</h3>
                    <p style="font-size:24px;font-weight:bold;color:#00a32a;margin:0;line-height:1.1;"><?php echo number_format_i18n( $total_vis ); ?></p>
                </div>
                <div class="card" style="padding:12px 14px;margin:0;">
                    <h3 style="margin:0 0 6px 0;font-size:13px;">Visitas (24h)</h3>
                    <p style="font-size:24px;font-weight:bold;color:#38bdf8;margin:0;line-height:1.1;"><?php echo number_format_i18n( $vis_24h ); ?></p>
                </div>
            </div>
            <div class="card" style="padding:10px 14px;margin:0;">
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:4px;">
                    <span>IPs Únicas: <strong style="color:#2271b1;"><?php echo number_format_i18n( $unique_vis ); ?></strong></span>
                    <span>Sesiones (30m): <strong style="color:#8b5cf6;"><?php echo number_format_i18n( (int) $session_metrics['sessions'] ); ?></strong></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;border-top:1px solid #f0f0f1;padding-top:4px;">
                    <span>Págs / Sesión: <strong><?php echo esc_html( $session_metrics['pages_per_session'] ); ?></strong></span>
                    <span>Rebote: <strong style="color:<?php echo $session_metrics['bounce_rate'] >= 75 ? '#d97706' : '#00a32a'; ?>;"><?php echo esc_html( $session_metrics['bounce_rate'] ); ?>%</strong></span>
                </div>
            </div>
        </div>
        <div class="card" style="padding:14px;margin:0;">
            <h3 style="margin:0 0 10px 0;">Top Paises</h3>
            <?php if ( empty( $top_countries ) ) : ?><p>Sin datos.</p><?php else : ?>
                <ul style="margin:0;padding:0;list-style:none;">
                    <?php foreach ( $top_countries as $tc ) : ?><li style="margin-bottom:4px;"><strong><?php echo esc_html( $tc->country_code ); ?></strong>: <?php echo (int) $tc->total; ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="card" style="padding:14px 16px;margin:0;">
            <h3 style="margin:0 0 10px 0;">Top ASNs / Operadores</h3>
            <?php if ( empty( $top_asns ) ) : ?><p>Sin datos.</p><?php else : ?>
                <ul style="margin:0;"><?php foreach ( $top_asns as $ta ) : ?><li style="margin-bottom:4px;"><small><strong><?php echo esc_html( $ta->asn ); ?></strong>: <?php echo (int) $ta->total; ?></small></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <div class="card" style="padding:14px 16px;margin:0;">
            <h3 style="margin:0 0 10px 0;">Top Paginas Visitadas</h3>
            <?php if ( empty( $top_uris ) ) : ?><p>Sin datos.</p><?php else : ?>
                <ul style="margin:0;"><?php foreach ( $top_uris as $tu ) : ?><li style="margin-bottom:4px;word-break:break-all;"><code><?php echo esc_html( $tu->request_uri ); ?></code>: <strong><?php echo (int) $tu->total; ?></strong></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    </div>
    <?php
    if ( function_exists( 'bap_render_estadisticas_charts' ) ) {
        $v_base_url = admin_url( 'admin.php?page=bap-admin-estadisticas&subtab=visitas' );
        bap_render_estadisticas_charts( $table, $total_vis, $top_countries, $top_uris, $v_base_url, $seo_where, 'visits' );
    }
    ?>

    <h2 style="margin:18px 0 10px 0;">Ultimas 100 visitas normales</h2>
    <div style="overflow-x:auto;">
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:145px;">Fecha y Hora</th>
                    <th style="width:135px;">Direccion IP</th>
                    <th style="width:65px;">Pais</th>
                    <th>ASN / Proveedor</th>
                    <th>Pagina Visitada</th><th>Origen (Referer)</th>
                    <th>Navegador / User-Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="6">No hay visitas normales registradas todavia.</td></tr>
                <?php else : ?>
                    <?php foreach ( $rows as $r ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $r->created_at ); ?></code></td>
                            <td><code><?php echo esc_html( $r->ip_address ); ?></code></td>
                            <td><strong><?php echo esc_html( $r->country_code ); ?></strong></td>
                            <td><small><?php echo esc_html( $r->asn ?: 'N/D' ); ?></small></td>
                            <td style="word-break:break-word;"><code><?php echo esc_html( $r->request_uri ); ?></code></td><td style="word-break:break-all;"><small><?php echo '' !== trim( (string) $r->referer ) ? esc_html( wp_parse_url( $r->referer, PHP_URL_HOST ) ?: $r->referer ) : '<span style="color:#8c8f94;">Directo</span>'; ?></small></td>
                            <td><small style="color:#50575e;"><?php echo esc_html( mb_substr( (string) $r->user_agent, 0, 95 ) ?: 'No registrado' ); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}