<?php
namespace App\Controllers;

use App\Services\FileStorage;

/** Lixeira: restaurar, excluir definitivamente, esvaziar (somente admin). */
final class TrashController extends Controller
{
    /** Restaura para a pasta original (se ainda existir); senão, para a raiz. */
    public function restore(): void
    {
        $this->requireAdmin();
        $s = $this->ctx->storage;

        list($area, $absDir, $sub) = $this->location();
        $item = $s->safeItem($this->post('item'));
        $origem = $absDir . $item;

        if ($area !== FileStorage::AREA_TRASH || $item === '' || !file_exists($origem)) {
            $this->flash('Item não encontrado.', 'error');
            $this->redirectBack();
        }

        $trashPath = $s->metaPath(FileStorage::AREA_TRASH, $sub, $item);

        // Pasta de destino: a original só vale para itens do topo da lixeira.
        $destSub = '';
        $destDir = $s->root(FileStorage::AREA_FILES);
        $origin = $sub === '' ? $this->ctx->meta->origin($trashPath) : null;
        if ($origin !== null) {
            $parent = trim(str_replace('\\', '/', dirname($origin)), './');
            list($dir, $clean) = $s->resolve(FileStorage::AREA_FILES, $parent);
            if ($clean === $parent) {
                $destDir = $dir;
                $destSub = $clean;
            }
        }

        $nomeFinal = $s->uniqueName($destDir, $item);
        if (rename($origem, $destDir . $nomeFinal)) {
            $this->ctx->meta->renamed(
                $trashPath,
                $s->metaPath(FileStorage::AREA_FILES, $destSub, $nomeFinal),
                (string) $this->ctx->auth->username()
            );
            $this->ctx->meta->forgetOrigin($trashPath);
            $this->ctx->logger->log('RESTAURAR', $item);
            $this->flash('Restaurado!', 'success');
        } else {
            $this->flash('Erro ao restaurar.', 'error');
        }
        $this->redirectBack();
    }

    public function purge(): void
    {
        $this->requireAdmin();
        $s = $this->ctx->storage;

        list($area, $absDir, $sub) = $this->location();
        $item = $s->safeItem($this->post('item'));
        $alvo = $absDir . $item;

        if ($area === FileStorage::AREA_TRASH && $item !== '' && file_exists($alvo)) {
            $trashPath = $s->metaPath(FileStorage::AREA_TRASH, $sub, $item);
            $this->ctx->meta->removed($trashPath);
            $this->ctx->meta->forgetOrigin($trashPath);
            $s->delete($alvo);
            $this->flash('Excluído.', 'success');
        }
        $this->ctx->logger->log('LIXEIRA', 'Delete: ' . $item);
        $this->redirectBack();
    }

    public function emptyTrash(): void
    {
        $this->requireAdmin();
        $s = $this->ctx->storage;
        $root = $s->root(FileStorage::AREA_TRASH);

        foreach (scandir($root) as $name) {
            if ($name === '.' || $name === '..' || $name === '.htaccess') {
                continue;
            }
            $s->delete($root . $name);
        }
        $this->ctx->meta->removed('.lixeira');
        $this->ctx->meta->forgetAllOrigins();

        $this->ctx->logger->log('LIXEIRA', 'Esvaziada');
        $this->flash('Lixeira limpa!', 'success');
        $this->redirect(['mode' => 'trash']);
    }
}
