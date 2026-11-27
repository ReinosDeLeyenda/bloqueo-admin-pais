<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_scan_unused_wordpress_files() {
    if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 180 ); }
    global $wpdb;
    $root     = wp_normalize_path( ABSPATH );
    $approved = get_option( 'bap_approved_unused_files', [] );
    if ( ! is_array( $approved ) ) { $approved = []; }
    $findings = [];

    $active_theme = get_stylesheet();
    $parent_theme = get_template();
    foreach ( wp_get_themes() as $slug => $theme_obj ) {
        if ( $slug === $active_theme || $slug === $parent_theme ) { continue; }
        $rel = 'wp-content/themes/' . $slug;
        if ( in_array( $rel, $approved, true ) ) { continue; }
        $findings[] = [
            'category' => 'Tema inactivo',
            'path'     => $rel,
            'is_dir'   => true,
            'size'     => 'Directorio',
            'detail'   => 'Tema instalado (' . $theme_obj->get( 'Name' ) . ') que no esta activo ni es tema padre.',
        ];
    }

    if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
    $own_slug = basename( dirname( __DIR__, 2 ) );
    foreach ( get_plugins() as $pfile => $pdata ) {
        if ( is_plugin_active( $pfile ) || ( is_multisite() && is_plugin_active_for_network( $pfile ) ) ) { continue; }
        $slug = ( false !== strpos( $pfile, '/' ) ) ? dirname( $pfile ) : $pfile;
        if ( $slug === $own_slug ) { continue; }
        $rel = 'wp-content/plugins/' . $slug;
        if ( in_array( $rel, $approved, true ) ) { continue; }
        $findings[] = [
            'category' => 'Plugin inactivo',
            'path'     => $rel,
            'is_dir'   => ( false !== strpos( $pfile, '/' ) ),
            'size'     => ( false !== strpos( $pfile, '/' ) ) ? 'Directorio' : size_format( (int) @filesize( WP_PLUGIN_DIR . '/' . $pfile ), 1 ),
            'detail'   => 'Plugin desactivado (' . ( $pdata['Name'] ?? $slug ) . ').',
        ];
    }

    $up_dir  = wp_upload_dir();
    $basedir = wp_normalize_path( $up_dir['basedir'] ?? ( WP_CONTENT_DIR . '/uploads' ) );
    if ( is_dir( $basedir ) ) {
        $known_files = [];
        $att_rows    = $wpdb->get_col( "SELECT meta_value FROM `{$wpdb->postmeta}` WHERE meta_key = '_wp_attached_file'" );
        foreach ( (array) $att_rows as $af ) {
            $af = ltrim( str_replace( '\\', '/', (string) $af ), '/' );
            if ( '' !== $af ) { $known_files[ $af ] = true; }
        }
        $meta_rows = $wpdb->get_col( "SELECT meta_value FROM `{$wpdb->postmeta}` WHERE meta_key = '_wp_attachment_metadata'" );
        foreach ( (array) $meta_rows as $raw_m ) {
            $m = maybe_unserialize( $raw_m );
            if ( is_array( $m ) && ! empty( $m['file'] ) ) {
                $main_rel = ltrim( str_replace( '\\', '/', (string) $m['file'] ), '/' );
                $known_files[ $main_rel ] = true;
                $sub_dir = dirname( $main_rel );
                $sub_dir = ( '.' === $sub_dir ) ? '' : $sub_dir . '/';
                foreach ( (array) ( $m['sizes'] ?? [] ) as $sz ) {
                    if ( ! empty( $sz['file'] ) ) { $known_files[ $sub_dir . $sz['file'] ] = true; }
                }
                if ( ! empty( $m['original_image'] ) ) { $known_files[ $sub_dir . $m['original_image'] ] = true; }
            }
        }

        $skip_dirs = [ 'bap-geo', 'cache', 'wpcf7_uploads', 'wc-logs', 'woocommerce_uploads', 'elementor', 'et_temp' ];
        $iter      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $basedir, FilesystemIterator::SKIP_DOTS ) );
        $checked_u = 0;
        foreach ( $iter as $f ) {
            if ( ! $f->isFile() ) { continue; }
            $fname = $f->getFilename();
            if ( in_array( $fname, [ 'index.php', '.htaccess', 'web.config' ], true ) ) { continue; }
            $full    = wp_normalize_path( $f->getPathname() );
            $rel_up  = ltrim( substr( $full, strlen( $basedir ) ), '/' );
            $top_dir = strtok( $rel_up, '/' );
            if ( in_array( $top_dir, $skip_dirs, true ) ) { continue; }
            $rel_root = ltrim( substr( $full, strlen( $root ) ), '/' );
            if ( in_array( $rel_root, $approved, true ) || isset( $known_files[ $rel_up ] ) ) { continue; }
            if ( ++$checked_u > 3000 ) { break; }

            $like_val = '%' . $wpdb->esc_like( $rel_up ) . '%';
            $in_posts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM `{$wpdb->posts}` WHERE post_content LIKE %s LIMIT 1", $like_val ) );
            if ( $in_posts > 0 ) { continue; }
            $in_meta  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM `{$wpdb->postmeta}` WHERE meta_value LIKE %s LIMIT 1", $like_val ) );
            if ( $in_meta > 0 ) { continue; }
            $in_opts  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM `{$wpdb->options}` WHERE option_value LIKE %s LIMIT 1", $like_val ) );
            if ( $in_opts > 0 ) { continue; }

            $findings[] = [
                'category' => 'Upload huerfano',
                'path'     => $rel_root,
                'is_dir'   => false,
                'size'     => size_format( (int) $f->getSize(), 1 ),
                'detail'   => 'No registrado en Biblioteca de Medios ni referenciado en posts, metadatos u opciones.',
            ];
            if ( count( $findings ) >= 200 ) { break; }
        }
    }

    return $findings;
}

function bap_render_unused_files_scanner_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice       = '';
    $findings     = null;
    $preview_path = '';
    $preview_img  = '';
    $preview_code = '';
    $root         = wp_normalize_path( ABSPATH );

    if ( isset( $_POST['bap_quarantine_all_unused'] ) && check_admin_referer( 'bap_unused_files_action', 'bap_unused_files_nonce' ) ) {
        $all_items = bap_scan_unused_wordpress_files();
        $moved     = 0;
        if ( function_exists( 'bap_quarantine_move_file' ) ) {
            foreach ( $all_items as $it ) {
                if ( empty( $it['is_dir'] ) && true === bap_quarantine_move_file( $it['path'], 'Archivo huerfano sin uso (Masivo)' ) ) {
                    $moved++;
                }
            }
        }
        $notice   = '<div class="notice notice-success inline"><p><strong>Cuarentena masiva completada:</strong> Se han aislado <strong>' . (int) $moved . '</strong> archivo(s) en la boveda.</p></div>';
        $findings = bap_scan_unused_wordpress_files();
    }

    if ( isset( $_POST['bap_preview_unused_file'] ) && check_admin_referer( 'bap_unused_files_action', 'bap_unused_files_nonce' ) ) {
        $rel  = ltrim( str_replace( '\\', '/', sanitize_text_field( wp_unslash( $_POST['bap_unused_path'] ?? '' ) ) ), '/' );
        $full = wp_normalize_path( $root . $rel );
        if ( '' !== $rel && false === strpos( $rel, '..' ) && 0 === strpos( $full, $root ) && is_file( $full ) && is_readable( $full ) ) {
            $preview_path = $rel;
            $ext          = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
            $mimes        = [ 'gif' => 'image/gif', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'ico' => 'image/x-icon', 'bmp' => 'image/bmp' ];
            if ( isset( $mimes[ $ext ] ) && (int) @filesize( $full ) <= 2097152 ) {
                $preview_img = 'data:' . $mimes[ $ext ] . ';base64,' . base64_encode( (string) @file_get_contents( $full ) );
            } else {
                $preview_code = (string) @file_get_contents( $full, false, null, 0, 16384 );
            }
        }
        $findings = bap_scan_unused_wordpress_files();
    }

    if ( isset( $_POST['bap_approve_unused_file'] ) && check_admin_referer( 'bap_unused_files_action', 'bap_unused_files_nonce' ) ) {
        $p = ltrim( str_replace( '\\', '/', sanitize_text_field( wp_unslash( $_POST['bap_unused_path'] ?? '' ) ) ), '/' );
        if ( '' !== $p ) {
            $app   = get_option( 'bap_approved_unused_files', [] );
            $app   = is_array( $app ) ? $app : [];
            $app[] = $p;
            update_option( 'bap_approved_unused_files', array_values( array_unique( $app ) ), false );
            $notice   = '<div class="notice notice-success inline"><p>Elemento aprobado e ignorado: <code>' . esc_html( $p ) . '</code></p></div>';
            $findings = bap_scan_unused_wordpress_files();
        }
    }
    if ( isset( $_POST['bap_run_unused_files_scan'] ) && check_admin_referer( 'bap_unused_files_action', 'bap_unused_files_nonce' ) ) {
        $findings = bap_scan_unused_wordpress_files();
    }
    echo '<div id="bap-box-unused-files" class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">';
    echo '<h2 style="margin-top:0;">Buscador de Archivos, Temas y Plugins sin Uso</h2>';
    echo '<p class="description">Detecta temas inactivos, plugins desactivados y archivos en <code>wp-content/uploads/</code> que no estan en la Biblioteca de Medios ni referenciados en posts, metadatos u opciones.</p>';
    echo wp_kses_post( $notice );
    echo '<form method="post" action="#bap-box-unused-files" style="margin:14px 0;">';
    wp_nonce_field( 'bap_unused_files_action', 'bap_unused_files_nonce' );
    echo '<button type="submit" name="bap_run_unused_files_scan" class="button button-primary">Buscar archivos sin uso ahora</button></form>';
    if ( is_array( $findings ) ) {
        if ( empty( $findings ) ) {
            echo '<div class="notice notice-success inline"><p><strong>Instalacion limpia:</strong> No se han encontrado temas/plugins inactivos ni archivos huerfanos sin referencia.</p></div>';
        } else {
            $files_only = count( array_filter( $findings, fn( $f ) => empty( $f['is_dir'] ) ) );
            echo '<div class="notice notice-warning inline" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:8px 12px;">';
            echo '<span>Se han detectado <strong>' . count( $findings ) . '</strong> elemento(s) potencialmente sin uso:</span>';
            if ( $files_only > 0 && function_exists( 'bap_quarantine_move_file' ) ) {
                echo '<form method="post" style="margin:0;" onsubmit="return confirm(\'Mover los ' . (int) $files_only . ' archivos huerfanos a cuarentena?\' );">';
                wp_nonce_field( 'bap_unused_files_action', 'bap_unused_files_nonce' );
                echo '<button type="submit" name="bap_quarantine_all_unused" class="button button-secondary" style="color:#d63638;border-color:#d63638;">Mover todos los archivos a cuarentena (' . (int) $files_only . ')</button></form>';
            }
            echo '</div>';

            if ( '' !== $preview_path ) {
                echo '<div style="margin:12px 0;padding:12px 14px;background:#1d2327;color:#e5e7eb;border:1px solid #3c434a;border-radius:6px;">';
                echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;"><strong style="color:#72aee6;">Vista previa: ' . esc_html( $preview_path ) . '</strong>';
                echo '<a href="#bap-box-unused-files" onclick="this.parentElement.parentElement.remove();return false;" style="color:#f87171;text-decoration:none;font-weight:700;">Cerrar &times;</a></div>';
                if ( '' !== $preview_img ) {
                    echo '<div style="text-align:center;padding:12px;background:#111827;border-radius:4px;"><img src="' . esc_attr( $preview_img ) . '" alt="" style="max-width:100%;max-height:280px;object-fit:contain;border:1px solid #374151;background:#fff;" /></div>';
                } else {
                    echo '<pre style="margin:0;white-space:pre-wrap;word-break:break-all;font-size:12px;max-height:280px;overflow-y:auto;">' . esc_html( $preview_code ) . '</pre>';
                }
                echo '</div>';
            }

            echo '<details open style="margin-top:12px;border:1px solid #dcdcde;border-radius:6px;padding:10px 14px;background:#fff;">';
echo '<summary style="cursor:pointer;font-weight:700;user-select:none;">&#9662; Mostrar / Ocultar lista de elementos detectados (' . count( $findings ) . ')</summary>';
echo '<div style="margin-top:10px;max-height:420px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:4px;">';
echo '<table class="widefat striped" style="border:0;"><thead><tr><th style="width:130px;">Tipo</th><th>Ruta relativa</th><th style="width:95px;">Tamano</th><th>Diagnostico</th><th style="width:240px;">Acciones</th></tr></thead><tbody>';
            foreach ( $findings as $item ) {
                $bg = ( 'Upload huerfano' === $item['category'] ) ? '#d97706' : '#2271b1';
                echo '<tr><td>' . bap_render_status_badge( $item['category'], $bg ) . '</td>';
                echo '<td><code>' . esc_html( $item['path'] ) . '</code></td>';
                echo '<td><small>' . esc_html( $item['size'] ) . '</small></td>';
                echo '<td><small>' . esc_html( $item['detail'] ) . '</small></td><td style="white-space:nowrap;">';
                if ( empty( $item['is_dir'] ) ) {
                    echo '<form method="post" action="#bap-box-unused-files" style="display:inline;">';
                    wp_nonce_field( 'bap_unused_files_action', 'bap_unused_files_nonce' );
                    echo '<input type="hidden" name="bap_unused_path" value="' . esc_attr( $item['path'] ) . '" />';
                    echo '<button type="submit" name="bap_preview_unused_file" class="button button-small">Ver</button></form> ';
                    if ( function_exists( 'bap_quarantine_move_file' ) ) {
                        echo '<form method="post" action="#bap-box-unused-files" style="display:inline;">';
                        wp_nonce_field( 'bap_quarantine_action', 'bap_quarantine_nonce' );
                        echo '<input type="hidden" name="bap_q_rel_path" value="' . esc_attr( $item['path'] ) . '" />';
                        echo '<input type="hidden" name="bap_q_reason" value="Archivo huerfano sin uso" />';
                        echo '<button type="submit" name="bap_quarantine_file_now" class="button button-small" style="color:#d63638;">Cuarentena</button></form> ';
                    }
                }
                echo '<form method="post" action="#bap-box-unused-files" style="display:inline;">';
                wp_nonce_field( 'bap_unused_files_action', 'bap_unused_files_nonce' );
                echo '<input type="hidden" name="bap_unused_path" value="' . esc_attr( $item['path'] ) . '" />';
                echo '<button type="submit" name="bap_approve_unused_file" class="button button-small">Ignorar</button></form>';
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
    }
    echo '</div>';
}