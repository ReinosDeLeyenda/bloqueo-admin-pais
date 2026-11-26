<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_get_official_wp_root_files() {
    return [
        'index.php', 'license.txt', 'readme.html', 'wp-activate.php', 'wp-blog-header.php',
        'wp-comments-post.php', 'wp-config.php', 'wp-config-sample.php', 'wp-cron.php',
        'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php', 'wp-settings.php',
        'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php', '.htaccess', '.user.ini',
        'robots.txt', 'ads.txt', 'app-ads.txt', 'favicon.ico', 'sitemap.xml', 'sitemap_index.xml',
    ];
}

function bap_get_legacy_wp_root_files() {
    return [
        'wp-atom.php', 'wp-commentsrss2.php', 'wp-feed.php', 'wp-pass.php',
        'wp-rdf.php', 'wp-register.php', 'wp-rss.php', 'wp-rss2.php', 'wp-app.php',
    ];
}

function bap_detect_root_file_usage( $full_path, $filename ) {
    $norm_full = wp_normalize_path( $full_path );
    $included  = array_map( 'wp_normalize_path', get_included_files() );
    if ( in_array( $norm_full, $included, true ) ) {
        return [ 'in_use' => true, 'legacy' => false, 'label' => 'EN USO (Cargado por PHP)' ];
    }

    $prepend = wp_normalize_path( (string) ini_get( 'auto_prepend_file' ) );
    $append  = wp_normalize_path( (string) ini_get( 'auto_append_file' ) );
    if ( ( '' !== $prepend && $prepend === $norm_full ) || ( '' !== $append && $append === $norm_full ) ) {
        return [ 'in_use' => true, 'legacy' => false, 'label' => 'EN USO (php.ini)' ];
    }

    $root = wp_normalize_path( ABSPATH );
    foreach ( [ '.user.ini', '.htaccess', 'wp-config.php' ] as $cfg ) {
        $cfg_path = $root . $cfg;
        if ( is_readable( $cfg_path ) ) {
            $raw = (string) @file_get_contents( $cfg_path );
            if ( '' !== $raw && false !== strpos( $raw, $filename ) ) {
                return [ 'in_use' => true, 'legacy' => false, 'label' => 'Referenciado en ' . $cfg ];
            }
        }
    }

    if ( in_array( strtolower( $filename ), bap_get_legacy_wp_root_files(), true ) ) {
        return [ 'in_use' => false, 'legacy' => true, 'label' => 'Archivo obsoleto de WP antiguo' ];
    }

    return [ 'in_use' => false, 'legacy' => false, 'label' => '' ];
}

function bap_scan_wp_root_orphan_files() {
    $root     = wp_normalize_path( ABSPATH );
    $official = bap_get_official_wp_root_files();
    $approved = get_option( 'bap_approved_root_files', [] );
    if ( ! is_array( $approved ) ) { $approved = []; }

    $findings = [];
    $checked  = 0;
    $entries  = @scandir( $root );
    if ( ! is_array( $entries ) ) {
        return [ 'checked' => 0, 'findings' => [] ];
    }

    $exec_exts = [ 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'sh', 'pl', 'py', 'cgi', 'asp', 'aspx', 'jsp' ];

    foreach ( $entries as $name ) {
        if ( '.' === $name || '..' === $name ) { continue; }
        $full = $root . $name;
        if ( ! is_file( $full ) ) { continue; }

        $checked++;
        $low = strtolower( $name );
        if ( in_array( $low, $official, true ) || in_array( $name, $approved, true ) ) {
            continue;
        }
        if ( 0 === strpos( $low, 'google' ) && '.html' === substr( $low, -5 ) ) {
            continue;
        }

        $ext     = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        $is_exec = in_array( $ext, $exec_exts, true );
        $mtime   = @filemtime( $full );
        $usage   = bap_detect_root_file_usage( $full, $name );

        $findings[] = [
            'name'     => $name,
            'size'     => size_format( (int) @filesize( $full ), 1 ),
            'modified' => $mtime ? wp_date( 'Y-m-d H:i:s', $mtime ) : 'N/D',
            'is_exec'  => $is_exec,
            'in_use'   => $usage['in_use'],
            'legacy'   => $usage['legacy'],
            'usage'    => $usage['label'],
            'risk'     => $is_exec ? 'ALTA (Ejecutable en raiz)' : 'MEDIA (Archivo extra)',
        ];
    }

    usort( $findings, fn( $a, $b ) => (int) $b['is_exec'] <=> (int) $a['is_exec'] );
    return [ 'checked' => $checked, 'findings' => $findings ];
}
function bap_render_root_directory_watcher_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice       = '';
    $preview_name = '';
    $preview_code = '';
    $root         = wp_normalize_path( ABSPATH );

    if ( isset( $_POST['bap_purge_legacy_root_files'] ) && check_admin_referer( 'bap_root_watch_action', 'bap_root_watch_nonce' ) ) {
        $deleted = 0;
        foreach ( bap_get_legacy_wp_root_files() as $leg ) {
            $full = wp_normalize_path( $root . $leg );
            $u    = bap_detect_root_file_usage( $full, $leg );
            if ( file_exists( $full ) && is_file( $full ) && empty( $u['in_use'] ) && @unlink( $full ) ) {
                $deleted++;
            }
        }
        $notice = '<div class="notice notice-success inline"><p><strong>Limpieza completada:</strong> Se han eliminado <strong>' . (int) $deleted . '</strong> archivo(s) obsoleto(s) de versiones antiguas de WordPress.</p></div>';
    }

    if ( isset( $_POST['bap_approve_root_file'] ) && check_admin_referer( 'bap_root_watch_action', 'bap_root_watch_nonce' ) ) {
        $fname = basename( sanitize_text_field( wp_unslash( $_POST['bap_root_fname'] ?? '' ) ) );
        if ( '' !== $fname ) {
            $app   = get_option( 'bap_approved_root_files', [] );
            $app   = is_array( $app ) ? $app : [];
            $app[] = $fname;
            update_option( 'bap_approved_root_files', array_values( array_unique( $app ) ), false );
            $notice = '<div class="notice notice-success inline"><p>Archivo <strong>' . esc_html( $fname ) . '</strong> aprobado en la lista blanca de raiz.</p></div>';
        }
    }

    $preview_img_uri = '';
    if ( isset( $_POST['bap_preview_root_file'] ) && check_admin_referer( 'bap_root_watch_action', 'bap_root_watch_nonce' ) ) {
        $fname = basename( wp_unslash( $_POST['bap_root_fname'] ?? '' ) );
        $full  = wp_normalize_path( $root . $fname );
        if ( '' !== $fname && 0 === strpos( $full, $root ) && is_file( $full ) && is_readable( $full ) ) {
            $preview_name = $fname;
            $ext          = strtolower( pathinfo( $fname, PATHINFO_EXTENSION ) );
            $img_mimes    = [
                'gif'  => 'image/gif',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'webp' => 'image/webp',
                'ico'  => 'image/x-icon',
                'bmp'  => 'image/bmp',
            ];
            if ( isset( $img_mimes[ $ext ] ) && (int) @filesize( $full ) <= 2097152 ) {
                $raw_img         = (string) @file_get_contents( $full );
                $preview_img_uri = 'data:' . $img_mimes[ $ext ] . ';base64,' . base64_encode( $raw_img );
            } else {
                $preview_code = (string) @file_get_contents( $full, false, null, 0, 16384 );
            }
        }
    }

    $res          = bap_scan_wp_root_orphan_files();
    $legacy_count = count( array_filter( $res['findings'], fn( $f ) => ! empty( $f['legacy'] ) && empty( $f['in_use'] ) ) );
    ?>
    <div id="bap-box-root" class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Vigilancia de Carpeta Raiz de WordPress (Root Directory Watcher)</h2>
        <p class="description">Analiza el directorio raiz (ABSPATH) en busca de scripts PHP o archivos huerfanos, detecta si estan en uso por PHP y permite limpiar archivos obsoletos de WordPress antiguo.</p>
        <?php echo wp_kses_post( $notice ); ?>

        <div style="margin:10px 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <span>
                <strong>Estado de raiz:</strong>
                <?php echo bap_render_status_badge( empty( $res['findings'] ) ? 'RAIZ LIMPIA' : count( $res['findings'] ) . ' ARCHIVO(S) EXTRA', empty( $res['findings'] ) ? '#00a32a' : '#d63638' ); ?>
                &nbsp;|&nbsp; Revisados: <strong><?php echo (int) $res['checked']; ?></strong>
            </span>
            <?php if ( $legacy_count > 0 ) : ?>
                <form method="post" style="margin-left:auto;" onsubmit="return confirm('Eliminar los <?php echo (int) $legacy_count; ?> archivos obsoletos de WordPress antiguo?');">
                    <?php wp_nonce_field( 'bap_root_watch_action', 'bap_root_watch_nonce' ); ?>
                    <button type="submit" name="bap_purge_legacy_root_files" class="button button-primary">Borrar obsoletos WP (<?php echo (int) $legacy_count; ?>)</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ( '' !== $preview_name ) : ?>
            <div style="margin:12px 0;padding:12px 14px;background:#1d2327;color:#e5e7eb;border:1px solid #3c434a;border-radius:6px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong style="color:#72aee6;">Vista previa: <?php echo esc_html( $preview_name ); ?></strong>
                    <a href="#bap-box-root" onclick="this.parentElement.parentElement.remove();return false;" style="color:#f87171;text-decoration:none;font-weight:700;">Cerrar &times;</a>
                </div>
                <?php if ( '' !== $preview_img_uri ) : ?>
                    <div style="text-align:center;padding:12px;background:#111827;border-radius:4px;">
                        <img src="<?php echo esc_attr( $preview_img_uri ); ?>" alt="<?php echo esc_attr( $preview_name ); ?>" style="max-width:100%;max-height:280px;object-fit:contain;border:1px solid #374151;background:#fff;" />
                    </div>
                <?php else : ?>
                    <pre style="margin:0;white-space:pre-wrap;word-break:break-all;font-size:12px;max-height:280px;overflow-y:auto;"><?php echo esc_html( $preview_code ); ?></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $res['findings'] ) ) : ?>
            <table class="widefat striped" style="margin-top:10px;">
                <thead><tr><th>Archivo en raiz</th><th>Riesgo / Uso detectado</th><th>Tamano / Fecha</th><th style="width:265px;">Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ( $res['findings'] as $f ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $f['name'] ); ?></strong></td>
                            <td>
                                <?php echo bap_render_status_badge( $f['risk'], $f['is_exec'] ? '#d63638' : '#d97706' ); ?>
                                <?php if ( '' !== $f['usage'] ) : ?>
                                    <br><small style="color:<?php echo ! empty( $f['in_use'] ) ? '#008a20' : '#2271b1'; ?>;font-weight:700;"><?php echo esc_html( $f['usage'] ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo esc_html( $f['size'] . ' · ' . $f['modified'] ); ?></small></td>
                            <td style="white-space:nowrap;">
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'bap_root_watch_action', 'bap_root_watch_nonce' ); ?>
                                    <input type="hidden" name="bap_root_fname" value="<?php echo esc_attr( $f['name'] ); ?>" />
                                    <button type="submit" name="bap_preview_root_file" class="button button-small">Ver</button>
                                </form>
                                <?php if ( function_exists( 'bap_quarantine_move_file' ) ) : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'bap_quarantine_action', 'bap_quarantine_nonce' ); ?>
                                        <input type="hidden" name="bap_q_rel_path" value="<?php echo esc_attr( $f['name'] ); ?>" />
                                        <input type="hidden" name="bap_q_reason" value="Archivo extrano en carpeta raiz" />
                                        <button type="submit" name="bap_quarantine_file_now" class="button button-small" style="color:#d63638;">Cuarentena</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'bap_root_watch_action', 'bap_root_watch_nonce' ); ?>
                                    <input type="hidden" name="bap_root_fname" value="<?php echo esc_attr( $f['name'] ); ?>" />
                                    <button type="submit" name="bap_approve_root_file" class="button button-small">Aprobar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}