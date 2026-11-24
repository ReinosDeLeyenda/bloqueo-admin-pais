<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_available_email_modules() {
    return [
        'cards'          => 'Tarjetas Resumen (Total Bloqueados, IPs Únicas, Top Países, Top ASNs)',
        'countries_bars' => 'Gráfico de Barras por País (Top 5)',
        'hourly_chart'   => 'Gráfico de Actividad por Hora (00h – 23h)',
        'top_uris'       => 'Ranking Top 5 Rutas Atacadas',
        'events_table'   => 'Tabla de Intentos Bloqueados (Agrupados)',
    ];
}

function bap_sanitize_custom_template_data( array $raw ) {
    $all_mods = array_keys( bap_get_available_email_modules() );
    $modules  = [];
    foreach ( (array) ( $raw['modules'] ?? [] ) as $m ) {
        $m = sanitize_key( $m );
        if ( in_array( $m, $all_mods, true ) && ! in_array( $m, $modules, true ) ) {
            $modules[] = $m;
        }
    }
    if ( empty( $modules ) ) {
        $modules = [ 'cards', 'events_table' ];
    }

    $fmt = (string) ( $raw['format'] ?? 'html_dark' );
    if ( ! in_array( $fmt, [ 'html_dark', 'html_light', 'text' ], true ) ) {
        $fmt = 'html_dark';
    }

    $name = trim( sanitize_text_field( (string) ( $raw['name'] ?? '' ) ) );
    if ( '' === $name ) {
        $name = 'Plantilla personalizada';
    }

    return [
        'name'        => mb_substr( $name, 0, 60 ),
        'format'      => $fmt,
        'table_limit' => max( 0, min( 200, (int) ( $raw['table_limit'] ?? 10 ) ) ),
        'modules'     => $modules,
    ];
}

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
        $order_raw = explode( ',', sanitize_text_field( wp_unslash( $_POST['bap_module_order'] ?? '' ) ) );
        $enabled   = (array) ( $_POST['bap_enabled_modules'] ?? [] );
        $ordered   = [];
        foreach ( $order_raw as $k ) {
            $k = trim( $k );
            if ( in_array( $k, $enabled, true ) ) {
                $ordered[] = $k;
            }
        }
        foreach ( $enabled as $k ) {
            if ( ! in_array( $k, $ordered, true ) ) {
                $ordered[] = $k;
            }
        }

        $customs[ $id ] = bap_sanitize_custom_template_data( [
            'name'        => wp_unslash( $_POST['bap_tpl_name'] ?? '' ),
            'format'      => wp_unslash( $_POST['bap_tpl_format'] ?? 'html_dark' ),
            'table_limit' => (int) ( $_POST['bap_tpl_limit'] ?? 10 ),
            'modules'     => $ordered,
        ] );
        update_option( 'bap_custom_email_templates', $customs );
        $editing_id = $id;
        return '<div class="notice notice-success is-dismissible"><p><strong>¡Plantilla guardada!</strong> Ya puedes seleccionarla en la pestaña <strong>2. Bloqueos Admin</strong>.</p></div>';
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
    $notice     = bap_handle_email_builder_actions( $editing_id );
    $customs    = bap_get_custom_email_templates();
    $all_mods   = bap_get_available_email_modules();

    $current = ( '' !== $editing_id && isset( $customs[ $editing_id ] ) )
        ? $customs[ $editing_id ]
        : [
            'name'        => '',
            'format'      => 'html_dark',
            'table_limit' => 10,
            'modules'     => [ 'cards', 'countries_bars', 'events_table' ],
        ];

    $ordered_keys = array_values( array_unique( array_merge( $current['modules'], array_keys( $all_mods ) ) ) );
    require plugin_dir_path( __FILE__ ) . 'admin-email-builder-view.php';
}
