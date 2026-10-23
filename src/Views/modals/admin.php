<?php
/**
 * Modais exclusivos de admin: Painel Master (logs) e lista de usuários.
 *
 * @var array    $logs
 * @var array    $users
 * @var int|null $currentUserId
 */
?>
<div id="adminModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header"><h3><i class="fas fa-cogs"></i> Painel Master</h3><button class="modal-close" type="button" data-modal-close="adminModal">&times;</button></div>
        <div class="modal-body">
            <div class="modal-toolbar" style="display: flex; gap: 10px; margin-bottom: 20px; align-items: center; flex-wrap: wrap;">
                <button type="button" data-modal-close="adminModal" data-modal-open="createUserModal" class="btn-pattern-green"><i class="fas fa-user-plus"></i> Novo Usuário</button>
                <button type="button" data-modal-close="adminModal" data-modal-open="listUsersModal" class="btn-pattern-list"><span>👥</span> Usuários</button>
                <button type="button" data-modal-close="adminModal" data-modal-open="changePasswordModal" class="btn-pattern-blue"><span>🔑</span> Senha</button>
                <a href="<?= h(url(['mode' => 'trash'])) ?>" class="btn-pattern-dark"><span>🗑️</span> Lixeira</a>
            </div>

            <h4 style="margin-top:20px;"><i class="fas fa-list-alt"></i> Logs do Sistema</h4>
            <div class="log-table-container">
                <table class="log-table">
                    <thead><tr><th>Data</th><th>IP</th><th>Usuário</th><th>Ação</th><th>Alvo</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= h(date('d/m H:i', strtotime($log['data_hora']))) ?></td>
                            <td><?= h($log['ip']) ?></td>
                            <td><strong><?= h($log['usuario']) ?></strong></td>
                            <td><?= h($log['acao']) ?></td>
                            <td><?= h($log['detalhe']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="listUsersModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header"><h3><i class="fas fa-users"></i> Lista de Usuários</h3><button class="modal-close" type="button" data-modal-close="listUsersModal" data-modal-open="adminModal">&times;</button></div>
        <div class="modal-body">
            <div class="log-table-container">
                <table class="log-table">
                    <thead><tr><th>Usuário</th><th>Tipo</th><th>Último Acesso</th><th>Ações</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong><?= h($u['username']) ?></strong></td>
                            <td><span style="background:<?= $u['role'] === 'admin' ? '#e74c3c' : '#2ecc71' ?>; color:white; padding:2px 8px; border-radius:10px; font-size:11px"><?= h(strtoupper($u['role'])) ?></span></td>
                            <td><?= $u['last_login'] ? h(date('d/m/Y H:i', strtotime($u['last_login']))) : '<span style="color:#999;font-style:italic">Nunca</span>' ?></td>
                            <td style="text-align:center">
                                <?php if ((int) $u['id'] !== (int) $currentUserId): ?>
                                    <button type="button" class="btn-icon delete" style="display:inline-flex; width:25px; height:25px;" data-post-action="delete_user" data-id="<?= (int) $u['id'] ?>" data-confirm="Excluir usuário?"><i class="fas fa-trash" style="font-size:12px"></i></button>
                                <?php else: ?>
                                    <span style="color:#ccc">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="createUserModal" class="modal-overlay">
    <div class="modal-box" style="max-width:400px">
        <div class="modal-header"><h3>Novo Usuário</h3><button class="modal-close" type="button" data-modal-close="createUserModal">&times;</button></div>
        <div class="modal-body">
            <form method="POST">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="create_user">
                <input type="text" name="new_username" class="form-input" placeholder="Login" required autocomplete="off" style="width:100%; margin-bottom:10px;">
                <input type="password" name="new_password" class="form-input" placeholder="Senha (mín. 8)" required minlength="8" autocomplete="new-password" style="width:100%; margin-bottom:10px;">
                <select name="new_type" class="form-input" style="width:100%; margin-bottom:15px;">
                    <option value="user">Usuário</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit" class="btn-primary" style="width:100%">Criar</button>
            </form>
        </div>
    </div>
</div>

<div id="renameModal" class="modal-overlay">
    <div class="modal-box" style="max-width:400px">
        <div class="modal-header"><h3>Renomear</h3><button class="modal-close" type="button" data-modal-close="renameModal">&times;</button></div>
        <div class="modal-body">
            <form method="POST">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="old_name" id="oldNameInput">
                <input type="text" name="new_name" id="newNameInput" class="form-input" required>
                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">Salvar</button>
            </form>
        </div>
    </div>
</div>
