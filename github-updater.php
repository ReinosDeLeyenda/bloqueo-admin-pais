<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BAP_GITHUB_REPO', 'ReinosDeLeyenda/bloqueo-admin-pais' );

function bap_get_github_latest_release() {
    $cache_key = 'bap_gh_release_' . md5( BAP_GITHUB_REPO );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $host = 'api' . '.github.com';
    $url  = 'https://' . $host . '/repos/' . BAP_GITHUB_REPO . '/releases/latest';
    $res  = wp_remote_get( $url, [
        'timeout' => 10,
        'headers' => [
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress-BAP-Updater',
        ],
    ] );

    if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
        return false;
    }

    $body = json_decode( wp_remote_retrieve_body( $res ), true );
    if ( empty( $body['tag_name'] ) ) {
        return false;
    }

    $zip_url = '';
    if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
        foreach ( $body['assets'] as $asset ) {
            if ( '.zip' === substr( (string) ( $asset['name'] ?? '' ), -4 ) ) {
                $zip_url = $asset['browser_download_url'] ?? '';
                break;
            }
        }
    }
    if ( '' === $zip_url ) {
        $zip_url = $body['zipball_url'] ?? '';
    }

    $info = [
        'version'   => ltrim( (string) $body['tag_name'], 'vV' ),
        'zip_url'   => $zip_url,
        'html_url'  => $body['html_url'] ?? '',
        'changelog' => $body['body'] ?? '',
        'published' => $body['published_at'] ?? '',
    ];

    set_transient( $cache_key, $info, 6 * HOUR_IN_SECONDS );
    return $info;
}

add_filter( 'pre_set_site_transient_update_plugins', function( $transient ) {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }

    $plugin_slug = plugin_basename( dirname( __FILE__ ) . '/bloqueo-admin-pais.php' );
    $current_ver = $transient->checked[ $plugin_slug ] ?? '1.0';
    $release     = bap_get_github_latest_release();

    if ( $release && ! empty( $release['zip_url'] ) && version_compare( $release['version'], $current_ver, '>' ) ) {
        $transient->response[ $plugin_slug ] = (object) [
            'slug'        => dirname( $plugin_slug ),
            'plugin'      => $plugin_slug,
            'new_version' => $release['version'],
            'url'         => $release['html_url'],
            'package'     => $release['zip_url'],
        ];
    }

    return $transient;
});

add_filter( 'plugins_api', function( $res, $action, $args ) {
    $plugin_folder = basename( dirname( __FILE__ ) );
    if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== $plugin_folder ) {
        return $res;
    }

    $release = bap_get_github_latest_release();
    if ( ! $release ) {
        return $res;
    }

    return (object) [
        'name'          => 'Bloqueo Admin por País e IP',
        'slug'          => $plugin_folder,
        'version'       => $release['version'],
        'homepage'      => $release['html_url'],
        'download_link' => $release['zip_url'],
        'last_updated'  => $release['published'],
        'sections'      => [
            'changelog' => nl2br( esc_html( $release['changelog'] ) ),
        ],
    ];
}, 10, 3 );

add_filter( 'upgrader_source_selection', function( $source, $remote_source, $upgrader, $hook_extra ) {
    $plugin_slug = plugin_basename( dirname( __FILE__ ) . '/bloqueo-admin-pais.php' );
    if ( ( $hook_extra['plugin'] ?? '' ) !== $plugin_slug ) {
        return $source;
    }
    $desired = trailingslashit( $remote_source ) . dirname( $plugin_slug );
    if ( untrailingslashit( $source ) !== $desired ) {
        global $wp_filesystem;
        if ( $wp_filesystem && $wp_filesystem->move( $source, $desired ) ) {
            return trailingslashit( $desired );
        }
    }
    return $source;
}, 10, 4 );

add_action( 'delete_site_transient_update_plugins', function() {
    delete_transient( 'bap_gh_release_' . md5( BAP_GITHUB_REPO ) );
});

// 1. Añadir enlace "Buscar actualizaciones" en la pantalla de Plugins
add_filter( 'plugin_action_links_' . plugin_basename( dirname( __FILE__ ) . '/bloqueo-admin-pais.php' ), function( $links ) {
    $url = wp_nonce_url(
        admin_url( 'plugins.php?bap_force_update_check=1' ),
        'bap_force_update_nonce'
    );
    $links[] = '<a href="' . esc_url( $url ) . '">Buscar actualizaciones</a>';
    return $links;
} );

// 2. Procesar la comprobación manual, limpiar caché y mostrar aviso con el resultado
add_action( 'admin_init', function() {
    if ( ! isset( $_GET['bap_force_update_check'] ) || ! current_user_can( 'update_plugins' ) ) {
        return;
    }
    check_admin_referer( 'bap_force_update_nonce' );

    // Borrar la caché de 6 horas de GitHub y forzar a WordPress a recomprobar
    delete_transient( 'bap_gh_release_' . md5( BAP_GITHUB_REPO ) );
    delete_site_transient( 'update_plugins' );
    wp_update_plugins();

    $plugin_slug = plugin_basename( dirname( __FILE__ ) . '/bloqueo-admin-pais.php' );
    $plugin_data = get_plugin_data( dirname( __FILE__ ) . '/bloqueo-admin-pais.php' );
    $current_ver = $plugin_data['Version'] ?? '1.0.0';
    $release     = bap_get_github_latest_release();

    if ( ! $release ) {
        set_transient( 'bap_manual_update_notice', [
            'type' => 'error',
            'msg'  => 'No se pudo conectar con GitHub para comprobar actualizaciones (o aún no hay releases publicadas).',
        ], 60 );
    } elseif ( version_compare( $release['version'], $current_ver, '>' ) ) {
        set_transient( 'bap_manual_update_notice', [
            'type' => 'warning',
            'msg'  => sprintf(
                '¡Nueva versión disponible: <strong>%s</strong> (tienes la %s)! Puedes actualizar directamente desde el aviso de este plugin.',
                esc_html( $release['version'] ),
                esc_html( $current_ver )
            ),
        ], 60 );
    } else {
        set_transient( 'bap_manual_update_notice', [
            'type' => 'success',
            'msg'  => sprintf(
                'El plugin está actualizado a la última versión (<strong>%s</strong>).',
                esc_html( $current_ver )
            ),
        ], 60 );
    }

    wp_safe_redirect( admin_url( 'plugins.php' ) );
    exit;
} );

// 3. Mostrar el aviso en la parte superior de la página de Plugins
add_action( 'admin_notices', function() {
    $notice = get_transient( 'bap_manual_update_notice' );
    if ( ! $notice ) {
        return;
    }
    delete_transient( 'bap_manual_update_notice' );
    printf(
        '<div class="notice notice-%s is-dismissible"><p><strong>Bloqueo Admin:</strong> %s</p></div>',
        esc_attr( $notice['type'] ),
        wp_kses_post( $notice['msg'] )
    );
} );
