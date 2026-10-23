// Alterna entre o formulário de login e o de troca de senha.
document.querySelectorAll('[data-toggle-forms]').forEach(function (link) {
    link.addEventListener('click', function () {
        var login = document.getElementById('formLogin');
        var change = document.getElementById('formChange');
        var showingLogin = login.style.display !== 'none';
        login.style.display = showingLogin ? 'none' : 'block';
        change.style.display = showingLogin ? 'block' : 'none';
    });
});
