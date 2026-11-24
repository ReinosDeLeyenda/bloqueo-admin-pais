<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$w_enabled = get_option( 'bap_web_enabled', '0' );
$w_scope   = get_option( 'bap_web_scope', 'all' );
$w_cmode   = get_option( 'bap_web_country_mode', 'allow' );
$w_amode   = get_option( 'bap_web_asn_mode', 'block' );
?>
<div class="wrap">
    <h1>Control de Acceso al Panel y Web</h1>
    <?php bap_render_tabs( 'bap-web-bloqueos' ); ?>
    <?php settings_errors(); ?>

    <div class="card" style="max-width:960px;padding:16px 24px;margin-top:12px;">
        <form method="post" action="options.php">
            <?php settings_fields( 'bap_web_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th>Activar Bloqueos Web (Frontend)</th>
                    <td>
                        <label>
                            <input type="checkbox" name="bap_web_enabled" value="1" <?php checked( $w_enabled, '1' ); ?> />
                            <strong>Aplicar reglas de bloqueo en la web pública</strong> (independientes de Bloqueos Admin)
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Ámbito de bloqueo en la Web</th>
                    <td>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="radio" name="bap_web_scope" value="all" <?php checked( $w_scope, 'all' ); ?> />
                            <strong>Toda la web pública</strong> (bloquea cualquier página, entrada o archivo del frontend)
                        </label>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="radio" name="bap_web_scope" value="paths" <?php checked( $w_scope, 'paths' ); ?> />
                            <strong>Solo rutas / páginas específicas</strong> (indicadas abajo)
                        </label>
                        <textarea name="bap_web_paths" rows="4" style="width:100%;max-width:380px;" class="code" placeholder="/tienda/*&#10;/mi-cuenta/*&#10;/descargas/*"><?php echo esc_textarea( get_option( 'bap_web_paths', '' ) ); ?></textarea>
                        <p class="description">Una ruta por línea (admite comodín <code>*</code>). Solo se aplica si eliges "Solo rutas / páginas específicas".</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;">
                            <span>Países PERMITIDOS (Web)</span>
                            <input type="radio" name="bap_web_country_mode" value="allow" <?php checked( $w_cmode, 'allow' ); ?> />
                        </label>
                    </th>
                    <td><input type="text" id="bap_web_allowed_countries" name="bap_web_allowed_countries" value="<?php echo esc_attr( get_option( 'bap_web_allowed_countries', '' ) ); ?>" class="regular-text" placeholder="Ej: ES, PT, FR (vacío = desactivado)" /></td>
                </tr>
                <tr>
                    <th>
                        <label style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;">
                            <span>Países BLOQUEADOS (Web)</span>
                            <input type="radio" name="bap_web_country_mode" value="block" <?php checked( $w_cmode, 'block' ); ?> />
                        </label>
                    </th>
                    <td><input type="text" id="bap_web_blocked_countries" name="bap_web_blocked_countries" value="<?php echo esc_attr( get_option( 'bap_web_blocked_countries', '' ) ); ?>" class="regular-text" placeholder="Ej: RU, CN, IN (vacío = desactivado)" /></td>
                </tr>
                <tr>
                    <th>
                        <label style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;">
                            <span>ASNs PERMITIDOS (Web)</span>
                            <input type="radio" name="bap_web_asn_mode" value="allow" <?php checked( $w_amode, 'allow' ); ?> />
                        </label>
                    </th>
                    <td><input type="text" id="bap_web_allowed_asns" name="bap_web_allowed_asns" value="<?php echo esc_attr( get_option( 'bap_web_allowed_asns', '' ) ); ?>" class="regular-text code" placeholder="Ej: AS3352, AS12479 (vacío = desactivado)" /></td>
                </tr>
                <tr>
                    <th>
                        <label style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;">
                            <span>ASNs BLOQUEADOS (Web)</span>
                            <input type="radio" name="bap_web_asn_mode" value="block" <?php checked( $w_amode, 'block' ); ?> />
                        </label>
                    </th>
                    <td><input type="text" id="bap_web_blocked_asns" name="bap_web_blocked_asns" value="<?php echo esc_attr( get_option( 'bap_web_blocked_asns', '' ) ); ?>" class="regular-text code" placeholder="Ej: AS16276, AS14061 (vacío = desactivado)" /></td>
                </tr>
                <tr>
                    <th>Bloquear riesgo AbuseIPDB en Web &ge;</th>
                    <td><input type="number" min="0" max="100" name="bap_web_abuse_risk" value="<?php echo (int) get_option( 'bap_web_abuse_risk', 0 ); ?>" class="small-text" /> % <span class="description">(<code>0</code> para desactivar)</span></td>
                </tr>
                <tr>
                    <th>Listas de amenazas en Web</th>
                    <td>
                        <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="bap_web_block_tor" value="1" <?php checked( get_option( 'bap_web_block_tor', '0' ), '1' ); ?> /> Bloquear Nodos de Salida Tor en la web pública</label>
                        <label style="display:block;"><input type="checkbox" name="bap_web_block_firehol" value="1" <?php checked( get_option( 'bap_web_block_firehol', '0' ), '1' ); ?> /> Bloquear Redes FireHOL Level 1 en la web pública</label>
                    </td>
                </tr>
                <tr>
                    <th>Exclusión SEO (Buscadores)</th>
                    <td>
                        <label>
                            <input type="checkbox" name="bap_web_allow_seo_bots" value="1" <?php checked( get_option( 'bap_web_allow_seo_bots', '1' ), '1' ); ?> />
                            Permitir siempre el paso a bots de búsqueda legítimos (Googlebot, Bingbot, DuckDuckBot, Applebot) para no perder indexación SEO
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Redirección personalizada por país</th>
                    <td>
                        <textarea name="bap_web_country_redirects" rows="4" style="width:100%;max-width:460px;" class="code" placeholder="FR => https://www.example.com"><?php echo esc_textarea( get_option( 'bap_web_country_redirects', '' ) ); ?></textarea>
                        <p class="description">Una regla por línea con el formato <code>PAÍS =&gt; https://url-destino</code>. Tiene prioridad sobre el bloqueo general y evita bucles automáticamente.</p>
                    </td>
                </tr>
                <tr>
                    <th>Redirigir visitante bloqueado a (URL)</th>
                    <td>
                        <input type="url" name="bap_web_redirect_url" value="<?php echo esc_attr( get_option( 'bap_web_redirect_url', '' ) ); ?>" class="regular-text code" placeholder="Opcional (si está vacío muestra el mensaje de abajo)" />
                    </td>
                </tr>
                <tr>
                    <th>Mensaje de bloqueo Web (403)</th>
                    <td>
                        <input type="text" name="bap_web_block_message" value="<?php echo esc_attr( get_option( 'bap_web_block_message', 'Este contenido no está disponible en tu ubicación o red.' ) ); ?>" class="large-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Guardar reglas de Bloqueos Web' ); ?>
        </form>
    </div>
</div>
