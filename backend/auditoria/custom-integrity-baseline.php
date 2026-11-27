<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_get_custom_baseline_path() {
    return function_exists( 'bap_get_geo_storage_path' )
        ? bap_get_geo_storage_path( 'custom-integrity-baseline.json' )
        : WP_CONTENT_DIR . '/uploads/bap-geo/custom-integrity-baseline.json';
}

function bap_collect_custom_code_hashes() {
    $root        = wp_normalize_path( ABSPATH );
    $content_dir = wp_normalize_path( WP_CONTENT_DIR );
    $own_plugin  = wp_normalize_path( dirname( __DIR__, 2 ) );
    $exts        = [ 'php', 'phtml', 'inc', 'js', 'css', 'json' ];
    $targets     = [ $content_dir . '/themes', $content_dir . '/plugins', $content_dir . '/mu-plugins' ];
    $hashes      = [];

    foreach ( $targets as $dir ) {
        if ( ! is_dir( $dir ) ) {
            continue;
        }
        $iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $iter as $f ) {
            if ( ! $f->isFile() ) {
                continue;
            }
            $ext = strtolower( $f->getExtension() );
            if ( ! in_array( $ext, $exts, true ) ) {
                continue;
            }
            $full = wp_normalize_path( $f->getPathname() );
            if ( 0 === strpos( $full, $own_plugin . '/' ) ) {
                continue;
            }
            if ( preg_match( '#/(temp|cache|tmp|logs|template-cache|fusion-styles|fusion-scripts)/#i', $full ) ) {
                continue;
            }
            if ( 'independent-analytics/iawp-click-config.php' === substr( $full, -46 ) ) {
                continue;
            }
            $size = $f->getSize();
            if ( $size <= 0 || $size > 2097152 ) {
                continue;
            }
            $h = @hash_file( 'sha256', $full );
            if ( false !== $h ) {
                $rel            = ltrim( substr( $full, strlen( $root ) ), '/' );
                $hashes[ $rel ] = $h;
            }
        }
    }

    ksort( $hashes );
    return $hashes;
}

function bap_refresh_custom_integrity_baseline() {
    $hashes  = bap_collect_custom_code_hashes();
    $payload = [
        'created_at' => current_time( 'mysql' ),
        'count'      => count( $hashes ),
        'files'      => $hashes,
    ];
    @file_put_contents( bap_get_custom_baseline_path(), wp_json_encode( $payload ), LOCK_EX );
    return $payload;
}

function bap_verify_custom_integrity_baseline() {
    $path = bap_get_custom_baseline_path();
    if ( ! file_exists( $path ) ) {
        return null;
    }
    $stored = json_decode( (string) @file_get_contents( $path ), true );
    if ( ! is_array( $stored ) || ! is_array( $stored['files'] ?? null ) ) {
        return null;
    }

    $base_files = $stored['files'];
    $cur_files  = bap_collect_custom_code_hashes();
    $modified   = [];
    $added      = [];
    $deleted    = [];

    foreach ( $cur_files as $rel => $cur_hash ) {
        if ( ! isset( $base_files[ $rel ] ) ) {
            $added[] = $rel;
        } elseif ( ! hash_equals( (string) $base_files[ $rel ], $cur_hash ) ) {
            $modified[] = $rel;
        }
    }

    foreach ( $base_files as $rel => $old_hash ) {
        if ( ! isset( $cur_files[ $rel ] ) ) {
            $deleted[] = $rel;
        }
    }

    return [
        'created_at' => (string) ( $stored['created_at'] ?? 'N/D' ),
        'checked'    => count( $cur_files ),
        'modified'   => $modified,
        'added'      => $added,
        'deleted'    => $deleted,
    ];
}

add_action( 'upgrader_process_complete', function( $upgrader, $options ) {
    if ( in_array( $options['type'] ?? '', [ 'plugin', 'theme' ], true ) && file_exists( bap_get_custom_baseline_path() ) ) {
        bap_refresh_custom_integrity_baseline();
        delete_transient( 'bap_last_custom_integrity_res' );
    }
}, 20, 2 );