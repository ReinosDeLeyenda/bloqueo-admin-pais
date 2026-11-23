(function () {
    const table = document.getElementById('bap_logs_table');
    if (table) {
        const headers = table.querySelectorAll('th.bap-sort');
        let currentCol = 4;
        let asc = false;

        headers.forEach((th, idx) => {
            th.addEventListener('click', function () {
                const type = th.getAttribute('data-type') || 'str';
                asc = (currentCol === idx) ? !asc : (type === 'str' || type === 'ip');
                currentCol = idx;

                headers.forEach(h => {
                    const arrow = h.querySelector('.bap-arrow');
                    if (arrow) arrow.textContent = '';
                });
                const activeArrow = th.querySelector('.bap-arrow');
                if (activeArrow) activeArrow.textContent = asc ? '▲' : '▼';

                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort((a, b) => {
                    const va = a.children[idx].getAttribute('data-sort') || '';
                    const vb = b.children[idx].getAttribute('data-sort') || '';
                    if (type === 'num') {
                        return asc ? (Number(va) - Number(vb)) : (Number(vb) - Number(va));
                    }
                    return asc ? va.localeCompare(vb) : vb.localeCompare(va);
                });
                rows.forEach(r => tbody.appendChild(r));
            });
        });
    }

    document.addEventListener('click', function (e) {
        const ipLink = e.target.closest('a.bap-link-ip2loc');
        if (ipLink) {
            e.preventDefault();
            const ip = ipLink.getAttribute('data-ip');
            if (ip) {
                const url = 'https://ip2location.com' + encodeURIComponent(ip);
                window.open(url, '_blank', 'noopener,noreferrer');
            }
            return;
        }
        const abuseLink = e.target.closest('a.bap-link-abuse');
        if (abuseLink) {
            e.preventDefault();
            const ip = abuseLink.getAttribute('data-ip');
            if (ip) {
                const url = ['https:/', '://abuseipdb.com', 'check', encodeURIComponent(ip)].join('/');
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        }
    });
})();
