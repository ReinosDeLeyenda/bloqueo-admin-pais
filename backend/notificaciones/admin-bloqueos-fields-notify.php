<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
function bap_render_bloqueos_auth_fields() {
    ?>
    <tr>
        <th>Contraseñas filtradas (Have I Been Pwned)</th>
        <td>
            <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="bap_hibp_enabled" value="1" <?php checked( get_option( 'bap_hibp_enabled', '1' ), '1' ); ?> /> Impedir contraseñas filtradas en brechas públicas</label>
            <label style="display:block;margin-bottom:8px;"><input type="checkbox" name="bap_hibp_check_login" value="1" <?php checked( get_option( 'bap_hibp_check_login', '1' ), '1' ); ?> /> Comprobar contraseña al iniciar sesión</label>
            <select name="bap_hibp_login_action">
                <option value="warn" <?php selected( get_option( 'bap_hibp_login_action', 'warn' ), 'warn' ); ?>>Permitir acceso pero mostrar alerta roja en el panel</option>
                <option value="block" <?php selected( get_option( 'bap_hibp_login_action', 'warn' ), 'block' ); ?>>Bloquear inicio de sesión y obligar a restablecer contraseña</option>
            </select>
        </td>
    </tr>
    <tr>
        <th>Política y Caducidad de Contraseñas</th>
        <td>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_pwd_policy_enabled" value="1" <?php checked( get_option( 'bap_pwd_policy_enabled', '0' ), '1' ); ?> />
                Exigir contraseñas fuertes obligatorias
            </label>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:8px;">
                <label>Aplicar a:
                    <select name="bap_pwd_policy_scope">
                        <option value="admins" <?php selected( get_option( 'bap_pwd_policy_scope', 'admins_editors' ), 'admins' ); ?>>Solo Administradores</option>
                        <option value="admins_editors" <?php selected( get_option( 'bap_pwd_policy_scope', 'admins_editors' ), 'admins_editors' ); ?>>Administradores y Editores</option>
                        <option value="all" <?php selected( get_option( 'bap_pwd_policy_scope', 'admins_editors' ), 'all' ); ?>>Todos los usuarios</option>
                    </select>
                </label>
                <label>Longitud mínima:
                    <input type="number" name="bap_pwd_min_length" value="<?php echo (int) get_option( 'bap_pwd_min_length', 12 ); ?>" min="8" max="64" class="small-text" /> caracteres
                </label>
            </div>
            <label style="display:block;margin-bottom:6px;">
                <input type="checkbox" name="bap_pwd_require_complexity" value="1" <?php checked( get_option( 'bap_pwd_require_complexity', '1' ), '1' ); ?> />
                Exigir mayúsculas, minúsculas, números y símbolos (y prohibir el nombre de usuario)
            </label>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_pwd_prevent_reuse" value="1" <?php checked( get_option( 'bap_pwd_prevent_reuse', '1' ), '1' ); ?> />
                Impedir reutilizar las últimas 5 contraseñas al cambiarla
            </label>
            <div>
                <label>Caducidad / rotación obligatoria cada:
                    <input type="number" name="bap_pwd_expiry_days" value="<?php echo (int) get_option( 'bap_pwd_expiry_days', 0 ); ?>" min="0" max="730" class="small-text" /> días
                </label>
                <span class="description">(<code>0</code> = sin caducidad; recomendado: <code>90</code> días)</span>
            </div>
        </td>
    </tr>
    <tr>
        <th>Protección CAPTCHA (Anti-Bots)</th>
        <td>
            <?php
            $cap_prov   = (string) get_option( 'bap_captcha_provider', 'none' );
            $cap_scopes = (array) get_option( 'bap_captcha_scopes', [ 'login', 'register', 'lostpassword' ] );
            ?>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:8px;">
                <select id="bap_captcha_provider" name="bap_captcha_provider">
                    <option value="none" <?php selected( $cap_prov, 'none' ); ?>>Desactivado</option>
                    <option value="math" <?php selected( $cap_prov, 'math' ); ?>>CAPTCHA Local Matemático (100 % sin APIs externas)</option>
                    <option value="altcha" <?php selected( $cap_prov, 'altcha' ); ?>>ALTCHA Proof-of-Work Local (Automático SHA-256 sin API)</option>
                    <option value="recaptcha_v3" <?php selected( $cap_prov, 'recaptcha_v3' ); ?>>Google reCAPTCHA v3 (Invisible por puntuación)</option>
                    <option value="recaptcha_v2" <?php selected( $cap_prov, 'recaptcha_v2' ); ?>>Google reCAPTCHA v2 (Casilla "No soy un robot")</option>
                    <option value="turnstile" <?php selected( $cap_prov, 'turnstile' ); ?>>Cloudflare Turnstile (Ilimitado gratis / Sin fricción)</option>
                    <option value="hcaptcha" <?php selected( $cap_prov, 'hcaptcha' ); ?>>hCaptcha (Gratis / Visual)</option>
                    <option value="friendly" <?php selected( $cap_prov, 'friendly' ); ?>>Friendly Captcha (Gratis 1k/mes / Sin cookies RGPD)</option>
                    <option value="mtcaptcha" <?php selected( $cap_prov, 'mtcaptcha' ); ?>>MTCaptcha (Gratis 10k/mes / RGPD)</option>
                </select>
                <label>Umbral reCAPTCHA v3:
                    <input type="number" name="bap_recaptcha_v3_score" value="<?php echo esc_attr( get_option( 'bap_recaptcha_v3_score', '0.5' ) ); ?>" min="0.1" max="0.9" step="0.1" class="small-text" />
                </label>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                <input type="text" name="bap_captcha_site_key" value="<?php echo esc_attr( get_option( 'bap_captcha_site_key', '' ) ); ?>" class="regular-text code" placeholder="Site Key (Clave del sitio)" />
                <input type="password" name="bap_captcha_secret_key" value="<?php echo esc_attr( get_option( 'bap_captcha_secret_key', '' ) ); ?>" class="regular-text code" placeholder="Secret Key (Clave secreta)" autocomplete="new-password" />
            </div>
            <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:4px;">
                <label><input type="checkbox" name="bap_captcha_scopes[]" value="login" <?php checked( in_array( 'login', $cap_scopes, true ) ); ?> /> Inicio de sesión</label>
                <label><input type="checkbox" name="bap_captcha_scopes[]" value="register" <?php checked( in_array( 'register', $cap_scopes, true ) ); ?> /> Registro</label>
                <label><input type="checkbox" name="bap_captcha_scopes[]" value="lostpassword" <?php checked( in_array( 'lostpassword', $cap_scopes, true ) ); ?> /> Recuperar contraseña</label>
                <label><input type="checkbox" name="bap_captcha_scopes[]" value="comments" <?php checked( in_array( 'comments', $cap_scopes, true ) ); ?> /> Comentarios</label>
            </div>
            <p class="description">Para <strong>CAPTCHA Local Matemático</strong> no necesitas claves API. Si usas reCAPTCHA v3, una puntuación menor al umbral (ej. <code>0.5</code>) bloquea el intento y lo registra en Estadísticas.</p>
        </td>
    </tr>
    <tr>
        <th>Acceso sin contraseña (Magic Links)</th>
        <td>
            <label>
                <input type="checkbox" name="bap_magic_login_enabled" value="1" <?php checked( get_option( 'bap_magic_login_enabled', '0' ), '1' ); ?> />
                Permitir iniciar sesión mediante <strong>Enlace Mágico de un solo uso</strong> enviado por Telegram y Email (válido 10 minutos)
            </label>
        </td>
    </tr>
    <tr>
        <th>Autenticación en Dos Factores (2FA)</th>
        <td>
            <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="bap_2fa_enabled" value="1" <?php checked( get_option( 'bap_2fa_enabled', '0' ), '1' ); ?> /> Habilitar 2FA en los perfiles de usuario</label>
            <label style="display:block;"><input type="checkbox" name="bap_2fa_enforce_admins" value="1" <?php checked( get_option( 'bap_2fa_enforce_admins', '0' ), '1' ); ?> /> Obligar 2FA a todos los <strong>Administradores</strong></label>
            <label style="display:block;margin-top:6px;"><input type="checkbox" name="bap_2fa_remember_device" value="1" <?php checked( get_option( 'bap_2fa_remember_device', '1' ), '1' ); ?> /> Permitir marcar <strong>"Recordar este dispositivo durante 30 días"</strong></label>
        </td>
    </tr>
    <?php
}
