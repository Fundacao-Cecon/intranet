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
