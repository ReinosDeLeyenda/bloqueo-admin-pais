<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( '1' === (string) get_option( 'bap_harden_disable_file_edit', '0' ) ) {
    if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
        define( 'DISALLOW_FILE_EDIT', true );
    }
}

if ( '1' === (string) get_option( 'bap_harden_hide_wp_version', '0' ) ) {
    remove_action( 'wp_head', 'wp_generator' );
    add_filter( 'the_generator', '__return_empty_string' );

    $strip_ver = function( $src ) {
        if ( is_string( $src ) && false !== strpos( $src, 'ver=' ) ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    };
    add_filter( 'style_loader_src', $strip_ver, 9999 );
    add_filter( 'script_loader_src', $strip_ver, 9999 );
}

add_action( 'send_headers', function() {
    if ( '1' !== (string) get_option( 'bap_harden_security_headers', '0' ) || headers_sent() ) {
        return;
    }
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
} );

if ( '1' === (string) get_option( 'bap_harden_disable_app_passwords', '0' ) ) {
    add_filter( 'wp_is_application_passwords_available', '__return_false', 999 );
}

if ( '1' === (string) get_option( 'bap_harden_disable_rss', '0' ) ) {
    $disable_feed = function() {
        wp_die( 'Los feeds RSS están deshabilitados por política de seguridad.', 'Feed deshabilitado', [ 'response' => 403 ] );
    };
    add_action( 'do_feed', $disable_feed, 1 );
    add_action( 'do_feed_rdf', $disable_feed, 1 );
    add_action( 'do_feed_rss', $disable_feed, 1 );
    add_action( 'do_feed_rss2', $disable_feed, 1 );
    add_action( 'do_feed_atom', $disable_feed, 1 );
    remove_action( 'wp_head', 'feed_links', 2 );
    remove_action( 'wp_head', 'feed_links_extra', 3 );
}

function bap_sync_uploads_php_protection( $enable ) {
    $upload  = wp_upload_dir();
    $basedir = $upload['basedir'] ?? '';
    if ( '' === $basedir || ! is_dir( $basedir ) ) {
        return false;
    }

    $ht_file = trailingslashit( $basedir ) . '.htaccess';
    if ( ! $enable ) {
        if ( file_exists( $ht_file ) && is_writable( $ht_file ) ) {
            $cur = (string) file_get_contents( $ht_file );
            if ( false !== strpos( $cur, '# BEGIN Bloqueo Admin Uploads' ) ) {
                $clean = preg_replace( '/# BEGIN Bloqueo Admin Uploads.*?# END Bloqueo Admin Uploads\s*/s', '', $cur );
                if ( '' === trim( $clean ) ) {
                    @unlink( $ht_file );
                } else {
                    @file_put_contents( $ht_file, $clean );
                }
            }
        }
        return true;
    }

    $rules = "# BEGIN Bloqueo Admin Uploads\n"
           . "<FilesMatch \"\\.(?:php|phtml|php3|php4|php5|php7|php8|phar|pl|py|cgi|sh)$\">\n"
           . "    <IfModule mod_authz_core.c>\n"
           . "        Require all denied\n"
           . "    </IfModule>\n"
           . "    <IfModule !mod_authz_core.c>\n"
           . "        Order allow,deny\n"
           . "        Deny from all\n"
           . "    </IfModule>\n"
           . "</FilesMatch>\n"
           . "# END Bloqueo Admin Uploads\n";

    $existing = file_exists( $ht_file ) ? (string) file_get_contents( $ht_file ) : '';
    if ( false !== strpos( $existing, '# BEGIN Bloqueo Admin Uploads' ) ) {
        $updated = preg_replace( '/# BEGIN Bloqueo Admin Uploads.*?# END Bloqueo Admin Uploads\s*/s', $rules, $existing );
    } else {
        $updated = $rules . ( '' !== $existing ? "\n" . $existing : '' );
    }

    return ( false !== @file_put_contents( $ht_file, $updated ) );
}
// Limpieza de cabecera X-Powered-By y metadatos RSD / WLWManifest / Shortlink
if ( '1' === (string) get_option( 'bap_harden_clean_head_headers', '0' ) ) {
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
    remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
    add_action( 'send_headers', function() {
        if ( ! headers_sent() && function_exists( 'header_remove' ) ) {
            @header_remove( 'X-Powered-By' );
        }
    }, 99 );
}

// Bloqueo de creación de nuevos usuarios (User Lockdown)
if ( '1' === (string) get_option( 'bap_harden_user_lockdown', '0' ) ) {
    add_filter( 'pre_option_users_can_register', '__return_zero', 999 );

    add_filter( 'wp_pre_insert_user_data', function( $data, $update ) {
        if ( $update ) {
            return $data;
        }
        if ( is_admin() && current_user_can( 'create_users' ) && current_user_can( 'manage_options' ) ) {
            return $data;
        }
        $ip      = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '127.0.0.1';
        $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
        $login   = sanitize_user( (string) ( $data['user_login'] ?? 'desconocido' ) );
        if ( function_exists( 'bap_log_blocked_attempt' ) ) {
            bap_log_blocked_attempt( $ip, $country, 'User Lockdown: Alta bloqueada (' . $login . ')' );
        }
        wp_die( '<strong>Seguridad (User Lockdown):</strong> La creación de nuevas cuentas de usuario está bloqueada por política del administrador.', 'Registro Bloqueado', [ 'response' => 403 ] );
    }, 1, 2 );
}

function bap_sync_hotlink_protection( $enable ) {
    if ( ! function_exists( 'insert_with_markers' ) ) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }
    $ht_file = ABSPATH . '.htaccess';
    if ( ! file_exists( $ht_file ) || ! is_writable( $ht_file ) ) {
        return false;
    }
    if ( ! $enable ) {
        return insert_with_markers( $ht_file, 'Bloqueo Admin Hotlink', [] );
    }

    $host = preg_quote( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '/' );
    if ( '' === $host ) {
        return false;
    }

    $rules = [
        '<IfModule mod_rewrite.c>',
        'RewriteEngine On',
        'RewriteCond %{HTTP_REFERER} !^$',
        'RewriteCond %{HTTP_REFERER} !^https?://(www\.)?' . $host . ' [NC]',
        'RewriteCond %{HTTP_REFERER} !^https?://(.*\.)?(google|bing|duckduckgo|yahoo)\. [NC]',
        'RewriteRule \.(jpg|jpeg|png|gif|webp|avif)$ - [F,NC,L]',
        '</IfModule>',
    ];

    return insert_with_markers( $ht_file, 'Bloqueo Admin Hotlink', $rules );
}

function bap_render_hardening_box() {
    $notice = '';
    if ( isset( $_POST['bap_save_hardening'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_hardening_action', 'bap_hardening_nonce' ) ) {
        $no_edit  = ! empty( $_POST['bap_harden_disable_file_edit'] ) ? '1' : '0';
        $hide_ver = ! empty( $_POST['bap_harden_hide_wp_version'] ) ? '1' : '0';
        $sec_hdrs = ! empty( $_POST['bap_harden_security_headers'] ) ? '1' : '0';
        $no_php   = ! empty( $_POST['bap_harden_block_uploads_php'] ) ? '1' : '0';
        $no_app   = ! empty( $_POST['bap_harden_disable_app_passwords'] ) ? '1' : '0';
        $no_rss   = ! empty( $_POST['bap_harden_disable_rss'] ) ? '1' : '0';
        $hotlink  = ! empty( $_POST['bap_harden_anti_hotlink'] ) ? '1' : '0';
        $clean_hd = ! empty( $_POST['bap_harden_clean_head_headers'] ) ? '1' : '0';
        $usr_lock = ! empty( $_POST['bap_harden_user_lockdown'] ) ? '1' : '0';
        $up_magic = ! empty( $_POST['bap_harden_upload_magic_bytes'] ) ? '1' : '0';
        $st_exif  = ! empty( $_POST['bap_harden_strip_image_exif'] ) ? '1' : '0';

        update_option( 'bap_harden_disable_file_edit', $no_edit );
        update_option( 'bap_harden_hide_wp_version', $hide_ver );
        update_option( 'bap_harden_security_headers', $sec_hdrs );
        update_option( 'bap_harden_block_uploads_php', $no_php );
        update_option( 'bap_harden_disable_app_passwords', $no_app );
        update_option( 'bap_harden_disable_rss', $no_rss );
        update_option( 'bap_harden_anti_hotlink', $hotlink );
        update_option( 'bap_harden_clean_head_headers', $clean_hd );
        update_option( 'bap_harden_user_lockdown', $usr_lock );
        update_option( 'bap_harden_upload_magic_bytes', $up_magic );
        update_option( 'bap_harden_strip_image_exif', $st_exif );

        $ok_up  = bap_sync_uploads_php_protection( '1' === $no_php );
        $ok_hl  = bap_sync_hotlink_protection( '1' === $hotlink );
        $notice = ( $ok_up && $ok_hl )
            ? '<div class="notice notice-success inline"><p><strong>Endurecimiento guardado y aplicado correctamente.</strong></p></div>'
            : '<div class="notice notice-warning inline"><p>Ajustes guardados, pero alguna regla <code>.htaccess</code> no se pudo escribir (revisa permisos o configuración Nginx).</p></div>';
    }
    ?>
    <div class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Endurecimiento de WordPress (Hardening en 1 Clic)</h2>
        <p class="description">Aplica medidas preventivas recomendadas para reducir la superficie de ataque de tu instalación WordPress.</p>

        <?php echo wp_kses_post( $notice ); ?>

        <form method="post" style="margin-top:12px;">
            <?php wp_nonce_field( 'bap_hardening_action', 'bap_hardening_nonce' ); ?>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_harden_disable_file_edit" value="1" <?php checked( get_option( 'bap_harden_disable_file_edit', '0' ), '1' ); ?> />
                <strong>Desactivar editor de archivos integrado</strong> (<code>DISALLOW_FILE_EDIT</code> — oculta Apariencia → Editor de temas y Plugins → Editor)
            </label>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_harden_hide_wp_version" value="1" <?php checked( get_option( 'bap_harden_hide_wp_version', '0' ), '1' ); ?> />
                <strong>Ocultar versión de WordPress</strong> (elimina la etiqueta meta <code>generator</code> y los parámetros <code>?ver=</code> en scripts/estilos)
            </label>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_harden_block_uploads_php" value="1" <?php checked( get_option( 'bap_harden_block_uploads_php', '0' ), '1' ); ?> />
                <strong>Bloquear ejecución de PHP en <code>wp-content/uploads/</code></strong> (genera regla <code>.htaccess</code> para impedir ejecutar scripts subidos)
            </label>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_harden_security_headers" value="1" <?php checked( get_option( 'bap_harden_security_headers', '0' ), '1' ); ?> />
                <strong>Activar cabeceras HTTP de seguridad</strong> (<code>X-Frame-Options</code>, <code>X-Content-Type-Options</code>, <code>Referrer-Policy</code> y <code>Permissions-Policy</code>)
            </label>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_harden_disable_app_passwords" value="1" <?php checked( get_option( 'bap_harden_disable_app_passwords', '0' ), '1' ); ?> />
                <strong>Desactivar Contraseñas de Aplicación (Application Passwords)</strong> (cierra el acceso por API REST mediante claves de aplicación)
            </label>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_harden_disable_rss" value="1" <?php checked( get_option( 'bap_harden_disable_rss', '0' ), '1' ); ?> />
                <strong>Desactivar Feeds RSS públicos</strong> (impide el raspado automático de entradas y comentarios vía <code>/feed/</code>)
            </label>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_harden_anti_hotlink" value="1" <?php checked( get_option( 'bap_harden_anti_hotlink', '0' ), '1' ); ?> />
                <strong>Protección Anti-Hotlinking de imágenes (<code>.htaccess</code>)</strong> (impide que webs externas incrusten tus imágenes robando ancho de banda)
            </label>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_harden_clean_head_headers" value="1" <?php checked( get_option( 'bap_harden_clean_head_headers', '0' ), '1' ); ?> />
                <strong>Ocultar cabecera <code>X-Powered-By</code> y metadatos RSD / WLWManifest / Shortlink</strong> (reduce la exposición de versión PHP y enlaces heredados en el <code>&lt;head&gt;</code>)
            </label>
            <label style="display:block;margin-bottom:14px;">
                <input type="checkbox" name="bap_harden_user_lockdown" value="1" <?php checked( get_option( 'bap_harden_user_lockdown', '0' ), '1' ); ?> />
                <strong>Bloqueo de creación de nuevos usuarios (User Lockdown)</strong> (impide cualquier alta pública o por código/API; solo un administrador puede crear usuarios desde el panel)
            </label>

            <label style="display:block;margin-bottom:14px;"><input type="checkbox" name="bap_harden_upload_magic_bytes" value="1" <?php checked( get_option( 'bap_harden_upload_magic_bytes', '0' ), '1' ); ?> /> <strong>Verificar firma binaria (Magic Bytes) y sanitizar SVG en subidas</strong> (impide subir webshells camuflados como .jpg o SVG con XSS)</label>

            <label style="display:block;margin-bottom:14px;"><input type="checkbox" name="bap_harden_strip_image_exif" value="1" <?php checked( get_option( 'bap_harden_strip_image_exif', '0' ), '1' ); ?> /> <strong>Eliminar metadatos EXIF y GPS al subir imágenes</strong> (re-procesa JPEG/WebP para borrar ubicación GPS, datos de cámara y payloads ocultos)</label>

            <button type="submit" name="bap_save_hardening" class="button button-primary">Guardar endurecimiento</button>
        </form>

        <?php
        $srv_sw   = strtolower( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ) );
        $is_nginx = ( false !== strpos( $srv_sw, 'nginx' ) );
        $site_h   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
        ?>
        <details style="margin-top:16px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:10px 14px;" <?php echo $is_nginx ? 'open' : ''; ?>>
            <summary style="cursor:pointer;font-weight:600;">
                Reglas equivalentes para servidores Nginx (si no usas Apache/LiteSpeed)
                <?php if ( $is_nginx ) : ?>
                    <span style="background:#d97706;color:#fff;font-size:10px;padding:1px 6px;border-radius:3px;margin-left:6px;">NGINX DETECTADO</span>
                <?php endif; ?>
            </summary>
            <p class="description" style="margin:8px 0;">Nginx ignora los archivos <code>.htaccess</code>. Añade este bloque dentro del bloque <code>server { ... }</code> de tu configuración de Nginx y recarga el servicio:</p>
            <textarea id="bap-nginx-snippet" rows="13" readonly class="large-text code" style="font-size:11px;background:#1d2327;color:#e5e7eb;" onclick="this.select();"># 1. Bloquear ejecución de PHP en wp-content/uploads/
location ~* /wp-content/uploads/.*\.(?:php|phtml|php3|php4|php5|php7|php8|phar|pl|py|cgi|sh)$ {
    deny all;
}

# 2. Bloquear acceso directo a archivos sensibles y xmlrpc.php
location ~* (?:/xmlrpc\.php|\.(?:log|zip|rar|tar|tar\.gz|tgz|7z|gz|bak|sql|env|git.*))$ {
    deny all;
}

# 3. Anti-Hotlinking de imágenes
location ~* \.(?:jpg|jpeg|png|gif|webp|avif)$ {
    valid_referers none blocked server_names <?php echo esc_html( $site_h ); ?> *.google. *.bing. *.duckduckgo. *.yahoo.;
    if ($invalid_referer) {
        return 403;
    }
}</textarea>
        </details>
    </div>
    <?php
}