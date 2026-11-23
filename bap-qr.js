(function () {
    const GF_EXP = new Uint8Array(512), GF_LOG = new Uint8Array(256);
    let x = 1;
    for (let i = 0; i < 255; i++) {
        GF_EXP[i] = x;
        GF_LOG[x] = i;
        x <<= 1;
        if (x & 0x100) x ^= 0x11d;
    }
    for (let i = 255; i < 512; i++) GF_EXP[i] = GF_EXP[i - 255];

    function gfMul(a, b) {
        return (a === 0 || b === 0) ? 0 : GF_EXP[GF_LOG[a] + GF_LOG[b]];
    }

    function rsGenPoly(nsym) {
        let g = new Array(1).fill(1);
        for (let i = 0; i < nsym; i++) {
            const next = new Array(g.length + 1).fill(0);
            for (let j = 0; j < g.length; j++) {
                next[j] ^= g[j];
                next[j + 1] ^= gfMul(g[j], GF_EXP[i]);
            }
            g = next;
        }
        return g;
    }

    function rsEncode(data, nsym) {
        const gen = rsGenPoly(nsym);
        const res = new Uint8Array(data.length + nsym);
        res.set(data);
        for (let i = 0; i < data.length; i++) {
            const coef = res[i];
            if (coef !== 0) {
                for (let j = 1; j < gen.length; j++) {
                    res[i + j] ^= gfMul(gen[j], coef);
                }
            }
        }
        return res.slice(data.length);
    }

    const SPECS = new Array(
        new Array(1, 19, 7),
        new Array(2, 34, 10),
        new Array(3, 55, 15),
        new Array(4, 80, 20),
        new Array(5, 108, 26),
        new Array(6, 136, 18)
    );

    function makeMatrix(text) {
        const bytes = new TextEncoder().encode(text);
        let spec = SPECS.find(s => bytes.length + 2 <= s[1]);
        if (!spec) return null;
        const ver = spec[0], cap = spec[1], ecLen = spec[2];
        const size = ver * 4 + 17;

        const bits = [];
        const pushBits = (val, len) => {
            for (let i = len - 1; i >= 0; i--) bits.push((val >> i) & 1);
        };
        pushBits(4, 4);
        pushBits(bytes.length, 8);
        bytes.forEach(b => pushBits(b, 8));
        pushBits(0, Math.min(4, cap * 8 - bits.length));
        while (bits.length % 8 !== 0) bits.push(0);

        const dataBytes = [];
        for (let i = 0; i < bits.length; i += 8) {
            let b = 0;
            for (let j = 0; j < 8; j++) b = (b << 1) | bits[i + j];
            dataBytes.push(b);
        }
        const pads = [0xec, 0x11];
        for (let i = 0; dataBytes.length < cap; i++) dataBytes.push(pads[i % 2]);

        let codewords = [];
        if (ver < 6) {
            codewords = dataBytes.concat(Array.from(rsEncode(dataBytes, ecLen)));
        } else {
            const b1 = dataBytes.slice(0, 68), b2 = dataBytes.slice(68, 136);
            const e1 = Array.from(rsEncode(b1, 18)), e2 = Array.from(rsEncode(b2, 18));
            for (let i = 0; i < 68; i++) { codewords.push(b1[i], b2[i]); }
            for (let i = 0; i < 18; i++) { codewords.push(e1[i], e2[i]); }
        }

        const mat = Array.from({ length: size }, () => new Int8Array(size).fill(-1));
        const res = Array.from({ length: size }, () => new Uint8Array(size));

        function setMod(r, c, v, lock) {
            if (r >= 0 && r < size && c >= 0 && c < size) {
                mat[r][c] = v ? 1 : 0;
                if (lock) res[r][c] = 1;
            }
        }

        function placeFinder(r, c) {
            for (let dr = -1; dr <= 7; dr++) {
                for (let dc = -1; dc <= 7; dc++) {
                    const on = (dr >= 0 && dr <= 6 && dc >= 0 && dc <= 6) &&
                        (dr === 0 || dr === 6 || dc === 0 || dc === 6 || (dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4));
                    setMod(r + dr, c + dc, on, true);
                }
            }
        }
        placeFinder(0, 0);
        placeFinder(0, size - 7);
        placeFinder(size - 7, 0);

        if (ver >= 2) {
            const pos = size - 7;
            for (let dr = -2; dr <= 2; dr++) {
                for (let dc = -2; dc <= 2; dc++) {
                    const on = Math.max(Math.abs(dr), Math.abs(dc)) !== 1;
                    setMod(pos + dr, pos + dc, on, true);
                }
            }
        }

        for (let i = 8; i < size - 8; i++) {
            setMod(6, i, i % 2 === 0, true);
            setMod(i, 6, i % 2 === 0, true);
        }
        setMod(size - 8, 8, 1, true);

        for (let i = 0; i < 9; i++) { if (i !== 6) { res[8][i] = 1; res[i][8] = 1; } }
        for (let i = 0; i < 8; i++) { res[8][size - 1 - i] = 1; res[size - 1 - i][8] = 1; }

        const allBits = [];
        codewords.forEach(cw => { for (let i = 7; i >= 0; i--) allBits.push((cw >> i) & 1); });
        let bi = 0, up = true;
        for (let col = size - 1; col > 0; col -= 2) {
            if (col === 6) col--;
            for (let i = 0; i < size; i++) {
                const r = up ? size - 1 - i : i;
                for (let dc = 0; dc < 2; dc++) {
                    const c = col - dc;
                    if (!res[r][c]) {
                        let bit = bi < allBits.length ? allBits[bi++] : 0;
                        if ((r + c) % 2 === 0) bit ^= 1;
                        mat[r][c] = bit;
                    }
                }
            }
            up = !up;
        }

        const fmt = 0x77c4;
        for (let i = 0; i < 6; i++) setMod(8, i, (fmt >> (14 - i)) & 1, false);
        setMod(8, 7, (fmt >> 8) & 1, false);
        setMod(8, 8, (fmt >> 7) & 1, false);
        setMod(7, 8, (fmt >> 6) & 1, false);
        for (let i = 0; i < 6; i++) setMod(5 - i, 8, (fmt >> i) & 1, false);
        for (let i = 0; i < 7; i++) setMod(size - 1 - i, 8, (fmt >> (14 - i)) & 1, false);
        for (let i = 0; i < 8; i++) setMod(8, size - 8 + i, (fmt >> (7 - i)) & 1, false);

        return mat;
    }

    document.querySelectorAll('canvas.bap-local-qr').forEach(function (canvas) {
        const uri = canvas.getAttribute('data-otp-uri');
        if (!uri) return;
        const mat = makeMatrix(uri);
        if (!mat) return;
        const scale = 4, margin = 3;
        const dim = (mat.length + margin * 2) * scale;
        canvas.width = dim;
        canvas.height = dim;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, dim, dim);
        ctx.fillStyle = '#000000';
        for (let r = 0; r < mat.length; r++) {
            for (let c = 0; c < mat.length; c++) {
                if (mat[r][c] === 1) {
                    ctx.fillRect((c + margin) * scale, (r + margin) * scale, scale, scale);
                }
            }
        }
    });
})();
