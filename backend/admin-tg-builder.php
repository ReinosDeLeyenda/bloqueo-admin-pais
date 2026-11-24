<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_tg_preview_sample_vars() {
    $site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $now  = current_time( 'mysql' );
    return [
        '{badge}'              => '🧪 <b>PRUEBA</b> · ',
        '{status_icon}'        => '⚠️',
        '{site_name}'          => esc_html( $site ),
        '{ip}'                 => '185.220.101.4',
        '{country}'            => 'DE',
        '{asn}'                => 'AS205100 — F3 Netze e.V.',
        '{risk}'               => '100%',
        '{reason}'             => 'Nodo de salida Tor',
        '{uri}'                => '/wp-login.php',
        '{time}'               => $now,
        '{period}'             => '24 horas',
        '{total_hits}'         => '42',
        '{unique_ips}'         => '9',
        '{top_countries_list}' => '<ul><li><b>RU</b>: 21 intentos (50%)</li><li><b>CN</b>: 12 intentos (28.6%)</li></ul>',
        '{events_table}'       => '<table><tr><th>Hits</th><th>IP / País</th><th>Riesgo</th><th>Motivo</th></tr><tr><td><b>x18</b></td><td><code>185.220.101.4</code> (DE)</td><td>100%</td><td>Nodo de salida Tor</td></tr></table>',
        '{top_asns_text}'      => '• <b>AS205100 — F3 Netze e.V.</b>: 18',
        '{asn_alert}'          => '⚠️ Distinto al de la última sesión (AS3352)',
        '{user_login}'         => 'admin',
        '{user_email}'         => 'admin@example.com',
        '{last_asn}'           => 'AS3352 — Telefonica de Espana SAU',
        '{active_asns}'        => 'AS3352 — Telefonica de Espana SAU',
        '{user_agent}'         => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ];
}

function bap_render_tg_templates_page() {
    $types       = bap_get_tg_template_types();
    $active_type = isset( $_GET['tpl_type'] ) ? sanitize_key( $_GET['tpl_type'] ) : 'single';
    if ( ! isset( $types[ $active_type ] ) ) {
        $active_type = 'single';
    }

    $notice   = '';
    $saved    = get_option( 'bap_tg_custom_templates', [] );
    if ( ! is_array( $saved ) ) {
        $saved = [];
    }
    $defaults = bap_get_tg_default_templates();

    if ( isset( $_POST['bap_save_tg_tpl'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_tg_builder_action' ) ) {
        $t_key         = sanitize_key( $_POST['bap_tg_tpl_type'] ?? $active_type );
        $clean_html    = bap_sanitize_tg_rich_html( wp_unslash( $_POST['bap_tg_tpl_html'] ?? '' ) );
        $saved[ $t_key ] = $clean_html;
        update_option( 'bap_tg_custom_templates', $saved );
        $active_type = $t_key;
        $notice      = '<div class="notice notice-success is-dismissible"><p><strong>¡Plantilla de Telegram guardada correctamente!</strong></p></div>';
    }

    if ( isset( $_POST['bap_reset_tg_tpl'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_tg_builder_action' ) ) {
        $t_key = sanitize_key( $_POST['bap_tg_tpl_type'] ?? $active_type );
        unset( $saved[ $t_key ] );
        update_option( 'bap_tg_custom_templates', $saved );
        $active_type = $t_key;
        $notice      = '<div class="notice notice-warning is-dismissible"><p>Plantilla restaurada al diseño original.</p></div>';
    }

    if ( isset( $_POST['bap_test_tg_tpl'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_tg_builder_action' ) ) {
        $t_key    = sanitize_key( $_POST['bap_tg_tpl_type'] ?? $active_type );
        $raw_html = wp_unslash( $_POST['bap_tg_tpl_html'] ?? '' );
        $rendered = bap_render_custom_tg_template( $t_key, bap_get_tg_preview_sample_vars(), $raw_html );
        $res      = bap_send_telegram_rich_article( $rendered['rich'], $rendered['fallback'] );
        $active_type = $t_key;
        $notice      = is_wp_error( $res )
            ? '<div class="notice notice-error is-dismissible"><p><strong>Error Telegram:</strong> ' . esc_html( $res->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success is-dismissible"><p><strong>¡Mensaje de prueba enviado a Telegram con este diseño!</strong></p></div>';
    }

    $current_html = ! empty( $saved[ $active_type ] ) ? $saved[ $active_type ] : $defaults[ $active_type ];

    wp_enqueue_script( 'bap-admin-tg-builder', plugin_dir_url( __FILE__ ) . 'admin-tg-builder.js', [], '1.0', true );
    wp_localize_script( 'bap-admin-tg-builder', 'bapTgBuilderData', [
        'samples' => bap_get_tg_preview_sample_vars(),
    ] );

    require plugin_dir_path( __FILE__ ) . 'admin-tg-builder-view.php';
}
