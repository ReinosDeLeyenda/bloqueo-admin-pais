<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_sys_detect_environment() {
    global $wpdb;
    $os_name = PHP_OS_FAMILY;
    $os_ver  = '';
    $os_slug = '';
    if ( @is_readable( '/etc/os-release' ) ) {
        $raw_os = (string) @file_get_contents( '/etc/os-release' );
        if ( preg_match( '/^PRETTY_NAME="([^"]+)"/m', $raw_os, $m ) ) { $os_name = $m; }
        if ( preg_match( '/^ID=([a-zA-Z0-9_-]+)/m', $raw_os, $mId ) ) { $os_slug = strtolower( $mId ); }
        if ( preg_match( '/^VERSION_ID="([^"]+)"/m', $raw_os, $mV ) ) { $os_ver = $mV; }
    }
    $kernel  = function_exists( 'php_uname' ) ? php_uname( 'r' ) : '';
    $arch    = function_exists( 'php_uname' ) ? php_uname( 'm' ) : '';

    $srv_raw = (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' );
    $web_srv = 'Desconocido'; $web_ver = '';
    if ( preg_match( '/(nginx|apache|litespeed|caddy|iis)(?:\/([0-9]+\.[0-9]+(?:\.[0-9]+)?))?/i', $srv_raw, $mw ) ) {
        $web_srv = ucfirst( strtolower( $mw ) );
        $web_ver = $mw ?? '';
    } elseif ( '' !== $srv_raw ) {
        $web_srv = $srv_raw;
    }

    $db_raw  = (string) $wpdb->get_var( 'SELECT VERSION()' );
    $is_mar  = ( false !== stripos( $db_raw, 'mariadb' ) );
    $db_type = $is_mar ? 'MariaDB' : 'MySQL';
    $db_ver  = preg_match( '/^([0-9]+\.[0-9]+\.[0-9]+)/', $db_raw, $md ) ? $md : $wpdb->db_version();

    return [
        'os_name'  => $os_name,
        'os_slug'  => $os_slug,
        'os_ver'   => $os_ver,
        'kernel'   => $kernel,
        'arch'     => $arch,
        'web_srv'  => $web_srv,
        'web_ver'  => $web_ver,
        'php_ver'  => PHP_VERSION,
        'db_type'  => $db_type,
        'db_ver'   => $db_ver,
    ];
}

function bap_sys_check_eol( $product, $version ) {
    if ( '' === $product || '' === $version ) { return null; }
    $parts = explode( '.', $version );
    $cycle = ( count( $parts ) >= 2 ) ? $parts . '.' . $parts : $parts;
    if ( in_array( $product, [ 'ubuntu', 'debian', 'almalinux', 'rocky-linux', 'centos' ], true ) ) {
        $cycle = ( 'ubuntu' === $product && count( $parts ) >= 2 ) ? $parts . '.' . $parts : $parts;
    }
    $ckey = 'bap_eol_' . md5( $product . '_' . $cycle );
    $data = get_transient( $ckey );
    if ( false === $data ) {
        $url = 'https://' . 'endoflife.date/api/' . rawurlencode( $product ) . '/' . rawurlencode( $cycle ) . '.json';
        $res = wp_remote_get( $url, [ 'timeout' => 8, 'headers' => [ 'Accept' => 'application/json' ] ] );
        if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) { return null; }
        $data = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! is_array( $data ) ) { return null; }
        set_transient( $ckey, $data, DAY_IN_SECONDS );
    }
    $eol    = $data['eol'] ?? false;
    $latest = (string) ( $data['latest'] ?? '' );
    $is_eol = is_string( $eol ) ? ( strtotime( $eol ) < time() ) : ( true === $eol );
    $outdated = ( '' !== $latest && version_compare( $version, $latest, '<' ) );
    return [ 'is_eol' => $is_eol, 'eol_date' => is_string( $eol ) ? $eol : '', 'latest' => $latest, 'outdated' => $outdated ];
}

function bap_sys_query_osv_cves( $package, $version ) {
    if ( '' === $package || '' === $version ) { return []; }
    $ckey   = 'bap_osv_' . md5( $package . '_' . $version );
    $cached = get_transient( $ckey );
    if ( false !== $cached && is_array( $cached ) ) { return $cached; }
    $url = 'https://' . 'api.osv.dev/v1/query';
    $res = wp_remote_post( $url, [
        'timeout' => 10,
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [ 'version' => $version, 'package' => [ 'name' => $package ] ] ),
    ] );
    if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) { return []; }
    $body  = json_decode( wp_remote_retrieve_body( $res ), true );
    $vulns = [];
    foreach ( array_slice( (array) ( $body['vulns'] ?? [] ), 0, 8 ) as $v ) {
        $aliases = (array) ( $v['aliases'] ?? [] );
        $cve     = '';
        foreach ( $aliases as $al ) { if ( 0 === stripos( (string) $al, 'CVE-' ) ) { $cve = (string) $al; break; } }
        $vulns[] = [
            'id'      => $cve ?: (string) ( $v['id'] ?? 'N/D' ),
            'summary' => mb_substr( (string) ( $v['summary'] ?? 'Vulnerabilidad conocida' ), 0, 140 ),
        ];
    }
    set_transient( $ckey, $vulns, 12 * HOUR_IN_SECONDS );
    return $vulns;
}

function bap_run_server_stack_audit() {
    $env  = bap_sys_detect_environment();
    $rows = [];

    // 1. Sistema Operativo
    $os_map  = [ 'ubuntu' => 'ubuntu', 'debian' => 'debian', 'almalinux' => 'almalinux', 'rocky' => 'rocky-linux', 'centos' => 'centos' ];
    $os_prod = $os_map[ $env['os_slug'] ] ?? '';
    $os_eol  = ( '' !== $os_prod && '' !== $env['os_ver'] ) ? bap_sys_check_eol( $os_prod, $env['os_ver'] ) : null;
    $rows[]  = [
        'component' => 'Sistema Operativo',
        'version'   => $env['os_name'] . ( '' !== $env['arch'] ? ' (' . $env['arch'] . ')' : '' ),
        'eol'       => $os_eol,
        'cves'      => [],
    ];

    // 2. Kernel Linux
    $k_clean = preg_match( '/^([0-9]+\.[0-9]+\.[0-9]+)/', $env['kernel'], $mk ) ? $mk[1] : '';
    $k_eol   = ( 'Linux' === PHP_OS_FAMILY && '' !== $k_clean ) ? bap_sys_check_eol( 'linux', $k_clean ) : null;
    $k_cves  = ( 'Linux' === PHP_OS_FAMILY && '' !== $k_clean ) ? bap_sys_query_osv_cves( 'Linux', $k_clean ) : [];
    $rows[]  = [
        'component' => 'Kernel del Sistema',
        'version'   => $env['kernel'] ?: 'No disponible',
        'eol'       => $k_eol,
        'cves'      => $k_cves,
    ];

    // 3. Servidor Web
    $ws_low  = strtolower( $env['web_srv'] );
    $ws_prod = ( 'nginx' === $ws_low ) ? 'nginx' : ( ( 'apache' === $ws_low ) ? 'apache-http-server' : '' );
    $ws_pkg  = ( 'nginx' === $ws_low ) ? 'nginx' : ( ( 'apache' === $ws_low ) ? 'httpd' : '' );
    $ws_eol  = ( '' !== $ws_prod && '' !== $env['web_ver'] ) ? bap_sys_check_eol( $ws_prod, $env['web_ver'] ) : null;
    $ws_cves = ( '' !== $ws_pkg && '' !== $env['web_ver'] ) ? bap_sys_query_osv_cves( $ws_pkg, $env['web_ver'] ) : [];
    $rows[]  = [
        'component' => 'Servidor Web',
        'version'   => $env['web_srv'] . ( '' !== $env['web_ver'] ? ' v' . $env['web_ver'] : ' (versión oculta por seguridad)' ),
        'eol'       => $ws_eol,
        'cves'      => $ws_cves,
    ];

    // 4. PHP
    $rows[] = [
        'component' => 'Motor PHP',
        'version'   => 'PHP ' . $env['php_ver'],
        'eol'       => bap_sys_check_eol( 'php', $env['php_ver'] ),
        'cves'      => bap_sys_query_osv_cves( 'php', $env['php_ver'] ),
    ];

    // 5. Base de Datos
    $db_slug = strtolower( $env['db_type'] );
    $rows[]  = [
        'component' => 'Base de Datos',
        'version'   => $env['db_type'] . ' ' . $env['db_ver'],
        'eol'       => bap_sys_check_eol( $db_slug, $env['db_ver'] ),
        'cves'      => bap_sys_query_osv_cves( $db_slug, $env['db_ver'] ),
    ];

    return $rows;
}

function bap_render_server_stack_auditor_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $audit = null;
    if ( isset( $_POST['bap_run_server_stack_audit'] ) && check_admin_referer( 'bap_srv_stack_action', 'bap_srv_stack_nonce' ) ) {
        $audit = bap_run_server_stack_audit();
    }
    echo '<div id="bap-box-srv-stack" class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">';
    echo '<h2 style="margin-top:0;">Auditoría de Stack del Servidor (SO, Kernel, Web, PHP y MySQL/MariaDB)</h2>';
    echo '<p class="description">Comprueba el ciclo de soporte oficial (<code>endoflife.date</code>), actualizaciones disponibles y vulnerabilidades CVE conocidas (<code>OSV.dev</code>) de la infraestructura del servidor.</p>';
    echo '<form method="post" style="margin:14px 0;">';
    wp_nonce_field( 'bap_srv_stack_action', 'bap_srv_stack_nonce' );
    echo '<button type="submit" name="bap_run_server_stack_audit" class="button button-primary">Analizar versiones y CVEs del servidor</button></form>';
    if ( is_array( $audit ) ) {
        echo '<table class="widefat striped" style="margin-top:10px;"><thead><tr><th style="width:165px;">Componente</th><th style="width:210px;">Versión detectada</th><th style="width:160px;">Estado / Soporte</th><th>Actualizaciones y CVEs conocidos</th></tr></thead><tbody>';
        foreach ( $audit as $r ) {
            $eol     = $r['eol'];
            $cves    = $r['cves'];
            $is_eol  = ( is_array( $eol ) && ! empty( $eol['is_eol'] ) );
            $has_cve = ! empty( $cves );
            $outd    = ( is_array( $eol ) && ! empty( $eol['outdated'] ) );
            $badge   = ( $is_eol || $has_cve ) ? bap_render_status_badge( $is_eol ? 'SIN SOPORTE (EOL)' : 'CVEs DETECTADOS', '#d63638' )
                     : ( $outd ? bap_render_status_badge( 'ACTUALIZABLE', '#d97706' ) : bap_render_status_badge( 'AL DÍA / SOPORTADO', '#00a32a' ) );
            echo '<tr><td><strong>' . esc_html( $r['component'] ) . '</strong></td>';
            echo '<td><code>' . esc_html( $r['version'] ) . '</code></td>';
            echo '<td>' . $badge . '</td><td>';
            if ( is_array( $eol ) ) {
                if ( $is_eol ) {
                    echo '<div style="color:#d63638;font-weight:600;">Rama sin parches de seguridad desde ' . esc_html( $eol['eol_date'] ) . '.</div>';
                } elseif ( $outd ) {
                    echo '<div>Última versión de esta rama: <code>' . esc_html( $eol['latest'] ) . '</code></div>';
                } elseif ( '' !== $eol['eol_date'] ) {
                    echo '<small style="color:#008a20;">Soporte activo hasta <code>' . esc_html( $eol['eol_date'] ) . '</code></small>';
                }
            } else {
                echo '<small style="color:#646970;">Sin datos de ciclo de vida para esta cadena de versión.</small>';
            }
            if ( $has_cve ) {
                echo '<ul style="margin:6px 0 0 16px;list-style:disc;color:#b91c1c;font-size:12px;">';
                foreach ( $cves as $cv ) {
                    echo '<li><strong>' . esc_html( $cv['id'] ) . ':</strong> ' . esc_html( $cv['summary'] ) . '</li>';
                }
                echo '</ul>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}