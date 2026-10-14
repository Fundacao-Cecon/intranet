<?php

use App\Http\Middleware\PrivateResponse;
use App\Http\Middleware\StrictCsrf;
use App\Http\Middleware\ValidSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(replace: [PreventRequestForgery::class => StrictCsrf::class]);
        $middleware->web(append: [ValidSession::class, PrivateResponse::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/documentos');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            if (request()->is('documentos/upload') && $response->getStatusCode() >= 400) {
                $body = json_decode($response->getContent(), true);
                if (! isset($body['results'])) {
                    $messages = [401 => 'Entre novamente para enviar arquivos.', 403 => 'Somente administradores podem enviar arquivos.', 413 => 'O envio excedeu o limite de tamanho.', 419 => 'Atualize a página e tente novamente.', 422 => 'Verifique os arquivos e a pasta de destino.', 503 => 'Serviço temporariamente indisponível.'];

                    return response()->json(['status' => 'error', 'msg' => $messages[$response->getStatusCode()] ?? 'Não foi possível concluir o envio.', 'errors' => $body['errors'] ?? []], $response->getStatusCode())->header('Cache-Control', 'private, no-store, max-age=0');
                }
            }

            return $response;
        });
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
