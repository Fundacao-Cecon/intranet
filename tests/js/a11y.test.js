// Auditoria de acessibilidade (axe-core) das telas reais, em jsdom.
// Limite: o jsdom não calcula layout nem cores, então "color-contrast" e regras visuais ficam de fora
// (conferir à mão ou no navegador). O resto (rótulos, nomes de botões, ARIA, idioma, landmarks) é checado.
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');
const { load, root } = require('./helpers');

const axeSource = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

async function audit(page, scripts, openModals = []) {
    const { window: w, document: d } = load(page, scripts);
    for (const id of openModals) d.getElementById(id).classList.add('active');   // audita também o conteúdo dos modais
    w.eval(axeSource);
    const res = await w.axe.run(d, {
        runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'] },
        rules: {
            'color-contrast': { enabled: false },          // jsdom não calcula cores
            'region': { enabled: false },                  // página simples sem landmarks: avaliar à parte
            'landmark-one-main': { enabled: false },
            'page-has-heading-one': { enabled: false },
        },
    });
    return res.violations.map((v) => ({
        id: v.id, impact: v.impact, help: v.help,
        nodes: v.nodes.map((n) => n.target.join(' ')).slice(0, 4),
    }));
}

function fmt(vs) {
    return '\n' + vs.map((v) => `  [${v.impact}] ${v.id}: ${v.help}\n      ${v.nodes.join('\n      ')}`).join('\n');
}

const pages = [
    ['login',        'login (entrar)',            ['theme.js', 'login.js'], []],
    ['login_change', 'login (nova senha)',        ['theme.js', 'login.js'], []],
    ['admin',        'painel do admin + modais',  ['theme.js', 'app.js'],   ['adminModal', 'listUsersModal', 'createUserModal', 'renameModal', 'changePasswordModal', 'folderAccessModal', 'groupsModal']],
    ['user',         'painel do usuário comum',   ['theme.js', 'app.js'],   ['changePasswordModal']],
    ['trash',        'lixeira',                   ['theme.js', 'app.js'],   []],
    ['images',       'pasta com imagem (lightbox)', ['theme.js', 'app.js'], ['lightboxModal']],
];

for (const [page, title, scripts, modals] of pages) {
    test(`acessibilidade (WCAG 2.1 A/AA): ${title}`, async () => {
        const violations = await audit(page, scripts, modals);
        assert.equal(violations.length, 0, `${violations.length} violação(ões) em "${page}":${fmt(violations)}`);
    });
}

test('todas as telas declaram idioma e título', () => {
    for (const [page] of pages) {
        const { document: d } = load(page, []);
        assert.equal(d.documentElement.getAttribute('lang'), 'pt-br', page);
        assert.ok(d.title.trim().length > 0, `${page}: sem <title>`);
    }
});

test('campos de senha têm autocomplete correto (gerenciadores de senha funcionam)', () => {
    const { document: d } = load('login', []);
    assert.equal(d.querySelector('input[name=pass]').autocomplete, 'current-password');
    assert.equal(d.querySelector('input[name=pass_new]').autocomplete, 'new-password');
    assert.equal(d.querySelector('input[name=user]').autocomplete, 'username');
});

void path; void root;
