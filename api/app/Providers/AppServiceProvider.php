<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Sanctum só por cookie de sessão: a migration de personal_access_tokens não é publicada,
        // para o banco compartilhado com o legado não ganhar uma tabela que ninguém usa.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Em qual proxy confiar para o IP do cliente (X-Forwarded-For). Vazio = em nenhum.
        TrustProxies::at(config('intranet.trusted_proxies'));
    }
}
