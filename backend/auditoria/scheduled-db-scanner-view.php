<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_db_injection_and_cron_box() {
    $db_findings = null;
    $cron_notice = '';

    if ( isset( $_POST['bap_save_auto_scan'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_db_scan_action', 'bap_db_scan_nonce' ) ) {
        $freq = in_array( $_POST['bap_auto_scan_freq'] ?? 'none', [ 'none', 'daily', 'weekly' ], true ) ? $_POST['bap_auto_scan_freq'] : 'none';
        update_option( 'bap_auto_scan_freq', $freq );
        bap_reschedule_security_scan_cron( $freq );
        $cron_notice = '<div class="notice notice-success inline"><p><strong>Programación de escaneo automático guardada.</strong></p></div>';
    }

    if ( isset( $_POST['bap_run_db_injection_scan'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_db_scan_action', 'bap_db_scan_nonce' ) ) {
        $db_findings = bap_scan_database_injections();
    }

    $cur_freq  = (string) get_option( 'bap_auto_scan_freq', 'none' );
    $last_time = (string) get_option( 'bap_last_auto_scan_time', '' );
    ?>
    <div id="bap-box-db-inj" class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Escáner de Inyecciones en Base de Datos y Auditoría Programada</h2>
        <p class="description">Analiza entradas, páginas (<code>wp_posts</code>) y opciones (<code>wp_options</code>) en busca de scripts maliciosos ocultos y programa auditorías automáticas en segundo plano.</p>

        <?php echo wp_kses_post( $cron_notice ); ?>

        <form method="post" style="margin:14px 0;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_db_scan_action', 'bap_db_scan_nonce' ); ?>
            <button type="submit" name="bap_run_db_injection_scan" class="button button-primary">Escanear Base de Datos ahora</button>

            <label style="margin-left:auto;"><strong>Auditoría automática:</strong>
                <select name="bap_auto_scan_freq">
                    <option value="none" <?php selected( $cur_freq, 'none' ); ?>>Desactivada</option>
                    <option value="daily" <?php selected( $cur_freq, 'daily' ); ?>>Diaria (cada 24h)</option>
                    <option value="weekly" <?php selected( $cur_freq, 'weekly' ); ?>>Semanal (cada 7 días)</option>
                </select>
            </label>
            <button type="submit" name="bap_save_auto_scan" class="button button-secondary">Guardar programación</button>
        </form>

        <?php if ( '' !== $last_time ) : ?>
            <p class="description">Último escaneo automático ejecutado: <code><?php echo esc_html( $last_time ); ?></code></p>
        <?php endif; ?>

        <?php if ( is_array( $db_findings ) ) : ?>
            <?php if ( empty( $db_findings ) ) : ?>
                <div class="notice notice-success inline" style="margin-top:12px;"><p><strong>¡Base de datos limpia!</strong> No se encontraron scripts ni inyecciones sospechosas en posts ni opciones.</p></div>
            <?php else : ?>
                <table class="widefat striped" style="margin-top:10px;">
                    <thead><tr><th>Origen</th><th>Elemento</th><th>Detección</th></tr></thead>
                    <tbody>
                        <?php foreach ( $db_findings as $f ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $f['source'] ); ?></code></td>
                                <td><strong><?php echo esc_html( $f['target'] ); ?></strong></td>
                                <td><span style="color:#d63638;font-weight:600;"><?php echo esc_html( $f['type'] ); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <script>
    (function() {
      var box = document.getElementById("bap-box-db-injection");
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
            var fresh = doc.getElementById("bap-box-db-injection");
            if (fresh) { box.innerHTML = fresh.innerHTML; window.scrollTo(0, y); }
          })
          .catch(function(){ if (btn) { btn.disabled = false; btn.textContent = orig; } form.submit(); });
      });
    })();
    </script>
    <?php
}