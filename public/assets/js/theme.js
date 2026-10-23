// Tema claro/escuro compartilhado (login e painel). Preferência fica no localStorage.
(function () {
    var body = document.body;
    var buttons = document.querySelectorAll('[data-theme-toggle]');

    function read() {
        try { return localStorage.getItem('tema'); } catch (e) { return null; }
    }
    function write(v) {
        try { localStorage.setItem('tema', v); } catch (e) { /* ignora */ }
    }
    function paint() {
        var dark = body.classList.contains('dark-mode');
        buttons.forEach(function (b) { b.textContent = dark ? '☀️' : '🌙'; });
    }

    if (read() === 'dark') { body.classList.add('dark-mode'); }
    paint();

    buttons.forEach(function (b) {
        b.addEventListener('click', function () {
            body.classList.toggle('dark-mode');
            write(body.classList.contains('dark-mode') ? 'dark' : 'light');
            paint();
        });
    });
})();
