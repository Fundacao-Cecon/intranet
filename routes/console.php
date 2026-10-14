<?php

use App\Services\HealthChecker;
use Illuminate\Support\Facades\Artisan;

Artisan::command('intranet:ready', function (HealthChecker $health): int {
    try {
        $health->check();

        return 0;
    } catch (Throwable) {
        $this->error('Ambiente indisponível.');

        return 1;
    }
})->purpose('Verifica PostgreSQL e storage privado sem expor detalhes internos.');
