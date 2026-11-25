<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
function bap_render_general_ip_source_field() {
    $ip_source = get_option( 'bap_ip_source', 'remote_addr' );
    ?>
    <tr>
        <th><label for="bap_ip_source">Detección de IP del visitante</label></th>
        <td>
            <select id="bap_ip_source" name="bap_ip_source">
                <option value="remote_addr" <?php selected( $ip_source, 'remote_addr' ); ?>>Conexión directa — REMOTE_ADDR (Recomendado / Anti-Spoofing)</option>
                <option value="cloudflare" <?php selected( $ip_source, 'cloudflare' ); ?>>Cloudflare CDN — HTTP_CF_CONNECTING_IP</option>
                <option value="x_forwarded_for" <?php selected( $ip_source, 'x_forwarded_for' ); ?>>Proxy inverso / Balanceador — HTTP_X_FORWARDED_FOR</option>
                <option value="x_real_ip" <?php selected( $ip_source, 'x_real_ip' ); ?>>Nginx Proxy — HTTP_X_REAL_IP</option>
            </select>
            <p class="description">
                Usa <strong>REMOTE_ADDR</strong> salvo que tu web pase por Cloudflare o un proxy inverso.
                <?php if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && 'cloudflare' !== $ip_source ) : ?>
                    <br /><span style="color:#d97706;font-weight:600;">Aviso: Se ha detectado tráfico de Cloudflare (<code><?php echo esc_html( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) ); ?></code>).</span>
                <?php endif; ?>
            </p>
        </td>
    </tr>
    <?php
}
