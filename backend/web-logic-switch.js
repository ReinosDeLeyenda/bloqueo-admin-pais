(function () {
    const list = document.getElementById('bap-conditions-list');
    const orderInput = document.getElementById('bap_rule_order');

    function syncOrderAndInterleave(cards) {
        if (!list) return;
        const switches = Array.from(list.querySelectorAll('.bap-logic-row'));
        list.innerHTML = '';
        cards.forEach((card, idx) => {
            list.appendChild(card);
            if (idx < switches.length) {
                list.appendChild(switches[idx]);
            }
        });
        if (orderInput) {
            orderInput.value = cards.map(c => c.dataset.key).join(',');
        }
    }

    document.addEventListener('click', function (e) {
        const toggleBtn = e.target.closest('.bap-logic-toggle');
        if (toggleBtn) {
            const wrap = toggleBtn.parentElement;
            const input = wrap.querySelector('input[type="hidden"]');
            const bg = toggleBtn.querySelector('.bap-sw-bg');
            const txt = toggleBtn.querySelector('.bap-sw-txt');
            const knob = toggleBtn.querySelector('.bap-sw-knob');
            const hint = wrap.querySelector('.bap-sw-hint');

            const nextIsOr = (input.value === 'AND');
            input.value = nextIsOr ? 'OR' : 'AND';
            bg.setAttribute('fill', nextIsOr ? '#d97706' : '#2271b1');
            txt.setAttribute('x', nextIsOr ? '27' : '47');
            txt.textContent = nextIsOr ? 'O' : 'Y';
            knob.setAttribute('cx', nextIsOr ? '58' : '16');
            hint.textContent = nextIsOr ? 'Opcional (alternativa al filtro anterior)' : 'Obligatorio (se exige además del anterior)';
            return;
        }

        const upBtn = e.target.closest('.bap-move-up');
        const downBtn = e.target.closest('.bap-move-down');
        if ((upBtn || downBtn) && list) {
            const card = (upBtn || downBtn).closest('.bap-cond-card');
            const cards = Array.from(list.querySelectorAll('.bap-cond-card'));
            const idx = cards.indexOf(card);
            if (upBtn && idx > 0) {
                [cards[idx - 1], cards[idx]] = [cards[idx], cards[idx - 1]];
                syncOrderAndInterleave(cards);
            } else if (downBtn && idx < cards.length - 1) {
                [cards[idx], cards[idx + 1]] = [cards[idx + 1], cards[idx]];
                syncOrderAndInterleave(cards);
            }
        }
    });

    if (list) {
        let dragged = null;

        list.addEventListener('dragstart', function (e) {
            const card = e.target.closest('.bap-cond-card');
            if (!card) return;
            dragged = card;
            card.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
        });

        list.addEventListener('dragend', function () {
            if (dragged) {
                dragged.style.opacity = '';
                dragged = null;
            }
        });

        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        list.addEventListener('drop', function (e) {
            e.preventDefault();
            const target = e.target.closest('.bap-cond-card');
            if (!dragged || !target || dragged === target) return;

            const cards = Array.from(list.querySelectorAll('.bap-cond-card'));
            const fromIdx = cards.indexOf(dragged);
            const toIdx = cards.indexOf(target);
            cards.splice(fromIdx, 1);
            cards.splice(toIdx, 0, dragged);
            syncOrderAndInterleave(cards);
        });
    }
})();
