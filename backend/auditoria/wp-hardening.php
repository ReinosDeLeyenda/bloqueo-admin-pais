<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Desactivar el editor de temas y plugins en el panel
if ( '1' === (string) get_option( 'bap_harden_disable_file_edit', '0' ) ) {
    if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
        define( 'DISALLOW_FILE_EDIT', true );
    }
}

// 2. Ocultar versión de WordPress en HTML, RSS y assets
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

// 3. Cabeceras HTTP de seguridad en respuestas públicas y admin
add_action( 'send_headers', function() {
    if ( '1' !== (string) get_option( 'bap_harden_security_headers', '0' ) || headers_sent() ) {
        return;
    }
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
} );

// 4. Proteger wp-content/uploads/ contra ejecución de PHP
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

function bap_render_hardening_box() {
    $notice = '';
    if ( isset( $_POST['bap_save_hardening'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_hardening_action', 'bap_hardening_nonce' ) ) {
        $no_edit  = ! empty( $_POST['bap_harden_disable_file_edit'] ) ? '1' : '0';
        $hide_ver = ! empty( $_POST['bap_harden_hide_wp_version'] ) ? '1' : '0';
        $sec_hdrs = ! empty( $_POST['bap_harden_security_headers'] ) ? '1' : '0';
        $no_php   = ! empty( $_POST['bap_harden_block_uploads_php'] ) ? '1' : '0';

        update_option( 'bap_harden_disable_file_edit', $no_edit );
        update_option( 'bap_harden_hide_wp_version', $hide_ver );
        update_option( 'bap_harden_security_headers', $sec_hdrs );
        update_option( 'bap_harden_block_uploads_php', $no_php );

        $ok_up  = bap_sync_uploads_php_protection( '1' === $no_php );
        $notice = $ok_up
            ? '<div class="notice notice-success inline"><p><strong>Endurecimiento guardado y aplicado correctamente.</strong></p></div>'
            : '<div class="notice notice-warning inline"><p>Ajustes guardados, pero no se pudo escribir <code>.htaccess</code> en <code>wp-content/uploads/</code> (si usas Nginx, bloquéalo en la configuración del servidor).</p></div>';
    }
    ?>
    <div class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Endurecimiento de WordPress (Hardening en 1 Clic)</h2>
        <p class="description">Aplica medidas preventivas recomendadas para reducir la superficie de ataque de tu instalación WordPress.</p>

        <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

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
            <label style="display:block;margin-bottom:14px;">
                <input type="checkbox" name="bap_harden_security_headers" value="1" <?php checked( get_option( 'bap_harden_security_headers', '0' ), '1' ); ?> />
                <strong>Activar cabeceras HTTP de seguridad</strong> (<code>X-Frame-Options</code>, <code>X-Content-Type-Options</code>, <code>Referrer-Policy</code> y <code>Permissions-Policy</code>)
            </label>

            <button type="submit" name="bap_save_hardening" class="button button-primary">Guardar endurecimiento</button>
        </form>
    </div>
    <?php
}
