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

        $findings[] = [
            'name'     => $name,
            'size'     => size_format( (int) @filesize( $full ), 1 ),
            'modified' => $mtime ? wp_date( 'Y-m-d H:i:s', $mtime ) : 'N/D',
            'is_exec'  => $is_exec,
            'risk'     => $is_exec ? 'ALTA (Ejecutable en raíz)' : 'MEDIA (Archivo extra)',
        ];
    }

    usort( $findings, fn( $a, $b ) => (int) $b['is_exec'] <=> (int) $a['is_exec'] );
    return [ 'checked' => $checked, 'findings' => $findings ];
}
function bap_render_root_directory_watcher_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';

    if ( isset( $_POST['bap_approve_root_file'] ) && check_admin_referer( 'bap_root_watch_action', 'bap_root_watch_nonce' ) ) {
        $fname = basename( sanitize_file_name( wp_unslash( $_POST['bap_root_fname'] ?? '' ) ) );
        if ( '' !== $fname ) {
            $app   = get_option( 'bap_approved_root_files', [] );
            $app   = is_array( $app ) ? $app : [];
            $app[] = $fname;
            update_option( 'bap_approved_root_files', array_values( array_unique( $app ) ), false );
            $notice = '<div class="notice notice-success inline"><p>Archivo <code>' . esc_html( $fname ) . '</code> aprobado en la lista blanca de raíz.</p></div>';
        }
    }

    $res = bap_scan_wp_root_orphan_files();
    ?>
    <div class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Vigilancia de Carpeta Raíz de WordPress (Root Directory Watcher)</h2>
        <p class="description">Analiza el directorio raíz (<code>ABSPATH</code>) en busca de scripts PHP o archivos huérfanos no pertenecientes al núcleo oficial de WordPress.</p>
        <?php echo wp_kses_post( $notice ); ?>

        <p style="margin:10px 0;">
            <strong>Estado de raíz:</strong>
            <?php echo bap_render_status_badge( empty( $res['findings'] ) ? 'RAÍZ LIMPIA' : count( $res['findings'] ) . ' ARCHIVO(S) EXTRA', empty( $res['findings'] ) ? '#00a32a' : '#d63638' ); ?>
            &nbsp;|&nbsp; Archivos revisados: <code><?php echo (int) $res['checked']; ?></code>
        </p>

        <?php if ( ! empty( $res['findings'] ) ) : ?>
            <table class="widefat striped" style="margin-top:10px;">
                <thead><tr><th>Archivo en raíz</th><th>Riesgo</th><th>Tamaño / Fecha</th><th style="width:230px;">Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ( $res['findings'] as $f ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $f['name'] ); ?></code></td>
                            <td><?php echo bap_render_status_badge( $f['risk'], $f['is_exec'] ? '#d63638' : '#d97706' ); ?></td>
                            <td><small><?php echo esc_html( $f['size'] . ' · ' . $f['modified'] ); ?></small></td>
                            <td style="white-space:nowrap;">
                                <?php if ( function_exists( 'bap_quarantine_move_file' ) ) : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'bap_quarantine_action', 'bap_quarantine_nonce' ); ?>
                                        <input type="hidden" name="bap_q_rel_path" value="<?php echo esc_attr( $f['name'] ); ?>" />
                                        <input type="hidden" name="bap_q_reason" value="Archivo extraño en carpeta raíz" />
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