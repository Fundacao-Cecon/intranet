// Painel principal: modais, lightbox, busca, seleção p/ ZIP, upload com progresso,
// toasts e ações POST (com CSRF). Sem handlers inline no HTML: tudo via data-*.
(function () {
    'use strict';

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    // ---- modais -----------------------------------------------------------
    function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); }
    function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }

    // ---- toast ------------------------------------------------------------
    function showToast(msg, type) {
        var box = document.getElementById('toast-container');
        if (!box) return;
        var d = document.createElement('div');
        d.className = 'toast ' + (type || 'info');
        var span = document.createElement('span');
        span.textContent = msg;               // textContent: nunca interpreta HTML
        var x = document.createElement('i');
        x.className = 'fas fa-times toast-close';
        x.addEventListener('click', function () { d.remove(); });
        d.appendChild(span);
        d.appendChild(x);
        box.appendChild(d);
        setTimeout(function () { d.remove(); }, 4000);
    }

    // ---- POST de ação (substitui os antigos links GET destrutivos) --------
    function postAction(action, fields) {
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = window.location.href;      // mantém ?dir= e ?mode=
        var all = Object.assign({ action: action, _csrf: csrf }, fields || {});
        Object.keys(all).forEach(function (k) {
            var i = document.createElement('input');
            i.type = 'hidden';
            i.name = k;
            i.value = all[k];
            f.appendChild(i);
        });
        document.body.appendChild(f);
        f.submit();
    }

    // ---- ZIP --------------------------------------------------------------
    function updateZipButton() {
        var n = document.querySelectorAll('.item-check:checked').length;
        var b = document.getElementById('btnZip');
        if (b) {
            b.style.display = n > 0 ? 'block' : 'none';
            b.textContent = 'Baixar ' + n + ' (ZIP)';
        }
    }

    // ---- cliques delegados -----------------------------------------------
    document.addEventListener('click', function (e) {
        var t = e.target;

        // clique no fundo escuro fecha o modal
        if (t.classList && t.classList.contains('modal-overlay')) { t.classList.remove('active'); return; }
        if (t.hasAttribute && t.hasAttribute('data-lightbox-backdrop')) { closeModal('lightboxModal'); return; }

        var el = t.closest('[data-modal-close],[data-modal-open],[data-post-action],[data-rename],[data-lightbox]');
        if (!el) return;

        if (el.hasAttribute('data-modal-close')) closeModal(el.getAttribute('data-modal-close'));
        if (el.hasAttribute('data-modal-open')) openModal(el.getAttribute('data-modal-open'));

        if (el.hasAttribute('data-post-action')) {
            var msg = el.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) return;
            var fields = {};
            if (el.hasAttribute('data-item')) fields.item = el.getAttribute('data-item');
            if (el.hasAttribute('data-id')) fields.id = el.getAttribute('data-id');
            postAction(el.getAttribute('data-post-action'), fields);
        }

        if (el.hasAttribute('data-rename')) {
            var name = el.getAttribute('data-rename');
            document.getElementById('oldNameInput').value = name;
            document.getElementById('newNameInput').value = name;
            openModal('renameModal');
        }

        if (el.hasAttribute('data-lightbox')) {
            document.getElementById('lightboxImg').src = el.getAttribute('data-lightbox');
            openModal('lightboxModal');
        }
    });

    // seleção para ZIP
    var selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.item-check').forEach(function (cb) { cb.checked = selectAll.checked; });
            updateZipButton();
        });
    }
    document.querySelectorAll('.item-check').forEach(function (cb) {
        cb.addEventListener('change', updateZipButton);
    });

    // busca local na lista
    var search = document.getElementById('searchInput');
    if (search) {
        search.addEventListener('input', function () {
            var term = search.value.toUpperCase();
            document.querySelectorAll('.filterable-item').forEach(function (item) {
                var name = item.querySelector('.col-name a').innerText.toUpperCase();
                item.style.display = name.indexOf(term) > -1 ? '' : 'none';
            });
        });
    }

    // ---- upload com barra de progresso -------------------------------------
    var dropZone = document.getElementById('dropZone');
    var fileInput = document.getElementById('fileInput');
    var btnUpload = document.getElementById('btnUploadStart');
    var uploadForm = document.getElementById('uploadForm');

    if (dropZone && fileInput && uploadForm) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files.length > 0) {
                dropZone.querySelector('h3').innerText = fileInput.files.length + ' selecionados';
                btnUpload.style.display = 'block';
            }
        });

        uploadForm.addEventListener('submit', function (e) {
            if (fileInput.files.length === 0) return;
            e.preventDefault();
            document.getElementById('progressContainer').style.display = 'block';

            var xhr = new XMLHttpRequest();
            xhr.upload.onprogress = function (ev) {
                if (ev.lengthComputable) {
                    document.getElementById('progressBar').style.width = Math.round((ev.loaded / ev.total) * 100) + '%';
                }
            };
            xhr.onload = function () {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    showToast(resp.msg, resp.status);
                    setTimeout(function () { window.location.reload(); }, 1500);
                } catch (err) {
                    window.location.reload();
                }
            };
            xhr.onerror = function () { showToast('Falha de rede no upload.', 'error'); };
            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(new FormData(uploadForm));
        });
    }

    // mensagem flash vinda do servidor (PRG)
    var flashText = document.body.getAttribute('data-flash-text');
    if (flashText) showToast(flashText, document.body.getAttribute('data-flash-type') || 'info');
})();
