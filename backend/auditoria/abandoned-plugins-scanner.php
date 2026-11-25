<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_scan_abandoned_plugins() {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $all_plugins = get_plugins();
    $own_slug    = basename( dirname( __DIR__, 2 ) );
    $host        = 'api' . '.wordpress.org';
    $now         = time();
    $results     = [];
    $checked     = 0;

    foreach ( $all_plugins as $plugin_file => $pdata ) {
        if ( false === strpos( $plugin_file, '/' ) ) {
            continue;
        }
        $slug = dirname( $plugin_file );
        if ( '' === $slug || $slug === $own_slug ) {
            continue;
        }

        $cache_key = 'bap_wporg_pinfo_' . md5( $slug );
        $info      = get_transient( $cache_key );

        if ( false === $info ) {
            $url = sprintf(
                'https://%s/plugins/info/1.2/?action=plugin_information&request[slug]=%s&request[fields][last_updated]=1&request[fields][tested]=1&request[fields][sections]=0',
                $host,
                rawurlencode( $slug )
            );
            $res = wp_remote_get( $url, [ 'timeout' => 10 ] );
            if ( is_wp_error( $res ) ) {
                continue;
            }
            $code = wp_remote_retrieve_response_code( $res );
            $body = json_decode( wp_remote_retrieve_body( $res ), true );

            if ( 404 === $code || ( is_array( $body ) && ! empty( $body['error'] ) && false !== stripos( (string) $body['error'], 'closed' ) ) ) {
                $info = [ 'status' => 'closed', 'error' => (string) ( $body['error'] ?? 'Cerrado en WordPress.org' ) ];
            } elseif ( 200 === $code && is_array( $body ) && ! empty( $body['last_updated'] ) ) {
                $info = [
                    'status'       => 'ok',
                    'last_updated' => (string) $body['last_updated'],
                    'tested'       => (string) ( $body['tested'] ?? '' ),
                    'version'      => (string) ( $body['version'] ?? '' ),
                ];
            } else {
                $info = [ 'status' => 'private' ];
            }
            set_transient( $cache_key, $info, DAY_IN_SECONDS );
        }

        if ( ! is_array( $info ) || 'private' === ( $info['status'] ?? 'private' ) ) {
            continue;
        }

        $checked++;
        $name   = (string) ( $pdata['Name'] ?? $slug );
        $active = is_plugin_active( $plugin_file );

        if ( 'closed' === $info['status'] ) {
            $results[] = [
                'name'         => $name,
                'slug'         => $slug,
                'active'       => $active,
                'last_updated' => 'N/D',
                'days_ago'     => 9999,
                'severity'     => 'critical',
                'badge'        => 'CERRADO / RETIRADO',
                'detail'       => 'Plugin cerrado en WordPress.org (' . $info['error'] . '). No recibirá parches de seguridad.',
            ];
            continue;
        }

        $ts = strtotime( $info['last_updated'] );
        if ( false === $ts ) {
            continue;
        }

        $days_ago = (int) floor( ( $now - $ts ) / DAY_IN_SECONDS );
        if ( $days_ago >= 730 ) {
            $years     = round( $days_ago / 365, 1 );
            $results[] = [
                'name'         => $name,
                'slug'         => $slug,
                'active'       => $active,
                'last_updated' => gmdate( 'Y-m-d', $ts ),
                'days_ago'     => $days_ago,
                'severity'     => 'critical',
                'badge'        => 'ABANDONADO (> 2 AÑOS)',
                'detail'       => "Sin actualizaciones desde hace {$years} años ({$days_ago} días). Probado hasta WP " . ( $info['tested'] ?: 'N/D' ) . '.',
            ];
        } elseif ( $days_ago >= 365 ) {
            $results[] = [
                'name'         => $name,
                'slug'         => $slug,
                'active'       => $active,
                'last_updated' => gmdate( 'Y-m-d', $ts ),
                'days_ago'     => $days_ago,
                'severity'     => 'warning',
                'badge'        => 'ANTIGUO (> 1 AÑO)',
                'detail'       => "Última actualización hace {$days_ago} días. Revisa si sigue mantenido.",
            ];
        }
    }

    usort( $results, fn( $a, $b ) => $b['days_ago'] <=> $a['days_ago'] );

    return [
        'checked'  => $checked,
        'findings' => $results,
    ];
}

function bap_render_abandoned_plugins_box() {
    $scan = null;
    if ( isset( $_POST['bap_run_abandoned_scan'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_abandoned_scan_action', 'bap_abandoned_scan_nonce' ) ) {
        $scan = bap_scan_abandoned_plugins();
    }
    ?>
    <div class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Detector de Plugins Abandonados o Retirados (WordPress.org)</h2>
        <p class="description">Comprueba la fecha de última actualización de tus plugins oficiales en <code>api.wordpress.org</code> para detectar extensiones abandonadas (+2 años sin parches) o cerradas por motivos de seguridad.</p>

        <form method="post" style="margin:14px 0;">
            <?php wp_nonce_field( 'bap_abandoned_scan_action', 'bap_abandoned_scan_nonce' ); ?>
            <button type="submit" name="bap_run_abandoned_scan" class="button button-primary">Analizar plugins abandonados ahora</button>
        </form>

        <?php if ( is_array( $scan ) ) : ?>
            <?php if ( empty( $scan['findings'] ) ) : ?>
                <div class="notice notice-success inline" style="margin-top:12px;">
                    <p><strong>¡Todos los plugins están al día!</strong> Se han verificado <strong><?php echo (int) $scan['checked']; ?></strong> plugin(s) oficial(es) y ninguno supera 1 año sin actualizaciones ni ha sido cerrado.</p>
                </div>
            <?php else : ?>
                <table class="widefat striped" style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th>Plugin</th>
                            <th style="width:110px;">Última Act.</th>
                            <th style="width:185px;">Estado</th>
                            <th>Diagnóstico</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $scan['findings'] as $f ) :
                            $bg = ( 'critical' === $f['severity'] ) ? '#d63638' : '#d97706';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $f['name'] ); ?></strong>
                                    <?php if ( $f['active'] ) : ?>
                                        <span style="background:#2271b1;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;margin-left:4px;">ACTIVO</span>
                                    <?php else : ?>
                                        <span style="color:#646970;font-size:11px;">(Inactivo)</span>
                                    <?php endif; ?><br>
                                    <small style="color:#646970;"><code><?php echo esc_html( $f['slug'] ); ?></code></small>
                                </td>
                                <td><code><?php echo esc_html( $f['last_updated'] ); ?></code></td>
                                <td><span style="background:<?php echo esc_attr( $bg ); ?>;color:#fff;padding:2px 7px;border-radius:3px;font-weight:700;font-size:11px;"><?php echo esc_html( $f['badge'] ); ?></span></td>
                                <td><?php echo esc_html( $f['detail'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}