<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_render_headers_and_mail_dns_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $dns_res = null;
    $hdr_res = null;

    if ( isset( $_POST['bap_run_dns_hdr_audit'] ) && check_admin_referer( 'bap_dns_hdr_action', 'bap_dns_hdr_nonce' ) ) {
        $dns_res = bap_audit_mail_dns_records();
        $hdr_res = bap_audit_live_http_headers();
    }
    ?>
    <div class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Auditoría de Correo (SPF / DMARC / MX) y Cabeceras HTTP</h2>
        <p class="description">Verifica que tu dominio tiene configurados los registros DNS <strong>SPF, DMARC y MX</strong> para que las alertas por correo no caigan en SPAM, y analiza las cabeceras de seguridad HTTP activas.</p>

        <form method="post" style="margin:14px 0;">
            <?php wp_nonce_field( 'bap_dns_hdr_action', 'bap_dns_hdr_nonce' ); ?>
            <button type="submit" name="bap_run_dns_hdr_audit" class="button button-primary">Comprobar DNS de Correo y Cabeceras HTTP</button>
        </form>

        <?php if ( is_wp_error( $dns_res ) ) : ?>
            <div class="notice notice-warning inline"><p><?php echo esc_html( $dns_res->get_error_message() ); ?></p></div>
        <?php elseif ( is_array( $dns_res ) ) : ?>
            <h3 style="margin:14px 0 6px 0;">Registros DNS de Correo (<code><?php echo esc_html( $dns_res['domain'] ); ?></code>)</h3>
            <table class="widefat striped">
                <thead><tr><th style="width:140px;">Registro</th><th style="width:110px;">Estado</th><th>Valor detectado</th></tr></thead>
                <tbody>
                    <?php foreach ( $dns_res['records'] as $r ) :
                        $bg = ( 'ok' === $r['status'] ) ? '#00a32a' : ( ( 'warning' === $r['status'] ) ? '#d97706' : '#d63638' );
                        $tx = ( 'ok' === $r['status'] ) ? 'CORRECTO' : ( ( 'warning' === $r['status'] ) ? 'MEJORABLE' : 'FALTA' );
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html( $r['type'] ); ?></strong></td>
                            <td><span style="background:<?php echo esc_attr( $bg ); ?>;color:#fff;padding:2px 7px;border-radius:3px;font-weight:700;font-size:11px;"><?php echo esc_html( $tx ); ?></span></td>
                            <td><code><?php echo esc_html( $r['value'] ); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ( is_array( $hdr_res ) ) : ?>
            <h3 style="margin:18px 0 6px 0;">Cabeceras HTTP de Seguridad en Vivo</h3>
            <table class="widefat striped">
                <thead><tr><th style="width:240px;">Cabecera</th><th style="width:110px;">Estado</th><th>Valor actual</th></tr></thead>
                <tbody>
                    <?php foreach ( $hdr_res as $h ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $h['label'] ); ?></strong></td>
                            <td>
                                <?php if ( $h['present'] ) : ?>
                                    <span style="background:#00a32a;color:#fff;padding:2px 7px;border-radius:3px;font-weight:700;font-size:11px;">ACTIVA</span>
                                <?php else : ?>
                                    <span style="background:#646970;color:#fff;padding:2px 7px;border-radius:3px;font-weight:700;font-size:11px;">AUSENTE</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html( $h['value'] ); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}