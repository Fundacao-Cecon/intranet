<?php

use App\Http\Middleware\EnforceSessionLimits;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SPA no mesmo domínio: sessão por cookie + CSRF (X-XSRF-TOKEN), sem tokens de API.
        $middleware->statefulApi();
        // Ociosidade de 8 h e teto de 12 h, como no legado.
        $middleware->appendToGroup('api', EnforceSessionLimits::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
