<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_audit_critical_file_permissions() {
    $root    = wp_normalize_path( ABSPATH );
    $content = wp_normalize_path( WP_CONTENT_DIR );
    $uploads = wp_normalize_path( wp_upload_dir()['basedir'] ?? ( $content . '/uploads' ) );
    $is_win  = ( 'WIN' === strtoupper( substr( PHP_OS, 0, 3 ) ) );

    $wp_cfg = file_exists( $root . 'wp-config.php' )
        ? $root . 'wp-config.php'
        : ( file_exists( dirname( rtrim( $root, '/' ) ) . '/wp-config.php' ) ? dirname( rtrim( $root, '/' ) ) . '/wp-config.php' : '' );

    $targets = [
        [
            'label' => 'wp-config.php (Configuración y claves)',
            'path'  => $wp_cfg,
            'type'  => 'file',
            'max'   => 0640,
            'rec'   => '0600 o 0640',
        ],
        [
            'label' => '.htaccess (Reglas del servidor)',
            'path'  => $root . '.htaccess',
            'type'  => 'file',
            'max'   => 0644,
            'rec'   => '0644',
        ],
        [
            'label' => 'Directorio raíz de WordPress (ABSPATH)',
            'path'  => rtrim( $root, '/' ),
            'type'  => 'dir',
            'max'   => 0755,
            'rec'   => '0755',
        ],
        [
            'label' => 'wp-admin/ (Panel de administración)',
            'path'  => $root . 'wp-admin',
            'type'  => 'dir',
            'max'   => 0755,
            'rec'   => '0755',
        ],
        [
            'label' => 'wp-includes/ (Núcleo de WordPress)',
            'path'  => $root . 'wp-includes',
            'type'  => 'dir',
            'max'   => 0755,
            'rec'   => '0755',
        ],
        [
            'label' => 'wp-content/ (Contenido y extensiones)',
            'path'  => $content,
            'type'  => 'dir',
            'max'   => 0755,
            'rec'   => '0755',
        ],
        [
            'label' => 'wp-content/plugins/ (Plugins)',
            'path'  => wp_normalize_path( WP_PLUGIN_DIR ),
            'type'  => 'dir',
            'max'   => 0755,
            'rec'   => '0755',
        ],
        [
            'label' => 'wp-content/themes/ (Temas)',
            'path'  => wp_normalize_path( get_theme_root() ),
            'type'  => 'dir',
            'max'   => 0755,
            'rec'   => '0755',
        ],
        [
            'label' => 'wp-content/uploads/ (Subidas)',
            'path'  => $uploads,
            'type'  => 'dir',
            'max'   => 0755,
            'rec'   => '0755',
        ],
    ];

    $results = [];
    foreach ( $targets as $t ) {
        if ( '' === $t['path'] || ! file_exists( $t['path'] ) ) {
            continue;
        }

        $raw_perms = @fileperms( $t['path'] );
        $mode      = ( false !== $raw_perms ) ? ( $raw_perms & 0777 ) : 0;
        $octal     = sprintf( '%04o', $mode );

        $world_write = ( 0 !== ( $mode & 0002 ) );
        $group_write = ( 0 !== ( $mode & 0020 ) );
        $world_read  = ( 0 !== ( $mode & 0004 ) );

        $status = 'ok';
        $detail = 'Permisos correctos';

        if ( $is_win ) {
            $status = 'info';
            $detail = 'Servidor Windows (ACLs NTFS; POSIX ' . $octal . ')';
        } elseif ( $world_write ) {
            $status = 'critical';
            $detail = 'Escritura pública (777/666): cualquier usuario del servidor puede modificarlo';
        } elseif ( $mode > $t['max'] || ( 'wp-config.php (Configuración y claves)' === $t['label'] && ( $group_write || $world_read ) ) ) {
            $status = 'warning';
            $detail = 'Permisos más abiertos de lo recomendado (' . $t['rec'] . ')';
        }

        $results[] = [
            'label'  => $t['label'],
            'path'   => $t['path'],
            'octal'  => $octal,
            'rec'    => $t['rec'],
            'status' => $status,
            'detail' => $detail,
        ];
    }

    return $results;
}

function bap_render_file_permissions_box() {
    $rows = null;
    if ( isset( $_POST['bap_run_perms_audit'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_perms_audit_action', 'bap_perms_audit_nonce' ) ) {
        $rows = bap_audit_critical_file_permissions();
    }
    ?>
    <div class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Auditoría de Permisos de Archivos y Carpetas Críticas</h2>
        <p class="description">Comprueba que <code>wp-config.php</code>, <code>.htaccess</code> y los directorios principales de WordPress no tengan permisos inseguros (como <code>0777</code> o <code>0666</code>).</p>

        <form method="post" style="margin:14px 0;">
            <?php wp_nonce_field( 'bap_perms_audit_action', 'bap_perms_audit_nonce' ); ?>
            <button type="submit" name="bap_run_perms_audit" class="button button-primary">Auditar permisos ahora</button>
        </form>

        <?php if ( is_array( $rows ) ) : ?>
            <table class="widefat striped" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th>Recurso</th>
                        <th style="width:90px;">Actual</th>
                        <th style="width:110px;">Recomendado</th>
                        <th style="width:110px;">Estado</th>
                        <th>Diagnóstico</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $r ) :
                        $bg = ( 'critical' === $r['status'] ) ? '#d63638' : ( ( 'warning' === $r['status'] ) ? '#d97706' : ( ( 'info' === $r['status'] ) ? '#2271b1' : '#00a32a' ) );
                        $tx = ( 'critical' === $r['status'] ) ? 'CRÍTICO' : ( ( 'warning' === $r['status'] ) ? 'REVISAR' : ( ( 'info' === $r['status'] ) ? 'WINDOWS' : 'SEGURO' ) );
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $r['label'] ); ?></strong><br>
                                <small style="color:#646970;"><code><?php echo esc_html( $r['path'] ); ?></code></small>
                            </td>
                            <td><code><?php echo esc_html( $r['octal'] ); ?></code></td>
                            <td><code><?php echo esc_html( $r['rec'] ); ?></code></td>
                            <td><span style="background:<?php echo esc_attr( $bg ); ?>;color:#fff;padding:2px 7px;border-radius:3px;font-weight:700;font-size:11px;"><?php echo esc_html( $tx ); ?></span></td>
                            <td><?php echo esc_html( $r['detail'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}