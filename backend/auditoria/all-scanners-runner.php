<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_execute_all_security_scans() {
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 180 );
    }

    $report = [];

    // 1. Integridad Core y Plugins WP.org
    if ( function_exists( 'bap_run_core_checksum_scan' ) ) {
        $core = bap_run_core_checksum_scan( true );
        if ( is_wp_error( $core ) ) {
            $report[] = [ 'name' => 'Integridad Core + Plugins WP.org', 'status' => 'warning', 'detail' => $core->get_error_message() ];
        } else {
            $issues   = count( $core['modified'] ) + count( $core['missing'] ) + count( $core['extra'] );
            $report[] = [
                'name'   => 'Integridad Core + Plugins WP.org',
                'status' => $issues > 0 ? 'error' : 'ok',
                'detail' => $issues > 0 ? "{$issues} anomalía(s) detectada(s) en archivos oficiales." : "Verificados {$core['checked']} archivos oficiales sin cambios.",
            ];
        }
    }

    // 2. Integridad Temas y Plugins Privados (SHA-256)
    if ( function_exists( 'bap_verify_custom_integrity_baseline' ) ) {
        $base = bap_verify_custom_integrity_baseline();
        if ( null === $base ) {
            $report[] = [ 'name' => 'Integridad Temas/Plugins Privados', 'status' => 'warning', 'detail' => 'Sin huella base SHA-256 creada todavía.' ];
        } else {
            $diff     = count( $base['modified'] ) + count( $base['added'] ) + count( $base['deleted'] );
            $report[] = [
                'name'   => 'Integridad Temas/Plugins Privados',
                'status' => $diff > 0 ? 'error' : 'ok',
                'detail' => $diff > 0 ? "{$diff} archivo(s) modificado(s), añadido(s) o eliminado(s)." : "Verificados {$base['checked']} archivos sin cambios.",
            ];
        }
    }

    // 3. Vulnerabilidades CVE
    if ( function_exists( 'bap_run_cve_vulnerability_scan' ) ) {
        $cve      = bap_run_cve_vulnerability_scan( true );
        $cnt      = count( $cve['findings'] ?? [] );
        $report[] = [
            'name'   => 'Vulnerabilidades Conocidas (CVE)',
            'status' => $cnt > 0 ? 'error' : 'ok',
            'detail' => $cnt > 0 ? "{$cnt} vulnerabilidad(es) CVE detectada(s)." : "Analizados {$cve['checked']} componentes sin CVEs conocidos.",
        ];
    }

    // 4. Malware y Backdoors
    if ( function_exists( 'bap_run_wp_content_malware_scan' ) ) {
        $mal      = bap_run_wp_content_malware_scan();
        $cnt      = count( $mal['findings'] ?? [] );
        $report[] = [
            'name'   => 'Escáner de Malware y Uploads',
            'status' => $cnt > 0 ? 'error' : 'ok',
            'detail' => $cnt > 0 ? "{$cnt} archivo(s) sospechoso(s) detectado(s)." : "Analizados {$mal['scanned']} archivos sin firmas maliciosas.",
        ];
    }

    // 5. Plugins Abandonados
    if ( function_exists( 'bap_scan_abandoned_plugins' ) ) {
        $ab       = bap_scan_abandoned_plugins();
        $cnt      = count( $ab['findings'] ?? [] );
        $report[] = [
            'name'   => 'Plugins Abandonados / Retirados',
            'status' => $cnt > 0 ? 'warning' : 'ok',
            'detail' => $cnt > 0 ? "{$cnt} plugin(s) antiguo(s) (+1 año) o cerrado(s)." : "Verificados {$ab['checked']} plugins oficiales al día.",
        ];
    }

    // 6. Inyecciones en Base de Datos
    if ( function_exists( 'bap_scan_database_injections' ) ) {
        $db       = bap_scan_database_injections();
        $cnt      = count( $db );
        $report[] = [
            'name'   => 'Inyecciones en Base de Datos',
            'status' => $cnt > 0 ? 'error' : 'ok',
            'detail' => $cnt > 0 ? "{$cnt} posible(s) script(s) o inyección en posts/opciones." : 'Base de datos limpia (sin scripts sospechosos).',
        ];
    }

    // 7. Permisos de Archivos Críticos
    if ( function_exists( 'bap_audit_critical_file_permissions' ) ) {
        $perms    = bap_audit_critical_file_permissions();
        $bad      = array_filter( $perms, fn( $r ) => in_array( $r['status'], [ 'critical', 'warning' ], true ) );
        $cnt      = count( $bad );
        $report[] = [
            'name'   => 'Permisos de Archivos Críticos',
            'status' => $cnt > 0 ? 'warning' : 'ok',
            'detail' => $cnt > 0 ? "{$cnt} archivo(s) o carpeta(s) con permisos abiertos." : 'Permisos de archivos y carpetas verificados.',
        ];
    }

    return $report;
}