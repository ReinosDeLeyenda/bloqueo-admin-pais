<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'admin-custom-dbs-view.php';

function bap_read_uploaded_custom_db_file( array $file ) {
    $tmp = $file['tmp_name'] ?? '';
    $fn  = $file['name'] ?? '';
    if ( '.gz' === strtolower( substr( $fn, -3 ) ) ) {
        $unp = wp_tempnam( 'bap_cdb' );
        bap_decompress_gz_to_file( $tmp, $unp );
        $content = (string) @file_get_contents( $unp );
        @unlink( $unp );
        return $content;
    }
    return (string) @file_get_contents( $tmp );
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
            $res = bap_install_custom_db_from_content( $id, bap_read_uploaded_custom_db_file( $_FILES['bap_cdb_file'] ) );
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
        $res = bap_install_custom_db_from_content( $id, bap_read_uploaded_custom_db_file( $_FILES['bap_cdb_up_file'] ) );
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
