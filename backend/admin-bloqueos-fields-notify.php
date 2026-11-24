<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_bloqueos_notify_and_auth_fields() {
    $channel     = (string) get_option( 'bap_notify_channel', 'none' );
    $freq        = (string) get_option( 'bap_notify_freq', 'daily' );
    $email_tpl   = get_option( 'bap_email_template', 'full' );
    $custom_tpls = function_exists( 'bap_get_custom_email_templates' ) ? bap_get_custom_email_templates() : [];
    ?>
    <tr>
        <th>Notificaciones (Canal y Frecuencia)</th>
        <td>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <select id="bap_notify_channel" name="bap_notify_channel">
                    <option value="none" <?php selected( $channel, 'none' ); ?>>Sin notificaciones</option>
                    <option value="email" <?php selected( $channel, 'email' ); ?>>Email</option>
                    <option value="telegram" <?php selected( $channel, 'telegram' ); ?>>Telegram</option>
                    <option value="both" <?php selected( $channel, 'both' ); ?>>Telegram y Email</option>
                </select>

                <select id="bap_notify_freq" name="bap_notify_freq">
                    <option value="instant" <?php selected( $freq, 'instant' ); ?>>Notificaciones individuales (1 intento = 1 aviso)</option>
                    <option value="hourly" <?php selected( $freq, 'hourly' ); ?>>Cada 1h (resumen agrupado)</option>
                    <option value="3hours" <?php selected( $freq, '3hours' ); ?>>Cada 3h (resumen agrupado)</option>
                    <option value="6hours" <?php selected( $freq, '6hours' ); ?>>Cada 6h (resumen agrupado)</option>
                    <option value="12hours" <?php selected( $freq, '12hours' ); ?>>Cada 12h (resumen agrupado)</option>
                    <option value="daily" <?php selected( $freq, 'daily' ); ?>>Cada 24h (resumen agrupado)</option>
                    <option value="weekly" <?php selected( $freq, 'weekly' ); ?>>Cada 7d (resumen agrupado)</option>
                    <option value="2weeks" <?php selected( $freq, '2weeks' ); ?>>Cada 14d (resumen agrupado)</option>
                    <option value="monthly" <?php selected( $freq, 'monthly' ); ?>>Cada 1 mes (resumen agrupado)</option>
                </select>
            </div>
        </td>
    </tr>
  <tr>
      <th>Avisos de conexión exitosa</th>
      <td>
          <?php
          $login_ch = (string) get_option( 'bap_login_notify_channel', 'none' );
          $routes   = get_option( 'bap_login_notify_routes', [] );
          if ( ! is_array( $routes ) || empty( $routes ) ) {
              $routes = [ [ 'user_id' => 0, 'target' => '', 'type' => 'telegram' ] ];
          }
          $wp_users = get_users( [ 'fields' => [ 'ID', 'user_login', 'display_name' ], 'orderby' => 'login', 'order' => 'ASC' ] );
          ?>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
              <select name="bap_login_notify_channel">
                  <option value="none" <?php selected( $login_ch, 'none' ); ?>>Desactivado</option>
                  <option value="email" <?php selected( $login_ch, 'email' ); ?>>Email</option>
                  <option value="telegram" <?php selected( $login_ch, 'telegram' ); ?>>Telegram</option>
                  <option value="both" <?php selected( $login_ch, 'both' ); ?>>Telegram y Email</option>
              </select>
          </div>

          <div id="bap-login-routes-list" style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px;">
              <?php foreach ( $routes as $r ) :
                  $r_uid  = (int) ( $r['user_id'] ?? 0 );
                  $r_tgt  = (string) ( $r['target'] ?? '' );
                  $r_type = ( 'email' === ( $r['type'] ?? 'telegram' ) ) ? 'email' : 'telegram';
              ?>
                  <div class="bap-login-route-row" style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;">
                      <select name="bap_login_route_user[]" class="bap-login-route-user" style="width:155px;">
                          <option value="0" <?php selected( $r_uid, 0 ); ?>>Todos los usuarios</option>
                          <?php foreach ( $wp_users as $wu ) : ?>
                              <option value="<?php echo (int) $wu->ID; ?>" <?php selected( $r_uid, (int) $wu->ID ); ?>>
                                  <?php echo esc_html( $wu->user_login . ( $wu->display_name && $wu->display_name !== $wu->user_login ? ' (' . $wu->display_name . ')' : '' ) ); ?>
                              </option>
                          <?php endforeach; ?>
                      </select>

                      <input type="text" name="bap_login_route_target[]" value="<?php echo esc_attr( $r_tgt ); ?>" class="bap-login-route-target" style="width:185px;" placeholder="<?php echo 'email' === $r_type ? 'correo@dominio.com' : 'Chat ID (ej. -124)'; ?>" />

                      <select name="bap_login_route_type[]" class="bap-login-route-type" style="width:95px;">
                          <option value="telegram" <?php selected( $r_type, 'telegram' ); ?>>Telegram</option>
                          <option value="email" <?php selected( $r_type, 'email' ); ?>>Email</option>
                      </select>

                      <button type="button" class="button button-secondary bap-add-login-route" title="Añadir otra línea" style="font-weight:700;padding:0 8px;">+</button>
                      <button type="button" class="button button-secondary bap-del-login-route" title="Borrar esta línea" style="color:#d63638;font-weight:700;padding:0 8px;">&times;</button>
                      <button type="button" class="button button-secondary bap-test-login-route">Probar aviso de conexión</button>
                  </div>
              <?php endforeach; ?>
          </div>

          <p class="description">Configura destinos específicos por usuario (o para todos). Si dejas el destino vacío, usará los destinatarios generales de bloqueos. Remarca si el <strong>ASN</strong> difiere de su última sesión o sesiones activas.</p>
      </td>
  </tr>
    <tr>
        <th>Destinatarios de emails</th>
        <td>
            <input type="text" name="bap_notify_emails" value="<?php echo esc_attr( get_option( 'bap_notify_emails', '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
            <p class="description">Uno o varios emails separados por comas.</p>
        </td>
    </tr>
    <tr>
        <th><label for="bap_email_template">Plantilla del email de resumen</label></th>
        <td>
            <div style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap;">
                <select id="bap_email_template" name="bap_email_template" style="min-width:290px;">
                    <optgroup label="Plantillas predefinidas">
                        <option value="full" <?php selected( $email_tpl, 'full' ); ?>>Completa (Tarjetas + Barras Países + Todos los eventos)</option>
                        <option value="top10" <?php selected( $email_tpl, 'top10' ); ?>>Top 10 (Tarjetas + Barras Países + Top 10 bloqueos)</option>
                        <option value="top5" <?php selected( $email_tpl, 'top5' ); ?>>Top 5 (Tarjetas + Barras Países + Top 5 bloqueos)</option>
                        <option value="text" <?php selected( $email_tpl, 'text' ); ?>>Solo texto plano (Agrupado)</option>
                    </optgroup>
                    <?php if ( ! empty( $custom_tpls ) ) : ?>
                        <optgroup label="Mis plantillas personalizadas">
                            <?php foreach ( $custom_tpls as $cid => $ctpl ) : ?>
                                <option value="<?php echo esc_attr( 'custom_' . $cid ); ?>" <?php selected( $email_tpl, 'custom_' . $cid ); ?>><?php echo esc_html( $ctpl['name'] ); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <button type="submit" form="bap_test_email_form" name="bap_send_test_email" value="1" class="button button-secondary">Enviar email de prueba</button>
                    <button type="submit" form="bap_test_email_form" name="bap_send_test_digest" value="1" class="button button-secondary">Enviar email de prueba de resumen</button>
                </div>
            </div>
        </td>
    </tr>
    <tr>
        <th>Bot Token y Chat ID(s) Telegram</th>
        <td>
            <div style="display:flex;flex-direction:column;gap:8px;max-width:460px;">
                <input type="password" name="bap_tg_bot_token" value="<?php echo esc_attr( get_option( 'bap_tg_bot_token', '' ) ); ?>" class="regular-text code" placeholder="Bot Token (ej. 123456789:AAH...)" autocomplete="new-password" />
                <input type="text" name="bap_tg_chat_ids" value="<?php echo esc_attr( get_option( 'bap_tg_chat_ids', '' ) ); ?>" class="regular-text code" placeholder="Chat ID(s) separados por comas (ej. 12345678, -100987654321)" />
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button type="submit" form="bap_test_email_form" name="bap_send_test_tg" value="1" class="button button-secondary">Probar alerta individual Telegram</button>
                    <button type="submit" form="bap_test_email_form" name="bap_send_test_tg_digest" value="1" class="button button-secondary">Probar resumen Telegram</button>
                </div>
            </div>
            <p class="description">Crea tu bot con <code>@BotFather</code>, inícialo enviándole <code>/start</code> y guarda antes de pulsar probar.</p>
        </td>
    </tr>
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
        <th>Autenticación en Dos Factores (2FA)</th>
        <td>
            <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="bap_2fa_enabled" value="1" <?php checked( get_option( 'bap_2fa_enabled', '0' ), '1' ); ?> /> Habilitar 2FA en los perfiles de usuario</label>
            <label style="display:block;"><input type="checkbox" name="bap_2fa_enforce_admins" value="1" <?php checked( get_option( 'bap_2fa_enforce_admins', '0' ), '1' ); ?> /> Obligar 2FA a todos los <strong>Administradores</strong></label>
            <label style="display:block;margin-top:6px;"><input type="checkbox" name="bap_2fa_remember_device" value="1" <?php checked( get_option( 'bap_2fa_remember_device', '1' ), '1' ); ?> /> Permitir marcar <strong>"Recordar este dispositivo durante 30 días"</strong></label>
        </td>
    </tr>
    <?php
}
