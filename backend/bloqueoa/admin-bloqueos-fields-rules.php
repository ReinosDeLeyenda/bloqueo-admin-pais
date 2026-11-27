<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_bloqueos_rules_fields( $bl_raw ) {
    $country_mode  = get_option( 'bap_country_mode', 'allow' );
    $asn_mode      = get_option( 'bap_asn_mode', 'allow' );
    $default_paths = function_exists( 'bap_get_default_protected_paths' ) ? bap_get_default_protected_paths() : "/wp-admin/*\n/wp-login.php";
    $paths_val     = get_option( 'bap_protected_paths', null );
    if ( null === $paths_val ) {
        $paths_val = $default_paths;
    }
    $ht_writable = file_exists( ABSPATH . '.htaccess' ) && is_writable( ABSPATH . '.htaccess' );
    ?>
    <tr>
        <th>Rutas / Páginas a proteger</th>
        <td>
            <div style="display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap;">
                <textarea id="bap_protected_paths" name="bap_protected_paths" rows="8" style="width:100%;max-width:360px;" class="code"><?php echo esc_textarea( $paths_val ); ?></textarea>
                <button type="button" id="bap_restore_paths" class="button button-secondary" data-defaults="<?php echo esc_attr( $default_paths ); ?>">Restaurar rutas por defecto</button>
            </div>
            <p class="description">Una ruta o extensión por línea (admite comodín <code>*</code>).</p>
        </td>
    </tr>
    <tr>
        <th>Bloqueo rápido vía .htaccess</th>
        <td>
            <label>
                <input type="checkbox" name="bap_enable_htaccess" value="1" <?php checked( get_option( 'bap_enable_htaccess', '0' ), '1' ); ?> <?php disabled( ! $ht_writable ); ?> />
                Bloquear archivos sensibles (<code>*.log</code>, <code>*.zip</code>, <code>*.rar</code>, <code>*.tar.gz</code>, etc.) en Apache/LiteSpeed
            </label>
            <?php if ( ! $ht_writable ) : ?>
                <p class="description" style="color:#d63638;">El archivo <code>.htaccess</code> no existe o no tiene permisos de escritura.</p>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th><label for="bap_custom_login_slug">URL de Acceso Personalizada (Hide Login)</label></th>
        <td>
            <?php $cur_slug = (string) get_option( 'bap_custom_login_slug', '' ); ?>
            <code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
            <input type="text" id="bap_custom_login_slug" name="bap_custom_login_slug" value="<?php echo esc_attr( $cur_slug ); ?>" class="regular-text code" style="width:210px;" placeholder="ej. acceso-privado" />
            <p class="description">
                Oculta <code>/wp-login.php</code> y <code>/wp-admin</code> tras una ruta secreta (déjalo vacío para desactivar).
                <?php if ( '' !== $cur_slug ) : ?>
                    <br /><strong>Tu URL actual de acceso es:</strong> <code><?php echo esc_html( home_url( '/' . $cur_slug ) ); ?></code>
                <?php endif; ?>
            </p>
        </td>
    </tr>
    <tr>
        <th>Modo Ausente por Horario (Away Mode)</th>
        <td>
            <?php
            $away_days = (array) get_option( 'bap_away_mode_days', [ '1', '2', '3', '4', '5', '6', '7' ] );
            $day_names = [ '1' => 'Lun', '2' => 'Mar', '3' => 'Mié', '4' => 'Jue', '5' => 'Vie', '6' => 'Sáb', '7' => 'Dom' ];
            ?>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_away_mode_enabled" value="1" <?php checked( get_option( 'bap_away_mode_enabled', '0' ), '1' ); ?> />
                <strong>Bloquear el acceso al panel durante el horario fuera de oficina</strong>
            </label>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
                <label>Desde: <input type="time" name="bap_away_mode_start" value="<?php echo esc_attr( get_option( 'bap_away_mode_start', '23:00' ) ); ?>" /></label>
                <label>Hasta: <input type="time" name="bap_away_mode_end" value="<?php echo esc_attr( get_option( 'bap_away_mode_end', '07:00' ) ); ?>" /></label>
                <span class="description">(Hora local WP actual: <code><?php echo esc_html( current_time( 'H:i' ) ); ?></code>)</span>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php foreach ( $day_names as $d_num => $d_lbl ) : ?>
                    <label><input type="checkbox" name="bap_away_mode_days[]" value="<?php echo esc_attr( $d_num ); ?>" <?php checked( in_array( $d_num, $away_days, true ) ); ?> /> <?php echo esc_html( $d_lbl ); ?></label>
                <?php endforeach; ?>
            </div>
            <p class="description">Admite franjas nocturnas (ej. <code>23:00</code> a <code>07:00</code>). Las IPs en Lista Blanca y la Llave secreta URL siguen pudiendo acceder.</p>
        </td>
    </tr>
    <tr>
        <th><label style="display:flex;justify-content:space-between;cursor:pointer;"><span>Países PERMITIDOS</span><input type="radio" name="bap_country_mode" value="allow" <?php checked( $country_mode, 'allow' ); ?> /></label></th>
        <td><input type="text" id="bap_allowed_countries" name="bap_allowed_countries" value="<?php echo esc_attr( get_option( 'bap_allowed_countries', 'ES' ) ); ?>" class="regular-text" placeholder="Ej: ES, FR (vacío = desactivado)" /></td>
    </tr>
    <tr>
        <th><label style="display:flex;justify-content:space-between;cursor:pointer;"><span>Países BLOQUEADOS</span><input type="radio" name="bap_country_mode" value="block" <?php checked( $country_mode, 'block' ); ?> /></label></th>
        <td><input type="text" id="bap_blocked_countries" name="bap_blocked_countries" value="<?php echo esc_attr( get_option( 'bap_blocked_countries', '' ) ); ?>" class="regular-text" placeholder="Ej: RU, CN (vacío = desactivado)" /></td>
    </tr>
    <tr>
        <th><label style="display:flex;justify-content:space-between;cursor:pointer;"><span>ASNs PERMITIDOS</span><input type="radio" name="bap_asn_mode" value="allow" <?php checked( $asn_mode, 'allow' ); ?> /></label></th>
        <td><input type="text" id="bap_allowed_asns" name="bap_allowed_asns" value="<?php echo esc_attr( get_option( 'bap_allowed_asns', '' ) ); ?>" class="regular-text code" placeholder="Ej: AS3352, AS12479 (vacío = desactivado)" /></td>
    </tr>
    <tr>
        <th><label style="display:flex;justify-content:space-between;cursor:pointer;"><span>ASNs BLOQUEADOS</span><input type="radio" name="bap_asn_mode" value="block" <?php checked( $asn_mode, 'block' ); ?> /></label></th>
        <td><input type="text" id="bap_blocked_asns" name="bap_blocked_asns" value="<?php echo esc_attr( get_option( 'bap_blocked_asns', '' ) ); ?>" class="regular-text code" placeholder="Ej: AS16276, AS14061 (vacío = desactivado)" /></td>
    </tr></table></div><table class="form-table">
    <tr>
        <th>Auto-bloquear riesgo AbuseIPDB &ge;</th>
        <td><input type="number" min="0" max="100" name="bap_abuseipdb_min_risk" value="<?php echo (int) get_option( 'bap_abuseipdb_min_risk', 90 ); ?>" class="small-text" /> % <span class="description">(0 = desactivar)</span></td>
    </tr>
    <tr>
        <th>Bloqueo automático tras intentos</th>
        <td><input type="number" min="0" max="100" name="bap_auto_ban_attempts" value="<?php echo (int) get_option( 'bap_auto_ban_attempts', 5 ); ?>" class="small-text" /></td>
    </tr>
    <tr>
        <th><label for="bap_auto_ban_duration">Duración del bloqueo automático</label></th>
        <td><input type="number" id="bap_auto_ban_duration" name="bap_auto_ban_duration" value="<?php echo esc_attr( get_option( 'bap_auto_ban_duration', 24 ) ); ?>" min="0" step="1" class="small-text" /> horas <span class="description">(0 = permanente)</span></td>
    </tr>
    <tr>
        <th>Filtros de seguridad activos</th>
        <td>
            <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="bap_session_hijack_shield" value="1" <?php checked( get_option( 'bap_session_hijack_shield', '0' ), '1' ); ?> /> <strong>Escudo Anti-Secuestro de Sesion</strong> (vincular cookie a subred IP y navegador)</label>
            <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="bap_block_nonexistent_users" value="1" <?php checked( get_option( 'bap_block_nonexistent_users', '1' ), '1' ); ?> /> Bloquear usuarios o emails inexistentes al instante</label>
            <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="bap_block_xmlrpc" value="1" <?php checked( get_option( 'bap_block_xmlrpc', '1' ), '1' ); ?> /> Desactivar <code>xmlrpc.php</code> y pingbacks</label>
            <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="bap_block_user_enum" value="1" <?php checked( get_option( 'bap_block_user_enum', '1' ), '1' ); ?> /> Evitar enumeración de usuarios (<code>?author</code> y REST API)</label>
            <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="bap_block_tor" value="1" <?php checked( get_option( 'bap_block_tor', '1' ), '1' ); ?> /> Bloquear nodos de salida Tor</label>
            <label style="display:block;"><input type="checkbox" name="bap_block_firehol" value="1" <?php checked( get_option( 'bap_block_firehol', '1' ), '1' ); ?> /> Bloquear redes maliciosas FireHOL Level 1</label>
        </td>
    </tr>
    <tr>
        <th>Lista Blanca de IPs / CIDR</th>
        <td><textarea id="bap_whitelist_ips" name="bap_whitelist_ips" rows="5" style="width:100%;max-width:320px;" class="code"><?php echo esc_textarea( get_option( 'bap_whitelist_ips', '' ) ); ?></textarea></td>
    </tr>
    <tr>
        <th>Lista Negra de IPs / CIDR</th>
        <td>
            <textarea id="bap_blacklist_textarea" name="bap_blacklist_ips" rows="7" style="width:100%;max-width:320px;line-height:20px;font-size:13px;" class="code"><?php echo esc_textarea( $bl_raw ); ?></textarea>
            <div id="bap_ip_tooltip" style="display:none;position:fixed;z-index:99999;background:#1d2327;color:#fff;padding:10px 14px;border-radius:6px;font-size:12px;line-height:1.5;box-shadow:0 4px 12px rgba(0,0,0,0.3);pointer-events:none;max-width:340px;"></div>
        </td>
    </tr>
    <tr><th>Redirigir a (URL)</th><td><input type="url" name="bap_redirect_url" value="<?php echo esc_attr( get_option( 'bap_redirect_url', '' ) ); ?>" class="regular-text code" /></td></tr>
    <tr>
        <th>Llave secreta URL</th>
        <td>
            <div style="display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <input type="password" id="bap_secret_key_input" name="bap_secret_key" value="<?php echo esc_attr( get_option( 'bap_secret_key', '' ) ); ?>" class="regular-text code" dir="ltr" autocomplete="new-password" />
                <button type="button" id="bap_toggle_secret" class="button button-secondary" style="padding:0 8px;"><span class="dashicons dashicons-visibility" style="vertical-align:middle;"></span></button>
                <button type="button" id="bap_copy_secret" class="button button-secondary" style="padding:0 8px;"><span class="dashicons dashicons-admin-page" style="vertical-align:middle;"></span></button>
                <button type="button" id="bap_gen_secret" class="button button-secondary">Generar</button>
                <span id="bap_copy_feedback" style="color:#008a20;font-weight:bold;display:none;">¡Copiada!</span>
            </div>
            <div id="bap_key_strength" style="margin-top:6px;font-size:12px;font-weight:600;"></div>
        </td>
    </tr>
    <?php
}

add_action( 'admin_footer', function() {
    if ( 'bap-bloqueos' !== ( $_GET['page'] ?? '' ) ) { return; }
    ?>
    <style>
    tbody.bap-exclusive-group { display: table-row-group; box-shadow: 0 0 0 1px rgba(148,163,184,0.45); border-radius: 10px; background: rgba(148,163,184,0.04); }
    tbody.bap-exclusive-group tr.bap-group-header td { padding: 8px 12px 2px 12px; }
    .bap-exclusive-pill { display: inline-block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; padding: 2px 9px; border-radius: 999px; background: rgba(56,189,248,0.15); color: #38bdf8; }
    </style>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
      function groupExclusiveRows(labelA, labelB, title) {
        var ths = Array.from(document.querySelectorAll(".form-table th"));
        var thA = ths.find(function(el){ return el.textContent.indexOf(labelA) !== -1; });
        var thB = ths.find(function(el){ return el.textContent.indexOf(labelB) !== -1; });
        if (!thA || !thB) return;
        var trA = thA.closest("tr"), trB = thB.closest("tr");
        var table = trA.closest("table");
        var tbody = document.createElement("tbody");
        tbody.className = "bap-exclusive-group";
        var hdrTr = document.createElement("tr");
        hdrTr.className = "bap-group-header";
        hdrTr.innerHTML = "<td colspan=\"2\"><span class=\"bap-exclusive-pill\">" + title + "</span></td>";
        table.insertBefore(tbody, trA.parentNode);
        tbody.appendChild(hdrTr);
        tbody.appendChild(trA);
        tbody.appendChild(trB);
      }
      groupExclusiveRows("Países PERMITIDOS", "Países BLOQUEADOS", "Filtro de Países (Elige una opción u otra)");
      groupExclusiveRows("ASNs PERMITIDOS", "ASNs BLOQUEADOS", "Filtro de ASNs (Elige una opción u otra)");
    });
    </script>
    <?php
} );