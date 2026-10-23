<?php
/** Modais disponíveis para qualquer usuário logado: alterar a própria senha. */
?>
<div id="changePasswordModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Alterar senha">
    <div class="modal-box" style="max-width:400px">
        <div class="modal-header"><h3>Alterar Senha</h3><button class="modal-close" type="button" aria-label="Fechar" data-modal-close="changePasswordModal">&times;</button></div>
        <div class="modal-body">
            <form method="POST">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="change_password">
                <input type="password" name="pass_old" class="form-input" placeholder="Atual" required autocomplete="current-password" aria-label="Senha atual" style="width:100%; margin-bottom:10px;">
                <input type="password" name="pass_new" class="form-input" placeholder="Nova (mín. 8)" required minlength="8" autocomplete="new-password" aria-label="Nova senha" style="width:100%; margin-bottom:15px;">
                <button type="submit" class="btn-primary" style="width:100%">Salvar</button>
            </form>
        </div>
    </div>
</div>
