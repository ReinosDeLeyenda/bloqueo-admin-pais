<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$base_page_url = admin_url( 'admin.php?page=bap-web-bloqueos' );
$order         = $current['order'];
$ops           = $current['ops'];
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-web-bloqueos' ); ?>
    <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <div class="card" style="max-width:920px;margin-top:16px;padding:18px 22px;">
        <h2 style="margin-top:0;">Firewall Avanzado de Bloqueos Web (Frontend)</h2>
        <p class="description">Usa los botones <strong>Subir / Bajar</strong> (o arrastra cada bloque) para ordenar la prioridad, y pulsa los interruptores <strong>Y / O</strong> para encadenar condiciones.</p>

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
            <input type="hidden" name="bap_rule_order" id="bap_rule_order" value="<?php echo esc_attr( implode( ',', $order ) ); ?>" />

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

            <div id="bap-conditions-list">
                <?php foreach ( $order as $idx => $cond_key ) : ?>
                    <?php bap_render_condition_card( $cond_key, $current ); ?>
                    <?php if ( $idx < count( $order ) - 1 ) : ?>
                        <?php bap_render_logic_switch_svg( $ops[ $idx ] ?? 'AND' ); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <?php bap_render_web_response_and_general_fields( $current ); ?>

            <p class="submit">
                <button type="submit" name="bap_save_web_rule" class="button button-primary button-large">Guardar configuración de <?php echo esc_html( '__all__' === $selected ? 'Toda la web' : $selected ); ?></button>
            </p>
        </form>
    </div>
</div>
