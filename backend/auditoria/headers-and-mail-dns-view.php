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
            <?php if ( empty( $dns_res['dkim_ok'] ) ) :
                $mta      = (string) ( $dns_res['mta'] ?? 'unknown' );
                $dom_esc  = esc_html( $dns_res['domain'] );
                $mta_lbls = [ 'postfix' => 'Postfix', 'exim4' => 'Exim4', 'sendmail' => 'Sendmail (nativo)', 'relay' => 'Relay SMTP (msmtp/nullmailer)', 'windows' => 'Windows', 'unknown' => 'Desconocido' ];
                $mta_name = $mta_lbls[ $mta ] ?? 'Desconocido';
            ?>
                <details open style="margin-top:12px;background:#f6f7f7;border:1px solid #dcdcde;border-left:4px solid #d97706;border-radius:4px;padding:10px 14px;">
                    <summary style="cursor:pointer;font-weight:700;">Guia para activar firma DKIM (Servidor detectado: <?php echo esc_html( $mta_name ); ?>)</summary>
                    <?php if ( 'postfix' === $mta ) : ?>
                        <p class="description" style="margin:8px 0 6px;">Tu sistema usa <strong>Postfix</strong> (aunque PHP llame al binario compatible <code>/usr/sbin/sendmail</code>). Instala OpenDKIM y vincúlalo por socket:</p>
                        <pre style="background:#1d2327;color:#e5e7eb;padding:10px;border-radius:4px;font-size:11px;overflow-x:auto;">apt update &amp;&amp; apt install -y opendkim opendkim-tools
mkdir -p /etc/opendkim/keys/<?php echo $dom_esc; ?> /var/spool/postfix/opendkim
opendkim-genkey -b 2048 -d <?php echo $dom_esc; ?> -D /etc/opendkim/keys/<?php echo $dom_esc; ?> -s default
chown -R opendkim:opendkim /etc/opendkim &amp;&amp; chown opendkim:postfix /var/spool/postfix/opendkim &amp;&amp; usermod -aG opendkim postfix
postconf -e "milter_default_action = accept" "milter_protocol = 6" "smtpd_milters = local:opendkim/opendkim.sock" "non_smtpd_milters = \$smtpd_milters"
systemctl restart opendkim postfix &amp;&amp; cat /etc/opendkim/keys/<?php echo $dom_esc; ?>/default.txt</pre>
                    <?php elseif ( 'exim4' === $mta ) : ?>
                        <p class="description" style="margin:8px 0 6px;">Tu sistema usa <strong>Exim4</strong>, que soporta DKIM de forma nativa sin instalar milters externos:</p>
                        <pre style="background:#1d2327;color:#e5e7eb;padding:10px;border-radius:4px;font-size:11px;overflow-x:auto;">mkdir -p /etc/exim4/dkim
openssl genrsa -out /etc/exim4/dkim/<?php echo $dom_esc; ?>.private.pem 2048
openssl rsa -in /etc/exim4/dkim/<?php echo $dom_esc; ?>.private.pem -pubout -outform PEM
chown -R Debian-exim:Debian-exim /etc/exim4/dkim &amp;&amp; chmod 640 /etc/exim4/dkim/*.pem
printf "DKIM_DOMAIN = <?php echo $dom_esc; ?>\nDKIM_SELECTOR = default\nDKIM_PRIVATE_KEY = /etc/exim4/dkim/<?php echo $dom_esc; ?>.private.pem\n" &gt; /etc/exim4/conf.d/main/00_local_dkim
update-exim4.conf &amp;&amp; systemctl restart exim4</pre>
                    <?php elseif ( 'sendmail' === $mta ) : ?>
                        <p class="description" style="margin:8px 0 6px;">Tu sistema usa <strong>Sendmail nativo</strong>. Instala <code>opendkim</code>, genera la clave y añade el filtro milter en <code>/etc/mail/sendmail.mc</code>:</p>
                        <pre style="background:#1d2327;color:#e5e7eb;padding:10px;border-radius:4px;font-size:11px;overflow-x:auto;">apt update &amp;&amp; apt install -y opendkim opendkim-tools
mkdir -p /etc/opendkim/keys/<?php echo $dom_esc; ?>
opendkim-genkey -b 2048 -d <?php echo $dom_esc; ?> -D /etc/opendkim/keys/<?php echo $dom_esc; ?> -s default
chown -R opendkim:opendkim /etc/opendkim
# Añade esta linea al final de /etc/mail/sendmail.mc antes de MAILER_DEFINITIONS:
# INPUT_MAIL_FILTER(`opendkim', `S=inet:8891@localhost')
m4 /etc/mail/sendmail.mc &gt; /etc/mail/sendmail.cf &amp;&amp; systemctl restart opendkim sendmail</pre>
                    <?php else : ?>
                        <p class="description" style="margin:8px 0 0;">No se ha detectado Postfix/Exim4/Sendmail local configurado con DKIM. Para evitar la carpeta SPAM de Gmail con <code>DMARC p=reject</code>, configura un <strong>plugin SMTP autenticado</strong> (Brevo, Amazon SES, Mailgun o Google Workspace) o instala <strong>Postfix + OpenDKIM</strong>.</p>
                    <?php endif; ?>
                    <p class="description" style="margin:8px 0 0;">Tras activar la firma en el servidor, copia la clave publica en un registro <strong>TXT</strong> llamado <code>default._domainkey.<?php echo $dom_esc; ?></code> en tu panel DNS.</p>
                </details>
            <?php endif; ?>
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
                                    <?php echo bap_render_status_badge( 'ACTIVA', '#00a32a' ); ?>
                                <?php else : ?>
                                    <?php echo bap_render_status_badge( 'AUSENTE', '#646970' ); ?>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html( $h['value'] ); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ( empty( $dns_res['dkim_ok'] ) ) :
                $mta      = (string) ( $dns_res['mta'] ?? 'unknown' );
                $dom_esc  = esc_html( $dns_res['domain'] );
                $mta_lbls = [ 'postfix' => 'Postfix', 'exim4' => 'Exim4', 'sendmail' => 'Sendmail (nativo)', 'relay' => 'Relay SMTP (msmtp/nullmailer)', 'windows' => 'Windows', 'unknown' => 'Desconocido' ];
                $mta_name = $mta_lbls[ $mta ] ?? 'Desconocido';
            ?>
                <details open style="margin-top:12px;background:#f6f7f7;border:1px solid #dcdcde;border-left:4px solid #d97706;border-radius:4px;padding:10px 14px;">
                    <summary style="cursor:pointer;font-weight:700;">Guia para activar firma DKIM (Servidor detectado: <?php echo esc_html( $mta_name ); ?>)</summary>
                    <?php if ( 'postfix' === $mta ) : ?>
                        <p class="description" style="margin:8px 0 6px;">Tu sistema usa <strong>Postfix</strong> (aunque PHP llame al binario compatible <code>/usr/sbin/sendmail</code>). Instala OpenDKIM y vincúlalo por socket:</p>
                        <pre style="background:#1d2327;color:#e5e7eb;padding:10px;border-radius:4px;font-size:11px;overflow-x:auto;">apt update &amp;&amp; apt install -y opendkim opendkim-tools
mkdir -p /etc/opendkim/keys/<?php echo $dom_esc; ?> /var/spool/postfix/opendkim
opendkim-genkey -b 2048 -d <?php echo $dom_esc; ?> -D /etc/opendkim/keys/<?php echo $dom_esc; ?> -s default
chown -R opendkim:opendkim /etc/opendkim &amp;&amp; chown opendkim:postfix /var/spool/postfix/opendkim &amp;&amp; usermod -aG opendkim postfix
postconf -e "milter_default_action = accept" "milter_protocol = 6" "smtpd_milters = local:opendkim/opendkim.sock" "non_smtpd_milters = \$smtpd_milters"
systemctl restart opendkim postfix &amp;&amp; cat /etc/opendkim/keys/<?php echo $dom_esc; ?>/default.txt</pre>
                    <?php elseif ( 'exim4' === $mta ) : ?>
                        <p class="description" style="margin:8px 0 6px;">Tu sistema usa <strong>Exim4</strong>, que soporta DKIM de forma nativa sin instalar milters externos:</p>
                        <pre style="background:#1d2327;color:#e5e7eb;padding:10px;border-radius:4px;font-size:11px;overflow-x:auto;">mkdir -p /etc/exim4/dkim
openssl genrsa -out /etc/exim4/dkim/<?php echo $dom_esc; ?>.private.pem 2048
openssl rsa -in /etc/exim4/dkim/<?php echo $dom_esc; ?>.private.pem -pubout -outform PEM
chown -R Debian-exim:Debian-exim /etc/exim4/dkim &amp;&amp; chmod 640 /etc/exim4/dkim/*.pem
printf "DKIM_DOMAIN = <?php echo $dom_esc; ?>\nDKIM_SELECTOR = default\nDKIM_PRIVATE_KEY = /etc/exim4/dkim/<?php echo $dom_esc; ?>.private.pem\n" &gt; /etc/exim4/conf.d/main/00_local_dkim
update-exim4.conf &amp;&amp; systemctl restart exim4</pre>
                    <?php elseif ( 'sendmail' === $mta ) : ?>
                        <p class="description" style="margin:8px 0 6px;">Tu sistema usa <strong>Sendmail nativo</strong>. Instala <code>opendkim</code>, genera la clave y añade el filtro milter en <code>/etc/mail/sendmail.mc</code>:</p>
                        <pre style="background:#1d2327;color:#e5e7eb;padding:10px;border-radius:4px;font-size:11px;overflow-x:auto;">apt update &amp;&amp; apt install -y opendkim opendkim-tools
mkdir -p /etc/opendkim/keys/<?php echo $dom_esc; ?>
opendkim-genkey -b 2048 -d <?php echo $dom_esc; ?> -D /etc/opendkim/keys/<?php echo $dom_esc; ?> -s default
chown -R opendkim:opendkim /etc/opendkim
# Añade esta linea al final de /etc/mail/sendmail.mc antes de MAILER_DEFINITIONS:
# INPUT_MAIL_FILTER(`opendkim', `S=inet:8891@localhost')
m4 /etc/mail/sendmail.mc &gt; /etc/mail/sendmail.cf &amp;&amp; systemctl restart opendkim sendmail</pre>
                    <?php else : ?>
                        <p class="description" style="margin:8px 0 0;">No se ha detectado Postfix/Exim4/Sendmail local configurado con DKIM. Para evitar la carpeta SPAM de Gmail con <code>DMARC p=reject</code>, configura un <strong>plugin SMTP autenticado</strong> (Brevo, Amazon SES, Mailgun o Google Workspace) o instala <strong>Postfix + OpenDKIM</strong>.</p>
                    <?php endif; ?>
                    <p class="description" style="margin:8px 0 0;">Tras activar la firma en el servidor, copia la clave publica en un registro <strong>TXT</strong> llamado <code>default._domainkey.<?php echo $dom_esc; ?></code> en tu panel DNS.</p>
                </details>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}