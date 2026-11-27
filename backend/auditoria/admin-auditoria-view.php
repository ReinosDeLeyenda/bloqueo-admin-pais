<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_auditoria_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $sub   = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'escaneres';
    $valid = [
        'escaneres' => 'Escáneres',
        'hardening' => 'Hardening y Protección',
        'cron'      => 'Tareas Cron',
    ];
    if ( ! isset( $valid[ $sub ] ) ) {
        $sub = 'escaneres';
    }
    $base_url = admin_url( 'admin.php?page=bap-auditoria' );

    $all_scan_report = null;
    if ( 'escaneres' === $sub && isset( $_POST['bap_run_all_scans'] ) && check_admin_referer( 'bap_all_scans_action', 'bap_all_scans_nonce' ) ) {
        if ( function_exists( 'bap_execute_all_security_scans' ) ) {
            $all_scan_report = bap_execute_all_security_scans();
        }
    }
    ?>
    <div class="wrap">
        <h1>Control de Acceso al Panel</h1>
        <?php bap_render_tabs( 'bap-auditoria' ); ?>

        <?php bap_render_subtabs( $base_url, $valid, $sub ); ?>

        <?php if ( 'cron' === $sub ) : ?>
            <?php if ( function_exists( 'bap_render_wp_cron_inspector_box' ) ) { bap_render_wp_cron_inspector_box(); } ?>
        <?php elseif ( 'hardening' === $sub ) : ?>
            <?php if ( function_exists( 'bap_render_ssl_and_config_monitor_box' ) ) { bap_render_ssl_and_config_monitor_box(); } ?>
            <?php if ( function_exists( 'bap_render_server_stack_auditor_box' ) ) { bap_render_server_stack_auditor_box(); } ?>
            <?php if ( function_exists( 'bap_render_headers_and_mail_dns_box' ) ) { bap_render_headers_and_mail_dns_box(); } ?>
            <?php if ( function_exists( 'bap_render_dnsbl_reputation_box' ) ) { bap_render_dnsbl_reputation_box(); } ?>
            <?php if ( function_exists( 'bap_render_dns_hijack_monitor_box' ) ) { bap_render_dns_hijack_monitor_box(); } ?>
            <?php if ( function_exists( 'bap_render_hardening_box' ) ) { bap_render_hardening_box(); } ?>
            <?php if ( function_exists( 'bap_render_emergency_mode_box' ) ) { bap_render_emergency_mode_box(); } ?>
            <?php if ( function_exists( 'bap_render_early_waf_box' ) ) { bap_render_early_waf_box(); } ?>
            <?php if ( function_exists( 'bap_render_virtual_patch_box' ) ) { bap_render_virtual_patch_box(); } ?>
            <?php if ( function_exists( 'bap_render_outbound_firewall_box' ) ) { bap_render_outbound_firewall_box(); } ?>
        <?php else : ?>
            <?php if ( function_exists( 'bap_render_executive_report_card' ) ) { bap_render_executive_report_card(); } ?>
            <div id="bap-box-admin-auditoria-view" class="card" style="max-width:980px;padding:16px 22px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h2 style="margin:0;">Auditoría Completa de Seguridad</h2>
                        <p class="description" style="margin:4px 0 0 0;">Ejecuta de una sola vez todos los escáneres de integridad, vulnerabilidades CVE, malware, plugins abandonados, base de datos y permisos.</p>
                    </div>
                    <form method="post" action="<?php echo esc_url( add_query_arg( 'subtab', 'escaneres', $base_url ) ); ?>" style="margin:0;">
                        <?php wp_nonce_field( 'bap_all_scans_action', 'bap_all_scans_nonce' ); ?>
                        <button type="submit" name="bap_run_all_scans" class="button button-primary button-large">Ejecutar TODOS los escáneres</button>
                    </form>
                </div>

                <?php if ( is_array( $all_scan_report ) ) : ?>
                    <table class="widefat striped" style="margin-top:14px;">
                        <thead>
                            <tr>
                                <th style="width:240px;">Escáner</th>
                                <th style="width:110px;">Estado</th>
                                <th>Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $all_scan_report as $row ) :
                                $bg = ( 'error' === $row['status'] ) ? '#d63638' : ( ( 'warning' === $row['status'] ) ? '#d97706' : '#00a32a' );
                                $tx = ( 'error' === $row['status'] ) ? 'ALERTA' : ( ( 'warning' === $row['status'] ) ? 'REVISAR' : 'LIMPIO' );
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $row['name'] ); ?></strong></td>
                                    <td><span style="background:<?php echo esc_attr( $bg ); ?>;color:#fff;padding:2px 7px;border-radius:3px;font-weight:700;font-size:11px;"><?php echo esc_html( $tx ); ?></span></td>
                                    <td>
                                        <?php echo esc_html( $row['detail'] ); ?><?php if ( ! empty( $row['link'] ) && 'ok' !== $row['status'] ) : ?> <a href="<?php echo esc_url( $row['link'] ); ?>" class="button button-small" style="margin-left:8px;">Ir a revisar &rarr;</a><?php endif; ?>
                                        <?php if ( ! empty( $row['items'] ) && is_array( $row['items'] ) ) : ?>
                                            <ul style="margin:6px 0 2px 16px;list-style:disc;color:#b91c1c;font-size:12px;">
                                                <?php foreach ( $row['items'] as $sub_item ) : ?>
                                                    <li><code><?php echo esc_html( $sub_item ); ?></code></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if ( function_exists( 'bap_render_core_integrity_box' ) ) { bap_render_core_integrity_box(); } ?>
            <?php if ( function_exists( 'bap_render_root_directory_watcher_box' ) ) { bap_render_root_directory_watcher_box(); } ?>
            <?php if ( function_exists( 'bap_render_unused_files_scanner_box' ) ) { bap_render_unused_files_scanner_box(); } ?>
            <?php if ( function_exists( 'bap_render_plugin_usage_scanner_box' ) ) { bap_render_plugin_usage_scanner_box(); } ?>
            <?php if ( function_exists( 'bap_render_server_error_log_scanner_box' ) ) { bap_render_server_error_log_scanner_box(); } ?>
            <?php if ( function_exists( 'bap_render_ssh_auth_log_scanner_box' ) ) { bap_render_ssh_auth_log_scanner_box(); } ?>
            <?php if ( function_exists( 'bap_render_custom_integrity_box' ) ) { bap_render_custom_integrity_box(); } ?>
            <?php if ( function_exists( 'bap_render_cve_scanner_box' ) ) { bap_render_cve_scanner_box(); } ?>
            <?php if ( function_exists( 'bap_render_malware_scanner_box' ) ) { bap_render_malware_scanner_box(); } ?>
            <?php if ( function_exists( 'bap_render_quarantine_vault_box' ) ) { bap_render_quarantine_vault_box(); } ?>
            <?php if ( function_exists( 'bap_render_abandoned_plugins_box' ) ) { bap_render_abandoned_plugins_box(); } ?>
            <?php if ( function_exists( 'bap_render_db_injection_and_cron_box' ) ) { bap_render_db_injection_and_cron_box(); } ?>
            <?php if ( function_exists( 'bap_render_db_schema_monitor_box' ) ) { bap_render_db_schema_monitor_box(); } ?>
            <?php if ( function_exists( 'bap_render_options_autoload_auditor_box' ) ) { bap_render_options_autoload_auditor_box(); } ?>
            <?php if ( function_exists( 'bap_render_seo_spam_scanner_box' ) ) { bap_render_seo_spam_scanner_box(); } ?>
            <?php if ( function_exists( 'bap_render_content_defacement_box' ) ) { bap_render_content_defacement_box(); } ?>
            <?php if ( function_exists( 'bap_render_file_permissions_box' ) ) { bap_render_file_permissions_box(); } ?>
            <?php if ( function_exists( 'bap_render_rogue_admin_box' ) ) { bap_render_rogue_admin_box(); } ?>
            <?php if ( function_exists( 'bap_render_roles_integrity_box' ) ) { bap_render_roles_integrity_box(); } ?>
            <?php if ( function_exists( 'bap_render_weak_passwords_auditor_box' ) ) { bap_render_weak_passwords_auditor_box(); } ?>
            
        <?php endif; ?>
    </div>
    <script>
    (function() {
      var box = document.getElementById("bap-box-admin-auditoria-view");
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
            var fresh = doc.getElementById("bap-box-admin-auditoria-view");
            if (fresh) { box.innerHTML = fresh.innerHTML; window.scrollTo(0, y); }
          })
          .catch(function(){ if (btn) { btn.disabled = false; btn.textContent = orig; } form.submit(); });
      });
    })();
    </script>
    <?php
}