(function () {
    const editor = document.getElementById('bap-tg-wysiwyg');
    const textarea = document.getElementById('bap_tg_tpl_html');
    const preview = document.getElementById('bap-tg-live-preview');
    const toolbar = document.getElementById('bap-tg-toolbar');
    const toggleBtn = document.getElementById('bap-tg-toggle-code');
    const form = document.getElementById('bap-tg-builder-form');
    const samples = (window.bapTgBuilderData && window.bapTgBuilderData.samples) ? window.bapTgBuilderData.samples : {};
    if (!editor || !textarea || !preview) return;

    let codeMode = false;

    function renderPreview() {
        let html = codeMode ? textarea.value : editor.innerHTML;
        Object.entries(samples).forEach(([token, val]) => {
            html = html.split(token).join(val);
        });
        preview.innerHTML = html;
        preview.querySelectorAll('table').forEach(t => {
            t.style.cssText = 'width:100%;border-collapse:collapse;margin:8px 0;font-size:12px;';
            t.querySelectorAll('th,td').forEach(c => {
                c.style.cssText = 'border:1px solid #2b3b4e;padding:5px 8px;text-align:left;';
            });
        });
        preview.querySelectorAll('blockquote').forEach(bq => {
            bq.style.cssText = 'border-left:3px solid #6ab2f2;background:rgba(106,178,242,0.1);padding:6px 10px;margin:8px 0;border-radius:0 4px 4px 0;';
        });
        preview.querySelectorAll('code').forEach(cd => {
            cd.style.cssText = 'background:#0e1621;color:#72aee6;padding:1px 4px;border-radius:3px;font-family:monospace;';
        });
    }

    function syncFromEditor() {
        textarea.value = editor.innerHTML;
        renderPreview();
    }

    function insertHtmlAtCursor(html) {
        if (codeMode) {
            const start = textarea.selectionStart || 0;
            const end = textarea.selectionEnd || 0;
            textarea.value = textarea.value.slice(0, start) + html + textarea.value.slice(end);
            renderPreview();
            return;
        }
        editor.focus();
        document.execCommand('insertHTML', false, html);
        syncFromEditor();
    }

    if (toolbar) {
        toolbar.addEventListener('click', function (e) {
            const btn = e.target.closest('button');
            if (!btn) return;

            if (btn.dataset.cmd) {
                if (!codeMode) {
                    editor.focus();
                    document.execCommand(btn.dataset.cmd, false, null);
                    syncFromEditor();
                }
                return;
            }

            if (btn.dataset.tag) {
                const selText = window.getSelection()?.toString() || 'Texto';
                if (btn.dataset.tag === 'h2') insertHtmlAtCursor('<h2>' + selText + '</h2>');
                if (btn.dataset.tag === 'h3') insertHtmlAtCursor('<h3>' + selText + '</h3>');
                if (btn.dataset.tag === 'code') insertHtmlAtCursor('<code>' + selText + '</code>');
                if (btn.dataset.tag === 'quote') insertHtmlAtCursor('<blockquote expandable>' + selText + '</blockquote>');
                if (btn.dataset.tag === 'spoiler') insertHtmlAtCursor('<tg-spoiler>' + selText + '</tg-spoiler>');
            }
        });
    }

    document.getElementById('bap-tg-insert-table')?.addEventListener('click', function () {
        insertHtmlAtCursor('<table><tr><th>Campo</th><th>Valor</th></tr><tr><td><b>Dato</b></td><td>{ip}</td></tr></table>');
    });

    document.querySelectorAll('.bap-tg-insert-var').forEach(btn => {
        btn.addEventListener('click', function () {
            insertHtmlAtCursor(btn.dataset.var || '');
        });
    });

    toggleBtn?.addEventListener('click', function () {
        codeMode = !codeMode;
        if (codeMode) {
            textarea.value = editor.innerHTML;
            editor.style.display = 'none';
            textarea.style.display = 'block';
            toggleBtn.textContent = 'Ver Editor Visual';
        } else {
            editor.innerHTML = textarea.value;
            textarea.style.display = 'none';
            editor.style.display = 'block';
            toggleBtn.textContent = 'Ver HTML';
        }
        renderPreview();
    });

    editor.addEventListener('input', syncFromEditor);
    textarea.addEventListener('input', renderPreview);
    form?.addEventListener('submit', function () {
        if (!codeMode) {
            textarea.value = editor.innerHTML;
        }
    });

    renderPreview();
})();
