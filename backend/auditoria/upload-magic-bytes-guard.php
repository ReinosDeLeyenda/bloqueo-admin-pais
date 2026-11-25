<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_upload_verify_magic_bytes( $tmp_path, $ext ) {
    $fh = @fopen( $tmp_path, 'rb' );
    if ( ! $fh ) { return false; }
    $head = (string) fread( $fh, 32 );
    fclose( $fh );

    if ( '' === $head ) { return false; }

    $sigs = [
        'jpg'  => [ "\xFF\xD8\xFF" ],
        'jpeg' => [ "\xFF\xD8\xFF" ],
        'png'  => [ "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A" ],
        'gif'  => [ 'GIF87a', 'GIF89a' ],
        'pdf'  => [ '%PDF-' ],
        'zip'  => [ "PK\x03\x04", "PK\x05\x06" ],
    ];

    if ( 'webp' === $ext ) {
        return ( 'RIFF' === substr( $head, 0, 4 ) && 'WEBP' === substr( $head, 8, 4 ) );
    }

    if ( ! isset( $sigs[ $ext ] ) ) {
        return true;
    }

    foreach ( $sigs[ $ext ] as $magic ) {
        if ( 0 === strncmp( $head, $magic, strlen( $magic ) ) ) {
            return true;
        }
    }
    return false;
}

add_filter( 'wp_handle_upload_prefilter', function( $file ) {
    if ( '1' !== (string) get_option( 'bap_harden_upload_magic_bytes', '0' ) ) {
        return $file;
    }

    $tmp  = (string) ( $file['tmp_name'] ?? '' );
    $name = strtolower( (string) ( $file['name'] ?? '' ) );
    if ( '' === $tmp || ! is_readable( $tmp ) ) {
        return $file;
    }

    $ext = pathinfo( $name, PATHINFO_EXTENSION );

    if ( in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip' ], true ) ) {
        if ( ! bap_upload_verify_magic_bytes( $tmp, $ext ) ) {
            $file['error'] = 'Seguridad: La firma binaria del archivo no coincide con su extension (.' . esc_html( $ext ) . ').';
            return $file;
        }
    }

    if ( in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' ], true ) ) {
        $chunk = (string) @file_get_contents( $tmp, false, null, 0, 65536 );
        if ( false !== stripos( $chunk, '<?php' ) || false !== stripos( $chunk, '<?=' ) ) {
            $file['error'] = 'Seguridad: Se ha detectado codigo PHP incrustado dentro del archivo.';
            return $file;
        }
        if ( 'svg' === $ext && preg_match( '/(?:<\s*(?:script|iframe|object|embed|foreignObject)\b|\bon[a-z]+\s*=|javascript\s*:)/i', $chunk ) ) {
            $file['error'] = 'Seguridad: El archivo SVG contiene codigo ejecutable o XSS bloqueado.';
            return $file;
        }
    }

    return $file;
}, 5 );