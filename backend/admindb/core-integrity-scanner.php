<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_run_core_checksum_scan() {
    global $wp_version;
    $locale = get_locale();
    $host   = 'api' . '.wordpress.org';
    $url    = sprintf(
        'https://%s/core/checksums/1.0/?version=%s&locale=%s',
        $host,
        rawurlencode( (string) $wp_version ),
        rawurlencode( (string) $locale )
    );

    $res = wp_remote_get( $url, [ 'timeout' => 20 ] );
    if ( is_wp_error( $res ) ) {
        return $res;
    }
    if ( 200 !== wp_remote_retrieve_response_code( $res ) ) {
        return new WP_Error( 'wp_api_err', 'La API de WordPress.org devolvió HTTP ' . wp_remote_retrieve_response_code( $res ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $res ), true );
    if ( empty( $body['checksums'] ) || ! is_array( $body['checksums'] ) ) {
        return new WP_Error( 'no_checksums', 'No se encontraron hashes oficiales para WordPress ' . esc_html( $wp_version ) . ' (' . esc_html( $locale ) . ').' );
    }

    $checksums = $body['checksums'];
    $modified  = [];
    $missing   = [];
    $extra     = [];
    $checked   = 0;
    $root      = wp_normalize_path( ABSPATH );

    // 1. Verificar archivos oficiales contra su MD5
    foreach ( $checksums as $rel_path => $expected_md5 ) {
        $rel_clean = ltrim( str_replace( '\\', '/', (string) $rel_path ), '/' );
        if ( 0 === strpos( $rel_clean, 'wp-content/' ) ) {
            continue;
        }
        $full_path = $root . $rel_clean;
        if ( ! file_exists( $full_path ) ) {
            $missing[] = $rel_clean;
            continue;
        }
        $checked++;
        $actual_md5 = @md5_file( $full_path );
        if ( false === $actual_md5 || ! hash_equals( strtolower( (string) $expected_md5 ), strtolower( $actual_md5 ) ) ) {
            $modified[] = $rel_clean;
        }
    }

    // 2. Buscar archivos no oficiales dentro de wp-admin/ y wp-includes/
    foreach ( [ 'wp-admin', 'wp-includes' ] as $core_dir ) {
        $dir_path = $root . $core_dir;
        if ( ! is_dir( $dir_path ) ) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir_path, FilesystemIterator::SKIP_DOTS )
        );
        foreach ( $iterator as $file_info ) {
            if ( ! $file_info->isFile() ) {
                continue;
            }
            $norm_file = wp_normalize_path( $file_info->getPathname() );
            $rel_file  = ltrim( substr( $norm_file, strlen( $root ) ), '/' );
            if ( ! isset( $checksums[ $rel_file ] ) ) {
                $extra[] = $rel_file;
            }
        }
    }

    sort( $modified );
    sort( $missing );
    sort( $extra );

    return [
        'version'    => $wp_version,
        'locale'     => $locale,
        'scanned_at' => current_time( 'mysql' ),
        'checked'    => $checked,
        'modified'   => $modified,
        'missing'    => $missing,
        'extra'      => $extra,
    ];
}

function bap_render_core_integrity_box() {
    $scan_res = null;
    if ( isset( $_POST['bap_run_core_scan'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_core_scan_action', 'bap_core_scan_nonce' ) ) {
        $scan_res = bap_run_core_checksum_scan();
    }
    global $wp_version;
    ?>
    <div class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Escáner de Integridad del Núcleo de WordPress (Core Checksums)</h2>
        <p class="description">Compara todos los archivos de WordPress <strong><?php echo esc_html( $wp_version ); ?></strong> (<code><?php echo esc_html( get_locale() ); ?></code>) con los hashes MD5 oficiales de <code>api.wordpress.org</code> para detectar archivos alterados o código inyectado en <code>wp-admin</code> y <code>wp-includes</code>.</p>

        <form method="post" style="margin:14px 0;">
            <?php wp_nonce_field( 'bap_core_scan_action', 'bap_core_scan_nonce' ); ?>
            <button type="submit" name="bap_run_core_scan" class="button button-primary">Verificar integridad de WordPress ahora</button>
        </form>

        <?php if ( is_wp_error( $scan_res ) ) : ?>
            <div class="notice notice-error inline"><p><strong>Error al escanear:</strong> <?php echo esc_html( $scan_res->get_error_message() ); ?></p></div>
        <?php elseif ( is_array( $scan_res ) ) :
            $issues = count( $scan_res['modified'] ) + count( $scan_res['missing'] ) + count( $scan_res['extra'] );
        ?>
            <?php if ( 0 === $issues ) : ?>
                <div class="notice notice-success inline" style="margin-top:12px;">
                    <p><strong>¡Integridad verificada al 100 %!</strong> Se han comprobado <strong><?php echo number_format_i18n( $scan_res['checked'] ); ?></strong> archivos oficiales de WordPress <?php echo esc_html( $scan_res['version'] ); ?> y ninguno ha sido alterado.</p>
                </div>
            <?php else : ?>
                <div class="notice notice-error inline" style="margin-top:12px;">
                    <p><strong>¡Atención! Se han detectado <?php echo (int) $issues; ?> anomalía(s)</strong> tras analizar <?php echo number_format_i18n( $scan_res['checked'] ); ?> archivos:</p>
                </div>

                <table class="widefat striped" style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th style="width:190px;">Estado</th>
                            <th>Archivo afectado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $scan_res['modified'] as $f ) : ?>
                            <tr>
                                <td><span style="background:#d63638;color:#fff;padding:2px 7px;border-radius:3px;font-weight:600;font-size:11px;">MODIFICADO (HASH)</span></td>
                                <td><code><?php echo esc_html( $f ); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ( $scan_res['extra'] as $f ) : ?>
                            <tr>
                                <td><span style="background:#d97706;color:#fff;padding:2px 7px;border-radius:3px;font-weight:600;font-size:11px;">ARCHIVO EXTRA EN CORE</span></td>
                                <td><code><?php echo esc_html( $f ); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ( $scan_res['missing'] as $f ) : ?>
                            <tr>
                                <td><span style="background:#646970;color:#fff;padding:2px 7px;border-radius:3px;font-weight:600;font-size:11px;">FALTANTE</span></td>
                                <td><code><?php echo esc_html( $f ); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
