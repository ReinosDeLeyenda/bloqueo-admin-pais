<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_custom_email_templates() {
    $list = get_option( 'bap_custom_email_templates', [] );
    return is_array( $list ) ? $list : [];
}

function bap_prepare_digest_stats( array $queue ) {
    $grouped    = [];
    $by_country = [];
    $by_asn     = [];
    $by_uri     = [];
    $by_hour    = array_fill( 0, 24, 0 );
    $unique_ips = [];

    foreach ( $queue as $row ) {
        $ip      = (string) ( $row['ip'] ?? 'N/D' );
        $country = (string) ( $row['country'] ?? 'DESCONOCIDO' );
        $asn     = (string) ( $row['asn'] ?? 'N/D' );
        $risk    = (string) ( $row['risk'] ?? 'N/D' );
        $reason  = (string) ( $row['reason'] ?? 'Bloqueado' );
        $uri     = (string) ( $row['uri'] ?? '' );
        $t       = (string) ( $row['time'] ?? '' );

        $unique_ips[ $ip ]      = true;
        $by_country[ $country ] = ( $by_country[ $country ] ?? 0 ) + 1;
        if ( '' !== $asn && 'N/D' !== $asn && 'Sin base de datos ASN' !== $asn ) {
            $by_asn[ $asn ] = ( $by_asn[ $asn ] ?? 0 ) + 1;
        }
        if ( '' !== $uri ) {
            $by_uri[ $uri ] = ( $by_uri[ $uri ] ?? 0 ) + 1;
        }
        if ( strlen( $t ) >= 13 ) {
            $hr = (int) substr( $t, 11, 2 );
            if ( $hr >= 0 && $hr <= 23 ) {
                $by_hour[ $hr ]++;
            }
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
    arsort( $by_uri );

    return [
        'total_hits'    => count( $queue ),
        'unique_ips'    => count( $unique_ips ),
        'grouped'       => $grouped,
        'top_countries' => array_slice( $by_country, 0, 5, true ),
        'top_asns'      => array_slice( $by_asn, 0, 5, true ),
        'top_uris'      => array_slice( $by_uri, 0, 5, true ),
        'by_hour'       => $by_hour,
    ];
}

function bap_render_digest_by_template( array $queue, $is_test = false, $template = 'full' ) {
    $data      = bap_prepare_digest_stats( $queue );
    $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $stats_url = admin_url( 'admin.php?page=bap-admin-estadisticas' );

    if ( 0 === strpos( (string) $template, 'custom_' ) ) {
        $custom_id = substr( (string) $template, 7 );
        $customs   = bap_get_custom_email_templates();
        if ( isset( $customs[ $custom_id ] ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'email-templates/template-custom.php';
            $tpl_cfg = $customs[ $custom_id ];
            if ( 'text' === ( $tpl_cfg['format'] ?? 'html_dark' ) ) {
                return [
                    'body'    => bap_render_custom_template_text( $tpl_cfg, $data, $site_name, $is_test ),
                    'headers' => [],
                ];
            }
            return [
                'body'    => bap_render_custom_template_html( $tpl_cfg, $data, $site_name, $stats_url, $is_test ),
                'headers' => [ 'Content-Type: text/html; charset=UTF-8' ],
            ];
        }
        $template = 'full';
    }

    $allowed = [ 'full', 'top10', 'top5', 'text' ];
    if ( ! in_array( $template, $allowed, true ) ) {
        $template = 'full';
    }

    $tpl_file = plugin_dir_path( __FILE__ ) . 'email-templates/template-' . $template . '.php';
    if ( ! file_exists( $tpl_file ) ) {
        $tpl_file = plugin_dir_path( __FILE__ ) . 'email-templates/template-text.php';
    }

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
