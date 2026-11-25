<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_get_pending_security_alerts_breakdown() {
    $cache_key = 'bap_menu_alerts_counts';
    $cached    = get_transient( $cache_key );
    if ( false !== $cached && is_array( $cached ) ) {
        return $cached;
    }

    if ( ! function_exists( 'bap_get_db_status_state' ) ) {
        require_once dirname( __DIR__ ) . '/admindb/admin-db-cards.php';
    }

    $freq    = (string) get_option( 'bap_db_auto_update_freq', 'none' );
    $mmdb    = bap_get_mmdb_path();
    $asn     = bap_get_asn_mmdb_path();
    $c_upd   = get_option( 'bap_mmdb_last_updated', '' );
    if ( '' === $c_upd && file_exists( $mmdb ) ) {
        $c_upd = wp_date( 'Y-m-d H:i:s', filemtime( $mmdb ) );
    }
    $a_upd = get_option( 'bap_asn_mmdb_last_updated', '' );
    if ( '' === $a_upd && file_exists( $asn ) ) {
        $a_upd = wp_date( 'Y-m-d H:i:s', filemtime( $asn ) );
    }

    $dbs = [
        [ file_exists( $mmdb ), $c_upd ],
        [ file_exists( $asn ), $a_upd ],
        [ file_exists( bap_get_abuseipdb_path() ), (string) get_option( 'bap_abuseipdb_last_updated', '' ) ],
        [ file_exists( bap_get_tor_list_path() ), (string) get_option( 'bap_tor_last_updated', '' ) ],
        [ file_exists( bap_get_firehol_list_path() ), (string) get_option( 'bap_firehol_last_updated', '' ) ],
    ];

    $db_alerts = 0;
    foreach ( $dbs as $d ) {
        $st = bap_get_db_status_state( $d[0], $d[1], $freq );
        if ( 'missing' === $st || 'outdated' === $st ) {
            $db_alerts++;
        }
    }

    $audit_alerts = 0;
    $scan_alerts  = get_option( 'bap_last_auto_scan_alerts', [] );
    if ( is_array( $scan_alerts ) ) {
        $audit_alerts += count( $scan_alerts );
    }

    if ( function_exists( 'bap_verify_critical_config_hashes' ) ) {
        foreach ( bap_verify_critical_config_hashes() as $cfg_row ) {
            if ( ! empty( $cfg_row['changed'] ) ) {
                $audit_alerts++;
            }
        }
    }

    $res = [
        'total' => $db_alerts + $audit_alerts,
        'db'    => $db_alerts,
        'audit' => $audit_alerts,
    ];

    set_transient( $cache_key, $res, 5 * MINUTE_IN_SECONDS );
    return $res;
}

add_action( 'admin_menu', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $counts = bap_get_pending_security_alerts_breakdown();
    if ( $counts['total'] <= 0 ) {
        return;
    }

    global $menu, $submenu;

    $badge_tpl = ' <span class="update-plugins count-%1$d"><span class="plugin-count">%2$s</span></span>';

    foreach ( (array) $menu as $idx => $item ) {
        if ( isset( $item[2] ) && 'bap-admin-db' === $item[2] ) {
            $menu[ $idx ][0] .= sprintf( $badge_tpl, $counts['total'], number_format_i18n( $counts['total'] ) );
            break;
        }
    }

    if ( ! empty( $submenu['bap-admin-db'] ) ) {
        foreach ( $submenu['bap-admin-db'] as $s_idx => $s_item ) {
            if ( 'bap-admin-db' === ( $s_item[2] ?? '' ) && $counts['db'] > 0 ) {
                $submenu['bap-admin-db'][ $s_idx ][0] .= sprintf( $badge_tpl, $counts['db'], number_format_i18n( $counts['db'] ) );
            } elseif ( 'bap-auditoria' === ( $s_item[2] ?? '' ) && $counts['audit'] > 0 ) {
                $submenu['bap-admin-db'][ $s_idx ][0] .= sprintf( $badge_tpl, $counts['audit'], number_format_i18n( $counts['audit'] ) );
            }
        }
    }
}, 99 );

// Limpiar caché del badge al actualizar DBs, aprobar huellas SHA-256 o guardar ajustes de DB
foreach ( [
    'update_option_bap_mmdb_last_updated',
    'update_option_bap_asn_mmdb_last_updated',
    'update_option_bap_abuseipdb_last_updated',
    'update_option_bap_tor_last_updated',
    'update_option_bap_firehol_last_updated',
    'update_option_bap_db_auto_update_freq',
    'update_option_bap_last_auto_scan_alerts',
    'update_option_bap_critical_config_hashes',
] as $hook_name ) {
    add_action( $hook_name, fn() => delete_transient( 'bap_menu_alerts_counts' ) );
}