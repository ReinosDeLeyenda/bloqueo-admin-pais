<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-admin-bloqueos' ); ?>
    <?php settings_errors(); ?>
    <?php echo $test_notice; ?>

    <div class="notice notice-info inline">
        <p><strong>Tu conexión actual:</strong> IP <code><?php echo esc_html( $ip ); ?></code> | País <code><?php echo esc_html( $ctry ); ?></code> <?php echo $src ? '(' . esc_html( $src ) . ')' : ''; ?> | ASN <code><?php echo esc_html( bap_get_asn_by_ip( $ip ) ); ?></code></p>
    </div>

    <?php if ( function_exists( 'bap_render_ip_simulator_box' ) ) { bap_render_ip_simulator_box(); } ?>

    <div class="card" style="max-width:960px;padding:16px 24px;margin-top:12px;">
        <form id="bap_rules_form" method="post" action="options.php">
            <?php settings_fields( 'bap_settings_group' ); ?>
            <table class="form-table">
                <?php
                $country_mode = get_option( 'bap_country_mode', 'allow' );
                $asn_mode     = get_option( 'bap_asn_mode', 'allow' );
                ?>
                <tr>
                    <th><label for="bap_ip_source">Detección de IP del visitante</label></th>
                    <td>
                        <?php $ip_source = get_option( 'bap_ip_source', 'remote_addr' ); ?>
                        <select id="bap_ip_source" name="bap_ip_source">
                            <option value="remote_addr" <?php selected( $ip_source, 'remote_addr' ); ?>>Conexión directa — REMOTE_ADDR (Recomendado / Anti-Spoofing)</option>
                            <option value="cloudflare" <?php selected( $ip_source, 'cloudflare' ); ?>>Cloudflare CDN — HTTP_CF_CONNECTING_IP</option>
                            <option value="x_forwarded_for" <?php selected( $ip_source, 'x_forwarded_for' ); ?>>Proxy inverso / Balanceador — HTTP_X_FORWARDED_FOR</option>
                            <option value="x_real_ip" <?php selected( $ip_source, 'x_real_ip' ); ?>>Nginx Proxy — HTTP_X_REAL_IP</option>
                        </select>
                        <p class="description">
                            Usa <strong>REMOTE_ADDR</strong> salvo que tu web pase por Cloudflare o un proxy inverso; de lo contrario, un atacante podría falsificar cabeceras HTTP para saltarse el bloqueo.
                            <?php if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && 'cloudflare' !== $ip_source ) : ?>
                                <br /><span style="color:#d97706;font-weight:600;">Aviso: Se ha detectado tráfico de Cloudflare (<code><?php echo esc_html( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) ); ?></code>). Considera seleccionar Cloudflare CDN.</span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Rutas / Páginas a proteger</th>
                    <td>
                        <?php
                        $default_paths = function_exists( 'bap_get_default_protected_paths' ) ? bap_get_default_protected_paths() : "/wp-admin/*\n/wp-login.php";
                        $paths_val     = get_option( 'bap_protected_paths', null );
                        if ( null === $paths_val ) {
                            $paths_val = $default_paths;
                        }
                        ?>
                        <div style="display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap;">
                            <textarea id="bap_protected_paths" name="bap_protected_paths" rows="8" style="width:100%;max-width:360px;" class="code"><?php echo esc_textarea( $paths_val ); ?></textarea>
                            <button type="button" id="bap_restore_paths" class="button button-secondary" data-defaults="<?php echo esc_attr( $default_paths ); ?>">Restaurar rutas por defecto</button>
                        </div>
                        <p class="description">Una ruta o extensión por línea (admite comodín <code>*</code>). Protege panel, login, <code>xmlrpc.php</code>, usuarios REST API, logs y archivos comprimidos/backups (<code>*.log</code>, <code>*.zip</code>, <code>*.rar</code>, <code>*.tar.gz</code>, etc.).</p>
                    </td>
                </tr>
                <tr>
                    <th>Bloqueo rápido vía .htaccess</th>
                    <td>
                        <?php $ht_writable = file_exists( ABSPATH . '.htaccess' ) && is_writable( ABSPATH . '.htaccess' ); ?>
                        <label>
                            <input type="checkbox" name="bap_enable_htaccess" value="1" <?php checked( get_option( 'bap_enable_htaccess', '0' ), '1' ); ?> <?php disabled( ! $ht_writable ); ?> />
                            Bloquear archivos sensibles (<code>*.log</code>, <code>*.zip</code>, <code>*.rar</code>, <code>*.tar.gz</code>, etc.) directamente en Apache/LiteSpeed antes de cargar PHP
                        </label>
                        <?php if ( ! $ht_writable ) : ?>
                            <p class="description" style="color:#d63638;">El archivo <code>.htaccess</code> no existe o no tiene permisos de escritura (solo aplicable en servidores Apache/LiteSpeed).</p>
                        <?php else : ?>
                            <p class="description">Recomendado para ahorrar recursos del servidor. Si entras con tu <strong>Llave secreta URL</strong>, podrás descargar tus backups sin que te bloquee.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;">
                            <span>Países PERMITIDOS</span>
                            <input type="radio" name="bap_country_mode" value="allow" <?php checked( $country_mode, 'allow' ); ?> />
                        </label>
                    </th>
                    <td>
                        <input type="text" id="bap_allowed_countries" name="bap_allowed_countries" value="<?php echo esc_attr( get_option( 'bap_allowed_countries', 'ES' ) ); ?>" class="regular-text" placeholder="Ej: ES, FR (vacío = desactivado)" />
                    </td>
                </tr>
                <tr>
                    <th>
                        <label style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;">
                            <span>Países BLOQUEADOS</span>
                            <input type="radio" name="bap_country_mode" value="block" <?php checked( $country_mode, 'block' ); ?> />
                        </label>
                    </th>
                    <td>
                        <input type="text" id="bap_blocked_countries" name="bap_blocked_countries" value="<?php echo esc_attr( get_option( 'bap_blocked_countries', '' ) ); ?>" class="regular-text" placeholder="Ej: RU, CN (vacío = desactivado)" />
                    </td>
                </tr>
                <tr>
                    <th>
                        <label style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;">
                            <span>ASNs PERMITIDOS</span>
                            <input type="radio" name="bap_asn_mode" value="allow" <?php checked( $asn_mode, 'allow' ); ?> />
                        </label>
                    </th>
                    <td>
                        <input type="text" id="bap_allowed_asns" name="bap_allowed_asns" value="<?php echo esc_attr( get_option( 'bap_allowed_asns', '' ) ); ?>" class="regular-text code" placeholder="Ej: AS3352, AS12479 (vacío = desactivado)" />
                        <p class="description">Permite el acceso directo a redes completas separadas por comas.</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;">
                            <span>ASNs BLOQUEADOS</span>
                            <input type="radio" name="bap_asn_mode" value="block" <?php checked( $asn_mode, 'block' ); ?> />
                        </label>
                    </th>
                    <td>
                        <input type="text" id="bap_blocked_asns" name="bap_blocked_asns" value="<?php echo esc_attr( get_option( 'bap_blocked_asns', '' ) ); ?>" class="regular-text code" placeholder="Ej: AS16276, AS14061 (vacío = desactivado)" />
                        <p class="description">Bloquea rangos completos de proveedores separados por comas.</p>
                    </td>
                </tr>
                <tr>
                    <th>Auto-bloquear riesgo AbuseIPDB &ge;</th>
                    <td><input type="number" min="0" max="100" name="bap_abuseipdb_min_risk" value="<?php echo (int) get_option( 'bap_abuseipdb_min_risk', 90 ); ?>" class="small-text" /> % <span class="description">(<code>0</code> para desactivar)</span></td>
                </tr>
                <tr>
                    <th>Bloqueo automático tras intentos</th>
                    <td><input type="number" min="0" max="100" name="bap_auto_ban_attempts" value="<?php echo (int) get_option( 'bap_auto_ban_attempts', 5 ); ?>" class="small-text" /></td>
                </tr>
                <tr>
                    <th><label for="bap_auto_ban_duration">Duración del bloqueo automático</label></th>
                    <td>
                        <input type="number" id="bap_auto_ban_duration" name="bap_auto_ban_duration" value="<?php echo esc_attr( get_option( 'bap_auto_ban_duration', 24 ) ); ?>" min="0" step="1" class="small-text" /> horas
                        <p class="description">Tiempo que una IP bloqueada automáticamente permanecerá en la lista negra antes de liberarse (0 = permanente)</p>
                    </td>
                </tr>
                <tr>
                    <th>Bloquear usuarios inexistentes</th>
                    <td>
                        <label>
                            <input type="checkbox" name="bap_block_nonexistent_users" value="1" <?php checked( get_option( 'bap_block_nonexistent_users', '1' ), '1' ); ?> />
                            Bloquear y registrar al instante cualquier intento de acceso con un usuario o email que no exista
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Bloquear XML-RPC</th>
                    <td>
                        <label>
                            <input type="checkbox" name="bap_block_xmlrpc" value="1" <?php checked( get_option( 'bap_block_xmlrpc', '1' ), '1' ); ?> />
                            Desactivar <code>xmlrpc.php</code> y métodos pingback para frenar ataques de fuerza bruta y DDoS
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Evitar enumeración de usuarios</th>
                    <td>
                        <label>
                            <input type="checkbox" name="bap_block_user_enum" value="1" <?php checked( get_option( 'bap_block_user_enum', '1' ), '1' ); ?> />
                            Bloquear consultas de autor y ocultar el endpoint de usuarios REST API a visitantes no autenticados
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Bloquear Nodos Tor</th>
                    <td>
                        <label>
                            <input type="checkbox" name="bap_block_tor" value="1" <?php checked( get_option( 'bap_block_tor', '1' ), '1' ); ?> />
                            Bloquear accesos desde nodos de salida de la red Tor
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Bloquear Redes FireHOL Level 1</th>
                    <td>
                        <label>
                            <input type="checkbox" name="bap_block_firehol" value="1" <?php checked( get_option( 'bap_block_firehol', '1' ), '1' ); ?> />
                            Bloquear subredes secuestradas/maliciosas (Spamhaus DROP / FireHOL)
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Lista Blanca de IPs / Rangos CIDR (ej. 192.168.1.0/24 o 2001:db8::/32)</th>
                    <td><textarea id="bap_whitelist_ips" name="bap_whitelist_ips" rows="5" style="width:100%;max-width:320px;" class="code"><?php echo esc_textarea( get_option( 'bap_whitelist_ips', '' ) ); ?></textarea></td>
                </tr>
                <tr>
                    <th>Lista Negra de IPs / Rangos CIDR (Bloqueadas) (ej. 185.220.101.0/24)</th>
                    <td>
                        <textarea id="bap_blacklist_textarea" name="bap_blacklist_ips" rows="7" style="width:100%;max-width:320px;line-height:20px;font-size:13px;" class="code"><?php echo esc_textarea( $bl_raw ); ?></textarea>
                        <p class="description">Pasa el cursor sobre cualquier IP del cuadro para ver su <strong>país</strong>, <strong>ASN</strong>, <strong>intentos</strong> y <strong>riesgo AbuseIPDB</strong>.</p>
                        <div id="bap_ip_tooltip" style="display:none;position:fixed;z-index:99999;background:#1d2327;color:#fff;padding:10px 14px;border-radius:6px;font-size:12px;line-height:1.5;box-shadow:0 4px 12px rgba(0,0,0,0.3);pointer-events:none;max-width:340px;"></div>
                    </td>
                </tr>
                <tr><th>Redirigir a (URL)</th><td><input type="url" name="bap_redirect_url" value="<?php echo esc_attr( get_option( 'bap_redirect_url', '' ) ); ?>" class="regular-text code" /></td></tr>
                <tr>
                    <th>Llave secreta URL</th>
                    <td>
                        <div style="display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap;">
                            <input type="password" id="bap_secret_key_input" name="bap_secret_key" value="<?php echo esc_attr( get_option( 'bap_secret_key', '' ) ); ?>" class="regular-text code" dir="ltr" autocomplete="new-password" />
                            <button type="button" id="bap_toggle_secret" class="button button-secondary" title="Mostrar / Ocultar clave" style="padding:0 8px;"><span class="dashicons dashicons-visibility" style="vertical-align:middle;"></span></button>
                            <button type="button" id="bap_copy_secret" class="button button-secondary" title="Copiar clave" style="padding:0 8px;"><span class="dashicons dashicons-admin-page" style="vertical-align:middle;"></span></button>
                            <button type="button" id="bap_gen_secret" class="button button-secondary" title="Generar clave aleatoria segura">Generar</button>
                            <span id="bap_copy_feedback" style="color:#008a20;font-weight:bold;display:none;">¡Copiada!</span>
                        </div>
                        <div id="bap_key_strength" style="margin-top:6px;font-size:12px;font-weight:600;"></div>
                        <p class="description" style="line-height:1.6;">
                            <strong>Requisitos mínimos:</strong><br />
                            15 chars (Mayús+Minús+Núm+Símb)<br />
                            20 chars (3 tipos)<br />
                            25 chars (2 tipos)<br />
                            30 chars (1 tipo)<br />
                            Alfabetos LTR y símbolos 100 % seguros en URL: <code>- _ . ~ ! * : @</code>.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Notificaciones por email</th>
                    <td>
                        <select name="bap_notify_freq">
                            <option value="none" <?php selected( $freq, 'none' ); ?>>Sin notificaciones</option>
                            <option value="instant" <?php selected( $freq, 'instant' ); ?>>Email individual (1 intento = 1 email)</option>
                            <option value="hourly" <?php selected( $freq, 'hourly' ); ?>>Email cada hora (todos los intentos de 1 hora)</option>
                            <option value="6hours" <?php selected( $freq, '6hours' ); ?>>Email cada 6 horas (todos los intentos de 6 horas)</option>
                            <option value="12hours" <?php selected( $freq, '12hours' ); ?>>Email cada 12 horas (todos los intentos de 12 horas)</option>
                            <option value="daily" <?php selected( $freq, 'daily' ); ?>>Email cada día (todos los intentos de 1 día)</option>
                            <option value="weekly" <?php selected( $freq, 'weekly' ); ?>>Email cada semana (todos los intentos de 1 semana)</option>
                            <option value="2weeks" <?php selected( $freq, '2weeks' ); ?>>Email cada 2 semanas (todos los intentos de 2 semanas)</option>
                            <option value="monthly" <?php selected( $freq, 'monthly' ); ?>>Email cada mes (todos los intentos de 1 mes)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="bap_email_template">Plantilla del email de resumen</label></th>
                    <td>
                        <?php $email_tpl = get_option( 'bap_email_template', 'full' ); ?>
                        <select id="bap_email_template" name="bap_email_template">
                            <option value="full" <?php selected( $email_tpl, 'full' ); ?>>Completa (Tarjetas + Barras Países + Todos los eventos)</option>
                            <option value="top10" <?php selected( $email_tpl, 'top10' ); ?>>Top 10 (Tarjetas + Barras Países + Top 10 bloqueos)</option>
                            <option value="top5" <?php selected( $email_tpl, 'top5' ); ?>>Top 5 (Tarjetas + Barras Países + Top 5 bloqueos)</option>
                            <option value="text" <?php selected( $email_tpl, 'text' ); ?>>Solo texto plano (Agrupado)</option>
                            <?php $custom_tpls = function_exists( 'bap_get_custom_email_templates' ) ? bap_get_custom_email_templates() : []; ?>
                            <?php if ( ! empty( $custom_tpls ) ) : ?>
                                <optgroup label="Mis plantillas personalizadas">
                                    <?php foreach ( $custom_tpls as $cid => $ctpl ) : ?>
                                        <option value="<?php echo esc_attr( 'custom_' . $cid ); ?>" <?php selected( $email_tpl, 'custom_' . $cid ); ?>><?php echo esc_html( $ctpl['name'] ); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <p class="description">Elige el formato y nivel de detalle de los correos de resumen programados (recuerda guardar antes de enviar prueba).</p>
                    </td>
                </tr>
                <tr>
                    <th>Destinatarios de emails</th>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <input type="text" name="bap_notify_emails" value="<?php echo esc_attr( get_option( 'bap_notify_emails', '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
                            <button type="submit" form="bap_test_email_form" name="bap_send_test_email" value="1" class="button button-secondary">Enviar email de prueba</button>
                            <button type="submit" form="bap_test_email_form" name="bap_send_test_digest" value="1" class="button button-secondary">Enviar email de prueba de resumen</button>
                        </div>
                        <p class="description">Uno o varios emails separados por comas (recuerda guardar primero si cambias el destinatario).</p>
                    </td>
                </tr>
                <tr>
                    <th>Contraseñas filtradas (Have I Been Pwned)</th>
                    <td>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="bap_hibp_enabled" value="1" <?php checked( get_option( 'bap_hibp_enabled', '1' ), '1' ); ?> />
                            Impedir guardar o restablecer contraseñas que aparezcan en brechas públicas (k-Anonymity)
                        </label>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="bap_hibp_check_login" value="1" <?php checked( get_option( 'bap_hibp_check_login', '1' ), '1' ); ?> />
                            Comprobar contraseña al iniciar sesión
                        </label>
                        <select name="bap_hibp_login_action">
                            <option value="warn" <?php selected( get_option( 'bap_hibp_login_action', 'warn' ), 'warn' ); ?>>Permitir acceso pero mostrar alerta roja en el panel</option>
                            <option value="block" <?php selected( get_option( 'bap_hibp_login_action', 'warn' ), 'block' ); ?>>Bloquear inicio de sesión y obligar a restablecer contraseña</option>
                        </select>
                        <p class="description">Consulta segura mediante hash parcial SHA-1 (nunca se envía la contraseña real por internet).</p>
                    </td>
                </tr>
                <tr>
                    <th>Autenticación en Dos Factores (2FA)</th>
                    <td>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="bap_2fa_enabled" value="1" <?php checked( get_option( 'bap_2fa_enabled', '0' ), '1' ); ?> />
                            Habilitar 2FA (App Autenticadora TOTP, Código por Email y Códigos de Respaldo) en los perfiles de usuario
                        </label>
                        <label style="display:block;">
                            <input type="checkbox" name="bap_2fa_enforce_admins" value="1" <?php checked( get_option( 'bap_2fa_enforce_admins', '0' ), '1' ); ?> />
                            Obligar 2FA a todos los <strong>Administradores</strong> (si no han configurado App, usará su Email automáticamente)
                        </label>
                        <label style="display:block;margin-top:6px;">
                            <input type="checkbox" name="bap_2fa_remember_device" value="1" <?php checked( get_option( 'bap_2fa_remember_device', '1' ), '1' ); ?> />
                            Permitir marcar <strong>"Recordar este dispositivo durante 30 días"</strong> en el inicio de sesión
                        </label>
                        <p class="description">Cada usuario puede elegir su método y ver su código QR en <strong>Usuarios &rarr; Perfil</strong>.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Guardar reglas de bloqueo' ); ?>
        </form>
    </div>

    <form id="bap_test_email_form" method="post" style="display:none;">
        <?php wp_nonce_field( 'bap_test_email_action' ); ?>
    </form>
</div>
