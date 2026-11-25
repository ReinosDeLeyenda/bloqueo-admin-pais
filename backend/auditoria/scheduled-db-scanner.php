<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_scan_database_injections() {
    global $wpdb;
    $findings = [];

    $patterns = [
        'Script / Eval ofuscado'     => '/<(?:script|iframe)\b[^>]*(?:eval\(|atob\(|fromCharCode|display\s*:\s*none|visibility\s*:\s*hidden)/i',
        'Redirección JS oculta'      => '/(?:window\.location|document\.location)\s*=\s*[\'"]https?:\/\//i',
        'PHP inyectado en contenido' => '/<\?php\b/i',
    ];

    $posts = $wpdb->get_results(
        "SELECT ID, post_title, post_type, post_content FROM `{$wpdb->posts}`
         WHERE post_status IN ('publish', 'draft', 'private')
         AND (post_content LIKE '%<script%' OR post_content LIKE '%<iframe%' OR post_content LIKE '%<?php%')
         LIMIT 500"
    );

    foreach ( (array) $posts as $p ) {
        foreach ( $patterns as $label => $regex ) {
            if ( preg_match( $regex, (string) $p->post_content ) ) {
                $findings[] = [
                    'source' => 'Post/Página #' . $p->ID . ' (' . $p->post_type . ')',
                    'target' => $p->post_title ?: '(Sin título)',
                    'type'   => $label,
                ];
                break;
            }
        }
    }

    $options = $wpdb->get_results(
        "SELECT option_name, option_value FROM `{$wpdb->options}`
         WHERE option_name NOT LIKE 'bap\_%'
         AND option_name NOT LIKE '\_transient\_%'
         AND (option_value LIKE '%<script%' OR option_value LIKE '%eval(base64_decode%' OR option_value LIKE '%<iframe%')
         LIMIT 300"
    );

    foreach ( (array) $options as $opt ) {
        foreach ( $patterns as $label => $regex ) {
            if ( preg_match( $regex, (string) $opt->option_value ) ) {
                $findings[] = [
                    'source' => 'Tabla wp_options',
                    'target' => $opt->option_name,
                    'type'   => $label,
                ];
                break;
            }
        }
    }

    return $findings;
}

function bap_run_scheduled_security_audit() {
    $alerts = [];

    if ( function_exists( 'bap_run_cve_vulnerability_scan' ) ) { $cve_res = bap_run_cve_vulnerability_scan( true ); if ( ! empty( $cve_res['findings'] ) ) { $alerts[] = 'Vulnerabilidades CVE: ' . count( $cve_res['findings'] ) . ' fallo(s) detectado(s).'; } }
    if ( function_exists( 'bap_verify_custom_integrity_baseline' ) && is_array( $cb = bap_verify_custom_integrity_baseline() ) ) { $cdiff = count( $cb['modified'] ) + count( $cb['added'] ) + count( $cb['deleted'] ); if ( $cdiff > 0 ) { $alerts[] = 'Temas/Plugins Privados: ' . $cdiff . ' archivo(s) alterado(s) respecto a la huella SHA-256.'; } }
    if ( function_exists( 'bap_run_core_checksum_scan' ) ) {
        $core = bap_run_core_checksum_scan( true );
        if ( is_array( $core ) ) {
            $issues = count( $core['modified'] ) + count( $core['missing'] );
            if ( $issues > 0 ) {
                $alerts[] = "Integridad: {$issues} archivo(s) oficial(es) modificado(s) o faltante(s).";
            }
        }
    }

    if ( function_exists( 'bap_run_wp_content_malware_scan' ) ) {
        $mal = bap_run_wp_content_malware_scan();
        if ( ! empty( $mal['findings'] ) ) {
            $alerts[] = 'Malware: ' . count( $mal['findings'] ) . ' archivo(s) sospechoso(s) detectado(s) en disco.';
        }
    }

    $db_hits = bap_scan_database_injections();
    if ( ! empty( $db_hits ) ) {
        $alerts[] = 'Base de Datos: ' . count( $db_hits ) . ' posible(s) inyección(es) en posts u opciones.';
    }

    if ( function_exists( 'bap_check_site_ssl_certificate' ) ) {
        $ssl = bap_check_site_ssl_certificate();
        if ( is_array( $ssl ) && $ssl['days_left'] <= 15 ) {
            $alerts[] = ( $ssl['days_left'] < 0 )
                ? 'SSL CADUCADO: El certificado HTTPS ha expirado.'
                : "SSL próximo a caducar: Quedan solo {$ssl['days_left']} día(s) ({$ssl['valid_to']}).";
        }
    }

    if ( function_exists( 'bap_verify_critical_config_hashes' ) ) {
        foreach ( bap_verify_critical_config_hashes() as $cfg_item ) {
            if ( ! empty( $cfg_item['changed'] ) ) {
                $alerts[] = "Archivo crítico alterado: {$cfg_item['label']} ha cambiado respecto a su huella SHA-256.";
            }
        }
    }

    update_option( 'bap_last_auto_scan_time', current_time( 'mysql' ), false );
    update_option( 'bap_last_auto_scan_alerts', $alerts, false );

    if ( empty( $alerts ) ) {
        return;
    }

    $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $subject = "[{$site}] ALERTA: Anomalías detectadas en el escaneo automático";
    $body    = "El escaneo programado de seguridad en {$site} ha detectado los siguientes problemas:\n\n• "
             . implode( "\n• ", $alerts )
             . "\n\nRevisa el panel: " . admin_url( 'admin.php?page=bap-auditoria' );

    $recipients = function_exists( 'bap_get_notify_recipients' ) ? bap_get_notify_recipients() : [ get_option( 'admin_email' ) ];
    if ( function_exists( 'bap_send_mail' ) ) {
        bap_send_mail( $recipients, $subject, $body );
    } else {
        wp_mail( $recipients, $subject, $body );
    }

    if ( function_exists( 'bap_send_telegram_rich_article' ) ) {
        $e    = fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        $list = '<ul><li>' . implode( '</li><li>', array_map( $e, $alerts ) ) . '</li></ul>';
        $rich = "<h2>🚨 Alerta de Escaneo Automático — {$e( $site )}</h2>{$list}";
        bap_send_telegram_rich_article( $rich, strip_tags( $rich ) );
    }
}
add_action( 'bap_scheduled_security_scan_event', 'bap_run_scheduled_security_audit' );

function bap_reschedule_security_scan_cron( $freq ) {
    wp_clear_scheduled_hook( 'bap_scheduled_security_scan_event' );
    $map = [
        'daily'  => [ 'daily', DAY_IN_SECONDS ],
        'weekly' => [ 'weekly', WEEK_IN_SECONDS ],
    ];
    if ( isset( $map[ $freq ] ) ) {
        wp_schedule_event( time() + $map[ $freq ][1], $map[ $freq ][0], 'bap_scheduled_security_scan_event' );
    }
}