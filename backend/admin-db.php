<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', function() {
    add_menu_page( 'Bloqueo Admin y Web', 'Bloqueo Admin', 'manage_options', 'bap-admin-db', 'bap_render_db_page', 'dashicons-shield', 81 );
    add_submenu_page( 'bap-admin-db', 'Administración Base de Datos', 'Base de Datos', 'manage_options', 'bap-admin-db', 'bap_render_db_page' );
    add_submenu_page( 'bap-admin-db', 'Bloqueos Admin', 'Bloqueos Admin', 'manage_options', 'bap-admin-bloqueos', 'bap_render_bloqueos_page' );
    add_submenu_page( 'bap-admin-db', 'Bloqueos Web', 'Bloqueos Web', 'manage_options', 'bap-web-bloqueos', 'bap_render_web_bloqueos_page' );
    add_submenu_page( 'bap-admin-db', 'Estadísticas', 'Estadísticas', 'manage_options', 'bap-admin-estadisticas', 'bap_render_estadisticas_page' );
});

add_filter( 'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/bloqueo-admin-pais.php' ), function( $links ) {
    $url = admin_url( 'admin.php?page=bap-admin-db' );
    array_unshift( $links, '<a href="' . esc_url( $url ) . '"><strong>Ajustes</strong></a>' );
    return $links;
});

function bap_render_tabs( $active_slug ) {
    $tabs = [
        'bap-admin-db'           => '1. Administración Base de Datos',
        'bap-admin-bloqueos'     => '2. Bloqueos Admin',
        'bap-web-bloqueos'       => '3. Bloqueos Web',
        'bap-admin-estadisticas' => '4. Estadísticas',
    ];
    echo '<h2 class="nav-tab-wrapper" style="margin-bottom:20px;">';
    foreach ( $tabs as $slug => $label ) {
        $class = ( $active_slug === $slug ) ? 'nav-tab nav-tab-active' : 'nav-tab';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</h2>';
}

function bap_delete_single_db( $key ) {
    $map = [
        'country' => [ bap_get_mmdb_path(), 'bap_mmdb_last_updated', '' ],
        'asn'     => [ bap_get_asn_mmdb_path(), 'bap_asn_mmdb_last_updated', '' ],
        'abuse'   => [ bap_get_abuseipdb_path(), 'bap_abuseipdb_last_updated', 'bap_abuseipdb_count' ],
        'tor'     => [ bap_get_tor_list_path(), 'bap_tor_last_updated', 'bap_tor_count' ],
        'firehol' => [ bap_get_firehol_list_path(), 'bap_firehol_last_updated', 'bap_firehol_count' ],
    ];
    if ( isset( $map[ $key ] ) ) {
        list( $path, $opt_date, $opt_cnt ) = $map[ $key ];
        if ( file_exists( $path ) ) {
            @unlink( $path );
        }
        delete_option( $opt_date );
        if ( '' !== $opt_cnt ) {
            delete_option( $opt_cnt );
        }
    }
}

function bap_run_all_db_updates() {
    $tasks = [
        'Países'    => bap_download_mmdb_database( 'country' ),
        'ASN'       => bap_download_mmdb_database( 'asn' ),
        'AbuseIPDB' => bap_download_abuseipdb_database(),
        'Tor'       => bap_download_tor_database(),
        'FireHOL'   => bap_download_firehol_database(),
    ];
    $errs = [];
    foreach ( $tasks as $name => $r ) {
        if ( is_wp_error( $r ) ) {
            $errs[] = $name . ': ' . $r->get_error_message();
        }
    }
    return $errs;
}

function bap_reschedule_db_cron( $freq ) {
    wp_clear_scheduled_hook( 'bap_auto_update_dbs_event' );
    $map = [
        'daily'   => [ 'daily', DAY_IN_SECONDS ],
        'weekly'  => [ 'weekly', WEEK_IN_SECONDS ],
        '2weeks'  => [ 'bap_2weeks', 14 * DAY_IN_SECONDS ],
        'monthly' => [ 'bap_monthly', 30 * DAY_IN_SECONDS ],
    ];
    if ( isset( $map[ $freq ] ) ) {
        list( $recurrence, $offset ) = $map[ $freq ];
        wp_schedule_event( time() + $offset, $recurrence, 'bap_auto_update_dbs_event' );
    }
}

function bap_handle_db_actions() {
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && empty( $_POST ) && empty( $_FILES ) ) {
        return '<div class="notice notice-error is-dismissible"><p><strong>El archivo supera el límite de subida de PHP (<code>' . esc_html( ini_get( 'post_max_size' ) ) . '</code>).</strong></p></div>';
    }
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bap_db_action' ) ) {
        return '';
    }

    if ( isset( $_POST['bap_test_ext_db'] ) && function_exists( 'bap_test_external_db_connection' ) ) {
        $host     = trim( sanitize_text_field( wp_unslash( $_POST['bap_ext_db_host'] ?? get_option( 'bap_ext_db_host', '' ) ) ) );
        $port     = (int) ( $_POST['bap_ext_db_port'] ?? get_option( 'bap_ext_db_port', 3306 ) );
        $name     = trim( sanitize_text_field( wp_unslash( $_POST['bap_ext_db_name'] ?? get_option( 'bap_ext_db_name', '' ) ) ) );
        $user     = trim( sanitize_text_field( wp_unslash( $_POST['bap_ext_db_user'] ?? get_option( 'bap_ext_db_user', '' ) ) ) );
        $raw_pass = (string) wp_unslash( $_POST['bap_ext_db_pass'] ?? '' );
        $pass     = '' !== trim( $raw_pass ) ? $raw_pass : bap_decrypt_db_pass( get_option( 'bap_ext_db_pass', '' ) );

        $res = bap_test_external_db_connection( $host, $port, $name, $user, $pass );
        return is_wp_error( $res )
            ? '<div class="notice notice-error is-dismissible"><p><strong>Error de conexión MySQL:</strong> ' . esc_html( $res->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success is-dismissible"><p><strong>¡Servidor centralizado accesible!</strong> ' . $res . '</p></div>';
    }

    if ( isset( $_POST['bap_update_all_dbs'] ) ) {
        $errs = bap_run_all_db_updates();
        return empty( $errs )
            ? '<div class="notice notice-success is-dismissible"><p><strong>¡Todas las bases de datos (5/5) se han descargado e instalado correctamente!</strong></p></div>'
            : '<div class="notice notice-warning is-dismissible"><p><strong>Proceso completado con avisos:</strong> ' . esc_html( implode( ' | ', $errs ) ) . '</p></div>';
    }
    if ( isset( $_POST['bap_delete_all_dbs'] ) ) {
        foreach ( [ 'country', 'asn', 'abuse', 'tor', 'firehol' ] as $k ) {
            bap_delete_single_db( $k );
        }
        return '<div class="notice notice-success is-dismissible"><p><strong>Todas las bases de datos locales han sido eliminadas.</strong></p></div>';
    }
    if ( isset( $_POST['bap_delete_db'] ) ) {
        bap_delete_single_db( sanitize_key( $_POST['bap_delete_db'] ) );
        return '<div class="notice notice-success is-dismissible"><p><strong>Base de datos eliminada correctamente.</strong></p></div>';
    }
    if ( isset( $_POST['bap_import_json'] ) && ! empty( $_FILES['bap_json_file']['tmp_name'] ) ) {
        return bap_import_json_config( $_FILES['bap_json_file']['tmp_name'] );
    }

    $uploads = [
        'bap_update_country_db' => fn() => bap_download_mmdb_database( 'country' ),
        'bap_upload_country_db' => fn() => bap_verify_and_save_mmdb( $_FILES['bap_mmdb_file']['tmp_name'] ?? '', '.gz' === strtolower( substr( $_FILES['bap_mmdb_file']['name'] ?? '', -3 ) ), 'country' ),
        'bap_update_asn_db'     => fn() => bap_download_mmdb_database( 'asn' ),
        'bap_upload_asn_db'     => fn() => bap_verify_and_save_mmdb( $_FILES['bap_asn_file']['tmp_name'] ?? '', '.gz' === strtolower( substr( $_FILES['bap_asn_file']['name'] ?? '', -3 ) ), 'asn' ),
        'bap_update_abuse_db'   => fn() => bap_download_abuseipdb_database(),
        'bap_upload_abuse_db'   => fn() => bap_install_abuseipdb_from_file( $_FILES['bap_abuse_file']['tmp_name'] ?? '', $_FILES['bap_abuse_file']['name'] ?? '' ),
        'bap_update_tor_db'     => fn() => bap_download_tor_database(),
        'bap_upload_tor_db'     => fn() => bap_install_tor_from_content( (string) @file_get_contents( $_FILES['bap_tor_file']['tmp_name'] ?? '' ) ),
        'bap_update_firehol_db' => fn() => bap_download_firehol_database(),
        'bap_upload_firehol_db' => fn() => bap_install_firehol_from_content( (string) @file_get_contents( $_FILES['bap_firehol_file']['tmp_name'] ?? '' ) ),
    ];

    foreach ( $uploads as $btn => $fn ) {
        if ( isset( $_POST[ $btn ] ) ) {
            $r = $fn();
            return is_wp_error( $r )
                ? '<div class="notice notice-error is-dismissible"><p>' . esc_html( $r->get_error_message() ) . '</p></div>'
                : '<div class="notice notice-success is-dismissible"><p><strong>¡Base de datos instalada/actualizada correctamente!</strong></p></div>';
        }
    }
    return '';
}

function bap_render_db_page() {
    $notice       = bap_handle_db_actions();
    $mmdb         = bap_get_mmdb_path();
    $asn_mmdb     = bap_get_asn_mmdb_path();
    $abuse_file   = bap_get_abuseipdb_path();
    $tor_file     = bap_get_tor_list_path();
    $firehol_file = bap_get_firehol_list_path();

    $country_updated = get_option( 'bap_mmdb_last_updated', '' );
    if ( '' === $country_updated && file_exists( $mmdb ) ) {
        $country_updated = wp_date( 'Y-m-d H:i:s', filemtime( $mmdb ) );
    }
    $asn_updated = get_option( 'bap_asn_mmdb_last_updated', '' );
    if ( '' === $asn_updated && file_exists( $asn_mmdb ) ) {
        $asn_updated = wp_date( 'Y-m-d H:i:s', filemtime( $asn_mmdb ) );
    }

    require plugin_dir_path( __FILE__ ) . 'admin-db-view.php';
}
