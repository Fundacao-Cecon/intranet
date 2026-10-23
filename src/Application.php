<?php
namespace App;

use App\Controllers\AuthController;
use App\Controllers\FilesController;
use App\Controllers\TrashController;
use App\Controllers\UsersController;
use App\Services\FileMeta;
use App\Services\FileStorage;
use Throwable;

/**
 * Front controller: sessão, headers de segurança, CSRF, autenticação e rotas.
 *
 *   GET  (sem action)            -> lista de arquivos (ou login)
 *   GET  ?action=download|logout
 *   POST action=<nome>           -> ver Application::POST_ROUTES
 */
final class Application
{
    /** action => [Controller, método, exige admin?] (a checagem de admin fica no controller) */
    const GET_ROUTES = [
        ''         => [FilesController::class, 'index'],
        'download' => [FilesController::class, 'download'],
        'logout'   => [AuthController::class, 'logout'],
    ];

    const POST_ROUTES = [
        'login'           => [AuthController::class, 'login'],
        'change_password' => [AuthController::class, 'changePassword'],
        'mkdir'           => [FilesController::class, 'mkdir'],
        'rename'          => [FilesController::class, 'rename'],
        'upload'          => [FilesController::class, 'upload'],
        'trash'           => [FilesController::class, 'trash'],
        'zip'             => [FilesController::class, 'zip'],
        'restore'         => [TrashController::class, 'restore'],
        'purge'           => [TrashController::class, 'purge'],
        'empty_trash'     => [TrashController::class, 'emptyTrash'],
        'create_user'     => [UsersController::class, 'create'],
        'delete_user'     => [UsersController::class, 'delete'],
    ];

    /** Rotas acessíveis sem login. */
    const PUBLIC_ACTIONS = ['', 'login', 'change_password'];

    /** @var array */
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function run(): void
    {
        $this->configureErrors();

        try {
            $this->startSession();
            $this->sendSecurityHeaders();

            $ctx = $this->buildContext();
            $this->dispatch($ctx);
        } catch (Throwable $e) {
            $this->handleFatal($e);
        }
    }

    private function dispatch(Context $ctx): void
    {
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

        // Upload maior que post_max_size chega com $_POST/$_FILES vazios.
        if ($isPost && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->plainError(413, 'O arquivo enviado excede o limite do servidor (post_max_size).');
        }

        $action = $isPost ? $this->scalar($_POST['action'] ?? '') : $this->scalar($_GET['action'] ?? '');
        $routes = $isPost ? self::POST_ROUTES : self::GET_ROUTES;

        if (!isset($routes[$action])) {
            http_response_code(404);
            exit('Rota não encontrada.');
        }

        if ($isPost && !Csrf::verifyRequest()) {
            $this->plainError(419, 'Sessão expirada ou requisição inválida. Recarregue a página e tente de novo.');
        }

        $loggedIn = $ctx->auth->check();
        if (!$loggedIn && !in_array($action, self::PUBLIC_ACTIONS, true)) {
            $action = '';
            $isPost = false;
            $routes = self::GET_ROUTES;
        }

        // Sem login, a "home" é a tela de login.
        if (!$loggedIn && $action === '') {
            (new AuthController($ctx))->showLogin();
            return;
        }

        list($class, $method) = $routes[$action];
        (new $class($ctx))->$method();
    }

    private function buildContext(): Context
    {
        $paths = $this->config['paths'];

        $db = Database::connect($paths['database']);
        $logger = new Logger($db);
        $auth = new Auth($db, $logger, $this->config['auth']);
        $logger->setUserProvider(function () use ($auth) {
            return $auth->username();
        });

        // Limpeza periódica de logs antigos (1 em cada ~50 requisições).
        if (random_int(1, 50) === 1) {
            $logger->purge((int) $this->config['logs']['retention_days']);
        }

        $storage = new FileStorage($paths['uploads'], $paths['trash'], $this->config['hidden_names']);
        return new Context($this->config, $db, $auth, $logger, $storage, new FileMeta($db));
    }

    private function startSession(): void
    {
        $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('INTRANETSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $path,
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    private function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store');
    }

    private function configureErrors(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->config['debug'] ? '1' : '0');
        ini_set('log_errors', '1');
        $logDir = $this->config['paths']['logs'];
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        ini_set('error_log', $logDir . '/php-error.log');
    }

    private function handleFatal(Throwable $e): void
    {
        error_log((string) $e);
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo $this->config['debug']
            ? '<pre>' . h((string) $e) . '</pre>'
            : 'Erro interno. Consulte o administrador (storage/logs/php-error.log).';
    }

    private function plainError(int $status, string $msg): void
    {
        $ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        http_response_code($status);
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'msg' => $msg]);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo $msg;
        }
        exit;
    }

    private function scalar($v): string
    {
        return is_string($v) ? $v : '';
    }
}
