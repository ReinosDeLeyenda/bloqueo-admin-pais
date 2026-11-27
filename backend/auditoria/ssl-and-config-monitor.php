<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_check_site_ssl_certificate() {
    $host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
    if ( '' === $host || in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
        return new WP_Error( 'local_host', 'Entorno local detectado (' . esc_html( $host ) . '). No aplica comprobación SSL pública.' );
    }

    if ( ! function_exists( 'stream_socket_client' ) || ! function_exists( 'openssl_x509_parse' ) ) {
        return new WP_Error( 'no_openssl', 'La extensión OpenSSL de PHP no está disponible en este servidor.' );
    }

    $ctx = stream_context_create( [
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'SNI_enabled'       => true,
            'peer_name'         => $host,
        ],
    ] );

    $errno  = 0;
    $errstr = '';
    $client = @stream_socket_client( "ssl://{$host}:443", $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx );
    if ( ! $client ) {
        return new WP_Error( 'ssl_connect_fail', "No se pudo conectar por HTTPS (puerto 443) a {$host}: {$errstr}" );
    }

    $params = stream_context_get_params( $client );
    fclose( $client );

    if ( empty( $params['options']['ssl']['peer_certificate'] ) ) {
        return new WP_Error( 'no_cert', 'El servidor no devolvió ningún certificado SSL/TLS.' );
    }

    $parsed = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );
    if ( ! is_array( $parsed ) || empty( $parsed['validTo_time_t'] ) ) {
        return new WP_Error( 'parse_cert_fail', 'No se pudieron leer los metadatos del certificado SSL.' );
    }

    $valid_to  = (int) $parsed['validTo_time_t'];
    $days_left = (int) floor( ( $valid_to - time() ) / DAY_IN_SECONDS );
    $issuer    = (string) ( $parsed['issuer']['O'] ?? ( $parsed['issuer']['CN'] ?? 'Desconocido' ) );
    $cn        = (string) ( $parsed['subject']['CN'] ?? $host );

    return [
        'host'      => $host,
        'cn'        => $cn,
        'issuer'    => $issuer,
        'valid_to'  => wp_date( 'Y-m-d H:i:s', $valid_to ),
        'days_left' => $days_left,
        'status'    => ( $days_left < 0 ) ? 'expired' : ( ( $days_left <= 15 ) ? 'warning' : 'ok' ),
    ];
}

function bap_get_critical_config_paths() {
    $root   = wp_normalize_path( ABSPATH );
    $wp_cfg = bap_get_wp_config_path();

    $list = [];
    if ( '' !== $wp_cfg ) {
        $list['wp-config.php'] = $wp_cfg;
    }
    if ( file_exists( $root . '.htaccess' ) ) {
        $list['.htaccess'] = $root . '.htaccess';
    }
    return $list;
}

function bap_refresh_critical_config_hashes() {
    $hashes = [];
    foreach ( bap_get_critical_config_paths() as $label => $path ) {
        $h = @hash_file( 'sha256', $path );
        if ( false !== $h ) {
            $hashes[ $label ] = [
                'sha256' => $h,
                'mtime'  => (int) @filemtime( $path ),
                'saved'  => current_time( 'mysql' ),
            ];
        }
    }
    update_option( 'bap_critical_config_hashes', $hashes, false );
    return $hashes;
}

function bap_verify_critical_config_hashes() {
    $stored = get_option( 'bap_critical_config_hashes', null );
    if ( ! is_array( $stored ) || empty( $stored ) ) {
        $stored = bap_refresh_critical_config_hashes();
    }

    $rows = [];
    foreach ( bap_get_critical_config_paths() as $label => $path ) {
        $cur_hash = @hash_file( 'sha256', $path );
        if ( false === $cur_hash ) {
            continue;
        }
        if ( ! isset( $stored[ $label ]['sha256'] ) ) {
            $stored[ $label ] = [ 'sha256' => $cur_hash, 'mtime' => (int) @filemtime( $path ), 'saved' => current_time( 'mysql' ) ];
            update_option( 'bap_critical_config_hashes', $stored, false );
        }

        $changed = ! hash_equals( (string) $stored[ $label ]['sha256'], $cur_hash );
        $rows[]  = [
            'label'    => $label,
            'path'     => $path,
            'changed'  => $changed,
            'baseline' => (string) ( $stored[ $label ]['saved'] ?? 'N/D' ),
            'sha_short'=> substr( $cur_hash, 0, 16 ) . '...',
        ];
    }
    return $rows;
}

function bap_render_ssl_and_config_monitor_box() {
    $notice  = '';
    $ssl_res = null;

    if ( isset( $_POST['bap_refresh_cfg_hashes'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_ssl_cfg_action', 'bap_ssl_cfg_nonce' ) ) {
        bap_refresh_critical_config_hashes();
        $notice = '<div class="notice notice-success inline" style="margin:10px 0;"><p><strong>Huellas SHA-256 actualizadas:</strong> El estado actual de <code>wp-config.php</code> y <code>.htaccess</code> se ha guardado como referencia segura.</p></div>';
    }

    if ( isset( $_POST['bap_check_ssl_now'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_ssl_cfg_action', 'bap_ssl_cfg_nonce' ) ) {
        $ssl_res = bap_check_site_ssl_certificate();
    }

    $cfg_rows = bap_verify_critical_config_hashes();
    ?>
    <div id="bap-box-ssl-and-config-monitor" class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Monitor de Certificado SSL y Vigilancia de Archivos Críticos</h2>
        <p class="description">Supervisa la caducidad del certificado HTTPS (<strong>SSL/TLS</strong>) y detecta cambios no autorizados (SHA-256) en <code>wp-config.php</code> y <code>.htaccess</code>.</p>

        <?php echo wp_kses_post( $notice ); ?>

        <form method="post" style="margin:14px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <?php wp_nonce_field( 'bap_ssl_cfg_action', 'bap_ssl_cfg_nonce' ); ?>
            <button type="submit" name="bap_check_ssl_now" class="button button-primary">Comprobar Certificado SSL ahora</button>
            <button type="submit" name="bap_refresh_cfg_hashes" class="button button-secondary" onclick="return confirm('¿Aceptar el contenido actual de wp-config.php y .htaccess como nueva huella base segura?');">Aprobar / Actualizar huella SHA-256</button>
        </form>

        <?php if ( is_wp_error( $ssl_res ) ) : ?>
            <div class="notice notice-warning inline" style="margin-bottom:14px;"><p><?php echo esc_html( $ssl_res->get_error_message() ); ?></p></div>
        <?php elseif ( is_array( $ssl_res ) ) :
            $cls = ( 'ok' === $ssl_res['status'] ) ? 'notice-success' : 'notice-error';
        ?>
            <div class="notice <?php echo esc_attr( $cls ); ?> inline" style="margin-bottom:14px;">
                <p>
                    <strong>Certificado SSL (<?php echo esc_html( $ssl_res['cn'] ); ?>):</strong>
                    Emisor: <code><?php echo esc_html( $ssl_res['issuer'] ); ?></code> &nbsp;|&nbsp;
                    Caduca: <code><?php echo esc_html( $ssl_res['valid_to'] ); ?></code> &nbsp;|&nbsp;
                    <strong><?php echo ( $ssl_res['days_left'] >= 0 ) ? esc_html( $ssl_res['days_left'] . ' días restantes' ) : '¡CADUCADO!'; ?></strong>
                </p>
            </div>
        <?php endif; ?>

        <table class="widefat striped" style="margin-top:8px;">
            <thead>
                <tr>
                    <th>Archivo crítico</th>
                    <th style="width:160px;">Huella SHA-256</th>
                    <th style="width:155px;">Referencia guardada</th>
                    <th style="width:130px;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $cfg_rows as $r ) : ?>
                    <tr>
                        <td><strong><code><?php echo esc_html( $r['label'] ); ?></code></strong></td>
                        <td><code><?php echo esc_html( $r['sha_short'] ); ?></code></td>
                        <td><code><?php echo esc_html( $r['baseline'] ); ?></code></td>
                        <td>
                            <?php if ( $r['changed'] ) : ?>
                                <?php echo bap_render_status_badge( 'MODIFICADO', '#d63638' ); ?>
                            <?php else : ?>
                                <?php echo bap_render_status_badge( 'INTACTO', '#00a32a' ); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    (function() {
      var box = document.getElementById("bap-box-ssl-and-config-monitor");
      if (!box || !window.fetch || box.dataset.bapAjaxBound) return;
      box.dataset.bapAjaxBound = "1";
      var lastBtn = null;
      box.addEventListener("click", function(e){ var b = e.target.closest("button[type=submit]"); if (b) lastBtn = b; });
      box.addEventListener("submit", function(e) {
        var form = e.target;
        if (!form || form.tagName !== "FORM") return;
        e.preventDefault();
        var y = window.scrollY;
        var btn = lastBtn || form.querySelector("button[type=submit]");
        var fd = new FormData(form);
        if (btn && btn.name) fd.append(btn.name, btn.value || "1");
        var orig = btn ? btn.textContent : "";
        if (btn) { btn.disabled = true; btn.textContent = "Procesando..."; }
        fetch(window.location.href, { method: "POST", body: fd, credentials: "same-origin" })
          .then(function(r){ return r.text(); })
          .then(function(html){
            var doc = new DOMParser().parseFromString(html, "text/html");
            var fresh = doc.getElementById("bap-box-ssl-and-config-monitor");
            if (fresh) { box.innerHTML = fresh.innerHTML; window.scrollTo(0, y); }
          })
          .catch(function(){ if (btn) { btn.disabled = false; btn.textContent = orig; } form.submit(); });
      });
    })();
    </script>
    <?php
}