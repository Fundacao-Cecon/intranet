const toggle = document.querySelector('.theme-toggle');
let savedTheme;
try { savedTheme = localStorage.getItem('fcecon-theme'); } catch { /* armazenamento opcional */ }
const setTheme = (dark) => {
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    if (toggle) toggle.textContent = dark ? 'Tema claro' : 'Tema escuro';
};
setTheme(savedTheme === 'dark');
toggle?.addEventListener('click', () => {
    const dark = document.documentElement.dataset.theme !== 'dark';
    setTheme(dark);
    try { localStorage.setItem('fcecon-theme', dark ? 'dark' : 'light'); } catch { /* armazenamento opcional */ }
});
const filter = document.querySelector('#name-filter');
const rows = [...document.querySelectorAll('.document-row')];
const applyFilter = () => {
    const value = filter.value.toLocaleLowerCase('pt-BR');
    let count = 0;
    for (const row of rows) {
        row.hidden = !row.dataset.name.toLocaleLowerCase('pt-BR').includes(value);
        if (!row.hidden) count++;
    }
    document.querySelector('#item-count').textContent = `${count} item(ns)`;
    document.querySelector('#empty-state').hidden = count > 0;
};
if (filter) {
    filter.addEventListener('input', applyFilter);
    filter.closest('form').addEventListener('submit', (event) => {
        if (!new URL(location.href).searchParams.get('q')) { event.preventDefault(); applyFilter(); }
    });
    applyFilter();
}

document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => { if (!confirm(form.dataset.confirm)) event.preventDefault(); });
});
const uploadForm = document.querySelector('#upload-form');
if (uploadForm) {
    uploadForm.addEventListener('submit', event => {
        event.preventDefault();
        const button = uploadForm.querySelector('button');
        const state = document.querySelector('#upload-state');
        const progress = document.querySelector('#upload-progress');
        const results = document.querySelector('#upload-results');
        const refresh = document.querySelector('#upload-refresh');
        const transmission = document.querySelector('#upload-transmission');
        const input = document.querySelector('#upload-files');
        if (button.disabled) return;
        const files = document.querySelector('#upload-files').files;
        if (files.length > 10 || [...files].some(f => f.size > 20 * 1024 * 1024) || [...files].reduce((n,f) => n + f.size, 0) > 64 * 1024 * 1024) {
            state.textContent = 'Lote acima dos limites: até 10 arquivos, 20 MiB por arquivo e 64 MiB no total.';
            return;
        }
        const payload = new FormData(uploadForm);
        button.disabled = true; input.disabled = true; progress.hidden = false; progress.value = 0; results.replaceChildren(); refresh.hidden = true;
        transmission.hidden = false; transmission.textContent = 'Progresso de transmissão dos bytes — não confirma a publicação.';
        state.textContent = 'Transmitindo arquivos…';
        const request = new XMLHttpRequest();
        request.open('POST', uploadForm.action); request.setRequestHeader('Accept', 'application/json');
        request.upload.onprogress = event => {
            if (event.lengthComputable) progress.value = Math.round(event.loaded / event.total * 100);
        };
        request.upload.onload = () => { transmission.textContent = 'Transmissão dos bytes concluída. Confira o resultado da publicação abaixo.'; state.textContent = 'Aguardando validação e publicação no servidor…'; };
        request.onload = () => {
            let response;
            try { response = JSON.parse(request.responseText); } catch { response = { msg: 'O servidor recusou ou não confirmou o envio. Verifique a listagem antes de reenviar.' }; }
            state.textContent = response.msg ?? response.message ?? 'Não foi possível confirmar o envio.';
            state.dataset.result = response.status ?? 'error';
            for (const result of response.results ?? []) {
                const li = document.createElement('li');
                const label = { success: 'Publicado', error: 'Recusado', pending: 'Pendente' }[result.status] ?? 'Não confirmado';
                li.textContent = `${result.name}: ${label} — ${result.msg}`; results.append(li);
            }
            refresh.hidden = false; button.disabled = false; input.disabled = false;
        };
        request.onerror = () => { state.textContent = 'Conexão interrompida; o resultado pode estar pendente. Verifique a listagem e solicite conferência antes de reenviar.'; refresh.hidden = false; button.disabled = false; input.disabled = false; };
        request.timeout = 150000; request.ontimeout = request.onerror;
        request.send(payload);
    });
}
