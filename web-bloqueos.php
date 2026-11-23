<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_init', function() {
    register_setting( 'bap_web_settings_group', 'bap_web_enabled', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '0'
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_scope', [
        'sanitize_callback' => fn( $i ) => ( 'paths' === $i ) ? 'paths' : 'all',
        'default'           => 'all'
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_paths', [
        'sanitize_callback' => function( $input ) {
            $lines = [];
            foreach ( preg_split( '/[\r\n,]+/', (string) $input ) as $line ) {
                $clean = trim( sanitize_text_field( $line ) );
                if ( '' !== $clean ) {
                    if ( '/' !== $clean[0] && '*' !== $clean[0] ) {
                        $clean = '/' . $clean;
                    }
                    $lines[] = $clean;
                }
            }
            return implode( "\n", array_unique( $lines ) );
        },
        'default' => ''
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_country_mode', [
        'sanitize_callback' => fn( $i ) => ( 'block' === $i ) ? 'block' : 'allow',
        'default'           => 'allow'
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_allowed_countries', [
        'sanitize_callback' => fn( $i ) => implode( ', ', bap_parse_list( $i, true ) ),
        'default'           => ''
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_blocked_countries', [
        'sanitize_callback' => fn( $i ) => implode( ', ', bap_parse_list( $i, true ) ),
        'default'           => ''
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_asn_mode', [
        'sanitize_callback' => fn( $i ) => ( 'block' === $i ) ? 'block' : 'allow',
        'default'           => 'block'
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_allowed_asns', [
        'sanitize_callback' => 'bap_sanitize_asn_list',
        'default'           => ''
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_blocked_asns', [
        'sanitize_callback' => 'bap_sanitize_asn_list',
        'default'           => ''
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_abuse_risk', [
        'sanitize_callback' => fn( $i ) => max( 0, min( 100, (int) $i ) ),
        'default'           => 0
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_block_tor', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '0'
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_block_firehol', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '0'
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_allow_seo_bots', [
        'sanitize_callback' => fn( $i ) => ( '1' === (string) $i ) ? '1' : '0',
        'default'           => '1'
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_redirect_url', [
        'sanitize_callback' => fn( $i ) => '' === trim( (string) $i ) ? '' : esc_url_raw( trim( (string) $i ) ),
        'default'           => ''
    ]);
    register_setting( 'bap_web_settings_group', 'bap_web_block_message', [
        'sanitize_callback' => fn( $i ) => sanitize_text_field( trim( (string) $i ) ),
        'default'           => 'Este contenido no está disponible en tu ubicación o red.'
    ]);
});

function bap_render_web_bloqueos_page() {
    wp_enqueue_script( 'bap-admin-bloqueos', plugin_dir_url( __FILE__ ) . 'admin-bloqueos.js', [], '13.2', true );
    wp_enqueue_script( 'bap-countries-picker', plugin_dir_url( __FILE__ ) . 'countries-picker.js', [], '13.2', true );
    require plugin_dir_path( __FILE__ ) . 'web-bloqueos-view.php';
}

