<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_execute_all_security_scans() {
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 180 );
    }

    $report  = [];
    $aud_url = admin_url( 'admin.php?page=bap-auditoria' );

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
        $GLOBALS['bap_all_scans_cve'] = $cve;
        $cnt      = count( $cve['findings'] ?? [] );
        $report[] = [
            'name'   => 'Vulnerabilidades Conocidas (CVE)',
            'status' => $cnt > 0 ? 'error' : 'ok',
            'detail' => $cnt > 0 ? "{$cnt} vulnerabilidad(es) CVE detectada(s)." : "Analizados {$cve['checked']} componentes sin CVEs conocidos.",
            'items'  => array_map( fn( $f ) => $f['component'] . ' v' . $f['version'] . ' (' . implode( ', ', $f['cves'] ) . ')', $cve['findings'] ?? [] ),
        ];
    }

    // 4. Malware y Backdoors
    if ( function_exists( 'bap_run_wp_content_malware_scan' ) ) {
        $mal      = bap_run_wp_content_malware_scan();
        $GLOBALS['bap_all_scans_mal'] = $mal;
        $cnt      = count( $mal['findings'] ?? [] );
        $report[] = [
            'name'   => 'Escáner de Malware y Uploads',
            'status' => $cnt > 0 ? 'error' : 'ok',
            'detail' => $cnt > 0 ? "{$cnt} archivo(s) sospechoso(s) detectado(s)." : "Analizados {$mal['scanned']} archivos sin firmas maliciosas.",
            'items'  => array_map( fn( $f ) => $f['type'] . ': ' . $f['file'] . ' [' . ( $f['date'] ?? 'N/D' ) . ']', $mal['findings'] ?? [] ),
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

    if ( function_exists( 'bap_scan_privileged_admins' ) ) {
        $adm      = bap_scan_privileged_admins();
        $report[] = [
            'name'   => 'Auditoría de Administradores',
            'status' => $adm['suspicious'] > 0 ? 'error' : 'ok',
            'detail' => $adm['suspicious'] > 0 ? "{$adm['suspicious']} cuenta(s) con privilegios sospechosos." : "{$adm['total']} administrador(es) verificado(s) sin anomalías.",
        ];
    }

    if ( function_exists( 'bap_scan_seo_spam_and_malicious_links' ) ) { $seo = bap_scan_seo_spam_and_malicious_links(); $cnt = count( $seo['findings'] ?? [] ); $report[] = [ 'name' => 'SEO Spam y Enlaces Maliciosos', 'status' => $cnt > 0 ? 'error' : 'ok', 'detail' => $cnt > 0 ? "{$cnt} enlace(s) oculto(s) o spam SEO detectado(s)." : "Revisados {$seo['posts_checked']} posts y {$seo['comments_checked']} comentarios limpios." ]; }

    if ( function_exists( 'bap_roles_verify_integrity' ) ) {
        $r_chk    = bap_roles_verify_integrity();
        $bad_r    = ( $r_chk['changed'] || ! empty( $r_chk['escalations'] ) );
        $report[] = [
            'name'   => 'Integridad de Roles y Capacidades',
            'status' => $bad_r ? 'error' : 'ok',
            'detail' => $bad_r ? 'Alteracion o escalada detectada en wp_user_roles.' : 'Roles y permisos de usuario verificados sin cambios.',
        ];
    }

    if ( function_exists( 'bap_dns_mon_verify' ) ) {
        $d_chk = bap_dns_mon_verify();
        if ( is_array( $d_chk ) ) {
            $report[] = [
                'name'   => 'Registros DNS del Dominio',
                'status' => ! empty( $d_chk['changed'] ) ? 'error' : 'ok',
                'detail' => ! empty( $d_chk['changed'] ) ? 'Cambios detectados en DNS (' . implode( ', ', array_keys( $d_chk['diffs'] ) ) . ').' : 'Registros NS, A, AAAA y MX verificados sin cambios.',
            ];
        }
    }

    if ( function_exists( 'bap_audit_privileged_users_weak_passwords' ) ) {
        $wp_res   = bap_audit_privileged_users_weak_passwords();
        $w_cnt    = count( $wp_res['weak'] ?? [] );
        $report[] = [
            'name'   => 'Contraseñas Debiles en Usuarios',
            'status' => $w_cnt > 0 ? 'error' : 'ok',
            'detail' => $w_cnt > 0 ? "{$w_cnt} cuenta(s) con clave debil o predecible." : "Verificadas {$wp_res['checked']} cuentas privilegiadas sin claves debiles.",
        ];
    }

    if ( function_exists( 'bap_get_scheduled_cron_events_list' ) ) {
        $evs      = bap_get_scheduled_cron_events_list();
        $susp     = count( array_filter( $evs, fn( $e ) => ! empty( $e['suspicious'] ) ) );
        $report[] = [
            'name'   => 'Tareas Programadas (WP-Cron)',
            'status' => $susp > 0 ? 'warning' : 'ok',
            'detail' => $susp > 0 ? "{$susp} tarea(s) cron huerfana(s) o sospechosa(s)." : count( $evs ) . ' tareas programadas verificadas.',
        ];
    }

    if ( function_exists( 'bap_db_schema_verify' ) ) {
        $sch      = bap_db_schema_verify();
        $report[] = [
            'name'   => 'Esquema de Tablas MySQL',
            'status' => ! empty( $sch['changed'] ) ? 'warning' : 'ok',
            'detail' => ! empty( $sch['changed'] ) ? 'Tablas nuevas (' . count( $sch['added'] ) . ') o eliminadas (' . count( $sch['removed'] ) . ').' : "{$sch['total']} tablas MySQL verificadas sin cambios.",
        ];
    }

    if ( function_exists( 'bap_content_guard_verify' ) ) {
        $cg       = bap_content_guard_verify();
        $report[] = [
            'name'   => 'Integridad de Páginas (Anti-Defacement)',
            'status' => ! empty( $cg['changed'] ) ? 'warning' : 'ok',
            'detail' => ! empty( $cg['changed'] ) ? 'Cambios detectados en ' . count( $cg['modified'] ) . ' página(s).' : "{$cg['checked']} páginas/entradas verificadas sin cambios.",
        ];
    }

    if ( function_exists( 'bap_scan_wp_root_orphan_files' ) ) {
        $rw       = bap_scan_wp_root_orphan_files();
        $cnt      = count( $rw['findings'] ?? [] );
        $report[] = [
            'name'   => 'Archivos Extra en Carpeta Raíz',
            'status' => $cnt > 0 ? 'warning' : 'ok',
            'detail' => $cnt > 0 ? "{$cnt} archivo(s) no oficial(es) en la raíz de WordPress." : "Revisados {$rw['checked']} archivos en raíz sin anomalías.",
        ];
    }

    if ( function_exists( 'bap_audit_wp_options_autoload' ) ) {
        $al       = bap_audit_wp_options_autoload();
        $report[] = [
            'name'   => 'Peso Autoload (wp_options)',
            'link'   => $aud_url . '#bap-box-autoload',
            'status' => $al['bloated'] ? 'warning' : 'ok',
            'detail' => "Total autoload: {$al['total_kb']} KB ({$al['total_rows']} opciones, {$al['expired_trans']} transients caducados).",
        ];
    }

    return $report;
}