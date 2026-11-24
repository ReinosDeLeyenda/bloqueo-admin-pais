(function () {
    const DATA = window.BAP_COUNTRIES_DATA || { ISO_CODES: [], REGIONS: [] };
    let dn = null;
    try { dn = new Intl.DisplayNames(['es'], { type: 'region' }); } catch (e) {}

    const BY_CODE = {};
    const COUNTRIES = DATA.ISO_CODES.map(code => {
        let name = code;
        if (dn) { try { name = dn.of(code) || code; } catch (e) {} }
        BY_CODE[code] = name;
        return {
            code: code,
            name: name,
            norm: (name + ' ' + code).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        };
    });

    function attachCountryPicker(input) {
        if (!input || input.dataset.bapPicker) return;
        input.dataset.bapPicker = '1';

        const outer = document.createElement('div');
        outer.style.cssText = 'width:100%;max-width:460px;';
        input.parentNode.insertBefore(outer, input);

        const bar = document.createElement('div');
        bar.style.cssText = 'display:flex;flex-wrap:wrap;gap:4px;margin-bottom:6px;';
        outer.appendChild(bar);

        const wrap = document.createElement('div');
        wrap.style.cssText = 'position:relative;display:block;width:100%;max-width:380px;';
        outer.appendChild(wrap);
        wrap.appendChild(input);

        const dropdown = document.createElement('div');
        dropdown.style.cssText = 'display:none;position:absolute;left:0;right:0;top:100%;z-index:99999;background:#1d2327;color:#fff;border:1px solid #3c434a;border-radius:4px;max-height:210px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,0.35);';
        wrap.appendChild(dropdown);

        const tagsBox = document.createElement('div');
        tagsBox.style.cssText = 'margin-top:5px;font-size:12px;color:#8c8f94;line-height:1.5;';
        outer.appendChild(tagsBox);

        function renderTags() {
            const codes = input.value.split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
            tagsBox.textContent = '';
            if (!codes.length) return;

            codes.slice(0, 15).forEach(c => {
                const span = document.createElement('span');
                const strong = document.createElement('strong');
                strong.textContent = c;
                span.appendChild(strong);

                if (BY_CODE[c]) {
                    span.style.cssText = 'display:inline-block;background:#2c3338;color:#72aee6;padding:1px 6px;border-radius:3px;margin:2px 4px 2px 0;';
                    span.appendChild(document.createTextNode(' = ' + BY_CODE[c]));
                } else {
                    span.style.cssText = 'display:inline-block;background:#3a1d1d;color:#f86368;padding:1px 6px;border-radius:3px;margin:2px 4px 2px 0;';
                    span.appendChild(document.createTextNode(' (código no reconocido)'));
                }
                tagsBox.appendChild(span);
            });

            if (codes.length > 15) {
                const more = document.createElement('span');
                more.style.cssText = 'display:inline-block;color:#646970;margin:2px 4px;';
                more.textContent = '... +' + (codes.length - 15) + ' países más (total: ' + codes.length + ')';
                tagsBox.appendChild(more);
            }
        }

        DATA.REGIONS.forEach(reg => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'button button-small';
            btn.textContent = reg.label;
            btn.addEventListener('click', function () {
                if (input.readOnly) return;
                const existing = input.value.split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
                reg.codes.forEach(code => { if (!existing.includes(code)) existing.push(code); });
                input.value = existing.join(', ');
                renderTags();
            });
            bar.appendChild(btn);
        });

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'button button-small';
        clearBtn.textContent = 'Limpiar';
        clearBtn.style.color = '#d63638';
        clearBtn.addEventListener('click', function () {
            if (input.readOnly) return;
            input.value = '';
            renderTags();
        });
        bar.appendChild(clearBtn);

        function updateDropdown() {
            if (input.readOnly) { dropdown.style.display = 'none'; return; }
            const parts = input.value.split(',');
            const rawTerm = (parts[parts.length - 1] || '').trim();
            if (!rawTerm) { dropdown.style.display = 'none'; return; }

            const term = rawTerm.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            const matches = COUNTRIES.filter(c => c.norm.includes(term)).slice(0, 10);
            if (!matches.length) { dropdown.style.display = 'none'; return; }

            dropdown.textContent = '';
            matches.forEach(c => {
                const row = document.createElement('div');
                row.className = 'bap-country-opt';
                row.setAttribute('data-code', c.code);
                row.style.cssText = 'padding:7px 10px;cursor:pointer;border-bottom:1px solid #2c3338;display:flex;justify-content:space-between;';
                const nameSpan = document.createElement('span');
                nameSpan.textContent = c.name;
                const codeStrong = document.createElement('strong');
                codeStrong.style.color = '#72aee6';
                codeStrong.textContent = c.code;
                row.appendChild(nameSpan);
                row.appendChild(codeStrong);
                dropdown.appendChild(row);
            });
            dropdown.style.display = 'block';
        }

        dropdown.addEventListener('mousedown', function (e) {
            const item = e.target.closest('.bap-country-opt');
            if (!item) return;
            e.preventDefault();
            const chosen = item.getAttribute('data-code');
            const existing = input.value.split(',').slice(0, -1).map(s => s.trim().toUpperCase()).filter(Boolean);
            if (!existing.includes(chosen)) existing.push(chosen);
            input.value = existing.join(', ') + ', ';
            dropdown.style.display = 'none';
            renderTags();
            input.focus();
        });

        input.addEventListener('input', () => { updateDropdown(); renderTags(); });
        input.addEventListener('focus', updateDropdown);
        input.addEventListener('blur', () => { setTimeout(() => { dropdown.style.display = 'none'; }, 150); renderTags(); });
        renderTags();
    }

    const selectors = [
        'input[name="bap_allowed_countries"]',
        'input[name="bap_blocked_countries"]',
        'input[name="bap_web_allowed_countries"]',
        'input[name="bap_web_blocked_countries"]'
    ];
    document.querySelectorAll(selectors.join(',')).forEach(attachCountryPicker);
})();
