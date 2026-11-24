<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_logic_switch_svg( $val ) {
    $is_or = ( 'OR' === strtoupper( (string) $val ) );
    $bg    = $is_or ? '#d97706' : '#2271b1';
    $cx    = $is_or ? '58' : '16';
    $txt_x = $is_or ? '27' : '47';
    $label = $is_or ? 'O' : 'Y';
    $hint  = $is_or ? 'Opcional (alternativa al filtro anterior)' : 'Obligatorio (se exige además del anterior)';
    $ns    = 'http://' . 'www.w3.org/2000/svg';
    ?>
    <div class="bap-logic-row" style="display:flex;align-items:center;gap:10px;margin:8px 0 8px 18px;">
        <input type="hidden" name="bap_rule_ops[]" value="<?php echo $is_or ? 'OR' : 'AND'; ?>" />
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

function bap_render_condition_card( $key, array $current ) {
    $titles = [
        'tor'     => 'Nodos de salida Tor',
        'firehol' => 'Redes maliciosas FireHOL Level 1',
        'abuse'   => 'Reputación AbuseIPDB',
        'country' => 'Países (Geolocalización)',
        'asn'     => 'ASN / Operadores',
    ];
    $accent = in_array( $key, [ 'tor', 'firehol', 'abuse' ], true ) ? '#d63638' : '#2271b1';
    ?>
    <div class="bap-cond-card" draggable="true" data-key="<?php echo esc_attr( $key ); ?>" style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo esc_attr( $accent ); ?>;border-radius:4px;padding:12px 14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;border-bottom:1px solid #f0f0f1;padding-bottom:6px;">
            <strong style="cursor:grab;user-select:none;font-size:13px;" title="Arrastra para reordenar">&#9776; <?php echo esc_html( $titles[ $key ] ?? $key ); ?></strong>
            <span style="display:inline-flex;gap:6px;">
                <button type="button" class="button button-small bap-move-up">Subir &#9650;</button>
                <button type="button" class="button button-small bap-move-down">Bajar &#9660;</button>
            </span>
        </div>

        <?php if ( 'tor' === $key ) : ?>
            <label>
                <input type="checkbox" name="bap_rule_block_tor" value="1" <?php checked( $current['block_tor'], '1' ); ?> />
                Que <strong>NO</strong> sea un nodo de salida Tor
            </label>

        <?php elseif ( 'firehol' === $key ) : ?>
            <label>
                <input type="checkbox" name="bap_rule_block_firehol" value="1" <?php checked( $current['block_firehol'], '1' ); ?> />
                Que <strong>NO</strong> pertenezca a una red maliciosa FireHOL Level 1
            </label>

        <?php elseif ( 'abuse' === $key ) : ?>
            <div>
                Permitir solo si el riesgo en AbuseIPDB es <strong>menor del</strong>
                <input type="number" name="bap_rule_abuse_risk" value="<?php echo (int) $current['abuse_risk']; ?>" min="0" max="100" style="width:75px;" /> %
                <span class="description">(<code>0</code> = ignorar)</span>
            </div>

        <?php elseif ( 'country' === $key ) : ?>
            <select name="bap_rule_country_mode" style="margin-bottom:6px;">
                <option value="allow" <?php selected( $current['country_mode'], 'allow' ); ?>>PERMITIR solo los países indicados</option>
                <option value="block" <?php selected( $current['country_mode'], 'block' ); ?>>BLOQUEAR los países indicados</option>
            </select><br>
            <input type="text" name="bap_rule_countries" value="<?php echo esc_attr( $current['countries'] ); ?>" class="regular-text" placeholder="Vacío = ignorar filtro de países" />

        <?php elseif ( 'asn' === $key ) : ?>
            <select name="bap_rule_asn_mode" style="margin-bottom:6px;">
                <option value="block" <?php selected( $current['asn_mode'], 'block' ); ?>>BLOQUEAR los ASNs indicados</option>
                <option value="allow" <?php selected( $current['asn_mode'], 'allow' ); ?>>PERMITIR solo los ASNs indicados</option>
            </select><br>
            <textarea name="bap_rule_asns" rows="2" class="large-text code" placeholder="Ej. AS12345 (Vacío = ignorar filtro de ASN)"><?php echo esc_textarea( $current['asns'] ); ?></textarea>
        <?php endif; ?>
    </div>
    <?php
}

function bap_render_web_response_and_general_fields( array $current ) {
    ?>
    <div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:14px 16px;margin-top:18px;">
        <h3 style="margin-top:0;margin-bottom:10px;">Respuesta al bloquear (en este ámbito) y opciones globales</h3>
        <table class="form-table" role="presentation" style="margin-top:0;">
            <tr>
                <th scope="row">URL de redirección al bloquear</th>
                <td>
                    <input type="url" name="bap_rule_redirect_url" value="<?php echo esc_attr( $current['redirect_url'] ); ?>" class="regular-text" placeholder="Vacío = mostrar página 403 personalizada" />
                </td>
            </tr>
            <tr>
                <th scope="row">Mensaje en página 403</th>
                <td>
                    <input type="text" name="bap_rule_block_message" value="<?php echo esc_attr( $current['block_message'] ); ?>" class="large-text" placeholder="Ej. Acceso restringido en esta sección." />
                </td>
            </tr>
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
                <th scope="row">Redirecciones por país (Global)</th>
                <td>
                    <textarea name="bap_web_country_redirects" rows="2" class="large-text code" placeholder="FR => https://example.com"><?php echo esc_textarea( get_option( 'bap_web_country_redirects', '' ) ); ?></textarea>
                    <p class="description">Una regla por línea con formato <code>ISO => URL</code>. Se aplica antes del bloqueo.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Rate Limit: Peticiones / min</th>
                <td>
                    <input type="number" name="bap_rl_max_req_min" value="<?php echo (int) get_option( 'bap_rl_max_req_min', 0 ); ?>" min="0" max="5000" style="width:85px;" /> peticiones/min
                    <span class="description">(<code>0</code> = desactivado; recomendado anti-scraping: <code>120</code>)</span>
                </td>
            </tr>
            <tr>
                <th scope="row">Rate Limit: Errores 404 / min</th>
                <td>
                    <input type="number" name="bap_rl_max_404_min" value="<?php echo (int) get_option( 'bap_rl_max_404_min', 0 ); ?>" min="0" max="1000" style="width:85px;" /> errores 404/min
                    <span class="description">(<code>0</code> = desactivado; recomendado anti-escáneres: <code>20</code>)</span>
                </td>
            </tr>
            <tr>
                <th scope="row">Auto-banear al exceder Rate Limit</th>
                <td>
                    <label>
                        <input type="checkbox" name="bap_rl_autoban" value="1" <?php checked( get_option( 'bap_rl_autoban', '0' ), '1' ); ?> />
                        Añadir automáticamente la IP a la Lista Negra temporal (usa la duración configurada en Bloqueos Admin)
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">User-Agents bloqueados (Global)</th>
                <td>
                    <?php $default_uas = function_exists( 'bap_get_default_blocked_user_agents' ) ? bap_get_default_blocked_user_agents() : ''; ?>
                    <div style="display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                        <textarea id="bap_blocked_user_agents" name="bap_blocked_user_agents" rows="5" class="large-text code" style="max-width:460px;" placeholder="Ej. sqlmap, nikto, python-requests, GPTBot (vacío = desactivado)"><?php echo esc_textarea( get_option( 'bap_blocked_user_agents', '' ) ); ?></textarea>
                        <button type="button" class="button button-secondary" onclick="document.getElementById('bap_blocked_user_agents').value=this.dataset.defaults;" data-defaults="<?php echo esc_attr( $default_uas ); ?>">Cargar lista recomendada</button>
                    </div>
                    <label style="display:block;margin-bottom:4px;">
                        <input type="checkbox" name="bap_block_empty_ua" value="1" <?php checked( get_option( 'bap_block_empty_ua', '0' ), '1' ); ?> />
                        Bloquear también peticiones sin cabecera <code>User-Agent</code> (vacío)
                    </label>
                    <p class="description">Una cadena por línea (no distingue mayúsculas/minúsculas). Nunca afecta a usuarios conectados, lista blanca ni bots SEO verificados.</p>
                </td>
            </tr>
        </table>
    </div>
    <?php
}
