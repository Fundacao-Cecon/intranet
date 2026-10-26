<?php
/**
 * Modais exclusivos de admin: Painel Master (logs) e lista de usuários.
 *
 * @var array    $logs
 * @var array    $users
 * @var array    $groups
 * @var array|null $moveNodes  subpastas da raiz para o seletor de destino (null na lixeira)
 * @var int|null $currentUserId
 */
?>
<div id="adminModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Painel Master">
    <div class="modal-box">
        <div class="modal-header"><h3><i aria-hidden="true" class="fas fa-cogs"></i> Painel Master</h3><button class="modal-close" type="button" aria-label="Fechar" data-modal-close="adminModal">&times;</button></div>
        <div class="modal-body">
            <div class="modal-toolbar" style="display: flex; gap: 10px; margin-bottom: 20px; align-items: center; flex-wrap: wrap;">
                <button type="button" data-modal-close="adminModal" data-modal-open="createUserModal" class="btn-pattern-green"><i aria-hidden="true" class="fas fa-user-plus"></i> Novo Usuário</button>
                <button type="button" data-modal-close="adminModal" data-modal-open="listUsersModal" class="btn-pattern-list"><span>👥</span> Usuários</button>
                <button type="button" data-modal-close="adminModal" data-modal-open="groupsModal" class="btn-pattern-list"><span>🏷️</span> Grupos</button>
                <button type="button" data-modal-close="adminModal" data-modal-open="changePasswordModal" class="btn-pattern-blue"><span>🔑</span> Senha</button>
                <a href="<?= h(url(['mode' => 'trash'])) ?>" class="btn-pattern-dark"><span>🗑️</span> Lixeira</a>
            </div>

            <h4 style="margin-top:20px;"><i aria-hidden="true" class="fas fa-list-alt"></i> Logs do Sistema</h4>
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

<div id="listUsersModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Lista de usuários">
    <div class="modal-box">
        <div class="modal-header"><h3><i aria-hidden="true" class="fas fa-users"></i> Lista de Usuários</h3><button class="modal-close" type="button" aria-label="Fechar" data-modal-close="listUsersModal" data-modal-open="adminModal">&times;</button></div>
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
                                    <button type="button" class="btn-icon delete" style="display:inline-flex; width:25px; height:25px;" data-post-action="delete_user" data-id="<?= (int) $u['id'] ?>" data-confirm="Excluir usuário?" aria-label="Excluir usuário <?= h($u['username']) ?>" title="Excluir usuário"><i aria-hidden="true" class="fas fa-trash" style="font-size:12px"></i></button>
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

<div id="createUserModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Novo usuário">
    <div class="modal-box" style="max-width:400px">
        <div class="modal-header"><h3>Novo Usuário</h3><button class="modal-close" type="button" aria-label="Fechar" data-modal-close="createUserModal">&times;</button></div>
        <div class="modal-body">
            <form method="POST">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="create_user">
                <input type="text" name="new_username" class="form-input" placeholder="Login" required autocomplete="off" aria-label="Login do novo usuário" style="width:100%; margin-bottom:10px;">
                <input type="password" name="new_password" class="form-input" placeholder="Senha (mín. 8)" required minlength="8" autocomplete="new-password" aria-label="Senha do novo usuário" style="width:100%; margin-bottom:10px;">
                <select name="new_type" class="form-input" aria-label="Tipo de usuário" style="width:100%; margin-bottom:15px;">
                    <option value="user">Usuário</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit" class="btn-primary" style="width:100%">Criar</button>
            </form>
        </div>
    </div>
</div>

<div id="renameModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Renomear">
    <div class="modal-box" style="max-width:400px">
        <div class="modal-header"><h3>Renomear</h3><button class="modal-close" type="button" aria-label="Fechar" data-modal-close="renameModal">&times;</button></div>
        <div class="modal-body">
            <form method="POST">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="old_name" id="oldNameInput">
                <input type="text" name="new_name" id="newNameInput" class="form-input" required aria-label="Novo nome">
                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">Salvar</button>
            </form>
        </div>
    </div>
</div>

<div id="folderAccessModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Acesso à pasta">
    <div class="modal-box" style="max-width:480px">
        <div class="modal-header"><h3><i aria-hidden="true" class="fas fa-lock"></i> Acesso à pasta</h3><button class="modal-close" type="button" aria-label="Fechar" data-modal-close="folderAccessModal">&times;</button></div>
        <div class="modal-body">
            <form method="POST" id="folderAccessForm">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="set_folder_access">
                <input type="hidden" name="folder" id="folderAccessName">
                <p>Pasta: <strong id="folderAccessTitle"></strong></p>
                <label class="access-choice"><input type="radio" name="mode" value="inherit" id="accessInherit"> Herdar: livre para todos (ou o que a pasta de cima definir)</label>
                <label class="access-choice"><input type="radio" name="mode" value="restrict" id="accessRestrict"> Restringir a:</label>
                <fieldset class="access-list" id="accessTargets">
                    <legend>Usuários</legend>
                    <?php $plain = array_filter($users, function ($u) { return $u['role'] !== 'admin'; }); ?>
                    <?php if (!$plain): ?><small>Nenhum usuário comum cadastrado.</small><?php endif; ?>
                    <?php foreach ($plain as $u): ?>
                        <label><input type="checkbox" name="users[]" value="<?= (int) $u['id'] ?>" data-access-user> <?= h($u['username']) ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <fieldset class="access-list" id="accessGroups">
                    <legend>Grupos</legend>
                    <?php if (!$groups): ?><small>Nenhum grupo criado (Painel Master → Grupos).</small><?php endif; ?>
                    <?php foreach ($groups as $g): ?>
                        <label><input type="checkbox" name="groups[]" value="<?= (int) $g['id'] ?>" data-access-group> <?= h($g['name']) ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <p class="access-hint"><small>Admins sempre têm acesso. Restringir sem marcar ninguém deixa a pasta só para admins. Para entrar numa subpasta, o usuário também precisa de acesso a todas as pastas acima dela.</small></p>
                <button type="submit" class="btn-primary" style="width:100%">Salvar</button>
            </form>
        </div>
    </div>
</div>

<div id="groupsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Grupos">
    <div class="modal-box">
        <div class="modal-header"><h3><i aria-hidden="true" class="fas fa-users-cog"></i> Grupos</h3><button class="modal-close" type="button" aria-label="Fechar" data-modal-close="groupsModal" data-modal-open="adminModal">&times;</button></div>
        <div class="modal-body">
            <form method="POST" class="input-group" style="margin-bottom:20px">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="create_group">
                <input type="text" name="group_name" class="form-input" placeholder="Novo grupo (ex.: RH)" required maxlength="50" autocomplete="off" aria-label="Nome do novo grupo">
                <button type="submit" class="btn-primary">Criar</button>
            </form>
            <?php if (!$groups): ?><p><em>Nenhum grupo ainda.</em></p><?php endif; ?>
            <?php foreach ($groups as $g): ?>
            <form method="POST" class="group-card">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                <div class="group-head">
                    <strong><?= h($g['name']) ?></strong>
                    <span><?= count($g['members']) ?> membro(s)</span>
                    <button type="button" class="btn-icon delete" style="display:inline-flex; width:25px; height:25px;" data-post-action="delete_group" data-id="<?= (int) $g['id'] ?>" data-confirm="Excluir o grupo e tirá-lo das regras de pasta?" aria-label="Excluir grupo <?= h($g['name']) ?>" title="Excluir grupo"><i aria-hidden="true" class="fas fa-trash" style="font-size:12px"></i></button>
                </div>
                <div class="group-members">
                    <?php foreach ($users as $u): ?>
                        <?php if ($u['role'] === 'admin') { continue; } ?>
                        <label><input type="checkbox" name="members[]" value="<?= (int) $u['id'] ?>"<?= in_array((int) $u['id'], $g['members'], true) ? ' checked' : '' ?>> <?= h($u['username']) ?></label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="action" value="set_group_members" class="btn-primary">Salvar membros</button>
            </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($moveNodes !== null): ?>
<div id="moveModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Mover itens">
    <div class="modal-box" style="max-width:520px">
        <div class="modal-header"><h3><i aria-hidden="true" class="fas fa-folder-tree"></i> Mover para</h3><button class="modal-close" type="button" aria-label="Fechar" data-modal-close="moveModal">&times;</button></div>
        <div class="modal-body">
            <form method="POST" id="moveForm">
                <?= App\Csrf::field() ?>
                <input type="hidden" name="action" value="move">
                <input type="hidden" name="dest" id="moveDest" value="">
                <div id="moveItems"></div>
                <p id="moveSummary"></p>
                <div class="tree-panel tree-select">
                    <ul role="tree" aria-label="Pasta de destino" data-tree data-tree-select data-tree-url="<?= h(url(['action' => 'tree'])) ?>">
                        <li role="treeitem" tabindex="0" aria-level="1" aria-expanded="true" aria-selected="false" data-path="" data-loaded="1">
                            <div class="tree-row">
                                <span class="tree-toggle" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
                                <a href="<?= h(url()) ?>" tabindex="-1"><i aria-hidden="true" class="fas fa-home"></i> Início</a>
                            </div>
                            <?php if ($moveNodes) { view('files/tree', ['nodes' => $moveNodes, 'current' => "\n", 'level' => 2]); } ?>
                        </li>
                    </ul>
                </div>
                <p id="moveHint" role="status" class="move-hint">Escolha a pasta de destino.</p>
                <button type="submit" id="moveSubmit" class="btn-primary" style="width:100%" disabled>Mover aqui</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
