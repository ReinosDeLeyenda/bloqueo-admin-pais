<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_audit_wp_options_autoload() {
    global $wpdb;
    $in_sql     = "'yes','on','auto','auto-on'";
    $total_size = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM `{$wpdb->options}` WHERE autoload IN ({$in_sql})" );
    $total_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->options}` WHERE autoload IN ({$in_sql})" );
    $exp_trans  = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$wpdb->options}` WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
        $wpdb->esc_like( '_transient_timeout_' ) . '%',
        time()
    ) );

    $top_rows = $wpdb->get_results(
        "SELECT option_name, LENGTH(option_value) AS bytes, autoload
         FROM `{$wpdb->options}`
         WHERE autoload IN ({$in_sql})
         ORDER BY bytes DESC
         LIMIT 20",
        ARRAY_A
    );

    return [
        'total_bytes'   => $total_size,
        'total_kb'      => round( $total_size / 1024, 1 ),
        'total_rows'    => $total_rows,
        'expired_trans' => $exp_trans,
        'top_options'   => is_array( $top_rows ) ? $top_rows : [],
        'bloated'       => ( $total_size > 819200 ),
    ];
}

function bap_purge_expired_wp_transients() {
    global $wpdb;
    $now     = time();
    $expired = $wpdb->get_col( $wpdb->prepare(
        "SELECT option_name FROM `{$wpdb->options}` WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d LIMIT 2000",
        $wpdb->esc_like( '_transient_timeout_' ) . '%',
        $now
    ) );

    $deleted = 0;
    foreach ( (array) $expired as $t_key ) {
        $slug = substr( (string) $t_key, strlen( '_transient_timeout_' ) );
        if ( '' !== $slug && delete_transient( $slug ) ) {
            $deleted++;
        }
    }
    return $deleted;
}