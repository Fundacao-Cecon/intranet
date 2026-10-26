// Árvore de pastas da lateral: carrega as subpastas sob demanda (GET ?action=tree&dir=...),
// navega por teclado (padrão ARIA tree) e lembra o que o usuário abriu. Sem handlers inline.
(function () {
    'use strict';

    var tree = document.querySelector('[data-tree]');
    if (!tree) return;

    var endpoint = tree.getAttribute('data-tree-url');
    var KEY_OPEN = 'tree.open';
    var KEY_PANEL = 'tree.panel';

    // ---- localStorage pode estar bloqueado: tudo em try/catch --------------
    function read(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } }
    function write(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* ignora */ } }

    // ---- DOM ----------------------------------------------------------------
    function items() { return Array.prototype.slice.call(tree.querySelectorAll('[role=treeitem]')); }

    function parentItem(li) {
        var p = li.parentElement;
        return p ? p.closest('[role=treeitem]') : null;
    }

    function visible(li) {
        var p = parentItem(li);
        while (p) {
            if (p.getAttribute('aria-expanded') === 'false') return false;
            p = parentItem(p);
        }
        return true;
    }

    function link(li) { return li.firstElementChild ? li.firstElementChild.querySelector('a') : null; }

    function focusItem(li) {
        if (!li) return;
        items().forEach(function (i) { i.setAttribute('tabindex', '-1'); });
        li.setAttribute('tabindex', '0');
        li.focus();
    }

    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;   // textContent: nome de pasta nunca vira HTML
        return e;
    }

    function icon(cls) {
        var i = el('i', 'fas ' + cls);
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    function buildItem(node, level) {
        var li = el('li');
        li.setAttribute('role', 'treeitem');
        li.setAttribute('tabindex', '-1');
        li.setAttribute('aria-level', String(level));
        li.setAttribute('data-path', node.path);
        li.setAttribute('data-loaded', node.hasChildren ? '0' : '1');
        if (node.hasChildren) li.setAttribute('aria-expanded', 'false');
        if (node.locked) li.setAttribute('aria-disabled', 'true');

        var row = el('div', 'tree-row');
        var toggle = el('span', 'tree-toggle');
        toggle.setAttribute('aria-hidden', 'true');
        if (node.hasChildren) toggle.appendChild(icon('fa-chevron-right'));
        row.appendChild(toggle);

        if (node.locked) {
            var locked = el('span', 'tree-locked');
            locked.title = 'Acesso restrito';
            locked.appendChild(icon('fa-lock'));
            locked.appendChild(document.createTextNode(' ' + node.name + ' '));
            locked.appendChild(el('span', 'sr-only', '(acesso restrito)'));
            row.appendChild(locked);
        } else {
            var a = el('a');
            a.href = 'index.php?dir=' + encodeURIComponent(node.path);
            a.setAttribute('tabindex', '-1');
            a.appendChild(icon('fa-folder'));
            a.appendChild(document.createTextNode(' ' + node.name));
            row.appendChild(a);
        }
        li.appendChild(row);
        return li;
    }

    function message(text) {
        var ul = el('ul', 'tree-error');
        ul.setAttribute('role', 'group');
        ul.appendChild(el('li', 'tree-msg', text));
        return ul;
    }

    // ---- carregar, abrir e fechar ------------------------------------------------
    function load(li) {
        var path = li.getAttribute('data-path') || '';
        var level = parseInt(li.getAttribute('aria-level'), 10) || 1;
        Array.prototype.slice.call(li.children).forEach(function (c) {
            if (c.classList.contains('tree-error')) li.removeChild(c);   // aviso de uma tentativa anterior
        });
        li.setAttribute('data-loaded', 'loading');
        return window.fetch(endpoint + '&dir=' + encodeURIComponent(path), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) {
            if (!r.ok) throw new Error('status ' + r.status);
            return r.json();
        }).then(function (data) {
            var group = el('ul');
            group.setAttribute('role', 'group');
            (data.children || []).forEach(function (n) { group.appendChild(buildItem(n, level + 1)); });
            li.appendChild(group);
            li.setAttribute('data-loaded', '1');
            return true;
        }).catch(function () {
            li.appendChild(message('Não foi possível carregar.'));
            li.setAttribute('data-loaded', '0');
            return false;
        });
    }

    function remember() {
        var open = items().filter(function (i) { return i.getAttribute('aria-expanded') === 'true' && i.getAttribute('data-path'); })
            .map(function (i) { return i.getAttribute('data-path'); });
        write(KEY_OPEN, JSON.stringify(open));
    }

    function expand(li) {
        if (!li.hasAttribute('aria-expanded') || li.getAttribute('aria-expanded') === 'true') return Promise.resolve();
        var state = li.getAttribute('data-loaded');
        if (state === 'loading') return Promise.resolve();
        var ready = state === '1' ? Promise.resolve(true) : load(li);
        return ready.then(function (ok) {
            if (!ok) return;                      // não abre uma pasta que não carregou
            li.setAttribute('aria-expanded', 'true');
            remember();
        });
    }

    function collapse(li) {
        if (li.getAttribute('aria-expanded') !== 'true') return;
        li.setAttribute('aria-expanded', 'false');
        remember();
    }

    function toggle(li) {
        return li.getAttribute('aria-expanded') === 'true' ? (collapse(li), Promise.resolve()) : expand(li);
    }

    // ---- eventos --------------------------------------------------------------------
    tree.addEventListener('click', function (e) {
        var t = e.target;
        var sw = t.closest ? t.closest('.tree-toggle') : null;
        if (!sw) return;
        var li = sw.closest('[role=treeitem]');
        if (li) {
            toggle(li);
            focusItem(li);
        }
    });

    tree.addEventListener('focusin', function (e) {
        var li = e.target.closest ? e.target.closest('[role=treeitem]') : null;
        if (!li) return;
        items().forEach(function (i) { i.setAttribute('tabindex', i === li ? '0' : '-1'); });
    });

    tree.addEventListener('keydown', function (e) {
        var li = e.target.closest ? e.target.closest('[role=treeitem]') : null;
        if (!li || e.target !== li) return;
        var list = items().filter(visible);
        var at = list.indexOf(li);
        var handled = true;

        switch (e.key) {
            case 'ArrowDown': focusItem(list[Math.min(at + 1, list.length - 1)]); break;
            case 'ArrowUp': focusItem(list[Math.max(at - 1, 0)]); break;
            case 'Home': focusItem(list[0]); break;
            case 'End': focusItem(list[list.length - 1]); break;
            case 'ArrowRight':
                if (li.getAttribute('aria-expanded') === 'false') {
                    expand(li);
                } else if (li.getAttribute('aria-expanded') === 'true') {
                    var first = li.querySelector('[role=treeitem]');
                    if (first) focusItem(first);
                }
                break;
            case 'ArrowLeft':
                if (li.getAttribute('aria-expanded') === 'true') collapse(li);
                else focusItem(parentItem(li));
                break;
            case 'Enter':
            case ' ':
                var a = link(li);
                if (a) a.click();
                break;
            default: handled = false;
        }
        if (handled) e.preventDefault();
    });

    // ---- painel (abrir/fechar) --------------------------------------------------------
    var panel = document.getElementById('treePanel');
    var btn = document.querySelector('[data-tree-toggle]');

    function setPanel(open, persist) {
        if (panel) panel.classList.toggle('closed', !open);
        if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (persist) write(KEY_PANEL, open ? 'open' : 'closed');
    }
    if (btn) {
        btn.addEventListener('click', function () {
            setPanel(panel.classList.contains('closed'), true);
        });
    }
    var narrow = window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
    var saved = read(KEY_PANEL);
    setPanel(saved ? saved === 'open' : !narrow, false);   // celular começa fechado; a escolha salva vale sempre

    // ---- restaura o que estava aberto (mais rasas primeiro) ----------------------------
    var wanted = [];
    try { wanted = JSON.parse(read(KEY_OPEN) || '[]'); } catch (e) { wanted = []; }
    if (Array.isArray(wanted)) {
        wanted.filter(function (p) { return typeof p === 'string'; })
            .sort(function (a, b) { return a.split('/').length - b.split('/').length; })
            .reduce(function (chain, path) {
                return chain.then(function () {
                    var li = items().filter(function (i) { return i.getAttribute('data-path') === path; })[0];
                    return li ? expand(li) : null;
                });
            }, Promise.resolve());
    }
})();
