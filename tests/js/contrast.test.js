// Contraste WCAG 2.1 AA (4.5:1) dos pares de cores críticos, lidos DIRETO dos CSS (tema claro e escuro).
// Complementa o axe (que no jsdom não enxerga cores). Evita regressão ao mexer na paleta.
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const css = (f) => fs.readFileSync(path.resolve(__dirname, '..', '..', 'public', 'assets', 'css', f), 'utf8');

/** Variáveis --nome: #hex de um bloco que começa em `selector {` */
function vars(source, selector) {
    const i = source.indexOf(selector + ' {');
    if (i < 0) return {};
    const body = source.slice(i, source.indexOf('}', i));
    const out = {};
    for (const m of body.matchAll(/(--[\w-]+)\s*:\s*#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/g)) {
        const h = m[2].length === 3 ? m[2].split('').map((c) => c + c).join('') : m[2];   // #fff -> #ffffff
        out[m[1]] = '#' + h.toLowerCase();
    }
    return out;
}

const lum = (hex) => {
    const c = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
        .map((v) => (v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)));
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
};
const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };

const app = css('app.css');
const login = css('login.css');
const themes = {
    'painel/claro':   vars(app, ':root'),
    'painel/escuro':  { ...vars(app, ':root'), ...vars(app, 'body.dark-mode') },
    'login/claro':    vars(login, ':root'),
    'login/escuro':   { ...vars(login, ':root'), ...vars(login, 'body.dark-mode') },
};
const WHITE = '#ffffff';

// [tema, descrição, texto, fundo]   ("var:--x" lê da paleta do tema)
const pairs = [];
for (const t of ['painel/claro', 'painel/escuro']) {
    pairs.push(
        [t, 'link (--accent-color) sobre a página',      'var:--accent-color', 'var:--bg-container'],
        [t, 'link (--accent-color) sobre o cinza hover', 'var:--accent-color', 'var:--hover-bg'],
        [t, 'texto branco sobre botão primário',         WHITE,                'var:--accent-bg'],
        [t, 'texto do botão "ver" sobre o fundo dele',   'var:--btn-view-text', 'var:--btn-view-bg'],
        [t, 'texto principal sobre o container',         'var:--text-primary', 'var:--bg-container'],
        [t, 'texto secundário sobre o container',        'var:--text-secondary', 'var:--bg-container'],
        [t, 'texto branco sobre botão "Novo usuário"',   WHITE,                'var:--btn-new-user'],
        [t, 'texto branco sobre botão "Senha"',          WHITE,                'var:--btn-key'],
        [t, 'texto branco sobre botão "Lixeira"',        WHITE,                'var:--btn-trash'],
    );
}
for (const t of ['login/claro', 'login/escuro']) {
    pairs.push(
        [t, 'link (--blue) sobre o cartão',              'var:--blue',          'var:--container'],
        [t, 'texto branco sobre o botão Entrar',         WHITE,                 'var:--blue-bg'],
        [t, 'texto principal sobre o cartão',            'var:--text',          'var:--container'],
        [t, 'texto secundário sobre o cartão',           'var:--text-secondary', 'var:--container'],
    );
}

for (const [theme, label, fg, bg] of pairs) {
    test(`contraste ≥ 4,5:1 — ${theme}: ${label}`, () => {
        const p = themes[theme];
        const get = (c) => (c.startsWith('var:') ? p[c.slice(4)] : c);
        const f = get(fg);
        const b = get(bg);
        assert.ok(f && b, `variável ausente no tema ${theme}: ${fg} / ${bg} (${f} / ${b})`);
        const r = ratio(f, b);
        assert.ok(r >= 4.5, `${label}: ${f} sobre ${b} = ${r.toFixed(2)}:1 (mínimo 4,5:1)`);
    });
}

test('os dois temas definem as mesmas variáveis de cor críticas', () => {
    for (const v of ['--accent-color', '--accent-bg', '--btn-view-text', '--btn-view-bg']) {
        assert.ok(themes['painel/claro'][v], `${v} ausente no claro`);
        assert.ok(themes['painel/escuro'][v], `${v} ausente no escuro`);
    }
    for (const v of ['--blue', '--blue-bg']) {
        assert.ok(themes['login/claro'][v] && themes['login/escuro'][v], `${v} ausente`);
    }
});
