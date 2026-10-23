<?php
namespace App\Controllers;

use App\Services\FileStorage;
use ZipArchive;

/** Listagem, upload, pastas, renomear, mover p/ lixeira, download e ZIP. */
final class FilesController extends Controller
{
    // ------------------------------------------------------------------
    // Listagem
    // ------------------------------------------------------------------

    public function index(): void
    {
        $s = $this->ctx->storage;
        $auth = $this->ctx->auth;
        $cfg = $this->ctx->config;

        list($area, $absDir, $sub) = $this->location();
        $isTrash = $area === FileStorage::AREA_TRASH;
        $base = $this->locationParams($area, $sub);

        list($dirs, $files) = $s->listDir($absDir);
        $names = array_merge($dirs, $files);

        // Uma única consulta para todos os metadados da página.
        $metaPaths = [];
        foreach ($names as $n) {
            $metaPaths[$n] = $s->metaPath($area, $sub, $n);
        }
        $meta = $this->ctx->meta->lookup(array_values($metaPaths));

        $rows = [];
        foreach ($names as $name) {
            $full = $absDir . $name;
            $isDir = is_dir($full);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $m = $meta[$metaPaths[$name]] ?? null;

            $fileParams = $base + ['action' => 'download', 'file' => $name];
            $rows[] = [
                'name'      => $name,
                'isDir'     => $isDir,
                'ext'       => $ext,
                'size'      => $isDir ? '-' : formatarTamanho(filesize($full)),
                'date'      => date('d/m/Y H:i', filemtime($full)),
                'createdBy' => $m['created_by'] ?? '-',
                'updatedBy' => $m['updated_by'] ?? '-',
                'isImage'   => !$isDir && in_array($ext, $cfg['image_extensions'], true),
                'canInline' => !$isDir && isset($cfg['inline_mime'][$ext]),
                'openUrl'   => $isDir ? url(['dir' => trim($sub . '/' . $name, '/')]) : null,
                'downloadUrl' => $isDir ? null : url($fileParams),
                'inlineUrl'   => $isDir ? null : url($fileParams + ['inline' => 1]),
            ];
        }

        $breadcrumbs = [];
        if ($sub !== '') {
            $acc = '';
            foreach (explode('/', $sub) as $part) {
                $acc .= ($acc === '' ? '' : '/') . $part;
                $breadcrumbs[] = ['nome' => $part, 'url' => url(['dir' => $acc] + ($isTrash ? ['mode' => 'trash'] : []))];
            }
        }

        $isAdmin = $auth->isAdmin();
        view('files/index', [
            'flash'       => $this->takeFlash(),
            'isTrash'     => $isTrash,
            'isAdmin'     => $isAdmin,
            'username'    => (string) $auth->username(),
            'currentUserId' => $auth->id(),
            'rows'        => $rows,
            'hasFiles'    => (bool) $files,
            'breadcrumbs' => $breadcrumbs,
            'logs'        => $isAdmin ? $this->ctx->logger->recent(50) : [],
            'users'       => $isAdmin ? $auth->listUsers() : [],
        ]);
    }

    // ------------------------------------------------------------------
    // Ações de admin
    // ------------------------------------------------------------------

    public function mkdir(): void
    {
        $this->requireAdmin();
        $s = $this->ctx->storage;
        list($area, $absDir, $sub) = $this->location();

        $nome = $s->cleanName($this->post('nome_pasta'));
        if ($area === FileStorage::AREA_FILES && $nome !== '' && !file_exists($absDir . $nome) && mkdir($absDir . $nome, 0775)) {
            $this->ctx->meta->created($s->metaPath($area, $sub, $nome), (string) $this->ctx->auth->username());
            $this->ctx->logger->log('PASTA', $nome);
            $this->flash('Criada!', 'success');
        } else {
            $this->flash('Nome inválido.', 'error');
        }
        $this->redirectBack();
    }

    public function rename(): void
    {
        $this->requireAdmin();
        $s = $this->ctx->storage;
        list($area, $absDir, $sub) = $this->location();

        $ant = $s->safeItem($this->post('old_name'));
        $newBase = $s->cleanName($this->post('new_name'));
        $orig = $absDir . $ant;

        if ($area === FileStorage::AREA_FILES && $ant !== '' && $newBase !== '' && file_exists($orig)) {
            $new = $newBase;
            if (is_file($orig)) { // arquivo mantém a extensão original
                $ext = pathinfo($ant, PATHINFO_EXTENSION);
                if (pathinfo($newBase, PATHINFO_EXTENSION) !== $ext) {
                    $new .= '.' . $ext;
                }
            }
            if (!file_exists($absDir . $new) && rename($orig, $absDir . $new)) {
                $this->ctx->meta->renamed(
                    $s->metaPath($area, $sub, $ant),
                    $s->metaPath($area, $sub, $new),
                    (string) $this->ctx->auth->username()
                );
                $this->ctx->logger->log('RENAME', "$ant -> $new");
                $this->flash('Renomeado!', 'success');
                $this->redirectBack();
            }
        }
        $this->flash('Erro/Existe.', 'error');
        $this->redirectBack();
    }

    /** Move arquivo/pasta para a lixeira, guardando a origem. */
    public function trash(): void
    {
        $this->requireAdmin();
        $s = $this->ctx->storage;
        list($area, $absDir, $sub) = $this->location();

        $item = $s->safeItem($this->post('item'));
        $origem = $absDir . $item;

        if ($area === FileStorage::AREA_FILES && $item !== '' && file_exists($origem)) {
            $trashRoot = $s->root(FileStorage::AREA_TRASH);
            $dest = $s->uniqueName($trashRoot, $item);
            if (rename($origem, $trashRoot . $dest)) {
                $originPath = $s->metaPath($area, $sub, $item);
                $trashPath = $s->metaPath(FileStorage::AREA_TRASH, '', $dest);
                $this->ctx->meta->renamed($originPath, $trashPath, (string) $this->ctx->auth->username());
                $this->ctx->meta->rememberOrigin($trashPath, $originPath);
                $this->ctx->logger->log('LIXEIRA', 'Movido: ' . $item);
                $this->flash('Movido p/ lixeira.', 'success');
                $this->redirectBack();
            }
            $this->flash('Erro ao mover.', 'error');
        }
        $this->redirectBack();
    }

    public function upload(): void
    {
        $this->requireAdmin();
        $s = $this->ctx->storage;
        list($area, $absDir, $sub) = $this->location();
        $allowed = $this->ctx->config['allowed_extensions'];

        $suc = 0;
        $bloq = 0;
        $falha = 0;
        $f = $_FILES['arquivos'] ?? null;

        if ($area === FileStorage::AREA_FILES && $f && is_array($f['name']) && !empty($f['name'][0])) {
            $total = count($f['name']);
            for ($i = 0; $i < $total; $i++) {
                if ($f['error'][$i] !== UPLOAD_ERR_OK) {
                    $falha++;
                    continue;
                }
                $nome = (string) $f['name'][$i];
                $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    $bloq++;
                    $this->ctx->logger->log('BLOQUEIO', $nome);
                    continue;
                }
                $final = $s->uniqueName($absDir, $nome);
                if (move_uploaded_file($f['tmp_name'][$i], $absDir . $final)) {
                    $this->ctx->meta->created($s->metaPath($area, $sub, $final), (string) $this->ctx->auth->username());
                    $this->ctx->logger->log('UPLOAD', $final);
                    $suc++;
                } else {
                    $falha++;
                }
            }
        }

        $msg = "$suc enviados" . ($bloq || $this->isAjax() ? ", $bloq bloqueados" : '') . ($falha ? ", $falha com erro" : '') . '.';
        $status = ($suc > 0 && $bloq === 0 && $falha === 0) ? 'success' : 'warning';

        if ($this->isAjax()) {
            $this->json(['status' => $status, 'msg' => $msg]);
        }
        $this->flash($msg, $status);
        $this->redirectBack();
    }

    // ------------------------------------------------------------------
    // Download / ZIP (qualquer usuário logado; lixeira só admin)
    // ------------------------------------------------------------------

    public function download(): void
    {
        $s = $this->ctx->storage;
        $cfg = $this->ctx->config;
        list(, $absDir) = $this->location();

        $name = $s->safeItem($_GET['file'] ?? '');
        $full = $absDir . $name;
        if ($name === '' || $s->isHidden($name) || !is_file($full)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $inline = !empty($_GET['inline']) && isset($cfg['inline_mime'][$ext]);
        if (!$inline) {
            $this->ctx->logger->log('DOWNLOAD', $name);
        }

        $this->stream(
            $full,
            $inline ? $cfg['inline_mime'][$ext] : 'application/octet-stream',
            $inline ? 'inline' : 'attachment',
            $name
        );
    }

    public function zip(): void
    {
        $s = $this->ctx->storage;
        list($area, $absDir) = $this->location();
        $sel = $_POST['selecionados'] ?? [];

        if (class_exists('ZipArchive') && is_array($sel) && $sel && $area === FileStorage::AREA_FILES) {
            $nome = 'dl_' . date('YmdHis') . '.zip';
            $tmp = tempnam(sys_get_temp_dir(), 'zip');
            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $cnt = 0;
                foreach ($sel as $it) {
                    $item = $s->safeItem($it);
                    if ($item !== '' && !$s->isHidden($item) && is_file($absDir . $item)) {
                        $zip->addFile($absDir . $item, $item);
                        $cnt++;
                    }
                }
                $zip->close();
                if ($cnt > 0) {
                    $this->ctx->logger->log('ZIP', "$cnt arquivos");
                    $this->stream($tmp, 'application/zip', 'attachment', $nome, true);
                }
            }
            @unlink($tmp);
        }

        $this->flash('Nada selecionado.', 'error');
        $this->redirectBack();
    }

    /**
     * Envia um arquivo em blocos, com suporte a Range (vídeo/PDF grandes).
     * Sempre com nosniff; só tipos da lista branca saem como inline.
     */
    private function stream(string $full, string $contentType, string $disposition, string $name, bool $deleteAfter = false): void
    {
        $size = filesize($full);
        $start = 0;
        $end = $size - 1;
        $status = 200;

        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                if ($m[2] !== '') {
                    $end = min($end, (int) $m[2]);
                }
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }
            $status = 206;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close(); // não trava outras requisições durante o envio
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        @set_time_limit(0);

        $ascii = str_replace(['"', '\\'], '', preg_replace('/[^\x20-\x7E]/', '_', $name));
        http_response_code($status);
        header('Content-Type: ' . $contentType);
        header("Content-Disposition: $disposition; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($end - $start + 1));
        if ($status === 206) {
            header("Content-Range: bytes $start-$end/$size");
        }

        $fh = fopen($full, 'rb');
        if ($fh) {
            fseek($fh, $start);
            $left = $end - $start + 1;
            while ($left > 0 && !feof($fh) && !connection_aborted()) {
                $chunk = fread($fh, min(131072, $left));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                $left -= strlen($chunk);
                flush();
            }
            fclose($fh);
        }
        if ($deleteAfter) {
            @unlink($full);
        }
        exit;
    }
}
