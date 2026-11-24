<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_handle_custom_db_actions() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '';
    }
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bap_db_action' ) ) {
        return '';
    }

    $dbs = bap_get_custom_dbs();

    if ( isset( $_POST['bap_add_custom_db'] ) ) {
        $name   = trim( sanitize_text_field( wp_unslash( $_POST['bap_cdb_name'] ?? '' ) ) );
        $url    = esc_url_raw( trim( (string) wp_unslash( $_POST['bap_cdb_url'] ?? '' ) ) );
        $action = ( 'allow' === ( $_POST['bap_cdb_action'] ?? 'block' ) ) ? 'allow' : 'block';
        $scope  = in_array( $_POST['bap_cdb_scope'] ?? 'both', [ 'both', 'admin', 'web' ], true ) ? $_POST['bap_cdb_scope'] : 'both';

        if ( '' === $name ) {
            return '<div class="notice notice-error is-dismissible"><p>Debes indicar un nombre para la base de datos.</p></div>';
        }

        $id         = 'cdb_' . substr( md5( uniqid( '', true ) ), 0, 8 );
        $dbs[ $id ] = [
            'name'         => mb_substr( $name, 0, 60 ),
            'url'          => $url,
            'action'       => $action,
            'scope'        => $scope,
            'enabled'      => '1',
            'count'        => 0,
            'last_updated' => '',
        ];
        update_option( 'bap_custom_dbs', $dbs );

        if ( ! empty( $_FILES['bap_cdb_file']['tmp_name'] ) ) {
            $tmp = $_FILES['bap_cdb_file']['tmp_name'];
            $fn  = $_FILES['bap_cdb_file']['name'] ?? '';
            if ( '.gz' === strtolower( substr( $fn, -3 ) ) ) {
                $unp = wp_tempnam( 'bap_cdb' );
                bap_decompress_gz_to_file( $tmp, $unp );
                $content = (string) @file_get_contents( $unp );
                @unlink( $unp );
            } else {
                $content = (string) @file_get_contents( $tmp );
            }
            $res = bap_install_custom_db_from_content( $id, $content );
            return is_wp_error( $res )
                ? '<div class="notice notice-error is-dismissible"><p>' . esc_html( $res->get_error_message() ) . '</p></div>'
                : '<div class="notice notice-success is-dismissible"><p><strong>¡Base de datos personalizada creada e instalada (' . number_format_i18n( $res ) . ' entradas)!</strong></p></div>';
        }

        if ( '' !== $url ) {
            $res = bap_download_custom_db( $id );
            return is_wp_error( $res )
                ? '<div class="notice notice-warning is-dismissible"><p>Guardada, pero falló la descarga inicial: ' . esc_html( $res->get_error_message() ) . '</p></div>'
                : '<div class="notice notice-success is-dismissible"><p><strong>¡Base de datos descargada e instalada (' . number_format_i18n( $res ) . ' entradas)!</strong></p></div>';
        }

        return '<div class="notice notice-info is-dismissible"><p>Base de datos registrada. Sube ahora un archivo manual para cargarla.</p></div>';
    }

    if ( isset( $_POST['bap_update_custom_db'] ) ) {
        $id  = sanitize_key( $_POST['bap_cdb_id'] ?? '' );
        $res = bap_download_custom_db( $id );
        return is_wp_error( $res )
            ? '<div class="notice notice-error is-dismissible"><p>' . esc_html( $res->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success is-dismissible"><p><strong>¡Base de datos actualizada (' . number_format_i18n( $res ) . ' entradas)!</strong></p></div>';
    }

    if ( isset( $_POST['bap_upload_custom_db'] ) && ! empty( $_FILES['bap_cdb_up_file']['tmp_name'] ) ) {
        $id  = sanitize_key( $_POST['bap_cdb_id'] ?? '' );
        $tmp = $_FILES['bap_cdb_up_file']['tmp_name'];
        $fn  = $_FILES['bap_cdb_up_file']['name'] ?? '';
        if ( '.gz' === strtolower( substr( $fn, -3 ) ) ) {
            $unp = wp_tempnam( 'bap_cdb' );
            bap_decompress_gz_to_file( $tmp, $unp );
            $content = (string) @file_get_contents( $unp );
            @unlink( $unp );
        } else {
            $content = (string) @file_get_contents( $tmp );
        }
        $res = bap_install_custom_db_from_content( $id, $content );
        return is_wp_error( $res )
            ? '<div class="notice notice-error is-dismissible"><p>' . esc_html( $res->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success is-dismissible"><p><strong>¡Archivo importado correctamente (' . number_format_i18n( $res ) . ' entradas)!</strong></p></div>';
    }

    if ( isset( $_POST['bap_toggle_custom_db'] ) ) {
        $id = sanitize_key( $_POST['bap_cdb_id'] ?? '' );
        if ( isset( $dbs[ $id ] ) ) {
            $dbs[ $id ]['enabled'] = ( '1' === (string) ( $dbs[ $id ]['enabled'] ?? '1' ) ) ? '0' : '1';
            update_option( 'bap_custom_dbs', $dbs );
            return '<div class="notice notice-success is-dismissible"><p>Estado actualizado.</p></div>';
        }
    }

    if ( isset( $_POST['bap_delete_custom_db'] ) ) {
        $id = sanitize_key( $_POST['bap_cdb_id'] ?? '' );
        if ( isset( $dbs[ $id ] ) ) {
            $file = bap_get_custom_db_file_path( $id );
            if ( file_exists( $file ) ) {
                @unlink( $file );
            }
            unset( $dbs[ $id ] );
            update_option( 'bap_custom_dbs', $dbs );
            return '<div class="notice notice-warning is-dismissible"><p>Base de datos personalizada eliminada.</p></div>';
        }
    }

    return '';
}

function bap_render_custom_dbs_section() {
    $dbs = bap_get_custom_dbs();
    ?>
    <div class="card" style="max-width:1180px;padding:18px 24px;margin-bottom:24px;">
        <h2 style="margin-top:0;">Bases de Datos Personalizadas (IPs / Rangos CIDR)</h2>
        <p class="description">Añade tus propias listas externas por <strong>URL</strong> (se actualizarán también con el cron automático) o sube archivos locales (<code>.txt</code>, <code>.netset</code>, <code>.csv</code>, <code>.json</code>, <code>.gz</code>) para bloquear amenazas o permitir redes de confianza.</p>

        <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;background:#f6f7f7;padding:12px 14px;border:1px solid #dcdcde;border-radius:4px;margin:14px 0;">
            <?php wp_nonce_field( 'bap_db_action' ); ?>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Nombre de la lista</label>
                <input type="text" name="bap_cdb_name" placeholder="Ej. Cloudflare IPs o Spamhaus EDROP" style="width:210px;" required />
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">URL remota (Opcional)</label>
                <input type="url" name="bap_cdb_url" placeholder="https://..." style="width:240px;" />
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">O archivo manual</label>
                <input type="file" name="bap_cdb_file" accept=".txt,.netset,.csv,.json,.lst,.ipv4,.gz" style="width:200px;" />
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Acción</label>
                <select name="bap_cdb_action">
                    <option value="block">Bloquear coincidencia</option>
                    <option value="allow">Permitir (Lista Blanca)</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Ámbito</label>
                <select name="bap_cdb_scope">
                    <option value="both">Admin y Web</option>
                    <option value="admin">Solo Panel Admin</option>
                    <option value="web">Solo Web Pública</option>
                </select>
            </div>
            <div>
                <button type="submit" name="bap_add_custom_db" class="button button-primary">+ Añadir lista</button>
            </div>
        </form>

        <?php if ( ! empty( $dbs ) ) : ?>
            <table class="widefat striped" style="margin-top:12px;">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Acción / Ámbito</th>
                        <th>Entradas</th>
                        <th>Última actualización</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $dbs as $id => $db ) :
                        $active   = ( '1' === (string) ( $db['enabled'] ?? '1' ) );
                        $is_allow = ( 'allow' === ( $db['action'] ?? 'block' ) );
                        $scopes   = [ 'both' => 'Admin + Web', 'admin' => 'Solo Admin', 'web' => 'Solo Web' ];
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $db['name'] ); ?></strong>
                                <?php if ( ! $active ) : ?><span style="color:#d63638;font-weight:600;"> [Pausada]</span><?php endif; ?>
                                <?php if ( ! empty( $db['url'] ) ) : ?><br><small style="color:#646970;"><code><?php echo esc_html( $db['url'] ); ?></code></small><?php endif; ?>
                            </td>
                            <td>
                                <span style="background:<?php echo $is_allow ? '#00a32a' : '#d63638'; ?>;color:#fff;padding:2px 7px;border-radius:3px;font-size:11px;font-weight:600;">
                                    <?php echo $is_allow ? 'PERMITIR' : 'BLOQUEAR'; ?>
                                </span>
                                <small style="margin-left:6px;"><?php echo esc_html( $scopes[ $db['scope'] ?? 'both' ] ?? 'Admin + Web' ); ?></small>
                            </td>
                            <td><strong><?php echo number_format_i18n( (int) ( $db['count'] ?? 0 ) ); ?></strong> IPs/CIDR</td>
                            <td><code><?php echo esc_html( $db['last_updated'] ?: 'Sin cargar' ); ?></code></td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                    <?php if ( ! empty( $db['url'] ) ) : ?>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field( 'bap_db_action' ); ?>
                                            <input type="hidden" name="bap_cdb_id" value="<?php echo esc_attr( $id ); ?>" />
                                            <button type="submit" name="bap_update_custom_db" class="button button-small button-primary">Actualizar URL</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" enctype="multipart/form-data" style="display:inline-flex;gap:4px;align-items:center;">
                                        <?php wp_nonce_field( 'bap_db_action' ); ?>
                                        <input type="hidden" name="bap_cdb_id" value="<?php echo esc_attr( $id ); ?>" />
                                        <input type="file" name="bap_cdb_up_file" accept=".txt,.netset,.csv,.json,.lst,.ipv4,.gz" required style="width:155px;font-size:11px;" />
                                        <button type="submit" name="bap_upload_custom_db" class="button button-small">Subir</button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'bap_db_action' ); ?>
                                        <input type="hidden" name="bap_cdb_id" value="<?php echo esc_attr( $id ); ?>" />
                                        <button type="submit" name="bap_toggle_custom_db" class="button button-small"><?php echo $active ? 'Pausar' : 'Activar'; ?></button>
                                    </form>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar esta base de datos personalizada?');">
                                        <?php wp_nonce_field( 'bap_db_action' ); ?>
                                        <input type="hidden" name="bap_cdb_id" value="<?php echo esc_attr( $id ); ?>" />
                                        <button type="submit" name="bap_delete_custom_db" class="button button-small" style="color:#d63638;">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
