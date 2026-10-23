<?php
/** Modais disponíveis para qualquer usuário logado: alterar a própria senha. */
?>
<div id="changePasswordModal" class="modal-overlay">
    <div class="modal-box" style="max-width:400px">
        <div class="modal-header"><h3>Alterar Senha</h3><button class="modal-close" type="button" data-modal-close="changePasswordModal">&times;</button></div>
        <div class="modal-body">
            <form method="POST">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="change_password">
                <input type="password" name="pass_old" class="form-input" placeholder="Atual" required autocomplete="current-password" style="width:100%; margin-bottom:10px;">
                <input type="password" name="pass_new" class="form-input" placeholder="Nova (mín. 8)" required minlength="8" autocomplete="new-password" style="width:100%; margin-bottom:15px;">
                <button type="submit" class="btn-primary" style="width:100%">Salvar</button>
            </form>
        </div>
    </div>
</div>
