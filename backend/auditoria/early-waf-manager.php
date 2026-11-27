<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_early_waf_script_path() {
    return bap_get_geo_storage_path( 'bap-early-waf.php' );
}

function bap_get_early_waf_rules_path() {
    return bap_get_geo_storage_path( 'bap-early-waf-rules.json' );
}

function bap_is_early_waf_installed() {
    $user_ini = ABSPATH . '.user.ini';
    if ( ! file_exists( $user_ini ) || ! file_exists( bap_get_early_waf_script_path() ) ) {
        return false;
    }
    $content = (string) @file_get_contents( $user_ini );
    return ( false !== strpos( $content, 'bap-early-waf.php' ) );
}

function bap_sync_early_waf_rules() {
    $secret = trim( (string) get_option( 'bap_secret_key', '' ) );
    $data   = [
        'enabled'     => true,
        'ip_source'   => (string) get_option( 'bap_ip_source', 'remote_addr' ),
        'whitelist'   => bap_parse_list( get_option( 'bap_whitelist_ips', '' ), false ),
        'blacklist'   => bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false ),
        'waf_enabled' => ( '1' === (string) get_option( 'bap_waf_enabled', '0' ) ),
        'bypass_cook' => '' !== $secret ? 'bap_bypass_' . md5( $secret ) : '',
        'bypass_tok'  => '' !== $secret ? hash( 'sha256', $secret . AUTH_SALT ) : '',
        'updated_at'  => current_time( 'mysql' ),
    ];
    @file_put_contents( bap_get_early_waf_rules_path(), wp_json_encode( $data ), LOCK_EX );
}

function bap_install_early_waf() {
    $user_ini = ABSPATH . '.user.ini';
    if ( file_exists( $user_ini ) && ! is_writable( $user_ini ) ) {
        return new WP_Error( 'ini_not_writable', 'El archivo .user.ini en la raíz de WordPress no tiene permisos de escritura.' );
    }
    if ( ! file_exists( $user_ini ) && ! is_writable( ABSPATH ) ) {
        return new WP_Error( 'root_not_writable', 'El directorio raíz de WordPress no tiene permisos de escritura para crear .user.ini.' );
    }

    bap_sync_early_waf_rules();

    $script_path = bap_get_early_waf_script_path();
    $payload     = <<<'PHP'
<?php
if ( defined( 'BAP_EARLY_WAF_LOADED' ) || 'cli' === PHP_SAPI ) {
    return;
}
define( 'BAP_EARLY_WAF_LOADED', true );

(function () {
    $rules_file = __DIR__ . '/bap-early-waf-rules.json';
    if ( ! is_readable( $rules_file ) ) {
        return;
    }
    $cfg = json_decode( (string) @file_get_contents( $rules_file ), true );
    if ( ! is_array( $cfg ) || empty( $cfg['enabled'] ) ) {
        return;
    }

    foreach ( array_keys( $_COOKIE ) as $ck ) {
        if ( 0 === strpos( (string) $ck, 'wordpress_logged_in_' ) ) {
            return;
        }
    }
    if ( ! empty( $cfg['bypass_cook'] ) && ! empty( $cfg['bypass_tok'] ) && isset( $_COOKIE[ $cfg['bypass_cook'] ] ) ) {
        if ( hash_equals( (string) $cfg['bypass_tok'], (string) $_COOKIE[ $cfg['bypass_cook'] ] ) ) {
            return;
        }
    }

    $src = (string) ( $cfg['ip_source'] ?? 'remote_addr' );
    $ip  = trim( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    if ( 'cloudflare' === $src && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $ip = trim( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] );
    } elseif ( 'x_forwarded_for' === $src && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $parts = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
        $ip    = trim( (string) reset( $parts ) );
    } elseif ( 'x_real_ip' === $src && ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
        $ip = trim( (string) $_SERVER['HTTP_X_REAL_IP'] );
    }

    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return;
    }
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
        return;
    }
    $match_list = static function ( $target_ip, array $list ) {
        if ( in_array( $target_ip, $list, true ) ) {
            return true;
        }
        $ip_bin = @inet_pton( $target_ip );
        if ( false === $ip_bin ) {
            return false;
        }
        $len = strlen( $ip_bin );
        foreach ( $list as $entry ) {
            if ( false === strpos( $entry, '/' ) ) {
                continue;
            }
            list( $sub, $mask ) = explode( '/', $entry, 2 );
            $sub_bin = @inet_pton( $sub );
            if ( false === $sub_bin || strlen( $sub_bin ) !== $len || ! ctype_digit( $mask ) ) {
                continue;
            }
            $bits = (int) $mask;
            if ( 0 === $bits ) {
                return true;
            }
            $bytes = intdiv( $bits, 8 );
            $rem   = $bits % 8;
            if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $sub_bin, 0, $bytes ) ) {
                continue;
            }
            if ( 0 === $rem || ( ord( $ip_bin[ $bytes ] ) & ( ( 0xFF << ( 8 - $rem ) ) & 0xFF ) ) === ( ord( $sub_bin[ $bytes ] ) & ( ( 0xFF << ( 8 - $rem ) ) & 0xFF ) ) ) {
                return true;
            }
        }
        return false;
    };

    if ( ! empty( $cfg['whitelist'] ) && $match_list( $ip, (array) $cfg['whitelist'] ) ) {
        return;
    }

    $deny = static function ( $reason ) {
        if ( ! headers_sent() ) {
            header( 'HTTP/1.1 403 Forbidden' );
            header( 'Content-Type: text/html; charset=utf-8' );
            header( 'X-BAP-Early-WAF: Blocked' );
        }
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 Forbidden</title></head>'
           . '<body style="background:#0f172a;color:#f8fafc;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;">'
           . '<div style="text-align:center;border:1px solid #334155;padding:32px;border-radius:12px;background:#1e293b;">'
           . '<h1 style="margin:0 0 10px;color:#f87171;">403 · Acceso Bloqueado (Early WAF)</h1>'
           . '<p style="margin:0;color:#cbd5e1;">' . htmlspecialchars( $reason, ENT_QUOTES, 'UTF-8' ) . '</p>'
           . '</div></body></html>';
        exit;
    };

    if ( ! empty( $cfg['blacklist'] ) && $match_list( $ip, (array) $cfg['blacklist'] ) ) {
        $deny( 'Dirección IP bloqueada por el cortafuegos perimetral.' );
    }

    if ( ! empty( $cfg['waf_enabled'] ) ) {
        $uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        if ( strlen( $uri ) > 2048 || false !== strpos( $uri, "\0" ) || false !== stripos( $uri, '%00' ) ) {
            $deny( 'Petición malformada interceptada antes de cargar WordPress.' );
        }
        $dec_uri = rawurldecode( $uri );
        if ( preg_match( '/(?:\.\.\/|\.\.\\\\|\/etc\/passwd\b|\bunion\s+(?:all\s+)?select\b|<\s*script\b|php:\/\/(?:input|filter))/i', $dec_uri ) ) {
            $deny( 'Patrón de ataque bloqueado por Early WAF.' );
        }
        if ( ! empty( $_FILES ) ) {
            $bad = [ 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'sh', 'exe', 'jsp', 'asp', 'aspx' ];
            array_walk_recursive( $_FILES, static function ( $val, $key ) use ( $bad, $deny ) {
                if ( 'name' === $key && is_string( $val ) && '' !== $val ) {
                    $parts = explode( '.', strtolower( trim( $val ) ) );
                    array_shift( $parts );
                    foreach ( $parts as $ext ) {
                        if ( in_array( $ext, $bad, true ) ) {
                            $deny( 'Subida de archivo ejecutable bloqueada por Early WAF.' );
                        }
                    }
                }
            } );
        }
    }
})();
PHP;

    if ( false === @file_put_contents( $script_path, $payload, LOCK_EX ) ) {
        return new WP_Error( 'waf_script_fail', 'No se pudo crear el archivo bap-early-waf.php en uploads/bap-geo/.' );
    }

    $existing = file_exists( $user_ini ) ? (string) @file_get_contents( $user_ini ) : '';
    $clean    = preg_replace( '/; BEGIN Bloqueo Admin Early WAF.*?; END Bloqueo Admin Early WAF\s*/s', '', $existing );
    $norm_pth = str_replace( '\\', '/', $script_path );
    $block    = "; BEGIN Bloqueo Admin Early WAF\nauto_prepend_file = \"{$norm_pth}\"\n; END Bloqueo Admin Early WAF\n";
    $final    = $block . ( '' !== trim( $clean ) ? "\n" . ltrim( $clean ) : '' );

    if ( false === @file_put_contents( $user_ini, $final, LOCK_EX ) ) {
        @unlink( $script_path );
        return new WP_Error( 'ini_write_fail', 'No se pudo escribir la directiva auto_prepend_file en .user.ini.' );
    }

    return true;
}

function bap_uninstall_early_waf() {
    $user_ini = ABSPATH . '.user.ini';
    if ( file_exists( $user_ini ) && is_writable( $user_ini ) ) {
        $existing = (string) @file_get_contents( $user_ini );
        $clean    = preg_replace( '/; BEGIN Bloqueo Admin Early WAF.*?; END Bloqueo Admin Early WAF\s*/s', '', $existing );
        if ( '' === trim( $clean ) ) {
            @unlink( $user_ini );
        } else {
            @file_put_contents( $user_ini, $clean, LOCK_EX );
        }
    }
    foreach ( [ bap_get_early_waf_script_path(), bap_get_early_waf_rules_path() ] as $f ) {
        if ( file_exists( $f ) ) {
            @unlink( $f );
        }
    }
    return true;
}

foreach ( [ 'bap_blacklist_ips', 'bap_whitelist_ips', 'bap_ip_source', 'bap_waf_enabled', 'bap_secret_key' ] as $opt_name ) {
    add_action( "update_option_{$opt_name}", function() {
        if ( bap_is_early_waf_installed() ) {
            bap_sync_early_waf_rules();
        }
    } );
}

function bap_render_early_waf_box() {
    $notice = '';
    if ( isset( $_POST['bap_toggle_early_waf'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_early_waf_action', 'bap_early_waf_nonce' ) ) {
        if ( bap_is_early_waf_installed() ) {
            bap_uninstall_early_waf();
            $notice = '<div class="notice notice-warning inline"><p>WAF Pre-WordPress (<code>auto_prepend_file</code>) desactivado y eliminado de <code>.user.ini</code>.</p></div>';
        } else {
            $res    = bap_install_early_waf();
            $notice = is_wp_error( $res )
                ? '<div class="notice notice-error inline"><p><strong>Error:</strong> ' . esc_html( $res->get_error_message() ) . '</p></div>'
                : '<div class="notice notice-success inline"><p><strong>¡WAF Pre-WordPress activado!</strong> PHP interceptará las IPs en Lista Negra y ataques críticos <code>SQLi/XSS/LFI</code> antes de cargar WordPress o MySQL.</p></div>';
        }
    }

    $installed = bap_is_early_waf_installed();
    ?>
    <div id="bap-box-early-waf-manager" class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Cortafuegos Pre-WordPress (<code>auto_prepend_file</code> — Estilo Wordfence)</h2>
        <p class="description">Configura <code>auto_prepend_file</code> en <code>.user.ini</code> para ejecutar un filtro ultraligero en PHP <strong>antes de que WordPress cargue su núcleo o abra conexiones a MySQL</strong>, bloqueando IPs de tu Lista Negra y ataques críticos con consumo mínimo de recursos.</p>

        <?php echo wp_kses_post( $notice ); ?>

        <p style="margin:12px 0;">
            <strong>Estado actual:</strong>
            <?php if ( $installed ) : ?>
                <span style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:3px;font-weight:700;font-size:11px;">ACTIVO EN .USER.INI</span>
            <?php else : ?>
                <span style="background:#646970;color:#fff;padding:2px 8px;border-radius:3px;font-weight:700;font-size:11px;">NO INSTALADO</span>
            <?php endif; ?>
        </p>

        <form method="post">
            <?php wp_nonce_field( 'bap_early_waf_action', 'bap_early_waf_nonce' ); ?>
            <button type="submit" name="bap_toggle_early_waf" class="button <?php echo $installed ? 'button-secondary' : 'button-primary'; ?>">
                <?php echo $installed ? 'Desactivar WAF Pre-WordPress' : 'Activar WAF Pre-WordPress en 1 clic'; ?>
            </button>
        </form>
    </div>
    <script>
    (function() {
      var box = document.getElementById("bap-box-early-waf-manager");
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
            var fresh = doc.getElementById("bap-box-early-waf-manager");
            if (fresh) { box.innerHTML = fresh.innerHTML; window.scrollTo(0, y); }
          })
          .catch(function(){ if (btn) { btn.disabled = false; btn.textContent = orig; } form.submit(); });
      });
    })();
    </script>
    <?php
}