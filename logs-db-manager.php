<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_logs_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'bap_blocked_logs';
}

function bap_maybe_create_logs_table() {
    global $wpdb;
    $table  = bap_get_logs_table_name();
    $db_ver = '1.0';

    if ( get_option( 'bap_logs_table_ver' ) === $db_ver ) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        ip_address varchar(45) NOT NULL DEFAULT '',
        country_code varchar(16) NOT NULL DEFAULT 'DESCONOCIDO',
        asn varchar(128) NOT NULL DEFAULT '',
        reason varchar(255) NOT NULL DEFAULT '',
        request_uri text NOT NULL,
        block_type varchar(32) NOT NULL DEFAULT 'admin',
        created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (id),
        KEY ip_address (ip_address),
        KEY country_code (country_code),
        KEY created_at (created_at)
    ) {$charset};";

    dbDelta( $sql );

    $legacy = get_option( 'bap_blocked_logs', null );
    if ( is_array( $legacy ) && ! empty( $legacy ) ) {
        foreach ( $legacy as $entry ) {
            $ip  = trim( (string) ( $entry['ip'] ?? '' ) );
            $cnt = max( 1, (int) ( $entry['count'] ?? 1 ) );
            if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                continue;
            }
            $asn = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : '';
            for ( $i = 0; $i < $cnt; $i++ ) {
                $wpdb->insert(
                    $table,
                    [
                        'ip_address'   => $ip,
                        'country_code' => (string) ( $entry['country'] ?? 'DESCONOCIDO' ),
                        'asn'          => (string) $asn,
                        'reason'       => (string) ( $entry['reason'] ?? 'Migrado' ),
                        'request_uri'  => '',
                        'block_type'   => 'legacy',
                        'created_at'   => (string) ( $entry['last_hit'] ?? current_time( 'mysql' ) ),
                    ],
                    [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
                );
            }
        }
        delete_option( 'bap_blocked_logs' );
    }

    update_option( 'bap_logs_table_ver', $db_ver );
}
add_action( 'admin_init', 'bap_maybe_create_logs_table' );

function bap_log_blocked_attempt( $ip, $country, $reason ) {
    global $wpdb;
    if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return;
    }

    bap_maybe_create_logs_table();
    $table = bap_get_logs_table_name();
    $asn   = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : '';
    $uri   = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $type  = ( 0 === strpos( $reason, 'Web:' ) ) ? 'web' : 'admin';

    $wpdb->insert(
        $table,
        [
            'ip_address'   => $ip,
            'country_code' => $country ?: 'DESCONOCIDO',
            'asn'          => $asn,
            'reason'       => $reason,
            'request_uri'  => $uri,
            'block_type'   => $type,
            'created_at'   => current_time( 'mysql' ),
        ],
        [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
    );

    $max_attempts = (int) get_option( 'bap_auto_ban_attempts', 5 );
    if ( $max_attempts > 0 ) {
        $total_ip_hits = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ip_address = %s", $ip )
        );
        if ( $total_ip_hits >= $max_attempts ) {
            $blacklist = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
            if ( ! in_array( $ip, $blacklist, true ) ) {
                $blacklist[] = $ip;
                update_option( 'bap_blacklist_ips', implode( "\n", $blacklist ) );
            }
        }
    }

    if ( function_exists( 'bap_queue_or_send_notification' ) ) {
        bap_queue_or_send_notification( $ip, $country, $reason );
    }
}
