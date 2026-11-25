<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'admin-email-builder-presets.php';

add_action( 'admin_init', function() {
    if ( isset( $_POST['bap_export_tpl_json'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_tpl_builder_action' ) ) {
        $id      = sanitize_key( $_POST['bap_tpl_id'] ?? '' );
        $customs = bap_get_custom_email_templates();
        if ( isset( $customs[ $id ] ) ) {
            $payload = [
                'plugin'      => 'bloqueo-admin-pais',
                'type'        => 'email_template',
                'exported_at' => current_time( 'mysql' ),
                'template'    => $customs[ $id ],
            ];
            bap_send_download_headers( 'plantilla-email-' . $id . '.json', 'application/json' );
            echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
            exit;
        }
    }
});

function bap_handle_email_builder_actions( &$editing_id ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '';
    }

    $customs = bap_get_custom_email_templates();

    if ( isset( $_POST['bap_save_custom_tpl'] ) && check_admin_referer( 'bap_tpl_builder_action' ) ) {
        $id = sanitize_key( $_POST['bap_tpl_id'] ?? '' );
        if ( '' === $id ) {
            $id = 'tpl_' . substr( md5( uniqid( '', true ) ), 0, 8 );
        }
        $ordered = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['bap_module_order'] ?? '' ) ) ) ) );

        $customs[ $id ] = bap_sanitize_custom_template_data( [
            'name'        => wp_unslash( $_POST['bap_tpl_name'] ?? '' ),
            'format'      => wp_unslash( $_POST['bap_tpl_format'] ?? 'html_dark' ),
            'table_limit' => (int) ( $_POST['bap_tpl_limit'] ?? 10 ),
            'modules'     => $ordered,
            'sizes'       => (array) ( $_POST['bap_mod_size'] ?? [] ),
        ] );
        update_option( 'bap_custom_email_templates', $customs );
        update_option( 'bap_email_template', 'custom_' . $id );
        $editing_id = $id;
        return '<div class="notice notice-success is-dismissible"><p><strong>¡Plantilla guardada y establecida como activa!</strong></p></div>';
    }

    if ( isset( $_POST['bap_delete_custom_tpl'] ) && check_admin_referer( 'bap_tpl_builder_action' ) ) {
        $del_id = sanitize_key( $_POST['bap_tpl_id'] ?? '' );
        if ( isset( $customs[ $del_id ] ) ) {
            unset( $customs[ $del_id ] );
            update_option( 'bap_custom_email_templates', $customs );
            if ( get_option( 'bap_email_template' ) === 'custom_' . $del_id ) {
                update_option( 'bap_email_template', 'full' );
            }
            $editing_id = '';
            return '<div class="notice notice-warning is-dismissible"><p>Plantilla personalizada eliminada.</p></div>';
        }
    }

    if ( isset( $_POST['bap_import_tpl_json'] ) && check_admin_referer( 'bap_tpl_builder_action' ) && ! empty( $_FILES['bap_tpl_json_file']['tmp_name'] ) ) {
        $data = json_decode( (string) file_get_contents( $_FILES['bap_tpl_json_file']['tmp_name'] ), true );
        if ( ! is_array( $data ) || 'email_template' !== ( $data['type'] ?? '' ) || empty( $data['template'] ) ) {
            return '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> El archivo JSON no es una plantilla de email válida.</p></div>';
        }
        $new_id             = 'tpl_' . substr( md5( uniqid( '', true ) ), 0, 8 );
        $customs[ $new_id ] = bap_sanitize_custom_template_data( (array) $data['template'] );
        update_option( 'bap_custom_email_templates', $customs );
        $editing_id = $new_id;
        return '<div class="notice notice-success is-dismissible"><p><strong>¡Plantilla importada correctamente!</strong></p></div>';
    }

    return '';
}

function bap_render_email_templates_page() {
    $editing_id = isset( $_GET['edit_tpl'] ) ? sanitize_key( $_GET['edit_tpl'] ) : '';
    $clone_key  = isset( $_GET['clone_preset'] ) ? sanitize_key( $_GET['clone_preset'] ) : '';
    $notice     = bap_handle_email_builder_actions( $editing_id );
    $customs    = bap_get_custom_email_templates();
    $presets    = bap_get_builtin_email_presets();
    $all_mods   = bap_get_available_email_modules();
    $active_tpl = (string) get_option( 'bap_email_template', 'full' );

    if ( '' !== $editing_id && isset( $customs[ $editing_id ] ) ) {
        $current = $customs[ $editing_id ];
    } elseif ( '' !== $clone_key && isset( $presets[ $clone_key ] ) ) {
        $current         = $presets[ $clone_key ];
        $current['name'] = 'Copia de ' . $presets[ $clone_key ]['name'];
    } else {
        $current = [
            'name'        => '',
            'format'      => 'html_dark',
            'table_limit' => 10,
            'modules'     => [ 'cards', 'countries_bars', 'top_uris', 'events_table' ],
            'sizes'       => [ 'cards' => '100', 'countries_bars' => '50', 'top_uris' => '50', 'events_table' => '100' ],
        ];
    }

    wp_enqueue_script( 'bap-admin-email-builder', plugin_dir_url( __FILE__ ) . 'admin-email-builder.js', [], '1.0', true );
    wp_localize_script( 'bap-admin-email-builder', 'bapEmailBuilderData', [
        'nonce' => wp_create_nonce( 'bap_tpl_preview_nonce' ),
    ] );

    require plugin_dir_path( __FILE__ ) . 'admin-email-builder-view.php';
}

add_action( 'wp_ajax_bap_preview_email_template', function() {
    if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'bap_tpl_preview_nonce', 'nonce', false ) ) {
        wp_send_json_error( 'No autorizado' );
    }

    $ordered = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['order'] ?? '' ) ) ) ) );
    $tpl_cfg = bap_sanitize_custom_template_data( [
        'name'        => wp_unslash( $_POST['name'] ?? 'Vista previa' ),
        'format'      => wp_unslash( $_POST['format'] ?? 'html_dark' ),
        'table_limit' => (int) ( $_POST['limit'] ?? 10 ),
        'modules'     => $ordered,
        'sizes'       => (array) ( $_POST['sizes'] ?? [] ),
    ] );

    require_once dirname( __DIR__, 2 ) . '/common/email-templates/template-custom.php';
    $queue     = bap_get_email_preview_sample_queue();
    $data      = bap_prepare_digest_stats( $queue );
    $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $stats_url = admin_url( 'admin.php?page=bap-admin-estadisticas' );

    if ( 'text' === $tpl_cfg['format'] ) {
        $text = bap_render_custom_template_text( $tpl_cfg, $data, $site_name, true );
        $html = '<pre style="margin:0;padding:18px;background:#111;color:#e5e7eb;font-family:monospace;font-size:12px;white-space:pre-wrap;">' . esc_html( $text ) . '</pre>';
    } else {
        $html = bap_render_custom_template_html( $tpl_cfg, $data, $site_name, $stats_url, true );
    }

    wp_send_json_success( [ 'html' => $html ] );
} );
