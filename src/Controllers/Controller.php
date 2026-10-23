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
        return [$area, $abs, $clean];
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
}
