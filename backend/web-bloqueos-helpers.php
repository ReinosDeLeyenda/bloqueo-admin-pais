function bap_render_web_general_settings() {
    ?>
    <div class="card" style="max-width:920px;margin-top:18px;padding:18px 22px;">
        <h2 style="margin-top:0;">2. Ajustes generales y valores por defecto</h2>
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
                        <p class="description">Una regla por línea con formato <code>ISO => URL</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">URL de redirección por defecto</th>
                    <td>
                        <input type="url" name="bap_web_redirect_url" value="<?php echo esc_attr( get_option( 'bap_web_redirect_url', '' ) ); ?>" class="regular-text" placeholder="Se usa si la regla no define una propia" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Mensaje 403 por defecto</th>
                    <td>
                        <input type="text" name="bap_web_block_message" value="<?php echo esc_attr( get_option( 'bap_web_block_message', 'Este contenido no está disponible en tu ubicación o red.' ) ); ?>" class="large-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Guardar ajustes generales' ); ?>
        </form>
    </div>
    <?php
}
