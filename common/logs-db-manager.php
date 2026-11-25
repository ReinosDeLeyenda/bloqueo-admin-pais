<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_logs_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'bap_blocked_logs';
}

function bap_maybe_create_logs_table() {
    if ( '1.2' === get_option( 'bap_logs_table_ver' ) ) {
        return;
    }
    global $wpdb;
    $table   = bap_get_logs_table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `{$table}` (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        ip_address varchar(45) NOT NULL,
        country_code varchar(16) NOT NULL,
        asn varchar(190) NOT NULL DEFAULT '',
        block_type varchar(32) NOT NULL DEFAULT 'admin',
        reason varchar(255) NOT NULL,
        request_uri text NOT NULL,
        user_agent text NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY idx_created_at (created_at),
        KEY idx_ip_address (ip_address)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    update_option( 'bap_logs_table_ver', '1.2' );
}
add_action( 'admin_init', 'bap_maybe_create_logs_table' );

function bap_get_sanitized_user_agent() {
    if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
        return '';
    }
    return mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 );
}

function bap_log_blocked_attempt( $ip, $country, $reason ) {
    global $wpdb;
    bap_maybe_create_logs_table();

    $uri        = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
    $asn        = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : '';
    $type       = ( 0 === strpos( $reason, 'Web:' ) ) ? 'web' : 'admin';
    $user_agent = bap_get_sanitized_user_agent();
    $now        = current_time( 'mysql' );

    $logged_externally = false;
    if ( function_exists( 'bap_log_to_external_db' ) ) {
        $logged_externally = bap_log_to_external_db( $ip, $country, $asn, $type, $reason, $uri, $now, $user_agent );
    }

    if ( ! $logged_externally ) {
        $wpdb->insert(
            bap_get_logs_table_name(),
            [
                'ip_address'   => $ip,
                'country_code' => $country,
                'asn'          => $asn,
                'block_type'   => $type,
                'reason'       => $reason,
                'request_uri'  => $uri,
                'user_agent'   => $user_agent,
                'created_at'   => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    $max_attempts = (int) get_option( 'bap_auto_ban_attempts', 0 );
    if ( $max_attempts > 0 && ! bap_is_server_ip( $ip ) ) {
        $key      = 'bap_att_' . md5( $ip );
        $attempts = (int) get_transient( $key ) + 1;
        set_transient( $key, $attempts, HOUR_IN_SECONDS );

        if ( $attempts >= $max_attempts ) {
            bap_add_ip_to_blacklist( $ip, true );
            delete_transient( $key );
        }
    }

    if ( function_exists( 'bap_track_and_autoban_subnet' ) ) { bap_track_and_autoban_subnet( $ip ); }

    if ( function_exists( 'bap_queue_or_send_notification' ) ) {
        bap_queue_or_send_notification( $ip, $country, $reason );
    }
}

add_action( 'bap_daily_logs_cleanup', function() {
    $days = (int) get_option( 'bap_logs_retention_days', 30 );
    if ( $days <= 0 ) {
        return;
    }
    global $wpdb;
    $table  = bap_get_logs_table_name();
    $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
    $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE created_at < %s", $cutoff ) );
} );

if ( ! wp_next_scheduled( 'bap_daily_logs_cleanup' ) ) {
    wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'bap_daily_logs_cleanup' );
}
