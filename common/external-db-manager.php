<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_encrypt_db_pass( $plain ) {
    $plain = (string) $plain;
    if ( '' === $plain ) {
        return '';
    }
    if ( function_exists( 'openssl_encrypt' ) && defined( 'AUTH_SALT' ) ) {
        $key = hash( 'sha256', AUTH_SALT, true );
        $iv  = random_bytes( 16 );
        $enc = openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        if ( false !== $enc ) {
            return 'enc:' . base64_encode( $iv . $enc );
        }
    }
    return $plain;
}

function bap_decrypt_db_pass( $stored ) {
    $stored = (string) $stored;
    if ( 0 === strpos( $stored, 'enc:' ) && function_exists( 'openssl_decrypt' ) && defined( 'AUTH_SALT' ) ) {
        $raw = base64_decode( substr( $stored, 4 ), true );
        if ( false !== $raw && strlen( $raw ) > 16 ) {
            $key = hash( 'sha256', AUTH_SALT, true );
            $dec = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
            if ( false !== $dec ) {
                return $dec;
            }
        }
    }
    return $stored;
}

function bap_get_logs_db( $force_reconnect = false ) {
    global $wpdb;
    static $ext_db = null;

    if ( '1' !== (string) get_option( 'bap_ext_db_enabled', '0' ) ) {
        return $wpdb;
    }

    if ( null !== $ext_db && ! $force_reconnect ) {
        return $ext_db;
    }

    $host = trim( (string) get_option( 'bap_ext_db_host', '' ) );
    $port = (int) get_option( 'bap_ext_db_port', 3306 );
    $name = trim( (string) get_option( 'bap_ext_db_name', '' ) );
    $user = trim( (string) get_option( 'bap_ext_db_user', '' ) );
    $pass = bap_decrypt_db_pass( get_option( 'bap_ext_db_pass', '' ) );

    if ( '' === $host || '' === $name || '' === $user ) {
        return $wpdb;
    }

    $host_with_port = ( $port > 0 && 3306 !== $port ) ? $host . ':' . $port : $host;
    $conn = new wpdb( $user, $pass, $name, $host_with_port );
    $conn->suppress_errors( true );

    if ( ! empty( $conn->error ) || ! $conn->dbh ) {
        return $wpdb; // Fallback seguro a MySQL local si el servidor remoto cae
    }

    $ext_db = $conn;
    return $ext_db;
}

function bap_test_external_db_connection( $host, $port, $name, $user, $pass ) {
    if ( '' === $host || '' === $name || '' === $user ) {
        return new WP_Error( 'missing_fields', 'Debes rellenar Host, Base de datos y Usuario.' );
    }
    $host_port = ( $port > 0 && 3306 !== $port ) ? $host . ':' . $port : $host;
    $test_db   = new wpdb( $user, $pass, $name, $host_port );
    $test_db->suppress_errors( true );

    if ( ! empty( $test_db->error ) || ! $test_db->dbh ) {
        return new WP_Error( 'conn_fail', 'No se pudo conectar con el servidor MySQL remoto. Verifica host, puerto, credenciales y firewall.' );
    }
    $ver = $test_db->get_var( 'SELECT VERSION()' );
    return 'Conexión exitosa (MySQL/MariaDB ' . esc_html( (string) $ver ) . ').';
}
