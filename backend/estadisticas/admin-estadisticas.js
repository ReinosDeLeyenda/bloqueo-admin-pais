(function () {
    const table = document.getElementById('bap-logs-table');
    if (!table) return;

    const tip = document.createElement('div');
    tip.style.cssText = 'display:none;position:fixed;z-index:999999;background:#1d2327;color:#f0f0f1;border:1px solid #3c434a;border-radius:6px;padding:12px 14px;font-size:12px;line-height:1.55;max-width:440px;word-break:break-word;box-shadow:0 6px 20px rgba(0,0,0,0.45);pointer-events:none;';
    document.body.appendChild(tip);

    function addLine(parent, label, value, valueColor) {
        const row = document.createElement('div');
        row.style.marginBottom = '3px';
        const strong = document.createElement('strong');
        strong.style.color = '#a7aaad';
        strong.textContent = label + ': ';
        const span = document.createElement('span');
        if (valueColor) {
            span.style.color = valueColor;
            span.style.fontWeight = '600';
        }
        span.textContent = value;
        row.appendChild(strong);
        row.appendChild(span);
        parent.appendChild(row);
    }

    function positionTip(e) {
        const offset = 16;
        let left = e.clientX + offset;
        let top = e.clientY + offset;
        const rect = tip.getBoundingClientRect();
        if (left + rect.width > window.innerWidth - 12) {
            left = Math.max(12, e.clientX - rect.width - offset);
        }
        if (top + rect.height > window.innerHeight - 12) {
            top = Math.max(12, e.clientY - rect.height - offset);
        }
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
    }

    table.addEventListener('mouseover', function (e) {
        const tr = e.target.closest('tr.bap-log-row');
        if (!tr || !tr.dataset.tip) return;
        let d = null;
        try { d = JSON.parse(tr.dataset.tip); } catch (err) { return; }

        tip.textContent = '';

        const header = document.createElement('div');
        header.style.cssText = 'font-size:13px;font-weight:700;color:#72aee6;border-bottom:1px solid #3c434a;padding-bottom:6px;margin-bottom:8px;';
        header.textContent = 'IP Atacante: ' + d.ip;
        tip.appendChild(header);

        addLine(tip, 'Última vez', d.last_seen, '#ffffff');
        addLine(tip, 'Primera vez', d.first_seen, '#ffffff');
        addLine(tip, 'Total bloqueos de esta IP', String(d.total_hits), '#f86368');

        const sep = document.createElement('hr');
        sep.style.cssText = 'border:none;border-top:1px solid #2c3338;margin:7px 0;';
        tip.appendChild(sep);

        addLine(tip, 'País', d.country);
        addLine(tip, 'ASN / Proveedor', d.asn);
        addLine(tip, 'Riesgo AbuseIPDB', d.risk, d.risk !== 'N/D' ? '#dba617' : '');
        addLine(tip, 'Nodo Tor / FireHOL', 'Tor: ' + d.tor + ' | FireHOL: ' + d.firehol);
        addLine(tip, 'Entorno / Tipo', d.block_type);
        addLine(tip, 'Ruta atacada', d.uri, '#72aee6');
        addLine(tip, 'Motivo', d.reason);
        addLine(tip, 'User-Agent', d.user_agent || 'No registrado', '#c3c4c7');

        tip.style.display = 'block';
        positionTip(e);
    });

    table.addEventListener('mousemove', function (e) {
        if (tip.style.display === 'block') {
            positionTip(e);
        }
    });

    table.addEventListener('mouseout', function (e) {
        const tr = e.target.closest('tr.bap-log-row');
        if (tr && !tr.contains(e.relatedTarget)) {
            tip.style.display = 'none';
        }
    });

    const tbody = table.querySelector('tbody');
    const headers = table.querySelectorAll('th[data-col]');
    let sortState = { col: -1, asc: true };

    headers.forEach(th => {
        th.addEventListener('click', () => {
            const col = parseInt(th.dataset.col, 10);
            const type = th.dataset.type || 'text';
            const asc = (sortState.col === col) ? !sortState.asc : true;
            sortState = { col, asc };

            const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.children.length > 1);
            rows.sort((a, b) => {
                const va = (a.children[col]?.dataset.sort ?? a.children[col]?.textContent ?? '').trim();
                const vb = (b.children[col]?.dataset.sort ?? b.children[col]?.textContent ?? '').trim();

                if (type === 'num') {
                    return asc ? (parseFloat(va) - parseFloat(vb)) : (parseFloat(vb) - parseFloat(va));
                }
                return asc ? va.localeCompare(vb, undefined, { numeric: true }) : vb.localeCompare(va, undefined, { numeric: true });
            });

            rows.forEach(r => tbody.appendChild(r));
        });
    });
})();

(function () {
    const wrap = document.getElementById('bap-filter-rows-wrap');
    if (!wrap) return;

    function syncDel() {
        const rows = wrap.querySelectorAll('.bap-filter-row');
        rows.forEach(r => {
            const d = r.querySelector('.bap-del-frow');
            if (d) d.disabled = (rows.length <= 1);
        });
    }

    wrap.addEventListener('click', function (e) {
        const sw = e.target.closest('.bap-fmode-switch');
        if (sw) {
            const row = sw.closest('.bap-filter-row');
            const inp = row.querySelector('.bap-fmode-val');
            const dot = sw.querySelector('.bap-fmode-dot');
            const txt = sw.querySelector('.bap-fmode-txt');
            const inc = (inp.value !== 'include');
            inp.value = inc ? 'include' : 'exclude';
            sw.style.background = inc ? '#2271b1' : '#d63638';
            txt.textContent = inc ? 'Incluir' : 'Excluir';
            txt.style.order = inc ? '1' : '2';
            dot.style.order = inc ? '2' : '1';
            return;
        }
        const add = e.target.closest('.bap-add-frow');
        if (add) {
            const row = add.closest('.bap-filter-row');
            const cl = row.cloneNode(true);
            cl.querySelectorAll('input[type="text"]').forEach(i => { i.value = ''; });
            const inp = cl.querySelector('.bap-fmode-val');
            const sb = cl.querySelector('.bap-fmode-switch');
            const dot = cl.querySelector('.bap-fmode-dot');
            const txt = cl.querySelector('.bap-fmode-txt');
            if (inp) inp.value = 'include';
            if (sb) sb.style.background = '#2271b1';
            if (txt) { txt.textContent = 'Incluir'; txt.style.order = '1'; }
            if (dot) dot.style.order = '2';
            row.after(cl);
            syncDel();
            return;
        }
        const del = e.target.closest('.bap-del-frow');
        if (del && wrap.querySelectorAll('.bap-filter-row').length > 1) {
            del.closest('.bap-filter-row').remove();
            syncDel();
        }
    });
    syncDel();
})();