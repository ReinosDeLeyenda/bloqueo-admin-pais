<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_detect_github_plugin_info( $plugin_file, array $pdata ) {
    $slug = dirname( $plugin_file );
    $candidates = [
        (string) ( $pdata['UpdateURI'] ?? '' ),
        (string) ( $pdata['PluginURI'] ?? '' ),
        (string) ( $pdata['AuthorURI'] ?? '' ),
    ];
    $main_path = WP_PLUGIN_DIR . '/' . $plugin_file;
    if ( is_readable( $main_path ) ) {
        $head = (string) @file_get_contents( $main_path, false, null, 0, 4096 );
        if ( preg_match_all( '#https?://github\.com/([a-zA-Z0-9_.-]+)/([a-zA-Z0-9_.-]+)#i', $head, $mh ) ) {
            foreach ( $mh
[
0
]
 as $u ) { $candidates[] = $u; }
        }
    }
    $owner = '';
    $repo  = '';
    foreach ( $candidates as $url ) {
        if ( preg_match( '#github\.com/([a-zA-Z0-9_.-]+)/([a-zA-Z0-9_.-]+)#i', $url, $m ) ) {
            $owner = $m
[
1
]
;
            $repo  = preg_replace( '/\.git$/i', '', $m
[
2
]
 );
            break;
        }
    }
    if ( '' === $owner ) {
        $owner = 'ReinosDeLeyenda';
        $repo  = $slug;
    }
    $gh_url = sprintf( 'https://github.com', rawurlencode( $owner ), rawurlencode( $repo ) );
    $res    = wp_remote_get( $gh_url, [ 'timeout' => 10, 'headers' => [ 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress-BAP-Scanner' ] ] );
    if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        $pub  = (string) ( $body['published_at'] ?? $body['created_at'] ?? '' );
        if ( '' !== $pub ) {
            return [
                'status'       => 'ok',
                'source'       => 'GitHub (' . $owner . '/' . $repo . ')',
                'last_updated' => $pub,
                'tested'       => '',
                'version'      => ltrim( (string) ( $body['tag_name'] ?? '' ), 'vV' ),
            ];
        }
    }
    $repo_url = sprintf( 'https://github.com', rawurlencode( $owner ), rawurlencode( $repo ) );
    $res2     = wp_remote_get( $repo_url, [ 'timeout' => 10, 'headers' => [ 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress-BAP-Scanner' ] ] );
    if ( ! is_wp_error( $res2 ) && 200 === wp_remote_retrieve_response_code( $res2 ) ) {
        $body2 = json_decode( wp_remote_retrieve_body( $res2 ), true );
        $push  = (string) ( $body2['pushed_at'] ?? $body2['updated_at'] ?? '' );
        if ( '' !== $push ) {
            return [
                'status'       => 'ok',
                'source'       => 'GitHub (' . $owner . '/' . $repo . ')',
                'last_updated' => $push,
                'tested'       => '',
                'version'      => '',
            ];
        }
    }
    return null;
}

function bap_scan_abandoned_plugins( $force = false ) {
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

        $cache_key = 'bap_wporg_pinfo_v2_' . md5( $slug );
        $info      = $force ? false : get_transient( $cache_key );

        if ( false === $info ) {
            $gh_info = bap_detect_github_plugin_info( $plugin_file, $pdata );
            if ( is_array( $gh_info ) ) {
                $info = $gh_info;
            } else {
                $url = sprintf(
                    'https://%s/plugins/info/1.2/?action=plugin_information&request[slug]=%s&request[fields][last_updated]=1&request[fields][tested]=1&request[fields][sections]=0',
                    $host,
                    rawurlencode( $slug )
                );
                $res = wp_remote_get( $url, [ 'timeout' => 10 ] );
                if ( is_wp_error( $res ) ) {
                    continue;
                }
                $code    = wp_remote_retrieve_response_code( $res );
                $body    = json_decode( wp_remote_retrieve_body( $res ), true );
                $err_str = is_array( $body ) ? (string) ( $body['error'] ?? '' ) : '';

                if ( '' !== $err_str && false !== stripos( $err_str, 'closed' ) ) {
                    $info = [ 'status' => 'closed', 'error' => $err_str ];
                } elseif ( 200 === $code && is_array( $body ) && ! empty( $body['last_updated'] ) ) {
                    $info = [
                        'status'       => 'ok',
                        'source'       => 'WordPress.org',
                        'last_updated' => (string) $body['last_updated'],
                        'tested'       => (string) ( $body['tested'] ?? '' ),
                        'version'      => (string) ( $body['version'] ?? '' ),
                    ];
                } else {
                    $info = [ 'status' => 'private' ];
                }
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
                'detail'       => 'Plugin cerrado en WordPress.org (' . $info['error'] . '). No recibira parches de seguridad.',
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
                'badge'        => 'ABANDONADO (> 2 ANOS)',
                'detail'       => "Sin actualizaciones en {$info['source']} desde hace {$years} anos ({$days_ago} dias).",
            ];
        } elseif ( $days_ago >= 365 ) {
            $results[] = [
                'name'         => $name,
                'slug'         => $slug,
                'active'       => $active,
                'last_updated' => gmdate( 'Y-m-d', $ts ),
                'days_ago'     => $days_ago,
                'severity'     => 'warning',
                'badge'        => 'ANTIGUO (> 1 ANO)',
                'detail'       => "Ultima actualizacion en {$info['source']} hace {$days_ago} dias. Revisa si sigue mantenido.",
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
        $scan = bap_scan_abandoned_plugins( true );
    }
    ?>
    <div id="bap-box-abandoned" class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Detector de Plugins Abandonados o Retirados (WordPress.org / GitHub)</h2>
        <p class="description">Comprueba la fecha de ultima actualizacion de tus plugins en <code>api.wordpress.org</code> y repositorios de <code>GitHub</code> para detectar extensiones abandonadas (+2 anos sin parches) o cerradas por seguridad.</p>

        <form method="post" action="#bap-box-abandoned" style="margin:14px 0;">
            <?php wp_nonce_field( 'bap_abandoned_scan_action', 'bap_abandoned_scan_nonce' ); ?>
            <button type="submit" name="bap_run_abandoned_scan" class="button button-primary">Analizar plugins abandonados ahora</button>
        </form>

        <?php if ( is_array( $scan ) ) : ?>
            <?php if ( empty( $scan['findings'] ) ) : ?>
                <div class="notice notice-success inline" style="margin-top:12px;">
                    <p><strong>¡Todos los plugins estan al dia!</strong> Se han verificado <strong><?php echo (int) $scan['checked']; ?></strong> plugin(s) (WordPress.org y GitHub) y ninguno supera 1 ano sin actualizaciones ni ha sido cerrado.</p>
                </div>
            <?php else : ?>
                <table class="widefat striped" style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th>Plugin</th>
                            <th style="width:110px;">Ultima Act.</th>
                            <th style="width:185px;">Estado</th>
                            <th>Diagnostico</th>
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
    <script>
    document.addEventListener("DOMContentLoaded", function() {
      var box = document.getElementById("bap-box-abandoned");
      if (!box || !window.fetch) return;
      box.addEventListener("submit", function(e) {
        var form = e.target;
        if (!form || form.tagName !== "FORM") return;
        var btn = form.querySelector("button[type=submit]");
        e.preventDefault();
        var fd = new FormData(form);
        if (btn && btn.name) fd.append(btn.name, btn.value || "1");
        var orig = btn ? btn.textContent : "";
        if (btn) { btn.disabled = true; btn.textContent = "Analizando plugins..."; }
        fetch(window.location.href, { method: "POST", body: fd, credentials: "same-origin" })
          .then(function(r){ return r.text(); })
          .then(function(html){
            var doc = new DOMParser().parseFromString(html, "text/html");
            var fresh = doc.getElementById("bap-box-abandoned");
            if (fresh) { box.innerHTML = fresh.innerHTML; }
          })
          .catch(function(){ if (btn) { btn.disabled = false; btn.textContent = orig; } form.submit(); });
      });
    });
    </script>
    <?php
}