<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$ip2loc_base    = 'https://www.' . '://ip2location.com';
$abuse_base     = 'https://www.' . '://abuseipdb.com';
$stats_page_url = admin_url( 'admin.php?page=bap-admin-estadisticas' );
?>
<div style="overflow-x:auto;">
<table id="bap-logs-table" class="widefat striped" style="table-layout:auto;width:100%;">
    <thead>
        <tr>
            <th data-col="0" data-type="text" style="width:95px;cursor:pointer;white-space:nowrap;">Fecha &#8597;</th>
            <th data-col="1" data-type="num" style="width:72px;cursor:pointer;white-space:nowrap;">Intentos &#8597;</th>
            <th data-col="2" data-type="ip" style="width:125px;cursor:pointer;white-space:nowrap;">Dirección IP &#8597;</th>
            <th data-col="3" data-type="num" style="width:82px;cursor:pointer;white-space:nowrap;">AbuseIPDB &#8597;</th>
            <th data-col="4" data-type="text" style="width:58px;cursor:pointer;white-space:nowrap;">País &#8597;</th>
            <th data-col="5" data-type="text" style="min-width:150px;cursor:pointer;">ASN / Proveedor &#8597;</th>
            <th data-col="6" data-type="text" style="min-width:160px;cursor:pointer;">Ruta Atacada &#8597;</th>
            <th data-col="7" data-type="text" style="min-width:170px;cursor:pointer;">Motivo &#8597;</th>
        </tr>
    </thead>
    <tbody>
        <?php if ( empty( $logs ) ) : ?>
            <tr><td colspan="8">No hay registros de bloqueos todavía.</td></tr>
        <?php else : ?>
            <?php foreach ( $logs as $log ) :
                $ip_val     = trim( (string) $log->ip_address );
                $packed_ip  = @inet_pton( $ip_val );
                $ip_hex     = ( false !== $packed_ip ) ? bin2hex( str_pad( $packed_ip, 16, "\0", STR_PAD_LEFT ) ) : strtolower( $ip_val );
                $risk       = ( '' !== $ip_val && function_exists( 'bap_get_abuseipdb_score' ) ) ? bap_get_abuseipdb_score( $ip_val ) : null;
                $risk_sort  = ( null === $risk ) ? -1 : (int) $risk;
                $uri_val    = isset( $log->request_uri ) && '' !== (string) $log->request_uri ? (string) $log->request_uri : '/';
                $ua_val     = isset( $log->user_agent ) && '' !== trim( (string) $log->user_agent ) ? trim( (string) $log->user_agent ) : 'No registrado';
                $hist       = $ip_history[ $ip_val ] ?? [];
                $total_hits = (int) ( $hist['total_hits'] ?? 1 );
                $is_tor     = ( '' !== $ip_val && function_exists( 'bap_is_tor_exit_node' ) && bap_is_tor_exit_node( $ip_val ) ) ? 'Sí' : 'No';
                $fh_cidr    = ( '' !== $ip_val && function_exists( 'bap_is_in_firehol' ) ) ? bap_is_in_firehol( $ip_val ) : false;

                $dt_raw     = (string) $log->created_at;
                $dt_parts   = explode( ' ', $dt_raw, 2 );
                $date_part  = $dt_parts[0] ?? $dt_raw;
                $time_part  = $dt_parts[1] ?? '';

                $tooltip_payload = [
                    'ip'         => '' !== $ip_val ? $ip_val : 'N/D',
                    'last_seen'  => (string) ( $hist['last_seen'] ?? $dt_raw ),
                    'first_seen' => (string) ( $hist['first_seen'] ?? $dt_raw ),
                    'total_hits' => $total_hits,
                    'country'    => (string) ( $log->country_code ?: 'DESCONOCIDO' ),
                    'asn'        => (string) ( $log->asn ?: 'N/D' ),
                    'risk'       => ( null === $risk ) ? 'N/D' : $risk . '%',
                    'tor'        => $is_tor,
                    'firehol'    => ( false !== $fh_cidr ) ? 'Sí (' . $fh_cidr . ')' : 'No',
                    'block_type' => (string) ( $log->block_type ?? 'N/D' ),
                    'uri'        => $uri_val,
                    'reason'     => (string) $log->reason,
                    'user_agent' => $ua_val,
                ];
            ?>
                <tr class="bap-log-row" data-tip="<?php echo esc_attr( wp_json_encode( $tooltip_payload ) ); ?>">
                    <td data-sort="<?php echo esc_attr( $dt_raw ); ?>" style="white-space:nowrap;line-height:1.3;">
                        <span><?php echo esc_html( $date_part ); ?></span>
                        <?php if ( '' !== $time_part ) : ?>
                            <br /><small style="color:#8c8f94;"><?php echo esc_html( $time_part ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td data-sort="<?php echo (int) $total_hits; ?>" style="text-align:center;">
                        <?php if ( '' !== $ip_val ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'filter_q', rawurlencode( $ip_val ), $stats_page_url ) ); ?>" title="Filtrar todos los intentos de esta IP" style="display:inline-block;min-width:24px;text-align:center;background:#d63638;color:#ffffff;font-weight:700;font-size:11px;line-height:18px;padding:0 6px;border-radius:4px;text-decoration:none;">
                                <?php echo (int) $total_hits; ?>
                            </a>
                        <?php else : ?>
                            <span style="color:#8c8f94;">0</span>
                        <?php endif; ?>
                    </td>
                    <td data-sort="<?php echo esc_attr( $ip_hex ); ?>" style="white-space:nowrap;">
                        <?php if ( '' !== $ip_val ) : ?>
                            <a href="<?php echo esc_url( $ip2loc_base . rawurlencode( $ip_val ) ); ?>" class="bap-ip2loc-link" data-ip="<?php echo esc_attr( $ip_val ); ?>" target="_blank" rel="noopener noreferrer" title="Consultar IP en IP2Location">
                                <code><?php echo esc_html( $ip_val ); ?></code>
                            </a>
                        <?php else : ?>
                            <code>N/D</code>
                        <?php endif; ?>
                    </td>
                    <td data-sort="<?php echo esc_attr( $risk_sort ); ?>" style="white-space:nowrap;">
                        <?php if ( '' !== $ip_val ) : ?>
                            <a href="<?php echo esc_url( $abuse_base . rawurlencode( $ip_val ) ); ?>" class="bap-abuse-link" data-ip="<?php echo esc_attr( $ip_val ); ?>" target="_blank" rel="noopener noreferrer" title="Consultar reputación en AbuseIPDB" style="text-decoration:none;">
                                <?php if ( null === $risk ) : ?>
                                    <span style="color:#8c8f94;">N/D</span>
                                <?php elseif ( $risk >= 75 ) : ?>
                                    <strong style="color:#d63638;"><?php echo (int) $risk; ?>%</strong>
                                <?php elseif ( $risk >= 25 ) : ?>
                                    <strong style="color:#dba617;"><?php echo (int) $risk; ?>%</strong>
                                <?php else : ?>
                                    <span style="color:#00a32a;"><?php echo (int) $risk; ?>%</span>
                                <?php endif; ?>
                            </a>
                        <?php else : ?>
                            <span style="color:#8c8f94;">N/D</span>
                        <?php endif; ?>
                    </td>
                    <td data-sort="<?php echo esc_attr( $log->country_code ); ?>" style="white-space:nowrap;"><strong><?php echo esc_html( $log->country_code ); ?></strong></td>
                    <td data-sort="<?php echo esc_attr( $log->asn ?? '' ); ?>"><small><?php echo esc_html( $log->asn ?: 'N/D' ); ?></small></td>
                    <td data-sort="<?php echo esc_attr( $uri_val ); ?>" style="word-break:break-word;">
                        <code title="<?php echo esc_attr( $uri_val ); ?>"><?php echo esc_html( $uri_val ); ?></code>
                    </td>
                    <td data-sort="<?php echo esc_attr( $log->reason ); ?>"><?php echo esc_html( $log->reason ); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>
