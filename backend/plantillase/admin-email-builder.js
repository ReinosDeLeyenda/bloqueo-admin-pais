(function () {
    const list = document.getElementById('bap-tpl-modules-list');
    const orderInp = document.getElementById('bap_module_order');
    const addBar = document.getElementById('bap-available-mods-bar');
    const frame = document.getElementById('bap-email-preview-frame');
    const statusEl = document.getElementById('bap-preview-status');
    const nonce = (window.bapEmailBuilderData && window.bapEmailBuilderData.nonce) ? window.bapEmailBuilderData.nonce : '';
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

    list.addEventListener('change', function (e) {
        const sel = e.target.closest('.bap-mod-size-sel');
        if (sel) {
            const card = sel.closest('.bap-mod-item');
            if (card) card.style.width = widthForSize(sel.value);
            schedulePreview();
        }
    });

    list.addEventListener('click', function (e) {
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
        addBar.addEventListener('click', function (e) {
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
    list.addEventListener('dragstart', function (e) {
        const item = e.target.closest('.bap-mod-item');
        if (!item) return;
        dragged = item;
        item.style.opacity = '0.45';
        e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragend', function () {
        if (dragged) { dragged.style.opacity = ''; dragged = null; }
    });
    list.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });
    list.addEventListener('drop', function (e) {
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
