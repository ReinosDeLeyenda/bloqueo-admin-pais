<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_scan_single_plugin_checksums( $slug, $version, $plugin_dir, $root ) {
    $host = 'downloads' . '.wordpress.org';
    $url  = sprintf( 'https://%s/plugin-checksums/%s/%s.json', $host, rawurlencode( $slug ), rawurlencode( $version ) );
    $res  = wp_remote_get( $url, [ 'timeout' => 12 ] );

    if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
        return null; // Plugin privado, de pago o sin checksums en WP.org
    }

    $body = json_decode( wp_remote_retrieve_body( $res ), true );
    if ( empty( $body['files'] ) || ! is_array( $body['files'] ) ) {
        return null;
    }

    $files_map = $body['files'];
    $modified  = [];
    $missing   = [];
    $extra     = [];
    $checked   = 0;
    $base_dir  = trailingslashit( wp_normalize_path( $plugin_dir ) );

    foreach ( $files_map as $rel_file => $hashes ) {
        $rel_clean = ltrim( str_replace( '\\', '/', (string) $rel_file ), '/' );
        $full_path = $base_dir . $rel_clean;
        $disp_path = ltrim( substr( $full_path, strlen( $root ) ), '/' );

        if ( ! file_exists( $full_path ) ) {
            $missing[] = [ 'file' => $disp_path, 'source' => 'plugin', 'slug' => $slug, 'version' => $version, 'rel' => $rel_clean ];
            continue;
        }

        $checked++;
        $actual_md5 = @md5_file( $full_path );
        $expected   = $hashes['md5'] ?? '';
        $valid_list = is_array( $expected ) ? array_map( 'strtolower', $expected ) : [ strtolower( (string) $expected ) ];

        if ( false === $actual_md5 || ! in_array( strtolower( $actual_md5 ), $valid_list, true ) ) {
            $modified[] = [ 'file' => $disp_path, 'source' => 'plugin', 'slug' => $slug, 'version' => $version, 'rel' => $rel_clean ];
        }
    }

    if ( is_dir( $base_dir ) ) {
        $iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base_dir, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $iter as $f_info ) {
            if ( ! $f_info->isFile() ) {
                continue;
            }
            $norm = wp_normalize_path( $f_info->getPathname() );
            $rel  = ltrim( substr( $norm, strlen( $base_dir ) ), '/' );
            if ( ! isset( $files_map[ $rel ] ) ) {
                $disp    = ltrim( substr( $norm, strlen( $root ) ), '/' );
                $extra[] = [ 'file' => $disp, 'source' => 'plugin', 'slug' => $slug, 'version' => $version, 'rel' => $rel ];
            }
        }
    }

    return [ 'checked' => $checked, 'modified' => $modified, 'missing' => $missing, 'extra' => $extra ];
}

function bap_run_core_checksum_scan( $include_plugins = true ) {
    global $wp_version;
    $locale = get_locale();
    $host   = 'api' . '.wordpress.org';
    $url    = sprintf( 'https://%s/core/checksums/1.0/?version=%s&locale=%s', $host, rawurlencode( (string) $wp_version ), rawurlencode( (string) $locale ) );

    $res = wp_remote_get( $url, [ 'timeout' => 20 ] );
    if ( is_wp_error( $res ) ) {
        return $res;
    }
    if ( 200 !== wp_remote_retrieve_response_code( $res ) ) {
        return new WP_Error( 'wp_api_err', 'La API de WordPress.org devolvió HTTP ' . wp_remote_retrieve_response_code( $res ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $res ), true );
    if ( empty( $body['checksums'] ) || ! is_array( $body['checksums'] ) ) {
        return new WP_Error( 'no_checksums', 'No se encontraron hashes oficiales para WordPress ' . esc_html( $wp_version ) . '.' );
    }

    $checksums = $body['checksums'];
    $modified  = [];
    $missing   = [];
    $extra     = [];
    $checked   = 0;
    $root      = wp_normalize_path( ABSPATH );

    foreach ( $checksums as $rel_path => $expected_md5 ) {
        $rel_clean = ltrim( str_replace( '\\', '/', (string) $rel_path ), '/' );
        if ( 0 === strpos( $rel_clean, 'wp-content/' ) ) {
            continue;
        }
        $full_path = $root . $rel_clean;
        if ( ! file_exists( $full_path ) ) {
            $missing[] = [ 'file' => $rel_clean, 'source' => 'core', 'slug' => 'wordpress', 'version' => $wp_version, 'rel' => $rel_clean ];
            continue;
        }
        $checked++;
        $actual_md5 = @md5_file( $full_path );
        if ( false === $actual_md5 || ! hash_equals( strtolower( (string) $expected_md5 ), strtolower( $actual_md5 ) ) ) {
            $modified[] = [ 'file' => $rel_clean, 'source' => 'core', 'slug' => 'wordpress', 'version' => $wp_version, 'rel' => $rel_clean ];
        }
    }

    foreach ( [ 'wp-admin', 'wp-includes' ] as $core_dir ) {
        $dir_path = $root . $core_dir;
        if ( ! is_dir( $dir_path ) ) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir_path, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $iterator as $file_info ) {
            if ( ! $file_info->isFile() ) {
                continue;
            }
            $norm_file = wp_normalize_path( $file_info->getPathname() );
            $rel_file  = ltrim( substr( $norm_file, strlen( $root ) ), '/' );
            if ( ! isset( $checksums[ $rel_file ] ) ) {
                $extra[] = [ 'file' => $rel_file, 'source' => 'core', 'slug' => 'wordpress', 'version' => $wp_version, 'rel' => $rel_file ];
            }
        }
    }

    $plugins_scanned = 0;
    if ( $include_plugins ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $own_slug = basename( dirname( __DIR__, 2 ) );
        foreach ( get_plugins() as $plugin_file => $pdata ) {
            if ( false === strpos( $plugin_file, '/' ) ) {
                continue;
            }
            $slug = dirname( $plugin_file );
            $ver  = (string) ( $pdata['Version'] ?? '' );
            if ( '' === $slug || '' === $ver || $slug === $own_slug ) {
                continue;
            }
            $p_res = bap_scan_single_plugin_checksums( $slug, $ver, WP_PLUGIN_DIR . '/' . $slug, $root );
            if ( is_array( $p_res ) ) {
                $plugins_scanned++;
                $checked += $p_res['checked'];
                $modified = array_merge( $modified, $p_res['modified'] );
                $missing  = array_merge( $missing, $p_res['missing'] );
                $extra    = array_merge( $extra, $p_res['extra'] );
            }
        }
    }

    return [
        'version'         => $wp_version,
        'locale'          => $locale,
        'checked'         => $checked,
        'plugins_scanned' => $plugins_scanned,
        'modified'        => $modified,
        'missing'         => $missing,
        'extra'           => $extra,
    ];
}

function bap_fetch_official_file_diff( $source, $slug, $version, $rel_path, $local_rel ) {
    $root      = wp_normalize_path( ABSPATH );
    $full_path = wp_normalize_path( $root . ltrim( $local_rel, '/' ) );

    if ( 0 !== strpos( $full_path, $root ) || ! is_readable( $full_path ) ) {
        return new WP_Error( 'invalid_local', 'No se pudo leer el archivo local.' );
    }

    $local_code = (string) file_get_contents( $full_path );
    if ( 'plugin' === $source ) {
        $svn_host = 'plugins' . '.svn.wordpress.org';
        $url      = sprintf( 'https://%s/%s/tags/%s/%s', $svn_host, rawurlencode( $slug ), rawurlencode( $version ), ltrim( $rel_path, '/' ) );
    } else {
        $svn_host = 'core' . '.svn.wordpress.org';
        $url      = sprintf( 'https://%s/tags/%s/%s', $svn_host, rawurlencode( $version ), ltrim( $rel_path, '/' ) );
    }

    $res = wp_remote_get( $url, [ 'timeout' => 15 ] );
    if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
        return new WP_Error( 'svn_err', 'No se pudo descargar el archivo original desde WordPress.org SVN.' );
    }

    $orig_code = wp_remote_retrieve_body( $res );
    $diff_html = wp_text_diff( $orig_code, $local_code, [
        'title'       => 'Comparación línea a línea: ' . $local_rel,
        'title_left'  => 'Original Oficial (WordPress.org)',
        'title_right' => 'Archivo Actual en tu Servidor',
    ] );

    return '' !== trim( (string) $diff_html )
        ? $diff_html
        : '<p>Los archivos son idénticos en texto (posible diferencia de codificación o fin de línea).</p>';
}

function bap_render_core_integrity_box() {
    $scan_res  = null;
    $diff_html = '';
    $diff_err  = '';

    if ( isset( $_POST['bap_view_file_diff'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_core_scan_action', 'bap_core_scan_nonce' ) ) {
        $src  = sanitize_key( $_POST['bap_diff_source'] ?? 'core' );
        $slug = sanitize_text_field( wp_unslash( $_POST['bap_diff_slug'] ?? '' ) );
        $ver  = sanitize_text_field( wp_unslash( $_POST['bap_diff_version'] ?? '' ) );
        $rel  = sanitize_text_field( wp_unslash( $_POST['bap_diff_rel'] ?? '' ) );
        $loc  = sanitize_text_field( wp_unslash( $_POST['bap_diff_local'] ?? '' ) );

        $diff_res = bap_fetch_official_file_diff( $src, $slug, $ver, $rel, $loc );
        if ( is_wp_error( $diff_res ) ) {
            $diff_err = $diff_res->get_error_message();
        } else {
            $diff_html = $diff_res;
        }
        $scan_res = bap_run_core_checksum_scan( true );
    } elseif ( isset( $_POST['bap_run_core_scan'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_core_scan_action', 'bap_core_scan_nonce' ) ) {
        $inc_plugins = ! empty( $_POST['bap_scan_include_plugins'] );
        $scan_res    = bap_run_core_checksum_scan( $inc_plugins );
    }

    global $wp_version;
    ?>
    <div class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Escáner de Integridad del Núcleo y Plugins (Checksums + Diff)</h2>
        <p class="description">Compara los archivos de WordPress <strong><?php echo esc_html( $wp_version ); ?></strong> y de tus <strong>plugins oficiales de WordPress.org</strong> contra sus hashes MD5 oficiales, permitiendo inspeccionar las diferencias línea a línea (<strong>Diff</strong>) si alguno ha sido alterado.</p>

        <form method="post" style="margin:14px 0;display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_core_scan_action', 'bap_core_scan_nonce' ); ?>
            <button type="submit" name="bap_run_core_scan" class="button button-primary">Verificar integridad ahora</button>
            <label>
                <input type="checkbox" name="bap_scan_include_plugins" value="1" checked />
                Incluir también plugins del repositorio oficial de WordPress.org
            </label>
        </form>

        <?php if ( '' !== $diff_err ) : ?>
            <div class="notice notice-error inline" style="margin:12px 0;"><p><strong>Error Diff:</strong> <?php echo esc_html( $diff_err ); ?></p></div>
        <?php endif; ?>

        <?php if ( '' !== $diff_html ) : ?>
            <div style="margin:16px 0;padding:14px;background:#fff;color:#1d2327;border:2px solid #2271b1;border-radius:6px;overflow-x:auto;">
                <style>
                    table.diff { width:100%; border-collapse:collapse; font-family:monospace; font-size:12px; }
                    table.diff td, table.diff th { padding:3px 6px; border:1px solid #dcdcde; vertical-align:top; white-space:pre-wrap; word-break:break-all; }
                    table.diff .diff-deletedline { background:#ffebe9; color:#82071e; }
                    table.diff .diff-addedline { background:#dafbe1; color:#116329; }
                    table.diff del { background:#ff818266; text-decoration:none; font-weight:700; }
                    table.diff ins { background:#abf2bc; text-decoration:none; font-weight:700; }
                </style>
                <?php echo $diff_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>

        <?php if ( is_wp_error( $scan_res ) ) : ?>
            <div class="notice notice-error inline"><p><strong>Error al escanear:</strong> <?php echo esc_html( $scan_res->get_error_message() ); ?></p></div>
        <?php elseif ( is_array( $scan_res ) ) :
            $issues = count( $scan_res['modified'] ) + count( $scan_res['missing'] ) + count( $scan_res['extra'] );
        ?>
            <?php if ( 0 === $issues ) : ?>
                <div class="notice notice-success inline" style="margin-top:12px;">
                    <p><strong>¡Integridad verificada al 100 %!</strong> Se han comprobado <strong><?php echo number_format_i18n( $scan_res['checked'] ); ?></strong> archivos oficiales (Núcleo + <strong><?php echo (int) $scan_res['plugins_scanned']; ?></strong> plugin(s) de WP.org) y ninguno ha sido alterado.</p>
                </div>
            <?php else : ?>
                <div class="notice notice-error inline" style="margin-top:12px;">
                    <p><strong>¡Atención! Se han detectado <?php echo (int) $issues; ?> anomalía(s)</strong> tras analizar <?php echo number_format_i18n( $scan_res['checked'] ); ?> archivos:</p>
                </div>

                <table class="widefat striped" style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th style="width:185px;">Estado</th>
                            <th>Archivo afectado</th>
                            <th style="width:120px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $scan_res['modified'] as $item ) : ?>
                            <tr>
                                <td><span style="background:#d63638;color:#fff;padding:2px 7px;border-radius:3px;font-weight:600;font-size:11px;">MODIFICADO (HASH)</span></td>
                                <td><code><?php echo esc_html( $item['file'] ); ?></code></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'bap_core_scan_action', 'bap_core_scan_nonce' ); ?>
                                        <input type="hidden" name="bap_diff_source" value="<?php echo esc_attr( $item['source'] ); ?>" />
                                        <input type="hidden" name="bap_diff_slug" value="<?php echo esc_attr( $item['slug'] ); ?>" />
                                        <input type="hidden" name="bap_diff_version" value="<?php echo esc_attr( $item['version'] ); ?>" />
                                        <input type="hidden" name="bap_diff_rel" value="<?php echo esc_attr( $item['rel'] ); ?>" />
                                        <input type="hidden" name="bap_diff_local" value="<?php echo esc_attr( $item['file'] ); ?>" />
                                        <button type="submit" name="bap_view_file_diff" class="button button-small button-primary">Ver Diff</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ( $scan_res['extra'] as $item ) : ?>
                            <tr>
                                <td><span style="background:#d97706;color:#fff;padding:2px 7px;border-radius:3px;font-weight:600;font-size:11px;">ARCHIVO EXTRA</span></td>
                                <td><code><?php echo esc_html( $item['file'] ); ?></code></td>
                                <td><small style="color:#646970;">No oficial</small></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ( $scan_res['missing'] as $item ) : ?>
                            <tr>
                                <td><span style="background:#646970;color:#fff;padding:2px 7px;border-radius:3px;font-weight:600;font-size:11px;">FALTANTE</span></td>
                                <td><code><?php echo esc_html( $item['file'] ); ?></code></td>
                                <td>—</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
