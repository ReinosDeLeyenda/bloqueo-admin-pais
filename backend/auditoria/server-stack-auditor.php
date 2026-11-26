<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_sys_get_dpkg_package_info( array $pkg_candidates ) {
    $found = [];
    if ( @is_readable( '/var/lib/dpkg/status' ) ) {
        $fh = @fopen( '/var/lib/dpkg/status', 'rb' );
        if ( $fh ) {
            $cur_pkg = ''; $cur_status = ''; $cur_ver = '';
            while ( ( $line = fgets( $fh ) ) !== false ) {
                $trim = trim( $line );
                if ( '' === $trim ) {
                    if ( '' !== $cur_pkg && in_array( $cur_pkg, $pkg_candidates, true ) && false !== strpos( $cur_status, 'installed' ) && '' !== $cur_ver ) {
                        $found[ $cur_pkg ] = $cur_ver;
                    }
                    $cur_pkg = ''; $cur_status = ''; $cur_ver = '';
                    continue;
                }
                if ( 0 === strpos( $trim, 'Package:' ) ) { $cur_pkg = trim( substr( $trim, 8 ) ); }
                elseif ( 0 === strpos( $trim, 'Status:' ) ) { $cur_status = trim( substr( $trim, 7 ) ); }
                elseif ( 0 === strpos( $trim, 'Version:' ) ) { $cur_ver = trim( substr( $trim, 8 ) ); }
            }
            fclose( $fh );
        }
    }
    foreach ( $pkg_candidates as $cand ) {
        $cl_path  = '/usr/share/doc/' . $cand . '/changelog.Debian.gz';
        $pkg_ver  = $found[ $cand ] ?? '';
        $rel_date = '';
        if ( function_exists( 'gzopen' ) && @is_readable( $cl_path ) ) {
            $gz = @gzopen( $cl_path, 'rb' );
            if ( $gz ) {
                $head = (string) @gzread( $gz, 2048 );
                @gzclose( $gz );
                if ( '' === $pkg_ver && preg_match( '/^' . preg_quote( $cand, '/' ) . '\s+\(([^)]+)\)/m', $head, $mv ) ) {
                    $pkg_ver = trim( $mv );
                }
                if ( preg_match( '/^ -- [^>]+>\s+([A-Za-z]{3},\s+\d{1,2}\s+[A-Za-z]{3}\s+\d{4}\s+\d{2}:\d{2}:\d{2}\s+[+-]\d{4})/m', $head, $md ) ) {
                    $ts = strtotime( (string) ( $md ?? '' ) );
                    if ( false !== $ts ) { $rel_date = wp_date( 'Y-m-d', $ts ); }
                }
            }
        }
        if ( '' !== $pkg_ver ) {
            return [ 'package' => $cand, 'version' => $pkg_ver, 'date' => $rel_date ];
        }
    }
    return null;
}

function bap_sys_detect_environment() {
    global $wpdb;
    $os_name = PHP_OS_FAMILY; $os_ver = ''; $os_slug = ''; $codename = '';
    if ( @is_readable( '/etc/os-release' ) ) {
        $raw_os = (string) @file_get_contents( '/etc/os-release' );
        if ( preg_match( '/^PRETTY_NAME="([^"]+)"/m', $raw_os, $m ) ) { $os_name = $m; }
        if ( preg_match( '/^ID=([a-zA-Z0-9_-]+)/m', $raw_os, $mId ) ) { $os_slug = strtolower( $mId ); }
        if ( preg_match( '/^VERSION_ID="([^"]+)"/m', $raw_os, $mV ) ) { $os_ver = $mV; }
        if ( preg_match( '/^VERSION_CODENAME=([a-z0-9_-]+)/m', $raw_os, $mC ) ) { $codename = strtolower( $mC ); }
    }
    $kernel  = function_exists( 'php_uname' ) ? php_uname( 'r' ) : '';
    $arch    = function_exists( 'php_uname' ) ? php_uname( 'm' ) : '';
    $srv_raw = (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' );
    $web_srv = 'Desconocido'; $web_ver = '';
    if ( preg_match( '/(nginx|apache|litespeed|caddy|iis)(?:\/([0-9]+\.[0-9]+(?:\.[0-9]+)?))?/i', $srv_raw, $mw ) ) {
        $web_srv = ucfirst( strtolower( $mw ) );
        $web_ver = $mw ?? '';
    } elseif ( '' !== $srv_raw ) { $web_srv = $srv_raw; }
    $db_raw  = (string) $wpdb->get_var( 'SELECT VERSION()' );
    $is_mar  = ( false !== stripos( $db_raw, 'mariadb' ) );
    $db_type = $is_mar ? 'MariaDB' : 'MySQL';
    $db_ver  = preg_match( '/^([0-9]+\.[0-9]+\.[0-9]+)/', $db_raw, $md ) ? $md : $wpdb->db_version();
    return [
        'os_name' => $os_name, 'os_slug' => $os_slug, 'os_ver' => $os_ver, 'codename' => $codename,
        'kernel' => $kernel, 'arch' => $arch, 'web_srv' => $web_srv, 'web_ver' => $web_ver,
        'php_ver' => PHP_VERSION, 'db_type' => $db_type, 'db_ver' => $db_ver,
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
    $eol      = $data['eol'] ?? false;
    $latest   = (string) ( $data['latest'] ?? '' );
    $is_eol   = is_string( $eol ) ? ( strtotime( $eol ) < time() ) : ( true === $eol );
    $outdated = ( '' !== $latest && version_compare( $version, $latest, '<' ) );
    return [ 'is_eol' => $is_eol, 'eol_date' => is_string( $eol ) ? $eol : '', 'latest' => $latest, 'outdated' => $outdated ];
}

function bap_sys_query_debian_tracker( $src_pkg, $codename, $installed_ver ) {
    if ( '' === $src_pkg || '' === $codename || '' === $installed_ver ) { return null; }
    $ckey   = 'bap_deb_sec_' . md5( $src_pkg . '_' . $codename );
    $cached = get_transient( $ckey );
    if ( false === $cached ) {
        $url = 'https://' . 'security-tracker.debian.org/tracker/source-package/' . rawurlencode( $src_pkg );
        $res = wp_remote_get( $url, [ 'timeout' => 12 ] );
        if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) { return null; }
        $html = (string) wp_remote_retrieve_body( $res );
        $open = [];
        $latest_deb = '';
        if ( preg_match( '/<td>\s*' . preg_quote( $codename, '/' ) . '(?:\s*\(security\))?\s*<\/td>\s*<td>([^<]+)<\/td>/i', $html, $mv ) ) {
            $latest_deb = trim( $mv );
        }
        if ( preg_match_all( '/<tr>\s*<td><a[^>]*>(CVE-\d{4}-\d+)<\/a><\/td>\s*<td>([^<]*)<\/td>\s*<td>\s*' . preg_quote( $codename, '/' ) . '\s*<\/td>\s*<td>([^<]*)<\/td>/is', $html, $mc, PREG_SET_ORDER ) ) {
            foreach ( $mc as $row ) {
                $st = strtolower( trim( $row ) );
                if ( 'open' === $st || 'vulnerable' === $st ) {
                    $open[] = [ 'id' => trim( $row ), 'summary' => trim( $row ) ?: 'Vulnerabilidad abierta en Debian ' . $codename ];
                }
            }
        }
        $cached = [ 'latest_deb' => $latest_deb, 'cves' => $open ];
        set_transient( $ckey, $cached, 12 * HOUR_IN_SECONDS );
    }
    $upd_avail = ( '' !== $cached['latest_deb'] && version_compare( $installed_ver, $cached['latest_deb'], '<' ) );
    return [ 'latest_deb' => $cached['latest_deb'], 'upd_avail' => $upd_avail, 'cves' => $cached['cves'] ];
}

function bap_run_server_stack_audit() {
    $env  = bap_sys_detect_environment();
    $code = ( 'debian' === $env['os_slug'] ) ? $env['codename'] : '';
    $rows = [];

    $os_map  = [ 'ubuntu' => 'ubuntu', 'debian' => 'debian', 'almalinux' => 'almalinux', 'rocky' => 'rocky-linux', 'centos' => 'centos' ];
    $os_prod = $os_map[ $env['os_slug'] ] ?? '';
    $rows[]  = [
        'component' => 'Sistema Operativo',
        'version'   => $env['os_name'] . ( '' !== $env['arch'] ? ' (' . $env['arch'] . ')' : '' ),
        'eol'       => ( '' !== $os_prod && '' !== $env['os_ver'] ) ? bap_sys_check_eol( $os_prod, $env['os_ver'] ) : null,
        'deb'       => null,
    ];

    $k_clean = preg_match( '/^([0-9]+\.[0-9]+\.[0-9]+)/', $env['kernel'], $mk ) ? $mk : '';
    $dp_k    = ( 'Linux' === PHP_OS_FAMILY && '' !== $env['kernel'] ) ? bap_sys_get_dpkg_package_info( [ 'linux-image-' . $env['kernel'], 'linux-image-amd64', 'linux-image-arm64' ] ) : null;
    $rows[]  = [
        'component' => 'Kernel del Sistema',
        'version'   => ( $env['kernel'] ?: 'No disponible' ) . ( is_array( $dp_k ) ? ' [Paquete: ' . $dp_k['version'] . ( '' !== $dp_k['date'] ? ' · ' . $dp_k['date'] : '' ) . ']' : '' ),
        'eol'       => ( 'Linux' === PHP_OS_FAMILY && '' !== $k_clean ) ? bap_sys_check_eol( 'linux', $k_clean ) : null,
        'deb'       => ( is_array( $dp_k ) && '' !== $code ) ? bap_sys_query_debian_tracker( 'linux', $code, $dp_k['version'] ) : null,
    ];

    $ws_low  = strtolower( $env['web_srv'] );
    $ws_prod = ( 'nginx' === $ws_low ) ? 'nginx' : ( ( 'apache' === $ws_low ) ? 'apache-http-server' : '' );
    $dp_ws   = ( 'nginx' === $ws_low ) ? bap_sys_get_dpkg_package_info( [ 'nginx-common', 'nginx', 'nginx-core', 'nginx-full', 'nginx-extras', 'nginx-light' ] )
             : ( ( 'apache' === $ws_low ) ? bap_sys_get_dpkg_package_info( [ 'apache2' ] ) : null );
    $deb_ws  = ( is_array( $dp_ws ) && '' !== $code ) ? bap_sys_query_debian_tracker( 'nginx' === $ws_low ? 'nginx' : 'apache2', $code, $dp_ws['version'] ) : null;
    $eol_ws  = ( '' !== $ws_prod && '' !== $env['web_ver'] ) ? bap_sys_check_eol( $ws_prod, $env['web_ver'] ) : null;
    if ( is_array( $deb_ws ) && is_array( $eol_ws ) ) { $eol_ws['is_eol'] = false; }
    $rows[]  = [
        'component' => 'Servidor Web',
        'version'   => $env['web_srv'] . ( '' !== $env['web_ver'] ? ' v' . $env['web_ver'] : ' (version oculta)' ) . ( is_array( $dp_ws ) ? ' [Paquete: ' . $dp_ws['version'] . ( '' !== $dp_ws['date'] ? ' · ' . $dp_ws['date'] : '' ) . ']' : '' ),
        'eol'       => $eol_ws,
        'deb'       => $deb_ws,
    ];

    $maj_min = implode( '.', array_slice( explode( '.', $env['php_ver'] ), 0, 2 ) );
    $dp_php  = bap_sys_get_dpkg_package_info( [ 'php' . $maj_min . '-fpm', 'php' . $maj_min . '-cli', 'php-fpm', 'php' ] );
    $rows[]  = [
        'component' => 'Motor PHP',
        'version'   => 'PHP ' . $env['php_ver'] . ( is_array( $dp_php ) ? ' [Paquete: ' . $dp_php['version'] . ( '' !== $dp_php['date'] ? ' · ' . $dp_php['date'] : '' ) . ']' : '' ),
        'eol'       => bap_sys_check_eol( 'php', $env['php_ver'] ),
        'deb'       => ( is_array( $dp_php ) && '' !== $code ) ? bap_sys_query_debian_tracker( 'php' . $maj_min, $code, $dp_php['version'] ) : null,
    ];

    $db_slug = strtolower( $env['db_type'] );
    $dp_db   = bap_sys_get_dpkg_package_info( ( 'MariaDB' === $env['db_type'] ) ? [ 'mariadb-server', 'mariadb-server-core' ] : [ 'mysql-server', 'mysql-server-8.0', 'mysql-server-8.4' ] );
    $rows[]  = [
        'component' => 'Base de Datos',
        'version'   => $env['db_type'] . ' ' . $env['db_ver'] . ( is_array( $dp_db ) ? ' [Paquete: ' . $dp_db['version'] . ( '' !== $dp_db['date'] ? ' · ' . $dp_db['date'] : '' ) . ']' : '' ),
        'eol'       => bap_sys_check_eol( $db_slug, $env['db_ver'] ),
        'deb'       => ( is_array( $dp_db ) && '' !== $code ) ? bap_sys_query_debian_tracker( $db_slug, $code, $dp_db['version'] ) : null,
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
    echo '<h2 style="margin-top:0;">Auditoria de Stack del Servidor (SO, Kernel, Web, PHP y MySQL/MariaDB)</h2>';
    echo '<p class="description">Comprueba el ciclo de soporte oficial (<code>endoflife.date</code>), paquetes Debian/Ubuntu instalados y CVEs abiertos reales (<code>security-tracker.debian.org</code>).</p>';
    echo '<form method="post" style="margin:14px 0;">';
    wp_nonce_field( 'bap_srv_stack_action', 'bap_srv_stack_nonce' );
    echo '<button type="submit" name="bap_run_server_stack_audit" class="button button-primary">Analizar versiones y CVEs del servidor</button></form>';
    if ( is_array( $audit ) ) {
        echo '<table class="widefat striped" style="margin-top:10px;"><thead><tr><th style="width:165px;">Componente</th><th style="width:230px;">Version detectada</th><th style="width:160px;">Estado / Soporte</th><th>Actualizaciones y CVEs abiertos</th></tr></thead><tbody>';
        foreach ( $audit as $r ) {
            $eol     = $r['eol'];
            $deb     = $r['deb'];
            $cves    = is_array( $deb ) ? ( $deb['cves'] ?? [] ) : [];
            $is_eol  = ( is_array( $eol ) && ! empty( $eol['is_eol'] ) );
            $has_cve = ! empty( $cves );
            $outd    = ( is_array( $deb ) && ! empty( $deb['upd_avail'] ) ) || ( ! is_array( $deb ) && is_array( $eol ) && ! empty( $eol['outdated'] ) );
            $badge   = ( $is_eol || $has_cve ) ? bap_render_status_badge( $is_eol ? 'SIN SOPORTE (EOL)' : 'CVEs ABIERTOS', '#d63638' )
                     : ( $outd ? bap_render_status_badge( 'ACTUALIZABLE', '#d97706' ) : bap_render_status_badge( 'AL DIA / SOPORTADO', '#00a32a' ) );
            echo '<tr><td><strong>' . esc_html( $r['component'] ) . '</strong></td>';
            echo '<td><code>' . esc_html( $r['version'] ) . '</code></td>';
            echo '<td>' . $badge . '</td><td>';
            if ( is_array( $deb ) ) {
                if ( ! empty( $deb['upd_avail'] ) ) {
                    echo '<div style="color:#b45309;font-weight:600;">Actualizacion Debian disponible: <code>' . esc_html( $deb['latest_deb'] ) . '</code></div>';
                } elseif ( '' !== $deb['latest_deb'] ) {
                    echo '<div><small style="color:#008a20;">Paquete Debian al dia (<code>' . esc_html( $deb['latest_deb'] ) . '</code>)</small></div>';
                }
            }
            if ( is_array( $eol ) ) {
                if ( $is_eol ) {
                    echo '<div style="color:#d63638;font-weight:600;">Rama upstream sin soporte desde ' . esc_html( $eol['eol_date'] ) . '.</div>';
                } elseif ( ! is_array( $deb ) && ! empty( $eol['outdated'] ) ) {
                    echo '<div>Ultima version de esta rama: <code>' . esc_html( $eol['latest'] ) . '</code></div>';
                } elseif ( '' !== $eol['eol_date'] ) {
                    echo '<small style="color:#008a20;">Soporte oficial hasta <code>' . esc_html( $eol['eol_date'] ) . '</code></small>';
                }
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