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