<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_logic_switch_svg( $input_name, $val ) {
    $is_or = ( 'OR' === strtoupper( (string) $val ) );
    $bg    = $is_or ? '#d97706' : '#2271b1';
    $cx    = $is_or ? '58' : '16';
    $txt_x = $is_or ? '27' : '47';
    $label = $is_or ? 'O' : 'Y';
    $hint  = $is_or ? 'Opcional (alternativa al filtro anterior)' : 'Obligatorio (se exige además del anterior)';
    $ns    = 'http://' . 'www.w3.org/2000/svg';
    ?>
    <div style="display:flex;align-items:center;gap:10px;margin:6px 0 6px 18px;">
        <input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo $is_or ? 'OR' : 'AND'; ?>" />
        <button type="button" class="bap-logic-toggle" style="background:none;border:none;padding:0;cursor:pointer;display:inline-flex;align-items:center;" title="Alternar entre Y (obligatorio) y O (opcional)">
            <svg width="74" height="28" viewBox="0 0 74 28" fill="none" xmlns="<?php echo esc_attr( $ns ); ?>">
                <rect class="bap-sw-bg" x="1" y="1" width="72" height="26" rx="13" fill="<?php echo esc_attr( $bg ); ?>" stroke="#1d2327" stroke-opacity="0.2" stroke-width="1"/>
                <text class="bap-sw-txt" x="<?php echo esc_attr( $txt_x ); ?>" y="19" text-anchor="middle" fill="#FFFFFF" font-family="sans-serif" font-size="13" font-weight="800"><?php echo esc_html( $label ); ?></text>
                <circle class="bap-sw-knob" cx="<?php echo esc_attr( $cx ); ?>" cy="14" r="10" fill="#FFFFFF"/>
            </svg>
        </button>
        <span class="bap-sw-hint" style="font-size:12px;color:#50575e;font-weight:500;"><?php echo esc_html( $hint ); ?></span>
    </div>
    <?php
}

function bap_render_web_general_settings() {
    ?>
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
                        <p class="description">Una regla por línea con formato <code>ISO => URL</code>.</p>
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
    <?php
}
