<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }



function bap_dns_mon_fetch_records() {
    $domain = bap_get_site_root_domain();
    if ( '' === $domain || in_array( $domain, [ 'localhost', '127.0.0.1', '::1' ], true ) || filter_var( $domain, FILTER_VALIDATE_IP ) ) {
        return new WP_Error( 'local_dom', 'Entorno local o IP directa: no aplica monitorizacion DNS publica.' );
    }

    $ns_list = [];
    foreach ( (array) @dns_get_record( $domain, DNS_NS ) as $r ) {
        if ( ! empty( $r['target'] ) ) { $ns_list[] = strtolower( rtrim( (string) $r['target'], '.' ) ); }
    }
    sort( $ns_list );
    $ns_list = array_values( array_unique( $ns_list ) );

    $a_list = [];
    foreach ( (array) @dns_get_record( $domain, DNS_A ) as $r ) {
        if ( ! empty( $r['ip'] ) ) { $a_list[] = (string) $r['ip']; }
    }
    sort( $a_list );
    $a_list = array_values( array_unique( $a_list ) );

    $aaaa_list = [];
    foreach ( (array) @dns_get_record( $domain, DNS_AAAA ) as $r ) {
        if ( ! empty( $r['ipv6'] ) ) { $aaaa_list[] = strtolower( (string) $r['ipv6'] ); }
    }
    sort( $aaaa_list );
    $aaaa_list = array_values( array_unique( $aaaa_list ) );

    $mx_list = [];
    foreach ( (array) @dns_get_record( $domain, DNS_MX ) as $r ) {
        if ( ! empty( $r['target'] ) ) { $mx_list[] = strtolower( rtrim( (string) $r['target'], '.' ) ); }
    }
    sort( $mx_list );
    $mx_list = array_values( array_unique( $mx_list ) );

    $records = [
        'NS'   => implode( ', ', $ns_list ) ?: 'N/D',
        'A'    => implode( ', ', $a_list ) ?: 'N/D',
        'AAAA' => implode( ', ', $aaaa_list ) ?: 'Ninguno',
        'MX'   => implode( ', ', $mx_list ) ?: 'Ninguno',
    ];

    return [
        'domain'   => $domain,
        'records'  => $records,
        'sha256'   => hash( 'sha256', wp_json_encode( $records ) ),
        'saved_at' => current_time( 'mysql' ),
    ];
}

function bap_dns_mon_refresh_baseline() {
    $snap = bap_dns_mon_fetch_records();
    if ( ! is_wp_error( $snap ) ) {
        update_option( 'bap_dns_baseline_data', $snap, false );
    }
    return $snap;
}

function bap_dns_mon_verify() {
    $cur = bap_dns_mon_fetch_records();
    if ( is_wp_error( $cur ) ) { return $cur; }

    $stored = get_option( 'bap_dns_baseline_data', null );
    if ( ! is_array( $stored ) || empty( $stored['sha256'] ) || ( $stored['domain'] ?? '' ) !== $cur['domain'] ) {
        update_option( 'bap_dns_baseline_data', $cur, false );
        $stored = $cur;
    }

    $diffs = [];
    foreach ( [ 'NS', 'A', 'AAAA', 'MX' ] as $type ) {
        $old_v = (string) ( $stored['records'][ $type ] ?? 'N/D' );
        $new_v = (string) ( $cur['records'][ $type ] ?? 'N/D' );
        if ( $old_v !== $new_v ) {
            $diffs[ $type ] = [ 'old' => $old_v, 'new' => $new_v ];
        }
    }

    return [
        'domain'   => $cur['domain'],
        'changed'  => ! empty( $diffs ),
        'diffs'    => $diffs,
        'records'  => $cur['records'],
        'saved_at' => (string) ( $stored['saved_at'] ?? 'N/D' ),
    ];
}
function bap_render_dns_hijack_monitor_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    if ( isset( $_POST['bap_approve_dns_baseline'] ) && check_admin_referer( 'bap_dns_mon_action', 'bap_dns_mon_nonce' ) ) {
        $saved  = bap_dns_mon_refresh_baseline();
        $notice = is_wp_error( $saved )
            ? '<div class="notice notice-warning inline"><p>' . esc_html( $saved->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success inline"><p><strong>Huella DNS actualizada:</strong> Registros actuales aprobados como referencia segura.</p></div>';
    }

    $res = bap_dns_mon_verify();
    ?>
    <div id="bap-box-dns-hijack-monitor" class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Monitor de Cambios DNS del Dominio (Anti-DNS Hijacking)</h2>
        <p class="description">Vigila los registros publicos <code>NS</code>, <code>A</code>, <code>AAAA</code> y <code>MX</code> de tu dominio para detectar secuestros de DNS o cambios no autorizados en el registrador.</p>
        <?php echo wp_kses_post( $notice ); ?>

        <?php if ( is_wp_error( $res ) ) : ?>
            <p class="description" style="margin-top:10px;"><?php echo esc_html( $res->get_error_message() ); ?></p>
        <?php else : ?>
            <p style="margin:10px 0;">
                <strong>Dominio:</strong> <code><?php echo esc_html( $res['domain'] ); ?></code> &nbsp;|&nbsp;
                <strong>Estado:</strong>
                <?php if ( ! $res['changed'] ) : ?>
                    <span style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:3px;font-weight:700;font-size:11px;">SIN CAMBIOS</span>
                <?php else : ?>
                    <span style="background:#d63638;color:#fff;padding:2px 8px;border-radius:3px;font-weight:700;font-size:11px;">CAMBIO DETECTADO</span>
                <?php endif; ?>
                &nbsp;|&nbsp; Referencia: <code><?php echo esc_html( $res['saved_at'] ); ?></code>
            </p>

            <table class="widefat striped" style="margin-top:10px;">
                <thead><tr><th style="width:90px;">Tipo</th><th>Valor actual</th><th style="width:130px;">Estado</th></tr></thead>
                <tbody>
                    <?php foreach ( $res['records'] as $type => $val ) :
                        $is_diff = isset( $res['diffs'][ $type ] );
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html( $type ); ?></strong></td>
                            <td>
                                <code><?php echo esc_html( $val ); ?></code>
                                <?php if ( $is_diff ) : ?>
                                    <br><small style="color:#d63638;">Anterior: <code><?php echo esc_html( $res['diffs'][ $type ]['old'] ); ?></code></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $is_diff ) : ?>
                                    <?php echo bap_render_status_badge( 'ALTERADO', '#d63638' ); ?>
                                <?php else : ?>
                                    <span style="color:#008a20;font-weight:600;">Verificado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" style="margin-top:12px;">
                <?php wp_nonce_field( 'bap_dns_mon_action', 'bap_dns_mon_nonce' ); ?>
                <button type="submit" name="bap_approve_dns_baseline" class="button button-secondary">Aprobar / Actualizar huella DNS actual</button>
            </form>
        <?php endif; ?>
    </div>
    <script>
    (function() {
      var box = document.getElementById("bap-box-dns-hijack-monitor");
      if (!box || !window.fetch || box.dataset.bapAjaxBound) return;
      box.dataset.bapAjaxBound = "1";
      var lastBtn = null;
      box.addEventListener("click", function(e){ var b = e.target.closest("button[type=submit]"); if (b) lastBtn = b; });
      box.addEventListener("submit", function(e) {
        var form = e.target;
        if (!form || form.tagName !== "FORM") return;
        e.preventDefault();
        var y = window.scrollY;
        var btn = lastBtn || form.querySelector("button[type=submit]");
        var fd = new FormData(form);
        if (btn && btn.name) fd.append(btn.name, btn.value || "1");
        var orig = btn ? btn.textContent : "";
        if (btn) { btn.disabled = true; btn.textContent = "Procesando..."; }
        fetch(window.location.href, { method: "POST", body: fd, credentials: "same-origin" })
          .then(function(r){ return r.text(); })
          .then(function(html){
            var doc = new DOMParser().parseFromString(html, "text/html");
            var fresh = doc.getElementById("bap-box-dns-hijack-monitor");
            if (fresh) { box.innerHTML = fresh.innerHTML; window.scrollTo(0, y); }
          })
          .catch(function(){ if (btn) { btn.disabled = false; btn.textContent = orig; } form.submit(); });
      });
    })();
    </script>
    <?php
}