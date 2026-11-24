<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_init', function() {
    $is_all = ! empty( $_POST['bap_export_logs_csv'] );
    $is_asn = ! empty( $_POST['bap_export_asn_csv'] );

    if ( ! $is_all && ! $is_asn ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No tienes permisos para exportar registros.', 'Acceso denegado', [ 'response' => 403 ] );
    }
    check_admin_referer( 'bap_logs_action' );

    global $wpdb;
    $table    = bap_get_logs_table_name();
    $prefix   = $is_asn ? 'resumen-asn-' : 'ataques-bloqueados-';
    $filename = $prefix . wp_date( 'Y-m-d-His' ) . '.csv';

    bap_send_download_headers( $filename, 'text/csv' );

    $out = fopen( 'php://output', 'w' );
    fwrite( $out, "\xEF\xBB\xBF" );

    if ( $is_asn ) {
        fputcsv( $out, [ 'ASN / Proveedor', 'Total Intentos', 'IPs Únicas', 'Países', 'Último Intento' ], ';' );

        $rows = $wpdb->get_results(
            "SELECT
                IF(asn = '', 'N/D', asn) AS asn_label,
                COUNT(*) AS total_hits,
                COUNT(DISTINCT ip_address) AS unique_ips,
                GROUP_CONCAT(DISTINCT country_code ORDER BY country_code SEPARATOR ', ') AS countries,
                MAX(created_at) AS last_hit
             FROM `{$table}`
             GROUP BY asn_label
             ORDER BY total_hits DESC",
            ARRAY_A
        );

        foreach ( (array) $rows as $r ) {
            fputcsv(
                $out,
                [
                    $r['asn_label'],
                    $r['total_hits'],
                    $r['unique_ips'],
                    $r['countries'],
                    $r['last_hit'],
                ],
                ';'
            );
        }

        fclose( $out );
        exit;
    }

    fputcsv( $out, [ 'ID', 'Fecha y Hora', 'Dirección IP', 'País', 'ASN / Proveedor', 'Tipo', 'Motivo', 'URL Atacada' ], ';' );

    $batch  = 2000;
    $offset = 0;

    while ( true ) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, created_at, ip_address, country_code, asn, block_type, reason, request_uri
                 FROM `{$table}`
                 ORDER BY id DESC
                 LIMIT %d OFFSET %d",
                $batch,
                $offset
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            break;
        }

        foreach ( $rows as $r ) {
            fputcsv(
                $out,
                [
                    $r['id'],
                    $r['created_at'],
                    $r['ip_address'],
                    $r['country_code'],
                    $r['asn'],
                    $r['block_type'],
                    $r['reason'],
                    $r['request_uri'],
                ],
                ';'
            );
        }

        flush();
        $offset += $batch;
    }

    fclose( $out );
    exit;
});
