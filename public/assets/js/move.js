// Mover itens entre pastas (admin): botão/modal com seletor de destino e arrastar e soltar.
// Arrastar itens da lista para uma pasta, nó da árvore ou migalha move; soltar arquivos do
// computador sobre eles envia para lá. O modal é o caminho para quem não usa mouse.
(function () {
    'use strict';

    var list = document.getElementById('fileList');
    var modal = document.getElementById('moveModal');
    var ui = window.IntranetUI;
    if (!list || !modal || !ui) return;

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var currentDir = list.getAttribute('data-current-dir') || '';
    var TYPE = 'application/x-intranet-move';

    var itemsBox = document.getElementById('moveItems');
    var destInput = document.getElementById('moveDest');
    var summary = document.getElementById('moveSummary');
    var hint = document.getElementById('moveHint');
    var submit = document.getElementById('moveSubmit');
    var picker = modal.querySelector('[data-tree]');
    var btnMove = document.getElementById('btnMove');

    function join(base, name) { return base ? base + '/' + name : name; }
    function label(path) { return path === '' ? 'Início' : path; }

    /** Por que não dá para mover `infos` para `dest` ('' = pode). Espelha as regras do servidor. */
    function problem(dest, infos) {
        if (dest === currentDir) return 'Os itens já estão nesta pasta.';
        for (var i = 0; i < infos.length; i++) {
            if (!infos[i].isDir) continue;
            var from = join(currentDir, infos[i].name);
            if (dest === from || dest.indexOf(from + '/') === 0) return 'Uma pasta não pode ir para dentro dela mesma.';
        }
        return '';
    }

    function checkedInfos() {
        return Array.prototype.map.call(list.querySelectorAll('.item-check:checked'), function (cb) {
            return { name: cb.value, isDir: cb.hasAttribute('data-dir-item') };
        });
    }

    function rowInfo(li) {
        return { name: li.getAttribute('data-name'), isDir: li.getAttribute('data-dir') === '1' };
    }

    // ---- botão "Mover selecionados" ----------------------------------------------------
    function updateMoveButton() {
        if (!btnMove) return;
        var n = list.querySelectorAll('.item-check:checked').length;
        btnMove.style.display = n > 0 ? 'block' : 'none';
        btnMove.textContent = 'Mover ' + n + (n === 1 ? ' item' : ' itens');
    }
    document.querySelectorAll('.item-check, #selectAll').forEach(function (cb) {
        cb.addEventListener('change', updateMoveButton);
    });

    // ---- modal --------------------------------------------------------------------------
    var moving = [];

    function openMove(infos) {
        if (!infos.length) return;
        moving = infos;
        itemsBox.textContent = '';
        infos.forEach(function (it) {
            var i = document.createElement('input');
            i.type = 'hidden';
            i.name = 'items[]';
            i.value = it.name;
            itemsBox.appendChild(i);
        });
        summary.textContent = infos.length === 1 ? 'Mover "' + infos[0].name + '" para:' : 'Mover ' + infos.length + ' itens para:';
        destInput.value = '';
        submit.disabled = true;
        hint.textContent = 'Escolha a pasta de destino.';
        picker.querySelectorAll('[role=treeitem]').forEach(function (li) {
            if (li.hasAttribute('aria-selected')) li.setAttribute('aria-selected', 'false');
            if (li.firstElementChild) li.firstElementChild.classList.remove('selected');
        });
        ui.openModal('moveModal');
    }

    if (btnMove) btnMove.addEventListener('click', function () { openMove(checkedInfos()); });

    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-move]') : null;
        if (!b) return;
        var li = b.closest('li.file-item');
        if (li) openMove([rowInfo(li)]);
    });

    picker.addEventListener('tree-select', function (e) {
        var path = e.detail.path;
        var why = problem(path, moving);
        destInput.value = why ? '' : path;
        submit.disabled = !!why;
        hint.textContent = why || 'Destino: ' + label(path);
    });

    // ---- arrastar e soltar -----------------------------------------------------------------
    var dragging = null;     // itens em arraste (a lista interna: dataTransfer não é legível no dragover)
    var lit = null;          // elemento com destaque de soltar

    function unlight() {
        if (lit) lit.classList.remove('drop-target');
        lit = null;
    }

    function hasType(e, type) {
        var types = (e.dataTransfer && e.dataTransfer.types) || [];
        return Array.prototype.indexOf.call(types, type) > -1;
    }

    /** Alvo de soltar sob o ponteiro: linha de pasta, nó da árvore lateral ou migalha. */
    function targetOf(node) {
        if (!node || !node.closest) return null;
        var row = node.closest('li.file-item[data-dir="1"]');
        if (row && list.contains(row)) return { el: row, path: join(currentDir, row.getAttribute('data-name')) };
        var crumb = node.closest('[data-drop-dir]');
        if (crumb) return { el: crumb, path: crumb.getAttribute('data-drop-dir') || '' };
        var item = node.closest('#treePanel [role=treeitem]');
        if (item && item.getAttribute('aria-disabled') !== 'true') {
            var r = item.firstElementChild;
            if (r) return { el: r, path: item.getAttribute('data-path') || '' };
        }
        return null;
    }

    list.addEventListener('dragstart', function (e) {
        var li = e.target.closest ? e.target.closest('li.file-item') : null;
        if (!li || li.getAttribute('draggable') !== 'true') return;
        var cb = li.querySelector('.item-check');
        // arrastar um item marcado leva a seleção inteira; um item solto leva só ele
        dragging = cb && cb.checked ? checkedInfos() : [rowInfo(li)];
        li.classList.add('dragging');
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData(TYPE, JSON.stringify(dragging.map(function (d) { return d.name; })));
            e.dataTransfer.setData('text/plain', dragging.map(function (d) { return d.name; }).join('\n'));
        }
    });

    list.addEventListener('dragend', function () {
        dragging = null;
        unlight();
        list.querySelectorAll('.dragging').forEach(function (li) { li.classList.remove('dragging'); });
    });

    document.addEventListener('dragover', function (e) {
        var internal = dragging && hasType(e, TYPE);
        var files = hasType(e, 'Files');
        if (!internal && !files) return;
        var t = targetOf(e.target);
        if (!t || (internal && problem(t.path, dragging))) {
            unlight();
            return;                                   // sem preventDefault: o cursor mostra "não pode"
        }
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = internal ? 'move' : 'copy';
        if (lit !== t.el) {
            unlight();
            lit = t.el;
            lit.classList.add('drop-target');
        }
    });

    document.addEventListener('dragleave', function (e) {
        if (lit && !(e.relatedTarget && lit.contains(e.relatedTarget))) unlight();
    });

    document.addEventListener('drop', function (e) {
        var internal = dragging && hasType(e, TYPE);
        var files = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length;
        unlight();
        if (!internal && !files) return;
        var t = targetOf(e.target);
        if (!t) return;
        e.preventDefault();
        if (internal) {
            if (problem(t.path, dragging)) return;
            ui.postAction('move', { items: dragging.map(function (d) { return d.name; }), dest: t.path });
            return;
        }
        upload(e.dataTransfer.files, t.path);
    });

    // ---- soltar arquivos do computador: envia para a pasta ---------------------------------
    function upload(files, path) {
        var fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('action', 'upload');
        for (var i = 0; i < files.length; i++) fd.append('arquivos[]', files[i], files[i].name);

        var xhr = new XMLHttpRequest();
        xhr.onload = function () {
            try {
                var resp = JSON.parse(xhr.responseText);
                ui.showToast(resp.msg, resp.status);
                setTimeout(function () { window.location.reload(); }, 1500);
            } catch (err) {
                window.location.reload();
            }
        };
        xhr.onerror = function () { ui.showToast('Falha de rede no envio.', 'error'); };
        xhr.open('POST', 'index.php?dir=' + encodeURIComponent(path), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        ui.showToast('Enviando ' + files.length + (files.length === 1 ? ' arquivo' : ' arquivos') + ' para ' + label(path) + '…', 'info');
        xhr.send(fd);
    }
})();
