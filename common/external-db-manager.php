<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_encrypt_ext_pass( $plain ) {
    if ( '' === (string) $plain ) {
        return '';
    }
    if ( function_exists( 'openssl_encrypt' ) ) {
        $key = hash( 'sha256', AUTH_SALT, true );
        $iv  = random_bytes( 16 );
        $enc = openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        if ( false !== $enc ) {
            return 'enc:' . base64_encode( $iv . $enc );
        }
    }
    return $plain;
}

function bap_decrypt_ext_pass( $stored ) {
    $stored = (string) $stored;
    if ( 0 === strpos( $stored, 'enc:' ) && function_exists( 'openssl_decrypt' ) ) {
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

function bap_get_external_wpdb( &$error_msg = '' ) {
    if ( '1' !== (string) get_option( 'bap_ext_db_enabled', '0' ) ) {
        return null;
    }

    $host = trim( (string) get_option( 'bap_ext_db_host', '' ) );
    $port = (int) get_option( 'bap_ext_db_port', 3306 );
    $name = trim( (string) get_option( 'bap_ext_db_name', '' ) );
    $user = trim( (string) get_option( 'bap_ext_db_user', '' ) );
    $pass = bap_decrypt_ext_pass( get_option( 'bap_ext_db_pass', '' ) );

    if ( '' === $host || '' === $name || '' === $user ) {
        $error_msg = 'Faltan datos obligatorios (Host, Base de Datos o Usuario).';
        return null;
    }

    $db_host = ( $port > 0 && 3306 !== $port ) ? $host . ':' . $port : $host;

    static $ext_db = null;
    if ( null === $ext_db ) {
        $ext_db = new wpdb( $user, $pass, $name, $db_host );
        $ext_db->hide_errors();
    }

    if ( ! empty( $ext_db->error ) || ! $ext_db->dbh ) {
        $error_msg = is_wp_error( $ext_db->error ) ? $ext_db->error->get_error_message() : 'No se pudo conectar al servidor MySQL remoto.';
        $ext_db    = null;
        return null;
    }

    return $ext_db;
}

function bap_ensure_external_logs_table( $ext_db, $table ) {
    $safe_table = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $table );
    if ( '' === $safe_table ) {
        $safe_table = 'bap_global_blocked_logs';
    }

    $sql = "CREATE TABLE IF NOT EXISTS `{$safe_table}` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `site_domain` varchar(190) NOT NULL DEFAULT '',
        `ip_address` varchar(45) NOT NULL,
        `country_code` varchar(16) NOT NULL,
        `asn` varchar(190) NOT NULL DEFAULT '',
        `block_type` varchar(32) NOT NULL DEFAULT 'admin',
        `reason` varchar(255) NOT NULL,
        `request_uri` text NOT NULL,
        `user_agent` text NOT NULL,
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_ip_address` (`ip_address`),
        KEY `idx_site_domain` (`site_domain`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $res = $ext_db->query( $sql );
    if ( false !== $res ) {
        $has_ua = $ext_db->get_var( "SHOW COLUMNS FROM `{$safe_table}` LIKE 'user_agent'" );
        if ( ! $has_ua ) {
            $ext_db->query( "ALTER TABLE `{$safe_table}` ADD COLUMN `user_agent` text NOT NULL AFTER `request_uri`" );
        }
    }
    return ( false !== $res );
}

function bap_log_to_external_db( $ip, $country, $asn, $type, $reason, $uri, $now, $user_agent = '' ) {
    $err    = '';
    $ext_db = bap_get_external_wpdb( $err );
    if ( ! $ext_db ) {
        return false;
    }

    $table = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) get_option( 'bap_ext_db_table', 'bap_global_blocked_logs' ) );
    if ( '' === $table ) {
        $table = 'bap_global_blocked_logs';
    }

    static $table_checked = false;
    if ( ! $table_checked ) {
        bap_ensure_external_logs_table( $ext_db, $table );
        $table_checked = true;
    }

    $domain = (string) wp_parse_url( home_url(), PHP_URL_HOST );

    $inserted = $ext_db->insert(
        $table,
        [
            'site_domain'  => $domain,
            'ip_address'   => $ip,
            'country_code' => $country,
            'asn'          => $asn,
            'block_type'   => $type,
            'reason'       => $reason,
            'request_uri'  => $uri,
            'user_agent'   => $user_agent,
            'created_at'   => $now,
        ],
        [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
    );

    return ( false !== $inserted );
}
