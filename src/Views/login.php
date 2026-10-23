<?php
/** @var array|null $flash  @var bool $hasUsers */
?><!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Intranet</title>
    <link rel="icon" href="assets/img/logotipo.png">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-card">
        <button class="theme-toggle-card" type="button" data-theme-toggle>🌙</button>
        <img src="assets/img/logotipo.png" alt="Logo" class="brand-icon">

        <?php if (!$hasUsers): ?>
            <div class="error">Nenhum usuário cadastrado. No servidor, execute: <code>php bin/create-user.php &lt;login&gt; admin</code></div>
        <?php endif; ?>
        <?php if ($flash): ?>
            <div class="<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['text']) ?></div>
        <?php endif; ?>

        <div id="formLogin">
            <h2>Acesso Intranet</h2>
            <form method="POST">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="login">
                <div style="text-align:left;"><label>Usuário</label><input type="text" name="user" required autofocus autocomplete="username"></div>
                <div style="text-align:left;"><label>Senha</label><input type="password" name="pass" required autocomplete="current-password"></div>
                <button type="submit" class="btn-login">Entrar</button>
            </form>
            <a class="link-action" data-toggle-forms>Alterar Senha</a>
        </div>

        <div id="formChange">
            <h2>Nova Senha</h2>
            <form method="POST">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="change_password">
                <div style="text-align:left;"><label>Seu Usuário</label><input type="text" name="user_change" required autocomplete="username"></div>
                <div style="text-align:left;"><label>Senha Atual</label><input type="password" name="pass_old" required autocomplete="current-password"></div>
                <div style="text-align:left;"><label>Nova Senha</label><input type="password" name="pass_new" required minlength="8" autocomplete="new-password"></div>
                <button type="submit" class="btn-login">Salvar</button>
            </form>
            <a class="link-action" data-toggle-forms>Voltar ao Login</a>
        </div>

        <div class="footer-credit">Desenvolvido por <strong>Tecnologia da Informação</strong></div>
    </div>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/login.js"></script>
</body>
</html>
