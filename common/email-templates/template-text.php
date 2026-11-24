<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$lines = [];
if ( $is_test ) {
    $lines[] = '*** CORREO DE PRUEBA DEL RESUMEN AGRUPADO ***';
    $lines[] = '';
}
$lines[] = sprintf(
    'Total bloqueados: %d | IPs únicas: %d | Eventos únicos: %d',
    $data['total_hits'],
    $data['unique_ips'],
    count( $data['grouped'] )
);
$lines[] = str_repeat( '-', 75 );

foreach ( $data['grouped'] as $g ) {
    $last_short = substr( $g['last_time'], 11 ) ?: $g['last_time'];
    $time_str   = ( $g['first_time'] !== $g['last_time'] && '' !== $g['last_time'] )
        ? "[{$g['first_time']} -> {$last_short}]"
        : "[{$g['first_time']}]";

    $lines[] = sprintf(
        '(x%d) %s IP: %s | País: %s | ASN: %s | Riesgo: %s | Motivo: %s',
        $g['count'],
        $time_str,
        $g['ip'],
        $g['country'],
        $g['asn'],
        $g['risk'],
        $g['reason']
    );
}

return implode( "\n", $lines );
