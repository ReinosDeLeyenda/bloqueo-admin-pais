<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<?php if ( empty( $logs ) ) : ?>
    <p>Aún no se ha registrado ningún intento bloqueado.</p>
<?php else : ?>
    <table id="bap_logs_table" class="widefat striped" style="max-width: 1120px;">
        <thead>
            <tr>
                <th class="bap-sort" data-type="ip" style="cursor:pointer;user-select:none;">Dirección IP <span class="bap-arrow"></span></th>
                <th class="bap-sort" data-type="str" style="cursor:pointer;user-select:none;">País <span class="bap-arrow"></span></th>
                <th class="bap-sort" data-type="num" style="cursor:pointer;user-select:none;">Riesgo <span class="bap-arrow"></span></th>
                <th class="bap-sort" data-type="str" style="cursor:pointer;user-select:none;">ASN / Proveedor <span class="bap-arrow"></span></th>
                <th class="bap-sort" data-type="num" style="cursor:pointer;user-select:none;">Intentos <span class="bap-arrow">▼</span></th>
                <th class="bap-sort" data-type="str" style="cursor:pointer;user-select:none;">Estado / Motivo <span class="bap-arrow"></span></th>
                <th class="bap-sort" data-type="str" style="cursor:pointer;user-select:none;">Último intento <span class="bap-arrow"></span></th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $logs as $entry ) : ?>
                <?php
                $ip_val    = $entry['ip'];
                $is_banned = in_array( $ip_val, $blacklist, true );
                $risk      = function_exists( 'bap_get_abuseipdb_score' ) ? bap_get_abuseipdb_score( $ip_val ) : null;
                $risk_sort = ( null === $risk ) ? -1 : (int) $risk;
                $asn_text  = function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip_val ) : 'N/D';
                $packed    = @inet_pton( $ip_val );
                $ip_hex    = ( false !== $packed ) ? bin2hex( str_pad( $packed, 16, "\0", STR_PAD_LEFT ) ) : $ip_val;

                $safe_ip   = preg_replace( '/[^0-9a-fA-F:.]/', '', $ip_val );
                $ip2loc    = 'https://' . implode( '.', [ 'www', 'ip2location', 'com' ] ) . '/' . $safe_ip;
                $abuse_url = 'https://' . implode( '.', [ 'www', 'abuseipdb', 'com' ] ) . '/check/' . $safe_ip;
                ?>
                <tr>
                    <td data-sort="<?php echo esc_attr( $ip_hex ); ?>">
                        <a href="<?php echo esc_url( $ip2loc ); ?>" target="_blank" rel="noopener noreferrer" title="Consultar IP en IP2Location"><code><?php echo esc_html( $ip_val ); ?></code></a>
                    </td>
                    <td data-sort="<?php echo esc_attr( $entry['country'] ); ?>"><strong><?php echo esc_html( $entry['country'] ); ?></strong></td>
                    <td data-sort="<?php echo (int) $risk_sort; ?>">
                        <a href="<?php echo esc_url( $abuse_url ); ?>" target="_blank" rel="noopener noreferrer" title="Consultar historial en AbuseIPDB" style="text-decoration:none;">
                            <?php if ( null === $risk ) : ?>
                                <span style="color:#8c8f94;">N/D</span>
                            <?php else : ?>
                                <?php $color = ( $risk >= 90 ) ? '#d63638' : ( ( $risk >= 50 ) ? '#dba617' : '#008a20' ); ?>
                                <strong style="color:<?php echo esc_attr( $color ); ?>;"><?php echo (int) $risk; ?>%</strong>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td data-sort="<?php echo esc_attr( strtolower( $asn_text ) ); ?>"><small><?php echo esc_html( $asn_text ); ?></small></td>
                    <td data-sort="<?php echo (int) $entry['count']; ?>"><strong><?php echo number_format_i18n( (int) $entry['count'] ); ?></strong></td>
                    <td data-sort="<?php echo esc_attr( strtolower( ( $is_banned ? 'bloqueada ' : '' ) . $entry['reason'] ) ); ?>">
                        <?php if ( $is_banned ) : ?>
                            <span style="color:#d63638;font-weight:bold;">[Bloqueada]</span>
                        <?php endif; ?>
                        <?php echo esc_html( $entry['reason'] ); ?>
                    </td>
                    <td data-sort="<?php echo esc_attr( $entry['last_hit'] ); ?>"><?php echo esc_html( $entry['last_hit'] ); ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field( 'bap_logs_action' ); ?>
                            <?php if ( $is_banned ) : ?>
                                <input type="hidden" name="bap_unban_ip" value="<?php echo esc_attr( $ip_val ); ?>" />
                                <input type="submit" class="button button-small" value="Desbloquear" />
                            <?php else : ?>
                                <input type="hidden" name="bap_ban_ip" value="<?php echo esc_attr( $ip_val ); ?>" />
                                <input type="submit" class="button button-small" value="Bloquear IP" />
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
