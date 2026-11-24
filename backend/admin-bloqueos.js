(function () {
    const meta = (window.bapAdminData && window.bapAdminData.ipMeta) ? window.bapAdminData.ipMeta : {};
    const ta = document.getElementById('bap_blacklist_textarea');
    const tip = document.getElementById('bap_ip_tooltip');

    if (ta && tip) {
        ta.addEventListener('mousemove', function (e) {
            const rect = ta.getBoundingClientRect();
            const style = window.getComputedStyle(ta);
            const lineHeight = parseFloat(style.lineHeight) || 20;
            const padTop = parseFloat(style.paddingTop) || 0;
            const lineIdx = Math.floor((e.clientY - rect.top - padTop + ta.scrollTop) / lineHeight);
            const lines = ta.value.split(/\r?\n/);
            const ip = (lines[lineIdx] || '').trim();

            if (ip && meta[ip]) {
                const d = meta[ip];
                tip.innerHTML =
                    '<strong style="color:#72aee6;font-size:13px;">' + ip + '</strong><br>' +
                    '<strong>País:</strong> ' + d.country + '<br>' +
                    '<strong>ASN:</strong> ' + d.asn + '<br>' +
                    '<strong>Intentos:</strong> ' + d.count + ' (' + d.reason + ')<br>' +
                    '<strong>Riesgo AbuseIPDB:</strong> ' + d.risk;
                tip.style.display = 'block';
                tip.style.left = (e.clientX + 14) + 'px';
                tip.style.top = (e.clientY + 14) + 'px';
            } else {
                tip.style.display = 'none';
            }
        });
        ta.addEventListener('mouseleave', function () {
            tip.style.display = 'none';
        });
    }

    const keyInput = document.getElementById('bap_secret_key_input');
    const toggleBtn = document.getElementById('bap_toggle_secret');
    const copyBtn = document.getElementById('bap_copy_secret');
    const genBtn = document.getElementById('bap_gen_secret');
    const feedback = document.getElementById('bap_copy_feedback');
    const strengthBox = document.getElementById('bap_key_strength');

    function evaluateKeyStrength() {
        if (!keyInput || !strengthBox) return;
        const val = keyInput.value;
        if (!val) {
            strengthBox.innerHTML = '<span style="color:#646970;">Sin llave configurada (función desactivada).</span>';
            return;
        }
        if (/[\u0590-\u08FF\uFB1D-\uFEFC]/.test(val)) {
            strengthBox.innerHTML = '<span style="color:#d63638;">Solo se permiten alfabetos de izquierda a derecha (LTR).</span>';
            return;
        }
        if (/[^\p{L}\p{N}\-_.~!*:@]/u.test(val)) {
            strengthBox.innerHTML = '<span style="color:#d63638;">Contiene símbolos no permitidos (usa solo letras LTR, números y - _ . ~ ! * : @).</span>';
            return;
        }
        const hasU = /\p{Lu}/u.test(val);
        const hasL = /\p{Ll}/u.test(val);
        const hasN = /[0-9]/.test(val);
        const hasS = /[\-_.~!*:@]/.test(val);
        const groups = (hasU ? 1 : 0) + (hasL ? 1 : 0) + (hasN ? 1 : 0) + (hasS ? 1 : 0);
        const req = groups === 4 ? 15 : (groups === 3 ? 20 : (groups === 2 ? 25 : 30));
        const len = Array.from(val).length;

        if (len >= req) {
            strengthBox.innerHTML = '<span style="color:#008a20;">Válida (' + len + '/' + req + ' caracteres, ' + groups + ' tipos).</span>';
        } else {
            strengthBox.innerHTML = '<span style="color:#d63638;">Insuficiente: ' + len + ' de ' + req + ' caracteres requeridos para ' + groups + ' tipo(s).</span>';
        }
    }

    if (keyInput) {
        keyInput.addEventListener('input', evaluateKeyStrength);
        evaluateKeyStrength();
    }

    if (toggleBtn && keyInput) {
        toggleBtn.addEventListener('click', function () {
            const isPass = keyInput.type === 'password';
            keyInput.type = isPass ? 'text' : 'password';
            const icon = toggleBtn.querySelector('.dashicons');
            if (icon) {
                icon.className = isPass ? 'dashicons dashicons-hidden' : 'dashicons dashicons-visibility';
            }
        });
    }

    if (copyBtn && keyInput) {
        copyBtn.addEventListener('click', function () {
            if (!keyInput.value) return;
            navigator.clipboard.writeText(keyInput.value).then(function () {
                if (feedback) {
                    feedback.style.display = 'inline';
                    setTimeout(function () { feedback.style.display = 'none'; }, 2000);
                }
            });
        });
    }

    if (genBtn && keyInput) {
        genBtn.addEventListener('click', function () {
            const U = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            const L = 'abcdefghijkmnopqrstuvwxyz';
            const N = '23456789';
            const S = '-_.*!@:';
            const all = U + L + N + S;
            let pwd = [
                U[Math.floor(Math.random() * U.length)],
                L[Math.floor(Math.random() * L.length)],
                N[Math.floor(Math.random() * N.length)],
                S[Math.floor(Math.random() * S.length)]
            ];
            for (let i = 4; i < 18; i++) {
                pwd.push(all[Math.floor(Math.random() * all.length)]);
            }
            pwd.sort(() => Math.random() - 0.5);
            keyInput.value = pwd.join('');
            keyInput.type = 'text';
            evaluateKeyStrength();
        });
    }

    function syncRadioInputs(radioName, allowId, blockId) {
        const selected = document.querySelector('input[name="' + radioName + '"]:checked')?.value || 'allow';
        const allowEl = document.getElementById(allowId);
        const blockEl = document.getElementById(blockId);
        if (allowEl) {
            allowEl.readOnly = (selected !== 'allow');
            allowEl.style.opacity = (selected === 'allow') ? '1' : '0.45';
        }
        if (blockEl) {
            blockEl.readOnly = (selected !== 'block');
            blockEl.style.opacity = (selected === 'block') ? '1' : '0.45';
        }
    }

    document.querySelectorAll('input[name="bap_country_mode"]').forEach(r => {
        r.addEventListener('change', () => syncRadioInputs('bap_country_mode', 'bap_allowed_countries', 'bap_blocked_countries'));
    });
    document.querySelectorAll('input[name="bap_asn_mode"]').forEach(r => {
        r.addEventListener('change', () => syncRadioInputs('bap_asn_mode', 'bap_allowed_asns', 'bap_blocked_asns'));
    });

    syncRadioInputs('bap_country_mode', 'bap_allowed_countries', 'bap_blocked_countries');
    syncRadioInputs('bap_asn_mode', 'bap_allowed_asns', 'bap_blocked_asns');

    const rulesForm = document.getElementById('bap_rules_form');
    if (rulesForm) {
        rulesForm.addEventListener('submit', function (e) {
            const ctryMode = document.querySelector('input[name="bap_country_mode"]:checked')?.value || 'allow';
            const asnMode = document.querySelector('input[name="bap_asn_mode"]:checked')?.value || 'allow';

            const ctry = (ctryMode === 'allow') ? (document.getElementById('bap_allowed_countries')?.value || '').trim() : '';
            const asn = (asnMode === 'allow') ? (document.getElementById('bap_allowed_asns')?.value || '').trim() : '';
            const wl = (document.getElementById('bap_whitelist_ips')?.value || '').trim();
            const key = (keyInput?.value || '').trim();

            if (!ctry && !asn && !wl && !key) {
                const ok = confirm('AVISO DE SEGURIDAD:\n\nNo tienes activo ningún filtro de PERMITIDOS (Países permitidos, ASNs permitidos o Lista Blanca) ni Llave secreta URL.\n\nSi guardas así, podrías quedar bloqueado o dejar el acceso abierto según tus reglas. ¿Seguro que deseas continuar?');
                if (!ok) {
                    e.preventDefault();
                }
            }
        });
    }
    const restorePathsBtn = document.getElementById('bap_restore_paths');
    const pathsTextarea = document.getElementById('bap_protected_paths');
    if (restorePathsBtn && pathsTextarea) {
        restorePathsBtn.addEventListener('click', function () {
            const def = restorePathsBtn.getAttribute('data-defaults') || '';
            pathsTextarea.value = def;
        });
    }
})();