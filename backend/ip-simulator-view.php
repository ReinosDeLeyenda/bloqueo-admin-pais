<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
    <h2 style="margin-top:0;">Simulador de IP (Comprobador de reglas en vivo)</h2>
    <p class="description">Comprueba cómo responderán tanto el <strong>Panel Admin</strong> como una <strong>URL de la Web Pública</strong> ante una dirección IPv4 o IPv6 sin necesidad de usar VPN ni modificar registros.</p>

    <form method="post" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:14px 0;">
        <?php wp_nonce_field( 'bap_sim_ip_action', 'bap_sim_nonce' ); ?>
        <input type="text" name="bap_sim_ip" value="<?php echo esc_attr( $sim_ip ); ?>" placeholder="Ej. 8.8.8.8 o 2001:4860:4860::8888" class="regular-text" style="width:240px;" required />

        <input type="text" name="bap_sim_uri" list="bap_sim_uri_presets" value="<?php echo esc_attr( $sim_uri ); ?>" placeholder="/ruta-a-simular" class="regular-text" style="width:320px;" />
        <datalist id="bap_sim_uri_presets">
            <option value="/">🌐 / (Portada / Regla Global)</option>
            <?php foreach ( $web_rules as $scope_key => $rule_cfg ) :
                if ( '__all__' === $scope_key ) {
                    continue;
                }
                $clean_example = rtrim( $scope_key, '*' );
            ?>
                <option value="<?php echo esc_attr( $clean_example ); ?>">📄 <?php echo esc_html( $scope_key ); ?></option>
            <?php endforeach; ?>
        </datalist>

        <button type="submit" class="button button-primary">Simular IP</button>
    </form>

    <?php if ( is_wp_error( $sim_res ) ) : ?>
        <div class="notice notice-error inline"><p><?php echo esc_html( $sim_res->get_error_message() ); ?></p></div>
    <?php elseif ( is_array( $sim_res ) ) : ?>
        <div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:14px 16px;margin-top:12px;">
            <p style="margin-top:0;margin-bottom:10px;">
                <strong>IP analizada:</strong> <code><?php echo esc_html( $sim_res['ip'] ); ?></code> &nbsp;|&nbsp;
                <strong>País:</strong> <code><?php echo esc_html( $sim_res['country'] ); ?></code> &nbsp;|&nbsp;
                <strong>ASN:</strong> <code><?php echo esc_html( $sim_res['asn'] ); ?></code> &nbsp;|&nbsp;
                <strong>AbuseIPDB:</strong> <code><?php echo null === $sim_res['abuse_risk'] ? 'N/D' : esc_html( $sim_res['abuse_risk'] . '%' ); ?></code> &nbsp;|&nbsp;
                <strong>Tor:</strong> <code><?php echo $sim_res['is_tor'] ? 'Sí' : 'No'; ?></code> &nbsp;|&nbsp;
                <strong>FireHOL:</strong> <code><?php echo false !== $sim_res['firehol'] ? esc_html( $sim_res['firehol'] ) : 'No'; ?></code>
            </p>

            <table class="widefat striped" style="margin-top:8px;">
                <thead>
                    <tr>
                        <th style="width:220px;">Entorno</th>
                        <th style="width:140px;">Veredicto</th>
                        <th>Regla aplicada y motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Panel Admin / Login</strong></td>
                        <td>
                            <?php if ( 'block' === $sim_res['admin_status'] ) : ?>
                                <span style="background:#d63638;color:#fff;padding:3px 8px;border-radius:3px;font-weight:600;">BLOQUEADA</span>
                            <?php else : ?>
                                <span style="background:#00a32a;color:#fff;padding:3px 8px;border-radius:3px;font-weight:600;">PERMITIDA</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $sim_res['admin_reason'] ); ?></td>
                    </tr>
                    <tr>
                        <td>
                            <strong>Web Pública</strong><br>
                            <small style="color:#646970;">Ruta: <code><?php echo esc_html( $sim_res['sim_uri'] ); ?></code></small>
                        </td>
                        <td>
                            <?php if ( 'block' === $sim_res['web_status'] ) : ?>
                                <span style="background:#d63638;color:#fff;padding:3px 8px;border-radius:3px;font-weight:600;">BLOQUEADA</span>
                            <?php elseif ( 'redirect' === $sim_res['web_status'] ) : ?>
                                <span style="background:#dba617;color:#1d2327;padding:3px 8px;border-radius:3px;font-weight:600;">REDIRIGIDA</span>
                            <?php else : ?>
                                <span style="background:#00a32a;color:#fff;padding:3px 8px;border-radius:3px;font-weight:600;">PERMITIDA</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong>Ámbito aplicado:</strong> <code><?php echo esc_html( $sim_res['web_scope'] ); ?></code><br>
                            <?php echo esc_html( $sim_res['web_reason'] ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
