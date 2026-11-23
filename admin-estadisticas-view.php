<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-admin-estadisticas' ); ?>
    <?php echo $notice; ?>

    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
        <div class="card" style="flex:1;min-width:180px;max-width:240px;margin:0;text-align:center;">
            <h3 style="margin:0 0 8px;">Intentos Bloqueados</h3>
            <p style="font-size:28px;font-weight:bold;color:#d63638;margin:0;"><?php echo number_format_i18n( (int) $total_hits ); ?></p>
        </div>
        <div class="card" style="flex:1;min-width:180px;max-width:240px;margin:0;text-align:center;">
            <h3 style="margin:0 0 8px;">IPs Únicas Detectadas</h3>
            <p style="font-size:28px;font-weight:bold;color:#1d2327;margin:0;"><?php echo number_format_i18n( (int) ( $unique_ips ?? count( $logs ) ) ); ?></p>
        </div>
        <div class="card" style="flex:1;min-width:180px;max-width:240px;margin:0;text-align:center;">
            <h3 style="margin:0 0 8px;">IPs en Lista Negra</h3>
            <p style="font-size:28px;font-weight:bold;color:#2271b1;margin:0;"><?php echo number_format_i18n( count( $blacklist ) ); ?></p>
        </div>
    </div>

    <?php if ( ! empty( $logs ) ) : ?>
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
            <div class="card" style="flex:1;min-width:280px;max-width:450px;margin:0;">
                <h2>Top Países Bloqueados</h2>
                <ul style="list-style:disc;margin-left:20px;">
                    <?php foreach ( array_slice( $country_rank, 0, 8, true ) as $c_code => $c_hits ) : ?>
                        <li><strong><?php echo esc_html( $c_code ); ?></strong>: <?php echo number_format_i18n( (int) $c_hits ); ?> intentos</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card" style="flex:1;min-width:280px;max-width:560px;margin:0;">
                <h2>Top ASNs / Proveedores</h2>
                <ul style="list-style:disc;margin-left:20px;">
                    <?php foreach ( array_slice( $asn_rank, 0, 8, true ) as $a_name => $a_hits ) : ?>
                        <li><strong><?php echo esc_html( $a_name ); ?></strong>: <?php echo number_format_i18n( (int) $a_hits ); ?> intentos</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <h2>Historial Detallado de IPs Bloqueadas</h2>
    <?php if ( ! empty( $logs ) || '' !== ( $ip2loc_tbl ?? '' ) ) : ?>
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap;">
            <?php if ( '' !== ( $ip2loc_tbl ?? '' ) ) : ?>
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field( 'bap_logs_action' ); ?>
                    <input type="submit" name="bap_import_ip2loc" class="button button-primary" value="Importar estadísticas de IP2Location (MySQL)" onclick="return confirm('Se importarán los registros detectados en la tabla <?php echo esc_js( $ip2loc_tbl ); ?>. ¿Continuar?');" />
                </form>
            <?php endif; ?>
            <?php if ( ! empty( $logs ) ) : ?>
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field( 'bap_logs_action' ); ?>
                    <input type="submit" name="bap_clear_logs" class="button button-secondary" value="Vaciar historial" onclick="return confirm('¿Seguro que quieres borrar todo el historial?');" />
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

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
</div>
