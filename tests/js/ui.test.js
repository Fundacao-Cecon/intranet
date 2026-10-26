// Testes do JavaScript do painel (app.js, theme.js, login.js) em jsdom, sobre o HTML real do sistema.
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { load, click, change, input } = require('./helpers');

const APP = ['theme.js', 'app.js'];

// ---------------------------------------------------------------- modais
test('modais: abrir, fechar pelo X, encadear (Painel -> Usuários -> voltar) e fechar clicando no fundo', () => {
    const { document: d } = load('admin', APP);
    const $ = (s) => d.querySelector(s);
    assert.ok(!$('.modal-overlay.active'), 'nenhum modal aberto no início');

    click($('[data-modal-open=adminModal]'));
    assert.ok($('#adminModal').classList.contains('active'));

    click($('#adminModal [data-modal-open=listUsersModal]'));   // fecha um e abre o outro
    assert.ok($('#listUsersModal').classList.contains('active') && !$('#adminModal').classList.contains('active'));

    click($('#listUsersModal .modal-close'));                    // X volta ao painel
    assert.ok($('#adminModal').classList.contains('active') && !$('#listUsersModal').classList.contains('active'));

    click($('#adminModal'));                                      // clique no fundo escuro
    assert.ok(!$('.modal-overlay.active'));

    click($('[data-modal-open=changePasswordModal]'));
    assert.ok($('#changePasswordModal').classList.contains('active'));
    click($('#changePasswordModal .modal-close'));
    assert.ok(!$('.modal-overlay.active'));
});

test('teclado: Esc fecha o modal; o foco entra no modal ao abrir e volta ao botão que o abriu', () => {
    const { document: d, window: w } = load('admin', APP);
    const opener = d.querySelector('[data-modal-open=changePasswordModal]');
    opener.focus();
    click(opener);
    const modal = d.querySelector('#changePasswordModal');
    assert.ok(modal.classList.contains('active'));
    assert.ok(modal.contains(d.activeElement), 'o foco deveria estar dentro do modal');

    d.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    assert.ok(!modal.classList.contains('active'), 'Esc deveria fechar');
    assert.equal(d.activeElement, opener, 'o foco deveria voltar ao botão de origem');

    d.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));   // sem modal aberto: não faz nada
    d.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'a', bubbles: true }));
    assert.ok(!d.querySelector('.modal-overlay.active'));
});

test('todo modal é um diálogo com nome acessível (role=dialog, aria-modal, aria-label)', () => {
    const { document: d } = load('admin', APP);
    const overlays = [...d.querySelectorAll('.modal-overlay')];
    assert.ok(overlays.length >= 6);
    for (const o of overlays) {
        assert.equal(o.getAttribute('role'), 'dialog', o.id);
        assert.equal(o.getAttribute('aria-modal'), 'true', o.id);
        assert.ok((o.getAttribute('aria-label') || '').trim(), `${o.id} sem aria-label`);
    }
});

test('modal de senha existe para o usuário comum, mas sem nenhum modal de admin', () => {
    const { document: d } = load('user', APP);
    assert.ok(d.querySelector('#changePasswordModal'));
    for (const id of ['adminModal', 'listUsersModal', 'createUserModal', 'renameModal']) {
        assert.equal(d.getElementById(id), null, `${id} não deveria existir para usuário comum`);
    }
});

// ---------------------------------------------------------------- busca, renomear, ZIP
test('busca filtra por nome (sem diferenciar maiúsculas) e limpar mostra tudo', () => {
    const { document: d } = load('admin', APP);
    const s = d.querySelector('#searchInput');
    const visible = () => [...d.querySelectorAll('.filterable-item')].filter((i) => i.style.display !== 'none').map((i) => i.querySelector('.col-name a').textContent);
    const total = d.querySelectorAll('.filterable-item').length;
    assert.ok(total >= 3);

    s.value = 'LEIA'; input(s);
    assert.deepEqual(visible(), ['leia-me.txt']);
    s.value = 'zzz-nao-existe'; input(s);
    assert.deepEqual(visible(), []);
    s.value = ''; input(s);
    assert.equal(visible().length, total);
});

test('renomear: abre o modal com o nome atual nos dois campos', () => {
    const { document: d } = load('admin', APP);
    click(d.querySelector('[data-rename="leia-me.txt"]'));
    assert.ok(d.querySelector('#renameModal').classList.contains('active'));
    assert.equal(d.querySelector('#oldNameInput').value, 'leia-me.txt');
    assert.equal(d.querySelector('#newNameInput').value, 'leia-me.txt');
});

test('ZIP: botão aparece com a contagem de arquivos, "selecionar todos" marca tudo e some ao desmarcar', () => {
    const { document: d } = load('admin', APP);
    const zip = d.querySelector('#btnZip');
    const files = [...d.querySelectorAll('.item-check:not([data-dir-item])')];
    assert.ok(files.length >= 1);
    assert.equal(zip.style.display === 'block', false, 'começa escondido');

    files[0].checked = true; change(files[0]);
    assert.equal(zip.style.display, 'block');
    assert.equal(zip.textContent, 'Baixar 1 (ZIP)');

    const all = d.querySelector('#selectAll');
    all.checked = true; change(all);
    assert.equal(d.querySelectorAll('.item-check:checked').length, d.querySelectorAll('.item-check').length, 'marca arquivos e pastas (para mover)');
    assert.equal(zip.textContent, `Baixar ${files.length} (ZIP)`, 'o ZIP conta só arquivos');

    all.checked = false; change(all);
    assert.equal(zip.style.display, 'none');
});

test('pasta tem caixa de seleção só para admin (mover); para usuário comum só arquivos entram', () => {
    const admin = load('admin', APP).document;
    for (const li of admin.querySelectorAll('.file-item')) {
        assert.ok(li.querySelector('.item-check'), 'admin: ' + li.textContent.trim());
    }
    const user = load('user', APP).document;
    for (const li of user.querySelectorAll('.file-item')) {
        const isFolder = !!li.querySelector('.fa-folder, .fa-lock');
        assert.equal(!!li.querySelector('.item-check'), !isFolder, li.textContent.trim());
    }
});

// ---------------------------------------------------------------- ações POST com CSRF
function stubSubmit(w, log) {
    w.HTMLFormElement.prototype.submit = function () {
        const f = {};
        for (const i of this.querySelectorAll('input')) f[i.name] = i.value;
        log.push({ method: this.method.toUpperCase(), action: this.action, fields: f });
    };
}

test('ação destrutiva vira POST com _csrf, action e item; o formulário vai para a URL atual', () => {
    const log = [];
    const { document: d } = load('admin', APP, { before: (w) => { stubSubmit(w, log); w.confirm = () => true; } });
    const token = d.querySelector('meta[name=csrf-token]').content;
    assert.match(token, /^[0-9a-f]{64}$/);

    click(d.querySelector('[data-post-action=trash]'));
    assert.equal(log.length, 1);
    assert.equal(log[0].method, 'POST');
    assert.equal(log[0].fields.action, 'trash');
    assert.equal(log[0].fields._csrf, token);
    assert.ok(log[0].fields.item && log[0].fields.item.length > 0);
    assert.equal(log[0].action, 'http://localhost/index.php');
});

test('confirmação recusada cancela a ação (nada é enviado); aceita envia', () => {
    const log = [];
    let answer = false;
    const asked = [];
    const { document: d } = load('trash', APP, { before: (w) => { stubSubmit(w, log); w.confirm = (m) => { asked.push(m); return answer; }; } });
    const purge = d.querySelector('[data-post-action=purge]');
    assert.equal(purge.getAttribute('data-confirm'), 'Permanente?');

    click(purge);
    assert.equal(log.length, 0, 'recusou: não pode enviar');
    assert.deepEqual(asked, ['Permanente?']);

    answer = true;
    click(purge);
    assert.equal(log.length, 1);
    assert.equal(log[0].fields.action, 'purge');
});

test('excluir usuário envia o id numérico; não existe botão para o próprio usuário', () => {
    const log = [];
    const { document: d } = load('admin', APP, { before: (w) => { stubSubmit(w, log); w.confirm = () => true; } });
    const btns = d.querySelectorAll('[data-post-action=delete_user]');
    const rows = d.querySelectorAll('#listUsersModal tbody tr').length;
    assert.equal(btns.length, rows - 1, 'um botão a menos: o do próprio usuário');
    click(btns[0]);
    assert.equal(log[0].fields.action, 'delete_user');
    assert.match(log[0].fields.id, /^\d+$/);
});

// ---------------------------------------------------------------- lightbox, toast, tema
test('lightbox: abre com a imagem certa, fecha pelo X e pelo fundo', () => {
    const { document: d } = load('images', APP);
    const eye = d.querySelector('[data-lightbox]');
    click(eye);
    assert.ok(d.querySelector('#lightboxModal').classList.contains('active'));
    assert.equal(d.querySelector('#lightboxImg').getAttribute('src'), eye.getAttribute('data-lightbox'));
    assert.match(d.querySelector('#lightboxImg').getAttribute('src'), /action=download.*inline=1/);

    click(d.querySelector('#lightboxModal [data-modal-close]'));
    assert.ok(!d.querySelector('#lightboxModal').classList.contains('active'));
    click(eye);
    click(d.querySelector('[data-lightbox-backdrop]'));
    assert.ok(!d.querySelector('#lightboxModal').classList.contains('active'));
});

test('toast do servidor aparece como TEXTO (HTML malicioso na mensagem não é interpretado)', () => {
    const { document: d } = load('flash', APP);
    const toast = d.querySelector('#toast-container .toast');
    assert.ok(toast, 'toast deveria ser criado a partir de data-flash-text');
    assert.ok(toast.className.includes('error'));
    assert.match(toast.textContent, /<img src=x onerror=alert\(1\)>/, 'o texto aparece literalmente');
    assert.equal(toast.querySelector('img'), null, 'nenhuma tag <img> pode ter sido criada');
    click(toast.querySelector('.toast-close'));
    assert.equal(d.querySelector('#toast-container .toast'), null, 'o X remove o toast');
});

test('tema: alterna, persiste no localStorage e sobrevive a localStorage bloqueado', () => {
    const { document: d, window: w } = load('admin', ['theme.js']);
    const btn = d.querySelector('[data-theme-toggle]');
    assert.equal(d.body.classList.contains('dark-mode'), false);
    click(btn);
    assert.ok(d.body.classList.contains('dark-mode'));
    assert.equal(w.localStorage.getItem('tema'), 'dark');
    assert.equal(btn.textContent, '☀️');
    click(btn);
    assert.equal(w.localStorage.getItem('tema'), 'light');
    assert.equal(btn.textContent, '🌙');

    // restaura o tema salvo
    const again = load('admin', ['theme.js'], { before: (win) => win.localStorage.setItem('tema', 'dark') });
    assert.ok(again.document.body.classList.contains('dark-mode'));

    // localStorage lançando erro (modo privado/bloqueado) não pode quebrar a página
    const blocked = load('admin', [], {
        before: (win) => Object.defineProperty(win, 'localStorage', { get() { throw new Error('bloqueado'); } }),
    });
    assert.doesNotThrow(() => blocked.window.eval(require('./helpers').js('theme.js')));
    click(blocked.document.querySelector('[data-theme-toggle]'));
    assert.ok(blocked.document.body.classList.contains('dark-mode'));
});

// ---------------------------------------------------------------- upload
test('upload: mostra "N selecionados" e o botão; envia por XHR para a URL atual com X-Requested-With e FormData com CSRF', () => {
    const xhrs = [];
    class FakeXHR {
        constructor() { this.upload = {}; xhrs.push(this); this.headers = {}; }
        open(m, u) { this.method = m; this.url = u; }
        setRequestHeader(k, v) { this.headers[k] = v; }
        send(body) { this.body = body; }
    }
    const { document: d, window: w } = load('admin', APP, { before: (win) => { win.XMLHttpRequest = FakeXHR; } });
    const input = d.querySelector('#fileInput');
    const f1 = new w.File(['a'], 'a.txt');
    const f2 = new w.File(['b'], 'b.txt');
    Object.defineProperty(input, 'files', { value: [f1, f2], configurable: true });
    change(input);
    assert.equal(d.querySelector('#dropZone h3').textContent, '2 selecionados');
    assert.equal(d.querySelector('#btnUploadStart').style.display, 'block');

    const form = d.querySelector('#uploadForm');
    form.dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
    assert.equal(xhrs.length, 1);
    assert.equal(xhrs[0].method, 'POST');
    assert.equal(xhrs[0].url, 'http://localhost/index.php');
    assert.equal(xhrs[0].headers['X-Requested-With'], 'XMLHttpRequest');
    assert.equal(d.querySelector('#progressContainer').style.display, 'block');
    assert.ok(xhrs[0].body instanceof w.FormData);
    assert.match(xhrs[0].body.get('_csrf'), /^[0-9a-f]{64}$/);
    assert.equal(xhrs[0].body.get('action'), 'upload');
});

test('upload sem arquivos escolhidos não dispara XHR', () => {
    const xhrs = [];
    const { document: d, window: w } = load('admin', APP, { before: (win) => { win.XMLHttpRequest = function () { xhrs.push(1); }; } });
    Object.defineProperty(d.querySelector('#fileInput'), 'files', { value: [], configurable: true });
    d.querySelector('#uploadForm').dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
    assert.equal(xhrs.length, 0);
});

// ---------------------------------------------------------------- login.js
test('login: alterna entre "Entrar" e "Nova senha" (botões)', () => {
    const { document: d } = load('login', ['theme.js', 'login.js']);
    const visible = (id) => d.getElementById(id).style.display !== 'none' && d.getElementById(id).style.display !== '';
    const toggles = d.querySelectorAll('[data-toggle-forms]');
    assert.equal(toggles.length, 2);
    for (const t of toggles) assert.equal(t.tagName, 'BUTTON', 'precisa ser <button> (foco/teclado)');

    click(toggles[0]);
    assert.equal(d.getElementById('formLogin').style.display, 'none');
    assert.equal(d.getElementById('formChange').style.display, 'block');
    click(toggles[1]);
    assert.equal(d.getElementById('formLogin').style.display, 'block');
    assert.equal(d.getElementById('formChange').style.display, 'none');
    void visible;
});

test('login ?form=change: já abre no formulário de nova senha e o botão "Voltar" funciona', () => {
    const { document: d } = load('login_change', ['theme.js', 'login.js']);
    assert.equal(d.getElementById('formLogin').style.display, 'none');
    assert.equal(d.getElementById('formChange').style.display, 'block');
    click(d.querySelectorAll('[data-toggle-forms]')[1]);
    assert.equal(d.getElementById('formLogin').style.display, 'block');
});

// ---------------------------------------------------------------- permissões por pasta
test('acesso à pasta: o botão preenche o modal com a regra atual e o modo "herdar" trava as listas', () => {
    const { document: d } = load('admin', APP);
    const btn = d.querySelector('[data-folder-access="Restrita"]');
    assert.ok(btn, 'botão de acesso da pasta Restrita');
    assert.equal(btn.getAttribute('data-restricted'), '1');
    click(btn);

    const modal = d.querySelector('#folderAccessModal');
    assert.ok(modal.classList.contains('active'));
    assert.equal(d.querySelector('#folderAccessName').value, 'Restrita');
    assert.equal(d.querySelector('#folderAccessTitle').textContent, 'Restrita');
    assert.ok(d.querySelector('#accessRestrict').checked && !d.querySelector('#accessInherit').checked);
    const users = [...d.querySelectorAll('[data-access-user]')];
    const groups = [...d.querySelectorAll('[data-access-group]')];
    assert.equal(users.filter((c) => c.checked).length, 1, 'um usuário marcado (js_outro)');
    assert.equal(groups.filter((c) => c.checked).length, 1, 'um grupo marcado (Equipe)');
    assert.ok(!d.querySelector('#accessTargets').disabled && !d.querySelector('#accessGroups').disabled);

    const inherit = d.querySelector('#accessInherit');
    inherit.checked = true;
    change(inherit);
    assert.ok(d.querySelector('#accessTargets').disabled && d.querySelector('#accessGroups').disabled, 'herdar deve travar as listas');
});

test('acesso à pasta: pasta sem regra abre em "herdar", sem ninguém marcado, e o título nunca vira HTML', () => {
    const { document: d } = load('admin', APP);
    const btn = d.querySelector('[data-folder-access="Imagens"]');
    assert.equal(btn.getAttribute('data-restricted'), '0');
    btn.setAttribute('data-folder-access', '<img src=x onerror=alert(1)>');     // nome hostil
    click(btn);
    assert.ok(d.querySelector('#accessInherit').checked);
    assert.equal(d.querySelectorAll('[data-access-user]:checked, [data-access-group]:checked').length, 0);
    assert.equal(d.querySelector('#folderAccessTitle').textContent, '<img src=x onerror=alert(1)>');
    assert.equal(d.querySelector('#folderAccessTitle img'), null, 'o nome foi interpretado como HTML');
    assert.equal(d.querySelector('#folderAccessForm [name=action]').value, 'set_folder_access');
    assert.ok(d.querySelector('#folderAccessForm [name=_csrf]'), 'formulário sem CSRF');
});

test('usuário comum: pasta fechada aparece com cadeado, sem link nem botões de admin', () => {
    const { document: d } = load('user', APP);
    const row = [...d.querySelectorAll('.file-item')].find((li) => li.textContent.includes('Restrita'));
    assert.ok(row, 'linha da pasta Restrita');
    assert.ok(row.querySelector('.locked-name'), 'sem marca de pasta restrita');
    assert.equal(row.querySelector('a'), null, 'pasta fechada não pode ter link');
    assert.ok(row.querySelector('.fa-lock'));
    assert.equal(d.querySelector('[data-folder-access]'), null);
    assert.equal(d.getElementById('folderAccessModal'), null);
});

test('grupos: o modal lista os grupos com seus membros e cada formulário leva CSRF', () => {
    const { document: d } = load('admin', APP);
    const cards = [...d.querySelectorAll('#groupsModal .group-card')];
    assert.equal(cards.length, 1);
    assert.ok(cards[0].textContent.includes('Equipe'));
    assert.ok(cards[0].querySelector('[name=_csrf]') && cards[0].querySelector('[name=id]'));
    assert.ok(cards[0].querySelector('button[name=action][value=set_group_members]'));
    assert.ok(cards[0].querySelector('[data-post-action=delete_group]'));
    click(d.querySelector('[data-modal-open=groupsModal]'));
    assert.ok(d.querySelector('#groupsModal').classList.contains('active'));
});

// ---------------------------------------------------------------- árvore de pastas
const TREE = ['theme.js', 'app.js', 'tree.js'];
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const key = (el, k) => el.dispatchEvent(new el.ownerDocument.defaultView.KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true }));

/** Carrega a página com fetch falso: `answer(url)` devolve o JSON (ou lança) e `calls` guarda as URLs pedidas. */
function loadTree(page, answer, store = {}) {
    const calls = [];
    const ctx = load(page, TREE, {
        before(w) {
            for (const [k, v] of Object.entries(store)) w.localStorage.setItem(k, v);
            w.fetch = (url) => {
                calls.push(String(url));
                try {
                    const body = answer(String(url));
                    return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(body) });
                } catch (e) {
                    return Promise.resolve({ ok: false, status: 403, json: () => Promise.resolve({}) });
                }
            };
        },
    });
    // âncoras não navegam no jsdom: evita o erro "not implemented: navigation"
    ctx.document.addEventListener('click', (e) => { if (e.target.closest && e.target.closest('a')) e.preventDefault(); });
    return Object.assign(ctx, { calls });
}
const item = (d, path) => [...d.querySelectorAll('[data-tree] [role=treeitem]')].find((i) => i.getAttribute('data-path') === path);
const sub = (children) => ({ dir: '', children });

test('árvore: estrutura ARIA, raiz aberta, pastas do primeiro nível e pasta atual marcada', () => {
    const { document: d } = loadTree('admin', () => sub([]));
    const tree = d.querySelector('[data-tree]');
    assert.equal(tree.getAttribute('role'), 'tree');
    assert.ok(tree.getAttribute('aria-label'));
    const root = item(d, '');
    assert.equal(root.getAttribute('aria-expanded'), 'true');
    assert.equal(root.getAttribute('aria-level'), '1');
    assert.equal(root.querySelector('a[aria-current=page]') !== null, true, 'a raiz é a pasta atual');
    for (const p of ['Norma Zero', 'Imagens', 'Restrita']) {
        const li = item(d, p);
        assert.ok(li, p);
        assert.equal(li.getAttribute('role'), 'treeitem');
        assert.equal(li.getAttribute('aria-level'), '2');
    }
    assert.equal(item(d, 'Norma Zero').getAttribute('aria-expanded'), 'false', 'tem subpasta, começa fechada');
    assert.equal(item(d, 'Imagens').hasAttribute('aria-expanded'), false, 'sem subpastas não é expansível');
    assert.deepEqual([...d.querySelectorAll('#treePanel [tabindex="0"]')].map((i) => i.getAttribute('data-path')), [''], 'só um item no tab (roving tabindex)');
});

test('árvore: na pasta aberta a cadeia vem aberta e a pasta atual fica marcada', () => {
    const { document: d } = loadTree('sub', () => sub([]));
    assert.equal(item(d, 'Norma Zero').getAttribute('aria-expanded'), 'true');
    const cur = d.querySelector('[data-tree] a[aria-current=page]');
    assert.ok(cur && cur.closest('[role=treeitem]').getAttribute('data-path') === 'Norma Zero/Sub');
    assert.ok(cur.closest('.tree-row').classList.contains('current'));
});

test('árvore: abrir busca as filhas uma vez; nomes viram texto; pasta fechada é cadeado sem link; fechar e reabrir não busca de novo', async () => {
    const hostile = '<img src=x onerror=alert(1)>';
    const { document: d, calls } = loadTree('admin', () => sub([
        { name: hostile, path: 'Imagens/' + hostile, locked: false, hasChildren: false },
        { name: 'Trancada', path: 'Imagens/Trancada', locked: true, hasChildren: false },
    ]));
    const li = item(d, 'Norma Zero');
    click(li.querySelector('.tree-toggle'));
    await flush();
    assert.equal(calls.length, 1);
    assert.match(calls[0], /action=tree&dir=Norma%20Zero$/);
    assert.equal(li.getAttribute('aria-expanded'), 'true');
    const kids = li.querySelectorAll('[role=group] > [role=treeitem]');
    assert.equal(kids.length, 2);
    assert.equal(kids[0].getAttribute('aria-level'), '3');
    assert.equal(li.querySelector('img'), null, 'nome interpretado como HTML');
    assert.equal(kids[0].querySelector('a').textContent.trim(), hostile);
    assert.equal(kids[1].querySelector('a'), null, 'pasta fechada não pode ter link');
    assert.equal(kids[1].getAttribute('aria-disabled'), 'true');
    assert.match(kids[1].textContent, /acesso restrito/);

    click(li.querySelector('.tree-toggle'));
    assert.equal(li.getAttribute('aria-expanded'), 'false');
    click(li.querySelector('.tree-toggle'));
    await flush();
    assert.equal(li.getAttribute('aria-expanded'), 'true');
    assert.equal(calls.length, 1, 'reabrir não deveria buscar de novo');
});

test('árvore: falha ao carregar mostra aviso, não abre e tenta de novo no próximo clique', async () => {
    let fail = true;
    const { document: d, calls } = loadTree('admin', () => { if (fail) throw new Error('403'); return sub([]); });
    const li = item(d, 'Norma Zero');
    click(li.querySelector('.tree-toggle'));
    await flush();
    assert.match(li.textContent, /Não foi possível carregar/);
    assert.equal(li.getAttribute('aria-expanded'), 'false');
    assert.equal(li.querySelectorAll('.tree-error').length, 1);
    fail = false;
    click(li.querySelector('.tree-toggle'));
    await flush();
    assert.equal(calls.length, 2);
    assert.equal(li.getAttribute('aria-expanded'), 'true');
    assert.equal(li.querySelectorAll('.tree-error').length, 0, 'o aviso antigo deveria sumir');
});

test('árvore: teclado (setas, Home/End, Enter) segue o padrão ARIA tree', async () => {
    const { document: d, window: w } = loadTree('admin', () => sub([{ name: 'Filha', path: 'Norma Zero/Filha', locked: false, hasChildren: false }]));
    const tree = d.querySelector('[data-tree]');
    const root = item(d, '');
    const nz = item(d, 'Norma Zero');
    root.focus();

    key(root, 'ArrowDown');
    assert.equal(d.activeElement.getAttribute('data-path'), 'Imagens', 'ordem do servidor: Imagens vem antes');
    assert.equal(d.activeElement.getAttribute('tabindex'), '0');
    assert.equal(root.getAttribute('tabindex'), '-1', 'roving tabindex');

    nz.focus();
    key(nz, 'ArrowRight');                         // abre
    await flush();
    assert.equal(nz.getAttribute('aria-expanded'), 'true');
    key(nz, 'ArrowRight');                         // já aberta: vai para a primeira filha
    assert.equal(d.activeElement.getAttribute('data-path'), 'Norma Zero/Filha', 'já aberta: vai para a primeira filha');
    const filha = item(d, 'Norma Zero/Filha');
    assert.ok(filha);
    key(d.activeElement, 'ArrowLeft');             // filha: sobe para a mãe
    assert.equal(d.activeElement, nz);
    key(nz, 'ArrowLeft');                          // mãe aberta: fecha
    assert.equal(nz.getAttribute('aria-expanded'), 'false');
    key(nz, 'ArrowDown');                          // pula as filhas escondidas
    assert.notEqual(d.activeElement, filha);

    key(d.activeElement, 'End');
    const all = [...tree.querySelectorAll('[role=treeitem]')].filter((i) => !i.closest('[aria-expanded=false] > [role=group]'));
    assert.equal(d.activeElement, all[all.length - 1]);
    key(d.activeElement, 'Home');
    assert.equal(d.activeElement, root);

    let clicked = 0;
    const a = item(d, 'Imagens').querySelector('a');
    a.addEventListener('click', () => { clicked++; });
    item(d, 'Imagens').focus();
    key(item(d, 'Imagens'), 'Enter');
    assert.equal(clicked, 1, 'Enter abre a pasta');
    key(item(d, 'Imagens'), ' ');
    assert.equal(clicked, 2, 'espaço também');
    key(item(d, 'Imagens'), 'a');                  // outras teclas não fazem nada
    void w;
});

test('árvore: lembra o que o usuário abriu e reabre ao voltar à página', async () => {
    const answer = () => sub([{ name: 'Filha', path: 'Norma Zero/Filha', locked: false, hasChildren: false }]);
    const first = loadTree('admin', answer);
    click(item(first.document, 'Norma Zero').querySelector('.tree-toggle'));
    await flush();
    const saved = first.window.localStorage.getItem('tree.open');
    assert.deepEqual(JSON.parse(saved), ['Norma Zero']);

    const again = loadTree('admin', answer, { 'tree.open': saved });
    await flush();
    await flush();
    assert.equal(item(again.document, 'Norma Zero').getAttribute('aria-expanded'), 'true');
    assert.ok(item(again.document, 'Norma Zero/Filha'));
    assert.equal(again.calls.length, 1);

    const junk = loadTree('admin', answer, { 'tree.open': '{isto não é lista' });   // lixo no localStorage não quebra
    await flush();
    assert.equal(junk.calls.length, 0);
});

test('árvore: botão "Pastas" fecha e abre o painel e lembra a escolha; localStorage bloqueado não quebra', () => {
    const { document: d, window: w } = loadTree('admin', () => sub([]));
    const btn = d.querySelector('[data-tree-toggle]');
    const panel = d.getElementById('treePanel');
    assert.equal(btn.getAttribute('aria-controls'), 'treePanel');
    assert.equal(btn.getAttribute('aria-expanded'), 'true');
    assert.ok(!panel.classList.contains('closed'));
    click(btn);
    assert.ok(panel.classList.contains('closed'));
    assert.equal(btn.getAttribute('aria-expanded'), 'false');
    assert.equal(w.localStorage.getItem('tree.panel'), 'closed');
    click(btn);
    assert.ok(!panel.classList.contains('closed'));

    const closed = loadTree('admin', () => sub([]), { 'tree.panel': 'closed' });
    assert.ok(closed.document.getElementById('treePanel').classList.contains('closed'), 'escolha salva vale ao recarregar');

    const blocked = load('admin', TREE, {
        before(win) {
            win.fetch = () => Promise.reject(new Error('sem rede'));
            Object.defineProperty(win, 'localStorage', { get() { throw new Error('bloqueado'); } });
        },
    });
    assert.ok(blocked.document.querySelector('[data-tree]'), 'sem localStorage a árvore continua de pé');
});

test('árvore: usuário comum vê a pasta fechada como cadeado; a lixeira não tem árvore', () => {
    const { document: d } = loadTree('user', () => sub([]));
    const li = item(d, 'Restrita');
    assert.ok(li && li.getAttribute('aria-disabled') === 'true');
    assert.equal(li.querySelector('a'), null);
    assert.ok(li.querySelector('.tree-locked .fa-lock'));
    const trash = load('trash', TREE);
    assert.equal(trash.document.querySelector('[data-tree]'), null);
    assert.equal(trash.document.getElementById('treePanel'), null);
});

// ---------------------------------------------------------------- mover e arrastar e soltar
const MOVE = ['theme.js', 'app.js', 'tree.js', 'move.js'];

/** Carrega a página com tudo do mover; `posts` recebe cada formulário enviado como lista [nome, valor]. */
function loadMove(page, store = {}) {
    const posts = [];
    const uploads = [];
    const ctx = load(page, MOVE, {
        before(w) {
            for (const [k, v] of Object.entries(store)) w.localStorage.setItem(k, v);
            w.fetch = () => Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({ children: [] }) });
            w.HTMLFormElement.prototype.submit = function () {
                posts.push({ action: this.action, fields: [...this.querySelectorAll('input')].map((i) => [i.name, i.value]) });
            };
            w.XMLHttpRequest = class {
                open(method, url) { this.method = method; this.url = url; this.headers = {}; }
                setRequestHeader(k, v) { this.headers[k] = v; }
                send(body) { this.body = body; uploads.push(this); }
            };
        },
    });
    ctx.document.addEventListener('click', (e) => { if (e.target.closest && e.target.closest('a')) e.preventDefault(); });
    return Object.assign(ctx, { posts, uploads });
}

/** Evento de arrastar com um dataTransfer mínimo (o jsdom não traz DragEvent). */
function drag(w, type, target, { types = [], files = [], related = null } = {}) {
    const ev = new w.Event(type, { bubbles: true, cancelable: true });
    const data = {};
    ev.dataTransfer = { types, files, dropEffect: '', effectAllowed: '', setData(t, v) { data[t] = v; if (!types.includes(t)) types.push(t); } };
    ev.relatedTarget = related;
    target.dispatchEvent(ev);
    return ev;
}
const MOVE_TYPE = 'application/x-intranet-move';
const row = (d, name) => [...d.querySelectorAll('#fileList li.file-item')].find((li) => li.getAttribute('data-name') === name);
const field = (post, name) => post.fields.filter(([n]) => n === name).map(([, v]) => v);
const pick = (d, path) => {
    const li = [...d.querySelectorAll('#moveModal [role=treeitem]')].find((i) => i.getAttribute('data-path') === path);
    return li.querySelector('a');
};

test('mover: o botão só aparece com seleção, conta pastas e arquivos e abre o modal com os itens', () => {
    const { document: d } = loadMove('admin');
    const btn = d.querySelector('#btnMove');
    assert.ok(btn, 'admin tem o botão');
    assert.notEqual(btn.style.display, 'block', 'começa escondido');
    const folder = d.querySelector('.item-check[data-dir-item]');
    assert.ok(folder, 'admin pode marcar pasta');
    folder.checked = true; change(folder);
    assert.equal(btn.style.display, 'block');
    assert.equal(btn.textContent, 'Mover 1 item');
    assert.equal(d.querySelector('#btnZip').style.display, 'none', 'pasta sozinha não habilita o ZIP');

    const all = d.querySelector('#selectAll');
    all.checked = true; change(all);
    const total = d.querySelectorAll('.item-check').length;
    assert.equal(btn.textContent, `Mover ${total} itens`);
    assert.equal(d.querySelector('#btnZip').textContent, `Baixar ${d.querySelectorAll('.item-check:not([data-dir-item])').length} (ZIP)`, 'ZIP conta só arquivos');

    click(btn);
    assert.ok(d.querySelector('#moveModal').classList.contains('active'));
    const items = [...d.querySelectorAll('#moveItems input[name="items[]"]')].map((i) => i.value);
    assert.equal(items.length, total);
    assert.match(d.querySelector('#moveSummary').textContent, new RegExp(`${total} itens`));
    assert.equal(d.querySelector('#moveSubmit').disabled, true, 'sem destino escolhido');
    assert.equal(d.querySelector('#moveDest').value, '');
});

test('mover: a busca esconde linhas — "todos" e "Mover selecionados" só valem para o que se vê', () => {
    const { document: d } = loadMove('admin');
    const search = d.querySelector('#searchInput');
    search.value = 'leia';
    input(search);
    const hidden = [...d.querySelectorAll('#fileList li.file-item')].filter((li) => li.style.display === 'none');
    assert.ok(hidden.length >= 1, 'a busca deveria esconder linhas');

    const all = d.querySelector('#selectAll');
    all.checked = true; change(all);
    assert.deepEqual([...d.querySelectorAll('.item-check:checked')].map((c) => c.value), ['leia-me.txt'], 'só a linha visível é marcada');
    assert.equal(d.querySelector('#btnMove').textContent, 'Mover 1 item');

    // item marcado antes da busca e agora escondido não entra no lote
    const sumida = d.querySelector('.item-check[value="Imagens"]');
    sumida.checked = true;
    input(search);
    click(d.querySelector('#btnMove'));
    assert.deepEqual([...d.querySelectorAll('#moveItems input')].map((i) => i.value), ['leia-me.txt']);
    assert.equal(d.querySelector('#btnMove').textContent, 'Mover 1 item');
});

test('mover: o botão da linha leva só aquele item; folha de destino escolhida habilita o envio', () => {
    const { document: d } = loadMove('admin');
    click(d.querySelector('[data-move="leia-me.txt"]'));
    assert.deepEqual([...d.querySelectorAll('#moveItems input')].map((i) => i.value), ['leia-me.txt']);
    assert.equal(d.querySelector('#moveSummary').textContent, 'Mover "leia-me.txt" para:');

    const link = pick(d, 'Imagens');
    const ev = new d.defaultView.MouseEvent('click', { bubbles: true, cancelable: true });
    link.dispatchEvent(ev);
    assert.equal(ev.defaultPrevented, true, 'no seletor o link escolhe, não navega');
    assert.equal(link.closest('[role=treeitem]').getAttribute('aria-selected'), 'true');
    assert.ok(link.closest('.tree-row').classList.contains('selected'));
    assert.equal(d.querySelector('#moveDest').value, 'Imagens');
    assert.equal(d.querySelector('#moveSubmit').disabled, false);
    assert.match(d.querySelector('#moveHint').textContent, /Destino: Imagens/);

    // a escolha anterior é limpa ao abrir de novo
    click(d.querySelector('[data-modal-close=moveModal]'));
    click(d.querySelector('[data-move="leia-me.txt"]'));
    assert.equal(d.querySelector('#moveDest').value, '');
    assert.equal(d.querySelector('#moveSubmit').disabled, true);
    assert.equal(d.querySelector('#moveModal .tree-row.selected'), null);
});

test('mover: o modal recusa a pasta atual e uma pasta dentro dela mesma, e aceita o início', () => {
    const { document: d } = loadMove('sub');   // aberta em "Norma Zero/Sub"
    const dest = d.querySelector('#moveDest');
    const submit = d.querySelector('#moveSubmit');
    const tree = d.querySelector('#moveModal [data-tree]');
    const choose = (path) => tree.dispatchEvent(new d.defaultView.CustomEvent('tree-select', { bubbles: true, detail: { path } }));

    // uma pasta fictícia "Fundo" dentro da pasta atual
    const li = d.createElement('li');
    li.className = 'file-item';
    li.setAttribute('data-name', 'Fundo');
    li.setAttribute('data-dir', '1');
    li.innerHTML = '<button type="button" data-move="Fundo"></button>';
    d.getElementById('fileList').appendChild(li);
    click(li.querySelector('button'));

    choose('Norma Zero/Sub');
    assert.equal(submit.disabled, true);
    assert.match(d.querySelector('#moveHint').textContent, /já estão nesta pasta/);
    choose('Norma Zero/Sub/Fundo');
    assert.equal(submit.disabled, true);
    assert.match(d.querySelector('#moveHint').textContent, /dentro dela mesma/);
    choose('Norma Zero/Sub/Fundo/Mais');
    assert.equal(submit.disabled, true, 'descendente da própria pasta');
    choose('Norma Zero/SubOutra');
    assert.equal(submit.disabled, false, 'prefixo parecido não é descendente');
    assert.equal(dest.value, 'Norma Zero/SubOutra');
    choose('');
    assert.equal(submit.disabled, false);
    assert.equal(dest.value, '');
    assert.match(d.querySelector('#moveHint').textContent, /Destino: Início/);
});

test('arrastar: item solto sobre uma pasta da lista vira POST move com a pasta como destino', () => {
    const { document: d, window: w, posts } = loadMove('admin');
    const file = row(d, 'leia-me.txt');
    const target = row(d, 'Imagens');
    assert.equal(file.getAttribute('draggable'), 'true');
    drag(w, 'dragstart', file);
    assert.ok(file.classList.contains('dragging'));

    const over = drag(w, 'dragover', target, { types: [MOVE_TYPE] });
    assert.equal(over.defaultPrevented, true, 'pasta aceita o item');
    assert.ok(target.classList.contains('drop-target'));
    const drop = drag(w, 'drop', target, { types: [MOVE_TYPE] });
    assert.equal(drop.defaultPrevented, true);
    assert.equal(target.classList.contains('drop-target'), false);
    assert.equal(posts.length, 1);
    assert.equal(posts[0].action, 'http://localhost/index.php');
    assert.deepEqual(field(posts[0], 'action'), ['move']);
    assert.deepEqual(field(posts[0], 'items[]'), ['leia-me.txt']);
    assert.deepEqual(field(posts[0], 'dest'), ['Imagens']);
    assert.ok(field(posts[0], '_csrf')[0], 'CSRF');
    drag(w, 'dragend', file);
    assert.equal(file.classList.contains('dragging'), false);
});

test('arrastar: item marcado leva a seleção inteira; arquivo não é alvo; pasta não cai em si mesma', () => {
    const { document: d, window: w, posts } = loadMove('admin');
    const a = d.querySelector('.item-check[value="leia-me.txt"]');
    const b = d.querySelector('.item-check[value="Norma Zero"]');
    a.checked = true; change(a);
    b.checked = true; change(b);
    drag(w, 'dragstart', row(d, 'leia-me.txt'));
    assert.equal(drag(w, 'dragover', row(d, 'leia-me.txt'), { types: [MOVE_TYPE] }).defaultPrevented, false, 'arquivo não recebe');
    assert.equal(drag(w, 'dragover', row(d, 'Norma Zero'), { types: [MOVE_TYPE] }).defaultPrevented, false, 'a pasta arrastada não cai nela mesma');
    drag(w, 'drop', row(d, 'Norma Zero'), { types: [MOVE_TYPE] });
    assert.equal(posts.length, 0);
    assert.equal(drag(w, 'dragover', row(d, 'Imagens'), { types: [MOVE_TYPE] }).defaultPrevented, true);
    drag(w, 'drop', row(d, 'Imagens'), { types: [MOVE_TYPE] });
    assert.deepEqual(field(posts[0], 'items[]').sort(), ['Norma Zero', 'leia-me.txt']);
    drag(w, 'dragend', row(d, 'leia-me.txt'));

    // sem arraste em andamento (arrasto de fora da página) nada é aceito como "mover"
    assert.equal(drag(w, 'dragover', row(d, 'Imagens'), { types: [MOVE_TYPE] }).defaultPrevented, false);
});

test('arrastar: migalha e árvore lateral são alvos; a pasta atual não é', () => {
    const { document: d, window: w, posts } = loadMove('sub');   // "Norma Zero/Sub": migalhas Início / Norma Zero / Sub
    const li = d.createElement('li');
    li.className = 'file-item';
    li.setAttribute('draggable', 'true');
    li.setAttribute('data-name', 'avulso.txt');
    li.setAttribute('data-dir', '0');
    d.getElementById('fileList').appendChild(li);
    drag(w, 'dragstart', li);

    const crumbs = [...d.querySelectorAll('.breadcrumbs [data-drop-dir]')];
    assert.deepEqual(crumbs.map((c) => c.getAttribute('data-drop-dir')), ['', 'Norma Zero', 'Norma Zero/Sub']);
    assert.equal(drag(w, 'dragover', crumbs[2], { types: [MOVE_TYPE] }).defaultPrevented, false, 'a pasta atual não é destino');
    assert.equal(drag(w, 'dragover', crumbs[1], { types: [MOVE_TYPE] }).defaultPrevented, true);
    drag(w, 'drop', crumbs[1], { types: [MOVE_TYPE] });
    assert.deepEqual(field(posts[0], 'dest'), ['Norma Zero']);

    drag(w, 'dragstart', li);
    const node = [...d.querySelectorAll('#treePanel [role=treeitem]')].find((i) => i.getAttribute('data-path') === 'Imagens');
    const nodeRow = node.querySelector('.tree-row');
    assert.equal(drag(w, 'dragover', nodeRow, { types: [MOVE_TYPE] }).defaultPrevented, true);
    assert.ok(nodeRow.classList.contains('drop-target'));
    drag(w, 'drop', nodeRow, { types: [MOVE_TYPE] });
    assert.deepEqual(field(posts[1], 'dest'), ['Imagens']);

    const locked = node.cloneNode(true);
    locked.setAttribute('aria-disabled', 'true');
    node.parentElement.appendChild(locked);
    assert.equal(drag(w, 'dragover', locked.querySelector('.tree-row'), { types: [MOVE_TYPE] }).defaultPrevented, false, 'pasta fechada não recebe');
});

test('arrastar: soltar arquivos do computador sobre uma pasta envia para ela (XHR com CSRF), e some o destaque ao sair', () => {
    const { document: d, window: w, uploads } = loadMove('admin');
    const target = row(d, 'Imagens');
    const files = [new w.File(['a'], 'a.pdf'), new w.File(['b'], 'b.pdf')];
    assert.equal(drag(w, 'dragover', target, { types: ['Files'] }).defaultPrevented, true);
    assert.ok(target.classList.contains('drop-target'));
    drag(w, 'dragleave', target, { types: ['Files'], related: d.body });
    assert.equal(target.classList.contains('drop-target'), false);

    const drop = drag(w, 'drop', target, { types: ['Files'], files });
    assert.equal(drop.defaultPrevented, true);
    assert.equal(uploads.length, 1);
    assert.equal(uploads[0].method, 'POST');
    assert.equal(uploads[0].url, 'index.php?dir=Imagens');
    assert.equal(uploads[0].headers['X-Requested-With'], 'XMLHttpRequest');
    assert.equal(uploads[0].body.get('action'), 'upload');
    assert.ok(uploads[0].body.get('_csrf'));
    assert.deepEqual(uploads[0].body.getAll('arquivos[]').map((f) => f.name), ['a.pdf', 'b.pdf']);
    assert.match(d.querySelector('#toast-container').textContent, /Enviando 2 arquivos para Imagens/);

    // arquivo sobre algo que não é pasta: o navegador segue o comportamento normal
    assert.equal(drag(w, 'dragover', row(d, 'leia-me.txt'), { types: ['Files'] }).defaultPrevented, false);
    drag(w, 'drop', row(d, 'leia-me.txt'), { types: ['Files'], files });
    assert.equal(uploads.length, 1);
});

test('mover: usuário comum e lixeira não têm botão, modal, linha arrastável nem checkbox de pasta', () => {
    const user = load('user', MOVE);
    assert.equal(user.document.getElementById('moveModal'), null);
    assert.equal(user.document.querySelector('[data-move], #btnMove'), null);
    assert.equal(user.document.querySelector('[draggable=true]'), null);
    assert.equal(user.document.querySelector('.item-check[data-dir-item]'), null, 'pasta sem caixa de seleção para quem só baixa');
    for (const li of user.document.querySelectorAll('.file-item')) {
        assert.equal(!!li.querySelector('.item-check'), !li.querySelector('.fa-folder, .fa-lock'), li.textContent.trim());
    }
    const trash = load('trash', MOVE);
    assert.equal(trash.document.getElementById('moveModal'), null);
    assert.equal(trash.document.querySelector('[draggable=true]'), null);
});
