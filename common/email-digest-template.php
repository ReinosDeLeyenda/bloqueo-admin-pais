<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_prepare_digest_stats( array $queue ) {
    $grouped    = [];
    $by_country = [];
    $by_asn     = [];
    $unique_ips = [];

    foreach ( $queue as $row ) {
        $ip      = (string) ( $row['ip'] ?? 'N/D' );
        $country = (string) ( $row['country'] ?? 'DESCONOCIDO' );
        $asn     = (string) ( $row['asn'] ?? 'N/D' );
        $risk    = (string) ( $row['risk'] ?? 'N/D' );
        $reason  = (string) ( $row['reason'] ?? 'Bloqueado' );
        $t       = (string) ( $row['time'] ?? '' );

        $unique_ips[ $ip ]      = true;
        $by_country[ $country ] = ( $by_country[ $country ] ?? 0 ) + 1;
        if ( '' !== $asn && 'N/D' !== $asn && 'Sin base de datos ASN' !== $asn ) {
            $by_asn[ $asn ] = ( $by_asn[ $asn ] ?? 0 ) + 1;
        }

        $key = $ip . '|' . $reason;
        if ( ! isset( $grouped[ $key ] ) ) {
            $grouped[ $key ] = [
                'count'      => 0,
                'first_time' => $t,
                'last_time'  => $t,
                'ip'         => $ip,
                'country'    => $country,
                'asn'        => $asn,
                'risk'       => $risk,
                'reason'     => $reason,
            ];
        }
        $grouped[ $key ]['count']++;
        if ( '' !== $t ) {
            if ( '' === $grouped[ $key ]['first_time'] || $t < $grouped[ $key ]['first_time'] ) {
                $grouped[ $key ]['first_time'] = $t;
            }
            if ( '' === $grouped[ $key ]['last_time'] || $t > $grouped[ $key ]['last_time'] ) {
                $grouped[ $key ]['last_time'] = $t;
            }
        }
    }

    uasort( $grouped, fn( $a, $b ) => $b['count'] <=> $a['count'] );
    arsort( $by_country );
    arsort( $by_asn );

    return [
        'total_hits'    => count( $queue ),
        'unique_ips'    => count( $unique_ips ),
        'grouped'       => $grouped,
        'top_countries' => array_slice( $by_country, 0, 5, true ),
        'top_asns'      => array_slice( $by_asn, 0, 5, true ),
    ];
}

function bap_render_digest_by_template( array $queue, $is_test = false, $template = 'full' ) {
    $allowed = [ 'full', 'top10', 'top5', 'text' ];
    if ( ! in_array( $template, $allowed, true ) ) {
        $template = 'full';
    }

    $tpl_file = plugin_dir_path( __FILE__ ) . 'email-templates/template-' . $template . '.php';
    if ( ! file_exists( $tpl_file ) ) {
        $tpl_file = plugin_dir_path( __FILE__ ) . 'email-templates/template-text.php';
    }

    $data      = bap_prepare_digest_stats( $queue );
    $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $stats_url = admin_url( 'admin.php?page=bap-admin-estadisticas' );

    if ( 'text' === $template ) {
        $body = include $tpl_file;
        return [ 'body' => (string) $body, 'headers' => [] ];
    }

    ob_start();
    include $tpl_file;
    return [
        'body'    => (string) ob_get_clean(),
        'headers' => [ 'Content-Type: text/html; charset=UTF-8' ],
    ];
}
