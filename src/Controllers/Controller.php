<?php
namespace App\Controllers;

use App\Context;
use App\Services\FileStorage;

/** Base: helpers de redirect, flash, JSON e resolução da pasta atual. */
abstract class Controller
{
    /** @var Context */
    protected $ctx;

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
    }

    // --- contexto de navegação (área + subpasta) -------------------------

    /** Área atual: lixeira só para admin (como no original). */
    protected function area(): string
    {
        $wantsTrash = ($_GET['mode'] ?? '') === 'trash';
        return ($wantsTrash && $this->ctx->auth->isAdmin()) ? FileStorage::AREA_TRASH : FileStorage::AREA_FILES;
    }

    /** @return array [string $area, string $absDir, string $cleanSub] */
    protected function location(): array
    {
        $area = $this->area();
        $sub = isset($_GET['dir']) && is_string($_GET['dir']) ? $_GET['dir'] : '';
        list($abs, $clean) = $this->ctx->storage->resolve($area, $sub);
        if ($area === FileStorage::AREA_FILES && $clean !== '' && !$this->ctx->access->allows($this->ctx->auth->user(), $clean)) {
            $this->denyAccess();
        }
        return [$area, $abs, $clean];
    }

    /**
     * Pasta fechada para o usuário (ADR 0006). Navegar volta para a raiz com aviso; download,
     * ZIP e ações respondem 403. A raiz nunca é fechada, então o redirect não entra em laço.
     */
    private function denyAccess(): void
    {
        $msg = 'Acesso negado a esta pasta.';
        if ($this->isAjax()) {
            $this->json(['status' => 'error', 'msg' => $msg], 403);
        }
        $navigating = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($_GET['action'] ?? '') === '';
        if ($navigating) {
            $this->flash($msg, 'error');
            $this->redirect();
        }
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit($msg);
    }

    /** Parâmetros de URL que preservam o contexto (dir + mode). */
    protected function locationParams(string $area, string $sub): array
    {
        return [
            'dir'  => $sub,
            'mode' => $area === FileStorage::AREA_TRASH ? 'trash' : null,
        ];
    }

    // --- respostas ---------------------------------------------------------

    protected function flash(string $text, string $type = 'info'): void
    {
        $_SESSION['flash'] = ['text' => $text, 'type' => $type];
    }

    /** Lê e apaga a mensagem flash. */
    protected function takeFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }

    protected function redirect(array $params = []): void
    {
        header('Location: ' . url($params));
        exit;
    }

    /** Volta para a pasta/área atual (padrão pós-POST). */
    protected function redirectBack(): void
    {
        list($area, , $sub) = $this->location();
        $this->redirect($this->locationParams($area, $sub));
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    protected function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    /** Interrompe se não for admin. */
    protected function requireAdmin(): void
    {
        if (!$this->ctx->auth->isAdmin()) {
            if ($this->isAjax()) {
                $this->json(['status' => 'error', 'msg' => 'Acesso negado.'], 403);
            }
            $this->flash('Acesso negado.', 'error');
            $this->redirect();
        }
    }

    protected function post(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    /** Lista de nomes de item vinda de campo[] (cada um passa por safeItem; vazios e repetidos saem). */
    protected function postNames(string $key): array
    {
        $v = $_POST[$key] ?? [];
        if (!is_array($v)) {
            return [];
        }
        $names = [];
        foreach ($v as $x) {
            $name = $this->ctx->storage->safeItem($x);
            if ($name !== '') {
                $names[$name] = $name;
            }
        }
        return array_values($names);
    }

    /** Lista de ids inteiros positivos vinda de campo[] (qualquer outra coisa é ignorada). */
    protected function postIds(string $key): array
    {
        $v = $_POST[$key] ?? [];
        if (!is_array($v)) {
            return [];
        }
        $ids = [];
        foreach ($v as $x) {
            if ((is_string($x) || is_int($x)) && ctype_digit((string) $x) && (int) $x > 0) {
                $ids[(int) $x] = (int) $x;
            }
        }
        return array_values($ids);
    }
}
