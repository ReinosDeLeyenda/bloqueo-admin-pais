document.addEventListener('click', function (e) {
    const btn = e.target.closest('.bap-logic-toggle');
    if (!btn) return;

    const wrap = btn.parentElement;
    const input = wrap.querySelector('input[type="hidden"]');
    const bg = btn.querySelector('.bap-sw-bg');
    const txt = btn.querySelector('.bap-sw-txt');
    const knob = btn.querySelector('.bap-sw-knob');
    const hint = wrap.querySelector('.bap-sw-hint');

    const nextIsOr = (input.value === 'AND');
    input.value = nextIsOr ? 'OR' : 'AND';
    bg.setAttribute('fill', nextIsOr ? '#d97706' : '#2271b1');
    txt.setAttribute('x', nextIsOr ? '27' : '47');
    txt.textContent = nextIsOr ? 'O' : 'Y';
    knob.setAttribute('cx', nextIsOr ? '58' : '16');
    hint.textContent = nextIsOr ? 'Opcional (alternativa al filtro anterior)' : 'Obligatorio (se exige además del anterior)';
});
