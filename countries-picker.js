(function () {
    const ISO_CODES = [
        'AD','AE','AF','AG','AI','AL','AM','AO','AQ','AR','AS','AT','AU','AW','AX','AZ',
        'BA','BB','BD','BE','BF','BG','BH','BI','BJ','BL','BM','BN','BO','BQ','BR','BS','BT','BV','BW','BY','BZ',
        'CA','CC','CD','CF','CG','CH','CI','CK','CL','CM','CN','CO','CR','CU','CV','CW','CX','CY','CZ',
        'DE','DJ','DK','DM','DO','DZ','EC','EE','EG','EH','ER','ES','ET','FI','FJ','FK','FM','FO','FR',
        'GA','GB','GD','GE','GF','GG','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT','GU','GW','GY',
        'HK','HM','HN','HR','HT','HU','ID','IE','IL','IM','IN','IO','IQ','IR','IS','IT','JE','JM','JO','JP',
        'KE','KG','KH','KI','KM','KN','KP','KR','KW','KY','KZ','LA','LB','LC','LI','LK','LR','LS','LT','LU','LV','LY',
        'MA','MC','MD','ME','MF','MG','MH','MK','ML','MM','MN','MO','MP','MQ','MR','MS','MT','MU','MV','MW','MX','MY','MZ',
        'NA','NC','NE','NF','NG','NI','NL','NO','NP','NR','NU','NZ','OM',
        'PA','PE','PF','PG','PH','PK','PL','PM','PN','PR','PS','PT','PW','PY','QA',
        'RE','RO','RS','RU','RW','SA','SB','SC','SD','SE','SG','SH','SI','SJ','SK','SL','SM','SN','SO','SR','SS','ST','SV','SX','SY','SZ',
        'TC','TD','TF','TG','TH','TJ','TK','TL','TM','TN','TO','TR','TT','TV','TW','TZ',
        'UA','UG','UM','US','UY','UZ','VA','VC','VE','VG','VI','VN','VU','WF','WS','YE','YT','ZA','ZM','ZW'
    ];

    let dn = null;
    try {
        dn = new Intl.DisplayNames(['es'], { type: 'region' });
    } catch (e) {}

    const COUNTRIES = ISO_CODES.map(code => {
        let name = code;
        if (dn) {
            try { name = dn.of(code) || code; } catch (e) {}
        }
        return {
            code: code,
            name: name,
            norm: (name + ' ' + code).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        };
    });

    const BY_CODE = {};
    COUNTRIES.forEach(c => { BY_CODE[c.code] = c.name; });

    function attachCountryPicker(input) {
        if (!input || input.dataset.bapPicker) return;
        input.dataset.bapPicker = '1';

        const wrap = document.createElement('div');
        wrap.style.position = 'relative';
        wrap.style.display = 'inline-block';
        wrap.style.width = '100%';
        wrap.style.maxWidth = '380px';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const dropdown = document.createElement('div');
        dropdown.style.cssText = 'display:none;position:absolute;left:0;right:0;top:100%;z-index:99999;background:#1d2327;color:#fff;border:1px solid #3c434a;border-radius:4px;max-height:210px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,0.35);';
        wrap.appendChild(dropdown);

        const tagsBox = document.createElement('div');
        tagsBox.style.cssText = 'margin-top:5px;font-size:12px;color:#8c8f94;line-height:1.5;';
        wrap.appendChild(tagsBox);

        function renderTags() {
            const codes = input.value.split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
            if (!codes.length) {
                tagsBox.textContent = '';
                return;
            }
            tagsBox.innerHTML = codes.map(c => {
                if (BY_CODE[c]) {
                    return '<span style="display:inline-block;background:#2c3338;color:#72aee6;padding:1px 6px;border-radius:3px;margin:2px 4px 2px 0;"><strong>' + c + '</strong> = ' + BY_CODE[c] + '</span>';
                }
                return '<span style="display:inline-block;background:#3a1d1d;color:#f86368;padding:1px 6px;border-radius:3px;margin:2px 4px 2px 0;"><strong>' + c + '</strong> (código no reconocido)</span>';
            }).join('');
        }

        function updateDropdown() {
            if (input.readOnly) {
                dropdown.style.display = 'none';
                return;
            }
            const parts = input.value.split(',');
            const rawTerm = (parts[parts.length - 1] || '').trim();
            if (!rawTerm) {
                dropdown.style.display = 'none';
                return;
            }
            const term = rawTerm.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            const matches = COUNTRIES.filter(c => c.norm.includes(term)).slice(0, 10);

            if (!matches.length) {
                dropdown.style.display = 'none';
                return;
            }

            dropdown.innerHTML = matches.map(c =>
                '<div class="bap-country-opt" data-code="' + c.code + '" style="padding:7px 10px;cursor:pointer;border-bottom:1px solid #2c3338;display:flex;justify-content:space-between;">' +
                '<span>' + c.name + '</span><strong style="color:#72aee6;">' + c.code + '</strong></div>'
            ).join('');
            dropdown.style.display = 'block';
        }

        dropdown.addEventListener('mousedown', function (e) {
            const item = e.target.closest('.bap-country-opt');
            if (!item) return;
            e.preventDefault();
            const chosen = item.getAttribute('data-code');
            const existing = input.value.split(',').slice(0, -1).map(s => s.trim().toUpperCase()).filter(Boolean);
            if (!existing.includes(chosen)) {
                existing.push(chosen);
            }
            input.value = existing.join(', ') + ', ';
            dropdown.style.display = 'none';
            renderTags();
            input.focus();
        });

        input.addEventListener('input', function () {
            updateDropdown();
            renderTags();
        });
        input.addEventListener('focus', updateDropdown);
        input.addEventListener('blur', function () {
            setTimeout(() => { dropdown.style.display = 'none'; }, 150);
            renderTags();
        });

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
