<?php

use App\Http\Middleware\PrivateResponse;
use App\Http\Middleware\StrictCsrf;
use App\Http\Middleware\ValidSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;

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
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
