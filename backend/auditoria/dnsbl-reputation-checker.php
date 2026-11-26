<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_resolve_server_public_ipv4() {
    $srv_ip = (string) ( $_SERVER['SERVER_ADDR'] ?? '' );
    if ( filter_var( $srv_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
        return $srv_ip;
    }
    $host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
    $ip   = ( '' !== $host ) ? @gethostbyname( $host ) : '';
    return filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ? $ip : '';
}

function bap_check_ip_against_dnsbls( $ip ) {
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        return [ 'ip' => $ip, 'results' => [] ];
    }

    $rev = implode( '.', array_reverse( explode( '.', $ip ) ) );
    $providers = [
        'Spamhaus ZEN'  => [ 'zone' => 'zen.spamhaus.org', 'delist' => 'https://' . 'check.spamhaus.org/results?query=' . rawurlencode( $ip ) ],
        'SpamCop'       => [ 'zone' => 'bl.spamcop.net', 'delist' => 'https://' . 'www.spamcop.net/w3m?action=checkblock&ip=' . rawurlencode( $ip ) ],
        'Barracuda'     => [ 'zone' => 'b.barracudacentral.org', 'delist' => 'https://' . 'www.barracudacentral.org/rbl/removal-request' ],
        'UCEPROTECT L1' => [ 'zone' => 'dnsbl-1.uceprotect.net', 'delist' => 'https://' . 'www.uceprotect.net/en/rblcheck.php?ipr=' . rawurlencode( $ip ) ],
    ];

    $rows = [];
    foreach ( $providers as $name => $cfg ) {
        $zone   = $cfg['zone'];
        $recs   = @dns_get_record( $rev . '.' . $zone . '.', DNS_A );
        $listed = false;
        foreach ( (array) $recs as $rec ) {
            $ans = (string) ( $rec['ip'] ?? '' );
            if ( '' !== $ans && 0 === strpos( $ans, '127.' ) && 0 !== strpos( $ans, '127.255.255.' ) ) {
                $listed = true;
                break;
            }
        }
        $rows[] = [ 'name' => $name, 'zone' => $zone, 'listed' => $listed, 'delist' => $cfg['delist'] ];
    }

    return [ 'ip' => $ip, 'results' => $rows ];
}

function bap_render_dnsbl_reputation_box() {
    $default_ip = bap_resolve_server_public_ipv4();
    $target_ip  = isset( $_POST['bap_dnsbl_ip'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['bap_dnsbl_ip'] ) ) ) : $default_ip;
    $check_res  = null;

    if ( isset( $_POST['bap_run_dnsbl_check'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_dnsbl_action', 'bap_dnsbl_nonce' ) ) {
        $check_res = bap_check_ip_against_dnsbls( $target_ip );
    }
    ?>
    <div class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Reputación del Servidor en Listas Negras (DNSBL)</h2>
        <p class="description">Comprueba si la IP pública de tu servidor web o de correo (SMTP) figura en listas negras internacionales de spam o malware (<strong>Spamhaus, Barracuda, SpamCop y SORBS</strong>).</p>

        <form method="post" style="margin:14px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_dnsbl_action', 'bap_dnsbl_nonce' ); ?>
            <label for="bap_dnsbl_ip"><strong>IPv4 a comprobar:</strong></label>
            <input type="text" id="bap_dnsbl_ip" name="bap_dnsbl_ip" value="<?php echo esc_attr( $target_ip ); ?>" placeholder="Ej. 93.184.216.34" class="regular-text code" style="width:200px;" required />
            <button type="submit" name="bap_run_dnsbl_check" class="button button-primary">Comprobar reputación DNSBL</button>
        </form>

        <?php if ( is_wp_error( $check_res ) ) : ?>
            <div class="notice notice-warning inline" style="margin-top:10px;"><p><?php echo esc_html( $check_res->get_error_message() ); ?></p></div>
        <?php elseif ( is_array( $check_res ) ) :
            $listed_cnt = count( array_filter( $check_res['results'], fn( $r ) => ! empty( $r['listed'] ) ) );
        ?>
            <?php if ( 0 === $listed_cnt ) : ?>
                <div class="notice notice-success inline" style="margin-top:10px;">
                    <p><strong>¡Reputación limpia!</strong> La IP <code><?php echo esc_html( $check_res['ip'] ); ?></code> no aparece en ninguna de las 4 listas negras consultadas.</p>
                </div>
            <?php else : ?>
                <div class="notice notice-error inline" style="margin-top:10px;">
                    <p><strong>¡Atención!</strong> La IP <code><?php echo esc_html( $check_res['ip'] ); ?></code> aparece listada en <strong><?php echo (int) $listed_cnt; ?></strong> lista(s) negra(s).</p>
                </div>
            <?php endif; ?>

            <table class="widefat striped" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th>Lista Negra (DNSBL)</th>
                        <th>Zona DNS</th>
                        <th style="width:140px;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $check_res['results'] as $row ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $row['provider'] ); ?></strong></td>
                            <td><code><?php echo esc_html( $row['zone'] ); ?></code></td>
                            <td>
                                <?php if ( $row['listed'] ) : ?>
                                    <?php echo bap_render_status_badge( 'LISTADA', '#d63638' ); ?>
                                <?php else : ?>
                                    <?php echo bap_render_status_badge( 'LIMPIA', '#00a32a' ); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}