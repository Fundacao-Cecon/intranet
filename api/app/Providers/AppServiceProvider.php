<?php

namespace App\Providers;

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
        //
    }
}
