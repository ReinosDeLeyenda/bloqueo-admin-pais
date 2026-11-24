<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_init', function() {
    register_setting( 'bap_db_settings_group', 'bap_db_auto_update_freq', [
        'sanitize_callback' => function( $i ) {
            $allowed = [ 'none', 'daily', 'weekly', '2weeks', 'monthly' ];
            return in_array( $i, $allowed, true ) ? $i : 'none';
        },
        'default' => 'none',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_enabled', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '0',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_host', [
        'sanitize_callback' => fn( $i ) => trim( sanitize_text_field( (string) $i ) ),
        'default'           => '',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_port', [
        'sanitize_callback' => fn( $i ) => max( 1, min( 65535, (int) $i ) ),
        'default'           => 3306,
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_name', [
        'sanitize_callback' => fn( $i ) => trim( sanitize_text_field( (string) $i ) ),
        'default'           => '',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_user', [
        'sanitize_callback' => fn( $i ) => trim( sanitize_text_field( (string) $i ) ),
        'default'           => '',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_pass', [
        'sanitize_callback' => function( $i ) {
            $raw = (string) $i;
            if ( '' === trim( $raw ) ) {
                return get_option( 'bap_ext_db_pass', '' );
            }
            return function_exists( 'bap_encrypt_db_pass' ) ? bap_encrypt_db_pass( $raw ) : $raw;
        },
        'default' => '',
    ]);
    register_setting( 'bap_db_settings_group', 'bap_ext_db_table', [
        'sanitize_callback' => fn( $i ) => preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $i ) ?: 'bap_central_logs',
        'default'           => 'bap_central_logs',
    ]);
});

add_action( 'update_option_bap_db_auto_update_freq', function( $old_val, $new_val ) {
    bap_reschedule_db_cron( $new_val );
}, 10, 2 );

add_action( 'update_option_bap_ext_db_enabled', function() {
    if ( function_exists( 'bap_get_logs_db' ) ) {
        bap_get_logs_db( true );
        bap_maybe_create_logs_table( true );
    }
});

add_action( 'bap_auto_update_dbs_event', 'bap_run_all_db_updates' );
