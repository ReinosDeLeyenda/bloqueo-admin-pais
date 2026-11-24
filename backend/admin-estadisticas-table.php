<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$ip2loc_base = 'https://www.' . '://ip2location.com';
$abuse_base  = 'https://www.' . '://abuseipdb.com';
?>
<table id="bap-logs-table" class="widefat fixed striped">
    <thead>
        <tr>
            <th data-col="0" data-type="text" style="width:145px;cursor:pointer;">Fecha y Hora &#8597;</th>
            <th data-col="1" data-type="ip" style="width:130px;cursor:pointer;">Dirección IP &#8597;</th>
            <th data-col="2" data-type="num" style="width:95px;cursor:pointer;">AbuseIPDB &#8597;</th>
            <th data-col="3" data-type="text" style="width:70px;cursor:pointer;">País &#8597;</th>
            <th data-col="4" data-type="text" style="width:185px;cursor:pointer;">ASN / Proveedor &#8597;</th>
            <th data-col="5" data-type="text" style="width:200px;cursor:pointer;">Ruta Atacada &#8597;</th>
            <th data-col="6" data-type="text" style="cursor:pointer;">Motivo &#8597;</th>
        </tr>
    </thead>
    <tbody>
        <?php if ( empty( $logs ) ) : ?>
            <tr><td colspan="7">No hay registros de bloqueos todavía.</td></tr>
        <?php else : ?>
            <?php foreach ( $logs as $log ) :
                $ip_val    = trim( (string) $log->ip_address );
                $packed_ip = @inet_pton( $ip_val );
                $ip_hex    = ( false !== $packed_ip ) ? bin2hex( str_pad( $packed_ip, 16, "\0", STR_PAD_LEFT ) ) : strtolower( $ip_val );
                $risk      = ( '' !== $ip_val && function_exists( 'bap_get_abuseipdb_score' ) ) ? bap_get_abuseipdb_score( $ip_val ) : null;
                $risk_sort = ( null === $risk ) ? -1 : (int) $risk;
                $uri_val   = isset( $log->request_uri ) && '' !== (string) $log->request_uri ? (string) $log->request_uri : '/';
                $ua_val    = isset( $log->user_agent ) && '' !== trim( (string) $log->user_agent ) ? trim( (string) $log->user_agent ) : 'No registrado';
                $hist      = $ip_history[ $ip_val ] ?? [];
                $is_tor    = ( '' !== $ip_val && function_exists( 'bap_is_tor_exit_node' ) && bap_is_tor_exit_node( $ip_val ) ) ? 'Sí' : 'No';
                $fh_cidr   = ( '' !== $ip_val && function_exists( 'bap_is_in_firehol' ) ) ? bap_is_in_firehol( $ip_val ) : false;

                $tooltip_payload = [
                    'ip'         => '' !== $ip_val ? $ip_val : 'N/D',
                    'last_seen'  => (string) ( $hist['last_seen'] ?? $log->created_at ),
                    'first_seen' => (string) ( $hist['first_seen'] ?? $log->created_at ),
                    'total_hits' => (int) ( $hist['total_hits'] ?? 1 ),
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
                    <td data-sort="<?php echo esc_attr( $log->created_at ); ?>"><?php echo esc_html( $log->created_at ); ?></td>
                    <td data-sort="<?php echo esc_attr( $ip_hex ); ?>">
                        <?php if ( '' !== $ip_val ) : ?>
                            <a href="<?php echo esc_url( $ip2loc_base . rawurlencode( $ip_val ) ); ?>" class="bap-ip2loc-link" data-ip="<?php echo esc_attr( $ip_val ); ?>" target="_blank" rel="noopener noreferrer" title="Consultar IP en IP2Location">
                                <code><?php echo esc_html( $ip_val ); ?></code>
                            </a>
                        <?php else : ?>
                            <code>N/D</code>
                        <?php endif; ?>
                    </td>
                    <td data-sort="<?php echo esc_attr( $risk_sort ); ?>">
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
                    <td data-sort="<?php echo esc_attr( $log->country_code ); ?>"><strong><?php echo esc_html( $log->country_code ); ?></strong></td>
                    <td data-sort="<?php echo esc_attr( $log->asn ?? '' ); ?>"><small><?php echo esc_html( $log->asn ?: 'N/D' ); ?></small></td>
                    <td data-sort="<?php echo esc_attr( $uri_val ); ?>" style="word-break:break-all;">
                        <code title="<?php echo esc_attr( $uri_val ); ?>"><?php echo esc_html( $uri_val ); ?></code>
                    </td>
                    <td data-sort="<?php echo esc_attr( $log->reason ); ?>"><?php echo esc_html( $log->reason ); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
