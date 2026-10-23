<?php
/**
 * Painel principal (lista de arquivos / lixeira).
 *
 * @var array|null $flash
 * @var bool       $isTrash
 * @var bool       $isAdmin
 * @var string     $username
 * @var int|null   $currentUserId
 * @var array      $rows         itens já preparados por FilesController::index()
 * @var bool       $hasFiles
 * @var array      $breadcrumbs  [['nome','url'], ...]
 * @var array      $logs
 * @var array      $users
 */
?><!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h(App\Csrf::token()) ?>">
    <title>Intranet</title>
    <link rel="icon" href="assets/img/logotipo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="<?= $isTrash ? 'trash-view' : '' ?>"
      data-flash-text="<?= h($flash['text'] ?? '') ?>"
      data-flash-type="<?= h($flash['type'] ?? 'info') ?>">
    <div id="toast-container"></div>

    <div id="lightboxModal" class="modal-overlay">
        <div style="position: relative; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center;" data-lightbox-backdrop>
            <img id="lightboxImg" class="lightbox-content" src="" alt="">
            <span data-modal-close="lightboxModal" style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; cursor: pointer;">&times;</span>
        </div>
    </div>

    <div class="main-container <?= $isTrash ? 'container-trash' : '' ?>">

        <div class="top-bar">
            <h2 style="margin:0; display:flex; align-items:center; gap:10px;">
                <?= $isTrash ? '🗑️ Lixeira' : '📂 Intranet' ?>
            </h2>
            <div class="user-area">
                <button class="btn-theme-header" type="button" data-theme-toggle title="Alternar Tema">🌙</button>

                <div class="user-badge"><i class="fas fa-user-circle"></i> <span><?= h($username) ?></span></div>

                <button type="button" class="btn-header" data-modal-open="changePasswordModal" title="Alterar minha senha"><i class="fas fa-key"></i></button>
                <?php if ($isAdmin && !$isTrash): ?>
                <button type="button" data-modal-open="adminModal" class="btn-header" style="background: #4a5568; color: white; border:none;"><i class="fas fa-tools"></i> Painel Master</button>
                <?php endif; ?>
                <a href="<?= h(url(['action' => 'logout'])) ?>" class="btn-header btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>

        <div class="content-body">

            <?php if (!$isTrash): ?>
            <div class="page-header">
                <div class="nav-bar">
                    <div class="breadcrumbs">
                        <a href="<?= h(url()) ?>"><i class="fas fa-home"></i> Início</a>
                        <?php foreach ($breadcrumbs as $crumb): ?>
                            <span class="separator">/</span> <a href="<?= h($crumb['url']) ?>"><?= h($crumb['nome']) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="search-box"><i class="fas fa-search"></i> <input type="text" id="searchInput" placeholder="Buscar..."></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isTrash): ?>
            <div class="trash-alert-bar">
                <div><i class="fas fa-exclamation-triangle"></i> Arquivos na lixeira.</div>
                <div class="trash-actions">
                    <a href="<?= h(url()) ?>" class="btn-header">Voltar</a>
                    <?php if ($rows): ?>
                    <button type="button" class="btn-header btn-danger" data-post-action="empty_trash" data-confirm="Tem certeza?"><i class="fas fa-trash-restore-alt"></i> Esvaziar Tudo</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isAdmin && !$isTrash): ?>
            <div class="actions-toolbar">
                <div class="upload-section">
                    <form id="uploadForm" method="POST" enctype="multipart/form-data">
                        <?= App\Csrf::field() ?>
                        <input type="hidden" name="action" value="upload">
                        <div class="upload-dropzone" id="dropZone">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h3 style="margin:0">Arraste arquivos aqui</h3>
                            <input type="file" name="arquivos[]" id="fileInput" multiple>
                        </div>
                        <div class="progress-bar-container" id="progressContainer"><div class="progress-bar-fill" id="progressBar"></div></div>
                        <button type="submit" class="btn-start-upload" id="btnUploadStart">Iniciar Upload</button>
                    </form>
                </div>
                <div class="folder-section">
                    <div class="action-card">
                        <div class="action-title"><i class="fas fa-folder-plus" style="color: #f39c12;"></i> Nova Pasta</div>
                        <form method="POST">
                            <?= App\Csrf::field() ?>
                            <input type="hidden" name="action" value="mkdir">
                            <div class="input-group"><input type="text" name="nome_pasta" class="form-input" placeholder="Nome..." required autocomplete="off"><button type="submit" class="btn-primary">Criar</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" id="fileListForm">
                <?= App\Csrf::field() ?>
                <div class="file-list-container">
                    <div class="file-list-header">
                        <div class="col-check"><?php if (!$isTrash): ?><input type="checkbox" id="selectAll" class="file-checkbox"><?php endif; ?></div>
                        <div class="col-icon">Tipo</div>
                        <div class="col-name">Nome</div>
                        <div class="col-user">Criado Por</div>
                        <div class="col-user">Editado Por</div>
                        <div class="col-size">Tamanho</div>
                        <div class="col-date">Data</div>
                        <div class="col-actions">Ações</div>
                    </div>
                    <ul id="fileList" style="list-style:none; padding:0; margin:0;">
                        <?php if (!$rows): ?>
                            <li class="empty-state"><i class="fas <?= $isTrash ? 'fa-trash-alt' : 'fa-folder-open' ?>"></i><h3><?= $isTrash ? 'Lixeira Vazia' : 'Esta pasta está vazia' ?></h3></li>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                            <li class="file-item filterable-item">
                                <div class="col-check"><?php if (!$r['isDir'] && !$isTrash): ?><input type="checkbox" name="selecionados[]" value="<?= h($r['name']) ?>" class="file-checkbox item-check"><?php endif; ?></div>
                                <div class="col-icon">
                                    <?php if ($r['isDir']): ?>
                                        <i class="fas fa-folder" style="color:#f39c12;"></i>
                                    <?php elseif ($r['isImage']): ?>
                                        <img src="<?= h($r['inlineUrl']) ?>" class="file-thumb" alt="">
                                    <?php elseif ($r['ext'] === 'pdf'): ?>
                                        <i class="fas fa-file-pdf" style="color:#e74c3c;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-file" style="color:#999;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="col-name">
                                    <?php if ($r['isDir'] && !$isTrash): ?>
                                        <a href="<?= h($r['openUrl']) ?>"><?= h($r['name']) ?></a>
                                    <?php elseif (!$r['isDir'] && !$isTrash): ?>
                                        <a href="<?= h($r['downloadUrl']) ?>"><?= h($r['name']) ?></a>
                                    <?php else: ?>
                                        <a href="#"><?= h($r['name']) ?></a>
                                    <?php endif; ?>
                                </div>

                                <div class="col-user" title="<?= h($r['createdBy']) ?>"><small><?= h($r['createdBy']) ?></small></div>
                                <div class="col-user" title="<?= h($r['updatedBy']) ?>"><small><?= h($r['updatedBy']) ?></small></div>

                                <div class="col-size"><?= h($r['size']) ?></div>
                                <div class="col-date"><?= h($r['date']) ?></div>
                                <div class="col-actions">
                                    <?php if ($isTrash && $isAdmin): ?>
                                        <button type="button" class="btn-icon" title="Restaurar" data-post-action="restore" data-item="<?= h($r['name']) ?>"><i class="fas fa-trash-restore"></i></button>
                                        <button type="button" class="btn-icon delete" title="Excluir" data-post-action="purge" data-item="<?= h($r['name']) ?>" data-confirm="Permanente?"><i class="fas fa-times"></i></button>
                                    <?php else: ?>
                                        <?php if (!$r['isDir']): ?>
                                            <?php if ($r['isImage']): ?>
                                                <button type="button" class="btn-icon view" data-lightbox="<?= h($r['inlineUrl']) ?>"><i class="fas fa-eye"></i></button>
                                            <?php elseif ($r['canInline']): ?>
                                                <a href="<?= h($r['inlineUrl']) ?>" target="_blank" rel="noopener" class="btn-icon view"><i class="fas fa-eye"></i></a>
                                            <?php else: ?>
                                                <a href="<?= h($r['downloadUrl']) ?>" class="btn-icon view" title="Baixar/Ver"><i class="fas fa-eye"></i></a>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($isAdmin): ?>
                                            <button type="button" class="btn-icon" data-rename="<?= h($r['name']) ?>"><i class="fas fa-edit"></i></button>
                                            <button type="button" class="btn-icon delete" title="Mover para a lixeira" data-post-action="trash" data-item="<?= h($r['name']) ?>"><i class="fas fa-trash"></i></button>
                                        <?php elseif (!$r['isDir']): ?>
                                            <a href="<?= h($r['downloadUrl']) ?>" class="btn-icon"><i class="fas fa-download"></i></a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <?php if (!$isTrash && $hasFiles): ?>
                    <div class="file-list-footer"><button type="submit" name="action" value="zip" id="btnZip" class="btn-zip">Baixar Selecionados (ZIP)</button></div>
                    <?php endif; ?>
                </div>
            </form>

            <div class="footer-credit">Desenvolvido por <strong>Tecnologia da Informação</strong></div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <?php view('modals/admin', ['logs' => $logs, 'users' => $users, 'currentUserId' => $currentUserId]); ?>
    <?php endif; ?>
    <?php view('modals/common', ['isAdmin' => $isAdmin]); ?>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
