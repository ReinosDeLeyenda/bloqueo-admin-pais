<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once plugin_dir_path( __FILE__ ) . 'admin-email-builder-sidebar.php';

$base_url    = admin_url( 'admin.php?page=bap-plantillas&subtab=email' );
$active_mods = $current['modules'] ?? [];
$mod_sizes   = $current['sizes'] ?? [];
?>
<div>
    <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">
        <div class="card" style="flex:2;min-width:440px;max-width:720px;padding:18px 24px;margin:0;">
            <h2 style="margin-top:0;"><?php echo '' !== $editing_id ? 'Editar plantilla personalizada' : 'Crear plantilla modular personalizada'; ?></h2>
            <p class="description">Arrastra las tarjetas para reordenarlas, elige su <strong>ancho (100%, 50% o 33%)</strong> para poner bloques en paralelo, o pulsa <strong>&times;</strong> para quitar un módulo.</p>

            <form method="post" action="<?php echo esc_url( '' !== $editing_id ? add_query_arg( 'edit_tpl', $editing_id, $base_url ) : $base_url ); ?>">
                <?php wp_nonce_field( 'bap_tpl_builder_action' ); ?>
                <input type="hidden" name="bap_tpl_id" value="<?php echo esc_attr( $editing_id ); ?>" />
                <input type="hidden" name="bap_module_order" id="bap_module_order" value="<?php echo esc_attr( implode( ',', $active_mods ) ); ?>" />

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
                            <span class="description">(0 = mostrar todos los eventos agrupados)</span>
                        </td>
                    </tr>
                </table>

                <div style="display:flex;justify-content:space-between;align-items:center;margin:14px 0 8px 0;flex-wrap:wrap;gap:8px;">
                    <h3 style="margin:0;">Módulos activos (Arrastra para ordenar)</h3>
                    <div id="bap-available-mods-bar" style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php foreach ( $all_mods as $m_key => $m_label ) :
                            $is_used = in_array( $m_key, $active_mods, true );
                        ?>
                            <button type="button" class="button button-small bap-add-mod-btn" data-key="<?php echo esc_attr( $m_key ); ?>" data-label="<?php echo esc_attr( $m_label ); ?>" style="<?php echo $is_used ? 'display:none;' : ''; ?>">
                                + <?php echo esc_html( strtok( $m_label, '(' ) ); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="bap-tpl-modules-list" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;min-height:60px;padding:10px;border:1px dashed #8c8f94;border-radius:6px;">
                    <?php foreach ( $active_mods as $m_key ) :
                        if ( ! isset( $all_mods[ $m_key ] ) ) {
                            continue;
                        }
                        $sz     = (string) ( $mod_sizes[ $m_key ] ?? '100' );
                        $flex_w = ( '50' === $sz ) ? 'calc(50% - 5px)' : ( ( '33' === $sz ) ? 'calc(33.33% - 7px)' : '100%' );
                    ?>
                        <div class="bap-mod-item" draggable="true" data-key="<?php echo esc_attr( $m_key ); ?>" style="width:<?php echo esc_attr( $flex_w ); ?>;box-sizing:border-box;display:flex;align-items:center;justify-content:space-between;gap:8px;background:#f6f7f7;border:1px solid #c3c4c7;padding:10px 12px;border-radius:4px;cursor:grab;">
                            <div style="display:flex;align-items:center;gap:6px;overflow:hidden;">
                                <span style="color:#646970;font-weight:bold;">&#9776;</span>
                                <strong style="font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo esc_attr( $all_mods[ $m_key ] ); ?>"><?php echo esc_html( $all_mods[ $m_key ] ); ?></strong>
                            </div>
                            <div style="display:inline-flex;align-items:center;gap:4px;flex-shrink:0;">
                                <select name="bap_mod_size[<?php echo esc_attr( $m_key ); ?>]" class="bap-mod-size-sel" data-key="<?php echo esc_attr( $m_key ); ?>" style="font-size:11px;min-height:26px;padding:0 4px;">
                                    <option value="100" <?php selected( $sz, '100' ); ?>>100%</option>
                                    <option value="50" <?php selected( $sz, '50' ); ?>>50%</option>
                                    <option value="33" <?php selected( $sz, '33' ); ?>>33%</option>
                                </select>
                                <button type="button" class="button button-small bap-mod-remove" title="Quitar módulo" style="color:#d63638;font-weight:700;padding:0 7px;">&times;</button>
                            </div>
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

        <?php bap_render_email_builder_sidebar( $presets, $customs, $active_tpl, $base_url ); ?>
    </div>

    <div class="card" style="max-width:1100px;margin-top:22px;padding:18px 24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h2 style="margin:0;">Vista previa en tiempo real</h2>
            <span id="bap-preview-status" style="font-size:12px;color:#646970;">Actualizada automáticamente</span>
        </div>
        <iframe id="bap-email-preview-frame" style="width:100%;height:620px;border:1px solid #c3c4c7;border-radius:6px;background:#0a0a0a;"></iframe>
    </div>
</div>
