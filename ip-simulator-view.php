<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="card" style="max-width:960px;padding:14px 20px;margin-top:12px;">
    <h3 style="margin-top:0;">Simulador / Buscador de IP</h3>
    <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <?php wp_nonce_field( 'bap_sim_ip_action', 'bap_sim_nonce' ); ?>
        <input type="text" name="bap_sim_ip" value="<?php echo esc_attr( $sim_ip ); ?>" class="regular-text code" placeholder="Ej: 185.220.101.1 o 2606:4700:4700::1111" required />
        <button type="submit" class="button button-secondary">Analizar IP</button>
    </form>

    <?php if ( is_wp_error( $sim_res ) ) : ?>
        <p style="color:#d63638;margin-bottom:0;"><strong>Error:</strong> <?php echo esc_html( $sim_res->get_error_message() ); ?></p>
    <?php elseif ( is_array( $sim_res ) ) : ?>
        <div style="margin-top:12px;padding:12px;background:#f6f7f7;border-left:4px solid #2271b1;border-radius:3px;">
            <p style="margin:0 0 8px;">
                <strong>IP:</strong> <code><?php echo esc_html( $sim_res['ip'] ); ?></code> |
                <strong>País:</strong> <code><?php echo esc_html( $sim_res['country'] ); ?></code> |
                <strong>ASN:</strong> <code><?php echo esc_html( $sim_res['asn'] ); ?></code> |
                <strong>AbuseIPDB:</strong> <code><?php echo null !== $sim_res['abuse_risk'] ? (int) $sim_res['abuse_risk'] . '%' : 'N/D'; ?></code> |
                <strong>Tor:</strong> <code><?php echo $sim_res['is_tor'] ? 'Sí' : 'No'; ?></code> |
                <strong>FireHOL:</strong> <code><?php echo false !== $sim_res['firehol'] ? esc_html( $sim_res['firehol'] ) : 'No'; ?></code>
            </p>
            <p style="margin:0 0 4px;">
                <strong>Panel Admin:</strong>
                <?php if ( 'block' === $sim_res['admin_status'] ) : ?>
                    <span style="color:#d63638;font-weight:bold;">BLOQUEADA</span> — <?php echo esc_html( $sim_res['admin_reason'] ); ?>
                <?php else : ?>
                    <span style="color:#008a20;font-weight:bold;">PERMITIDA</span> — <?php echo esc_html( $sim_res['admin_reason'] ); ?>
                <?php endif; ?>
            </p>
            <p style="margin:0;">
                <strong>Web Pública:</strong>
                <?php if ( 'block' === $sim_res['web_status'] ) : ?>
                    <span style="color:#d63638;font-weight:bold;">BLOQUEADA</span> — <?php echo esc_html( $sim_res['web_reason'] ); ?>
                <?php elseif ( 'redirect' === $sim_res['web_status'] ) : ?>
                    <span style="color:#2271b1;font-weight:bold;">REDIRIGIDA</span> — <?php echo esc_html( $sim_res['web_reason'] ); ?>
                <?php else : ?>
                    <span style="color:#008a20;font-weight:bold;">PERMITIDA</span> — <?php echo esc_html( $sim_res['web_reason'] ); ?>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>
