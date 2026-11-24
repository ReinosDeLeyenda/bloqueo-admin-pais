<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$base_url    = admin_url( 'admin.php?page=bap-email-templates' );
$active_mods = $current['modules'] ?? [];
$mod_sizes   = $current['sizes'] ?? [];
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-email-templates' ); ?>
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
        <div style="flex:1;min-width:290px;max-width:400px;display:flex;flex-direction:column;gap:16px;">
            <div class="card" style="padding:16px 20px;margin:0;">
                <h3 style="margin-top:0;">Plantillas predefinidas (Base)</h3>
                <ul style="margin:0;padding:0;list-style:none;">
                    <?php foreach ( $presets as $pkey => $pdata ) : ?>
                        <li style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #dcdcde;">
                            <div>
                                <strong><?php echo esc_html( $pdata['name'] ); ?></strong>
                                <?php if ( $active_tpl === $pkey ) : ?>
                                    <span style="background:#00a32a;color:#fff;font-size:10px;padding:1px 6px;border-radius:3px;margin-left:4px;">ACTIVA</span>
                                <?php endif; ?>
                                <br><small style="color:#646970;"><?php echo esc_html( $pdata['desc'] ); ?></small>
                            </div>
                            <a href="<?php echo esc_url( add_query_arg( 'clone_preset', $pkey, $base_url ) ); ?>" class="button button-small">Clonar</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card" style="padding:16px 20px;margin:0;">
                <h3 style="margin-top:0;">Mis plantillas personalizadas</h3>
                <?php if ( empty( $customs ) ) : ?>
                    <p class="description">Aún no has creado ninguna. Usa el editor o pulsa <strong>Clonar</strong> sobre una predefinida.</p>
                <?php else : ?>
                    <ul style="margin:0;padding:0;list-style:none;">
                        <?php foreach ( $customs as $cid => $ctpl ) : ?>
                            <li style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #dcdcde;">
                                <div>
                                    <strong><?php echo esc_html( $ctpl['name'] ); ?></strong>
                                    <?php if ( $active_tpl === 'custom_' . $cid ) : ?>
                                        <span style="background:#00a32a;color:#fff;font-size:10px;padding:1px 6px;border-radius:3px;margin-left:4px;">ACTIVA</span>
                                    <?php endif; ?>
                                    <br><small style="color:#646970;"><?php echo esc_html( strtoupper( $ctpl['format'] ) . ' · ' . count( $ctpl['modules'] ) . ' módulos' ); ?></small>
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

    <div class="card" style="max-width:1100px;margin-top:22px;padding:18px 24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h2 style="margin:0;">Vista previa en tiempo real</h2>
            <span id="bap-preview-status" style="font-size:12px;color:#646970;">Actualizada automáticamente</span>
        </div>
        <iframe id="bap-email-preview-frame" style="width:100%;height:620px;border:1px solid #c3c4c7;border-radius:6px;background:#0a0a0a;"></iframe>
    </div>
</div>
<script>
(function(){
    const list = document.getElementById('bap-tpl-modules-list');
    const orderInp = document.getElementById('bap_module_order');
    const addBar = document.getElementById('bap-available-mods-bar');
    const frame = document.getElementById('bap-email-preview-frame');
    const statusEl = document.getElementById('bap-preview-status');
    const nonce = '<?php echo esc_js( wp_create_nonce( 'bap_tpl_preview_nonce' ) ); ?>';
    if (!list || !orderInp) return;

    let timer = null;
    function refreshPreview() {
        if (!frame) return;
        if (statusEl) statusEl.textContent = 'Actualizando vista previa...';
        const fd = new FormData();
        fd.append('action', 'bap_preview_email_template');
        fd.append('nonce', nonce);
        fd.append('name', document.getElementById('bap_tpl_name')?.value || 'Vista previa');
        fd.append('format', document.getElementById('bap_tpl_format')?.value || 'html_dark');
        fd.append('limit', document.getElementById('bap_tpl_limit')?.value || '10');
        fd.append('order', orderInp.value);
        list.querySelectorAll('.bap-mod-size-sel').forEach(sel => fd.append('sizes[' + sel.dataset.key + ']', sel.value));

        fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res && res.success && res.data) {
                    frame.srcdoc = res.data.html;
                    if (statusEl) statusEl.textContent = 'Vista previa actualizada';
                }
            });
    }

    function schedulePreview() {
        clearTimeout(timer);
        timer = setTimeout(refreshPreview, 220);
    }

    function sync() {
        orderInp.value = Array.from(list.querySelectorAll('.bap-mod-item')).map(el => el.dataset.key).join(',');
        schedulePreview();
    }

    function widthForSize(sz) {
        return sz === '50' ? 'calc(50% - 5px)' : (sz === '33' ? 'calc(33.33% - 7px)' : '100%');
    }

    list.addEventListener('change', function(e) {
        const sel = e.target.closest('.bap-mod-size-sel');
        if (sel) {
            const card = sel.closest('.bap-mod-item');
            if (card) card.style.width = widthForSize(sel.value);
            schedulePreview();
        }
    });

    list.addEventListener('click', function(e) {
        const rem = e.target.closest('.bap-mod-remove');
        if (rem) {
            const card = rem.closest('.bap-mod-item');
            const key = card.dataset.key;
            card.remove();
            const addBtn = addBar?.querySelector('.bap-add-mod-btn[data-key="' + key + '"]');
            if (addBtn) addBtn.style.display = 'inline-block';
            sync();
        }
    });

    if (addBar) {
        addBar.addEventListener('click', function(e) {
            const btn = e.target.closest('.bap-add-mod-btn');
            if (!btn) return;
            const key = btn.dataset.key;
            const label = btn.dataset.label;
            btn.style.display = 'none';

            const div = document.createElement('div');
            div.className = 'bap-mod-item';
            div.draggable = true;
            div.dataset.key = key;
            div.style.cssText = 'width:100%;box-sizing:border-box;display:flex;align-items:center;justify-content:space-between;gap:8px;background:#f6f7f7;border:1px solid #c3c4c7;padding:10px 12px;border-radius:4px;cursor:grab;';
            div.innerHTML = '<div style="display:flex;align-items:center;gap:6px;overflow:hidden;"><span style="color:#646970;font-weight:bold;">&#9776;</span><strong style="font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + label + '</strong></div>' +
                '<div style="display:inline-flex;align-items:center;gap:4px;flex-shrink:0;">' +
                '<select name="bap_mod_size[' + key + ']" class="bap-mod-size-sel" data-key="' + key + '" style="font-size:11px;min-height:26px;padding:0 4px;"><option value="100" selected>100%</option><option value="50">50%</option><option value="33">33%</option></select>' +
                '<button type="button" class="button button-small bap-mod-remove" title="Quitar módulo" style="color:#d63638;font-weight:700;padding:0 7px;">&times;</button></div>';
            list.appendChild(div);
            sync();
        });
    }

    let dragged = null;
    list.addEventListener('dragstart', function(e) {
        const item = e.target.closest('.bap-mod-item');
        if (!item) return;
        dragged = item;
        item.style.opacity = '0.45';
        e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragend', function() {
        if (dragged) { dragged.style.opacity = ''; dragged = null; }
    });
    list.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });
    list.addEventListener('drop', function(e) {
        e.preventDefault();
        const target = e.target.closest('.bap-mod-item');
        if (!dragged || !target || dragged === target) return;
        const items = Array.from(list.querySelectorAll('.bap-mod-item'));
        if (items.indexOf(dragged) < items.indexOf(target)) {
            target.after(dragged);
        } else {
            target.before(dragged);
        }
        sync();
    });

    ['bap_tpl_name', 'bap_tpl_format', 'bap_tpl_limit'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', schedulePreview);
    });

    refreshPreview();
})();
</script>
