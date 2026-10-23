<?php
/** @var array|null $flash  @var bool $hasUsers  @var bool $showChange abre direto o formulário de nova senha */
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

        <div id="formLogin"<?= $showChange ? ' style="display:none"' : '' ?>>
            <h2>Acesso Intranet</h2>
            <form method="POST">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="login">
                <div style="text-align:left;"><label for="login-user">Usuário</label><input type="text" id="login-user" name="user" required autofocus autocomplete="username"></div>
                <div style="text-align:left;"><label for="login-pass">Senha</label><input type="password" id="login-pass" name="pass" required autocomplete="current-password"></div>
                <button type="submit" class="btn-login">Entrar</button>
            </form>
            <button type="button" class="link-action" data-toggle-forms>Alterar Senha</button>
        </div>

        <div id="formChange"<?= $showChange ? ' style="display:block"' : '' ?>>
            <h2>Nova Senha</h2>
            <form method="POST">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="change_password">
                <div style="text-align:left;"><label for="chg-user">Seu Usuário</label><input type="text" id="chg-user" name="user_change" required autocomplete="username"></div>
                <div style="text-align:left;"><label for="chg-old">Senha Atual</label><input type="password" id="chg-old" name="pass_old" required autocomplete="current-password"></div>
                <div style="text-align:left;"><label for="chg-new">Nova Senha</label><input type="password" id="chg-new" name="pass_new" required minlength="8" autocomplete="new-password"></div>
                <button type="submit" class="btn-login">Salvar</button>
            </form>
            <button type="button" class="link-action" data-toggle-forms>Voltar ao Login</button>
        </div>

        <div class="footer-credit">Desenvolvido por <strong>Tecnologia da Informação</strong></div>
    </div>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/login.js"></script>
</body>
</html>
