<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$base_url = admin_url( 'admin.php?page=bap-email-templates' );
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-email-templates' ); ?>
    <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">
        <div class="card" style="flex:2;min-width:420px;max-width:680px;padding:18px 24px;margin:0;">
            <h2 style="margin-top:0;"><?php echo '' !== $editing_id ? 'Editar plantilla personalizada' : 'Crear nueva plantilla de email'; ?></h2>
            <p class="description">Activa los módulos que quieras incluir en el resumen y ordénalos con los botones <strong>Subir / Bajar</strong>.</p>

            <form method="post" action="<?php echo esc_url( '' !== $editing_id ? add_query_arg( 'edit_tpl', $editing_id, $base_url ) : $base_url ); ?>">
                <?php wp_nonce_field( 'bap_tpl_builder_action' ); ?>
                <input type="hidden" name="bap_tpl_id" value="<?php echo esc_attr( $editing_id ); ?>" />
                <input type="hidden" name="bap_module_order" id="bap_module_order" value="<?php echo esc_attr( implode( ',', $ordered_keys ) ); ?>" />

                <table class="form-table">
                    <tr>
                        <th><label for="bap_tpl_name">Nombre de la plantilla</label></th>
                        <td><input type="text" id="bap_tpl_name" name="bap_tpl_name" value="<?php echo esc_attr( $current['name'] ); ?>" class="regular-text" placeholder="Ej. Informe Ejecutivo Semanal" required /></td>
                    </tr>
                    <tr>
                        <th><label for="bap_tpl_format">Formato / Tema</label></th>
                        <td>
                            <select id="bap_tpl_format" name="bap_tpl_format">
                                <option value="html_dark" <?php selected( $current['format'], 'html_dark' ); ?>>HTML Visual — Tema Oscuro (Estilo Estadísticas)</option>
                                <option value="html_light" <?php selected( $current['format'], 'html_light' ); ?>>HTML Visual — Tema Claro</option>
                                <option value="text" <?php selected( $current['format'], 'text' ); ?>>Solo Texto Plano (Sin HTML)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bap_tpl_limit">Límite de filas en Tabla</label></th>
                        <td>
                            <input type="number" id="bap_tpl_limit" name="bap_tpl_limit" value="<?php echo (int) $current['table_limit']; ?>" min="0" max="200" class="small-text" />
                            <span class="description">(<code>0</code> = mostrar todos los eventos agrupados)</span>
                        </td>
                    </tr>
                </table>

                <h3>Módulos incluidos y orden</h3>
                <div id="bap-tpl-modules-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
                    <?php foreach ( $ordered_keys as $m_key ) :
                        $checked = in_array( $m_key, $current['modules'], true );
                    ?>
                        <div class="bap-mod-item" data-key="<?php echo esc_attr( $m_key ); ?>" style="display:flex;align-items:center;justify-content:space-between;background:#f6f7f7;border:1px solid #c3c4c7;padding:10px 12px;border-radius:4px;">
                            <label style="margin:0;cursor:pointer;flex:1;">
                                <input type="checkbox" name="bap_enabled_modules[]" value="<?php echo esc_attr( $m_key ); ?>" <?php checked( $checked ); ?> />
                                <strong><?php echo esc_html( $all_mods[ $m_key ] ); ?></strong>
                            </label>
                            <span style="display:inline-flex;gap:4px;">
                                <button type="button" class="button button-small bap-mod-up">&#9650;</button>
                                <button type="button" class="button button-small bap-mod-down">&#9660;</button>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <button type="submit" name="bap_save_custom_tpl" class="button button-primary">Guardar plantilla</button>
                    <?php if ( '' !== $editing_id ) : ?>
                        <button type="submit" name="bap_export_tpl_json" class="button button-secondary">Exportar JSON</button>
                        <button type="submit" name="bap_delete_custom_tpl" class="button button-link-delete" onclick="return confirm('¿Eliminar esta plantilla?');">Eliminar</button>
                        <a href="<?php echo esc_url( $base_url ); ?>" class="button button-secondary">+ Nueva</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div style="flex:1;min-width:290px;max-width:400px;display:flex;flex-direction:column;gap:16px;">
            <div class="card" style="padding:16px 20px;margin:0;">
                <h3 style="margin-top:0;">Mis plantillas guardadas</h3>
                <?php if ( empty( $customs ) ) : ?>
                    <p class="description">Aún no has creado ninguna plantilla personalizada.</p>
                <?php else : ?>
                    <ul style="margin:0;padding:0;list-style:none;">
                        <?php foreach ( $customs as $cid => $ctpl ) : ?>
                            <li style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #dcdcde;">
                                <div>
                                    <strong><?php echo esc_html( $ctpl['name'] ); ?></strong><br>
                                    <small style="color:#646970;"><?php echo esc_html( strtoupper( $ctpl['format'] ) . ' · ' . count( $ctpl['modules'] ) . ' módulos' ); ?></small>
                                </div>
                                <a href="<?php echo esc_url( add_query_arg( 'edit_tpl', $cid, $base_url ) ); ?>" class="button button-small">Editar</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="card" style="padding:16px 20px;margin:0;">
                <h3 style="margin-top:0;">Importar plantilla (.json)</h3>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'bap_tpl_builder_action' ); ?>
                    <input type="file" name="bap_tpl_json_file" accept=".json" required style="margin-bottom:10px;width:100%;" />
                    <button type="submit" name="bap_import_tpl_json" class="button button-secondary">Importar plantilla JSON</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    const list = document.getElementById('bap-tpl-modules-list');
    const orderInp = document.getElementById('bap_module_order');
    if (!list || !orderInp) return;
    function sync() {
        orderInp.value = Array.from(list.querySelectorAll('.bap-mod-item')).map(el => el.dataset.key).join(',');
    }
    list.addEventListener('click', function(e) {
        const up = e.target.closest('.bap-mod-up');
        const down = e.target.closest('.bap-mod-down');
        if (!up && !down) return;
        const item = (up || down).closest('.bap-mod-item');
        if (up && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
            sync();
        } else if (down && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
            sync();
        }
    });
})();
</script>
