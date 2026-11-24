<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$base_page_url = admin_url( 'admin.php?page=bap-web-bloqueos' );
?>
<div class="wrap">
    <h1>Bloqueo Admin por País e IP</h1>
    <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-bloqueos' ) ); ?>" class="nav-tab">Bloqueos Admin</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-web-bloqueos' ) ); ?>" class="nav-tab nav-tab-active">Bloqueo Web (Frontend)</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-db' ) ); ?>" class="nav-tab">Bases de datos</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-estadisticas' ) ); ?>" class="nav-tab">Estadísticas</a>
    </h2>

    <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <div class="card" style="max-width:920px;margin-top:16px;padding:18px 22px;">
        <h2 style="margin-top:0;">1. Constructor de reglas de la Web Pública (Frontend)</h2>
        <p class="description">Pulsa sobre los interruptores <strong>Y</strong> / <strong>O</strong> para encadenar condiciones.</p>

        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;background:#f6f7f7;padding:12px 14px;border:1px solid #dcdcde;border-radius:4px;margin:14px 0;">
            <label for="bap_scope_selector"><strong>Ámbito a editar:</strong></label>
            <select id="bap_scope_selector" style="min-width:300px;" onchange="window.location.href=this.value;">
                <?php foreach ( $rules as $scope_key => $r_data ) :
                    $status = ( '1' === (string) ( $r_data['enabled'] ?? '0' ) ) ? 'Activo' : 'Nada / Exento';
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
                <input type="text" name="bap_new_web_url" placeholder="/ruta/* (ej. /ayuda/formulario*)" class="regular-text" style="width:250px;" required />
                <button type="submit" name="bap_add_web_url" class="button button-secondary">+ Añadir URL</button>
            </form>
        </div>

        <form method="post" action="<?php echo esc_url( add_query_arg( 'rule_scope', rawurlencode( $selected ), $base_page_url ) ); ?>">
            <?php wp_nonce_field( 'bap_web_rules_nonce' ); ?>
            <input type="hidden" name="bap_active_scope" value="<?php echo esc_attr( $selected ); ?>" />

            <div style="margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e5e5e5;">
                <label><strong>Estado en este ámbito:</strong></label>
                <select name="bap_rule_enabled" style="margin-left:8px;">
                    <option value="1" <?php selected( $current['enabled'], '1' ); ?>>Activo (Aplicar reglas de abajo)</option>
                    <option value="0" <?php selected( $current['enabled'], '0' ); ?>>Nada (Desactivado / Exento de bloqueo)</option>
                </select>
                <?php if ( '__all__' !== $selected ) : ?>
                    <button type="submit" name="bap_delete_web_url" class="button button-link-delete" style="margin-left:14px;" onclick="return confirm('¿Eliminar esta regla?');">Eliminar esta URL</button>
                <?php endif; ?>
            </div>

            <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;border-radius:4px;padding:12px 14px;">
                <strong>1. Países</strong><br>
                <select name="bap_rule_country_mode" style="margin:6px 0;">
                    <option value="allow" <?php selected( $current['country_mode'], 'allow' ); ?>>PERMITIR solo los países indicados</option>
                    <option value="block" <?php selected( $current['country_mode'], 'block' ); ?>>BLOQUEAR los países indicados</option>
                </select><br>
                <input type="text" name="bap_rule_countries" value="<?php echo esc_attr( $current['countries'] ); ?>" class="regular-text" placeholder="Vacío = ignorar filtro" />
            </div>

            <?php bap_render_logic_switch_svg( 'bap_rule_op_1', $current['op_1'] ); ?>

            <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;border-radius:4px;padding:12px 14px;">
                <strong>2. ASN / Operadores</strong><br>
                <select name="bap_rule_asn_mode" style="margin:6px 0;">
                    <option value="block" <?php selected( $current['asn_mode'], 'block' ); ?>>BLOQUEAR los ASNs indicados</option>
                    <option value="allow" <?php selected( $current['asn_mode'], 'allow' ); ?>>PERMITIR solo los ASNs indicados</option>
                </select><br>
                <textarea name="bap_rule_asns" rows="2" class="large-text code" placeholder="Ej. AS12345 (Vacío = ignorar filtro)"><?php echo esc_textarea( $current['asns'] ); ?></textarea>
            </div>

            <?php bap_render_logic_switch_svg( 'bap_rule_op_2', $current['op_2'] ); ?>

            <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;border-radius:4px;padding:12px 14px;">
                <strong>3. Reputación AbuseIPDB</strong><br>
                <div style="margin-top:6px;">
                    Permitir solo si el riesgo es <strong>menor del</strong>
                    <input type="number" name="bap_rule_abuse_risk" value="<?php echo (int) $current['abuse_risk']; ?>" min="0" max="100" style="width:75px;" /> %
                    <span class="description">(<code>0</code> = ignorar)</span>
                </div>
            </div>

            <?php bap_render_logic_switch_svg( 'bap_rule_op_3', $current['op_3'] ); ?>

            <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;border-radius:4px;padding:12px 14px;">
                <label>
                    <input type="checkbox" name="bap_rule_block_tor" value="1" <?php checked( $current['block_tor'], '1' ); ?> />
                    <strong>4. Que NO sea un nodo de salida Tor</strong>
                </label>
            </div>

            <?php bap_render_logic_switch_svg( 'bap_rule_op_4', $current['op_4'] ); ?>

            <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;border-radius:4px;padding:12px 14px;">
                <label>
                    <input type="checkbox" name="bap_rule_block_firehol" value="1" <?php checked( $current['block_firehol'], '1' ); ?> />
                    <strong>5. Que NO pertenezca a una red maliciosa FireHOL Level 1</strong>
                </label>
            </div>

            <p class="submit">
                <button type="submit" name="bap_save_web_rule" class="button button-primary">Guardar regla de <?php echo esc_html( '__all__' === $selected ? 'Toda la web' : $selected ); ?></button>
            </p>
        </form>
    </div>

    <?php bap_render_web_general_settings(); ?>
</div>
