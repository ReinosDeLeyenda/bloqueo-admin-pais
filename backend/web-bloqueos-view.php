<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$base_page_url = admin_url( 'admin.php?page=bap-web-bloqueos' );
?>
<div class="wrap">
    <h1>Firewall Avanzado de la Web Pública (Frontend)</h1>
    <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <!-- BLOQUE 1: SELECTOR DE ÁMBITO (TODO / URL1 / URL2) Y SUS FILTROS -->
    <div class="card" style="max-width:920px;margin-top:16px;padding:18px 22px;">
        <h2 style="margin-top:0;">1. Reglas de bloqueo por ámbito o URL</h2>
        <p class="description">Configura filtros independientes para <strong>Toda la web</strong> o para <strong>URLs específicas</strong>. Si una URL tiene una regla propia (o está en modo <em>Nada / Sin bloqueo</em>), prevalece sobre la regla global.</p>

        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;background:#f6f7f7;padding:12px 14px;border:1px solid #dcdcde;border-radius:4px;margin:14px 0;">
            <label for="bap_scope_selector"><strong>Ámbito a editar:</strong></label>
            <select id="bap_scope_selector" style="min-width:320px;" onchange="window.location.href=this.value;">
                <?php foreach ( $rules as $scope_key => $r_data ) :
                    $is_on  = ( '1' === (string) ( $r_data['enabled'] ?? '0' ) );
                    $status = $is_on ? 'Activo' : 'Nada / Exento';
                    $label  = ( '__all__' === $scope_key ) ? '🌐 Toda la web (Regla global)' : '📄 ' . $scope_key;
                    $url    = add_query_arg( 'rule_scope', rawurlencode( $scope_key ), $base_page_url );
                ?>
                    <option value="<?php echo esc_url( $url ); ?>" <?php selected( $selected, $scope_key ); ?>>
                        <?php echo esc_html( $label . ' [' . $status . ']' ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <form method="post" action="<?php echo esc_url( $base_page_url ); ?>" style="display:inline-flex;gap:6px;align-items:center;margin-left:auto;">
                <?php wp_nonce_field( 'bap_web_rules_nonce' ); ?>
                <input type="text" name="bap_new_web_url" placeholder="/ruta-o-url/* (ej. /ayuda/formulario*)" class="regular-text" style="width:270px;" required />
                <button type="submit" name="bap_add_web_url" class="button button-secondary">+ Añadir URL</button>
            </form>
        </div>

        <form method="post" action="<?php echo esc_url( add_query_arg( 'rule_scope', rawurlencode( $selected ), $base_page_url ) ); ?>">
            <?php wp_nonce_field( 'bap_web_rules_nonce' ); ?>
            <input type="hidden" name="bap_active_scope" value="<?php echo esc_attr( $selected ); ?>" />

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Acción en este ámbito</th>
                    <td>
                        <select name="bap_rule_enabled" id="bap_rule_enabled_select">
                            <option value="1" <?php selected( $current['enabled'], '1' ); ?>>Aplicar los filtros configurados abajo</option>
                            <option value="0" <?php selected( $current['enabled'], '0' ); ?>>Nada (Desactivado / Exento de bloqueo)</option>
                        </select>
                        <?php if ( '__all__' !== $selected ) : ?>
                            <button type="submit" name="bap_delete_web_url" class="button button-link-delete" style="margin-left:14px;" onclick="return confirm('¿Eliminar la regla específica para <?php echo esc_js( $selected ); ?>?');">Eliminar esta URL</button>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Filtro de Países (Y/O)</th>
                    <td>
                        <select name="bap_rule_country_mode" style="margin-bottom:6px;">
                            <option value="allow" <?php selected( $current['country_mode'], 'allow' ); ?>>PERMITIR solo los países indicados</option>
                            <option value="block" <?php selected( $current['country_mode'], 'block' ); ?>>BLOQUEAR los países indicados</option>
                        </select><br>
                        <input type="text" name="bap_rule_countries" value="<?php echo esc_attr( $current['countries'] ); ?>" class="regular-text" placeholder="Ej. ES, PT, FR (dejar vacío para no filtrar por país)" />
                    </td>
                </tr>

                <tr>
                    <th scope="row">Filtro de ASN / Operadores (Y/O)</th>
                    <td>
                        <select name="bap_rule_asn_mode" style="margin-bottom:6px;">
                            <option value="block" <?php selected( $current['asn_mode'], 'block' ); ?>>BLOQUEAR los ASNs indicados</option>
                            <option value="allow" <?php selected( $current['asn_mode'], 'allow' ); ?>>PERMITIR solo los ASNs indicados</option>
                        </select><br>
                        <textarea name="bap_rule_asns" rows="3" class="large-text code" placeholder="Ej. AS14061, AS16509 (dejar vacío para no filtrar por ASN)"><?php echo esc_textarea( $current['asns'] ); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row">AbuseIPDB (Y/O)</th>
                    <td>
                        <input type="number" name="bap_rule_abuse_risk" value="<?php echo (int) $current['abuse_risk']; ?>" min="0" max="100" style="width:80px;" /> % de riesgo mínimo
                        <p class="description">Bloquea IPs con puntuación igual o superior en AbuseIPDB (<code>0</code> = desactivado).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Listas de amenazas (Y/O)</th>
                    <td>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="bap_rule_block_tor" value="1" <?php checked( $current['block_tor'], '1' ); ?> />
                            Bloquear nodos de salida <strong>Tor</strong>
                        </label>
                        <label style="display:block;">
                            <input type="checkbox" name="bap_rule_block_firehol" value="1" <?php checked( $current['block_firehol'], '1' ); ?> />
                            Bloquear redes maliciosas <strong>FireHOL Level 1</strong>
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="bap_save_web_rule" class="button button-primary">Guardar regla de <?php echo esc_html( '__all__' === $selected ? 'Toda la web' : $selected ); ?></button>
            </p>
        </form>
    </div>

    <!-- BLOQUE 2: OPCIONES GENERALES DEL FRONTEND -->
    <div class="card" style="max-width:920px;margin-top:18px;padding:18px 22px;">
        <h2 style="margin-top:0;">2. Ajustes generales de respuesta y bots SEO</h2>
        <form method="post" action="options.php">
            <?php settings_fields( 'bap_web_settings_group' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Bots de buscadores (SEO)</th>
                    <td>
                        <label>
                            <input type="checkbox" name="bap_web_allow_seo_bots" value="1" <?php checked( get_option( 'bap_web_allow_seo_bots', '1' ), '1' ); ?> />
                            Permitir siempre Googlebot, Bingbot, DuckDuckBot y Applebot verificados por DNS inverso
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Redirecciones por país</th>
                    <td>
                        <textarea name="bap_web_country_redirects" rows="3" class="large-text code" placeholder="FR => https://example.com"><?php echo esc_textarea( get_option( 'bap_web_country_redirects', '' ) ); ?></textarea>
                        <p class="description">Una regla por línea con formato <code>ISO => URL</code>. Se aplica antes del bloqueo.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">URL de redirección al bloquear</th>
                    <td>
                        <input type="url" name="bap_web_redirect_url" value="<?php echo esc_attr( get_option( 'bap_web_redirect_url', '' ) ); ?>" class="regular-text" placeholder="Vacío = mostrar página 403 personalizada" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Mensaje en página 403</th>
                    <td>
                        <input type="text" name="bap_web_block_message" value="<?php echo esc_attr( get_option( 'bap_web_block_message', 'Este contenido no está disponible en tu ubicación o red.' ) ); ?>" class="large-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Guardar ajustes generales' ); ?>
        </form>
    </div>
</div>
