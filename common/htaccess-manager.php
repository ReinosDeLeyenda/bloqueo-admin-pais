<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_default_protected_paths() {
    return implode( "\n", [
        '/wp-admin/*', '/wp-login.php', '/wp-register.php', '/xmlrpc.php', '/wp-json/wp/v2/users*',
        '*.log', '*.zip', '*.rar', '*.tar', '*.tar.gz', '*.tgz', '*.7z', '*.gz', '*.bak', '*.sql', '*.env', '*.git*',
    ] );
}

function bap_is_protected_request() {
    $raw_paths = get_option( 'bap_protected_paths', null );
    if ( null === $raw_paths ) {
        $raw_paths = bap_get_default_protected_paths();
    }

    $patterns = bap_parse_list( $raw_paths, false );
    if ( empty( $patterns ) ) {
        return false;
    }

    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
    $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
    if ( '' === $path ) {
        $path = $uri;
    }

    if ( false !== strpos( $path, '/wp-admin/admin-ajax.php' ) || false !== strpos( $path, '/wp-admin/admin-post.php' ) ) {
        return false;
    }

    foreach ( $patterns as $pattern ) {
        $pat = strtolower( trim( $pattern ) );
        if ( '' === $pat ) {
            continue;
        }
        $regex = '#^' . str_replace( '\*', '.*', preg_quote( $pat, '#' ) ) . '$#i';
        if ( preg_match( $regex, $path ) || preg_match( $regex, $uri ) ) {
            return true;
        }
        if ( '/*' === substr( $pat, -2 ) && rtrim( $path, '/' ) === substr( $pat, 0, -2 ) ) {
            return true;
        }
    }

    return false;
}

function bap_sync_htaccess_rules() {
    if ( ! function_exists( 'insert_with_markers' ) ) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }

    $htaccess_file = ABSPATH . '.htaccess';
    if ( ! file_exists( $htaccess_file ) || ! is_writable( $htaccess_file ) ) {
        return false;
    }

    if ( '1' !== (string) get_option( 'bap_enable_htaccess', '0' ) ) {
        return insert_with_markers( $htaccess_file, 'Bloqueo Admin Pais', [] );
    }

    $raw_paths = get_option( 'bap_protected_paths', null );
    if ( null === $raw_paths ) {
        $raw_paths = bap_get_default_protected_paths();
    }

    $exts = [];
    foreach ( bap_parse_list( $raw_paths, false ) as $pat ) {
        if ( preg_match( '/^\*\.([a-zA-Z0-9._-]+)$/', $pat, $m ) ) {
            $clean_ext = str_replace( '*', '', $m[1] );
            if ( '' !== $clean_ext ) {
                $exts[] = preg_quote( $clean_ext, '/' );
            }
        }
    }

    $block_xmlrpc = ( '1' === (string) get_option( 'bap_block_xmlrpc', '1' ) );
    $rules        = [];

    if ( ! empty( $exts ) || $block_xmlrpc ) {
        $secret = trim( (string) get_option( 'bap_secret_key', '' ) );
        $cname  = '' !== $secret ? 'bap_bypass_' . md5( $secret ) : '';

        $rules[] = '<IfModule mod_rewrite.c>';
        $rules[] = 'RewriteEngine On';

        if ( ! empty( $exts ) ) {
            if ( '' !== $cname ) {
                $rules[] = 'RewriteCond %{HTTP_COOKIE} !' . $cname . '= [NC]';
            }
            $rules[] = 'RewriteCond %{REQUEST_URI} \.(' . implode( '|', array_unique( $exts ) ) . ')$ [NC]';
            $rules[] = 'RewriteRule .* - [F,L]';
        }

        if ( $block_xmlrpc ) {
            if ( '' !== $cname ) {
                $rules[] = 'RewriteCond %{HTTP_COOKIE} !' . $cname . '= [NC]';
            }
            $rules[] = 'RewriteRule ^xmlrpc\.php$ - [F,L]';
        }

        $rules[] = '</IfModule>';
    }

    return insert_with_markers( $htaccess_file, 'Bloqueo Admin Pais', $rules );
}
