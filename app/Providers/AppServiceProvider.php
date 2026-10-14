<?php

namespace App\Providers;

use App\Services\HealthChecker;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->environment('testing')) {
            foreach (['pgsql', 'pgsql_migrate'] as $connection) {
                $c = config('database.connections.'.$connection);
                if ($c['host'] !== 'db-test' || $c['database'] !== 'intranet_test') {
                    throw new RuntimeException('Testes exigem o PostgreSQL isolado db-test/intranet_test.');
                }
            }
        }
        Event::listen(DiagnosingHealth::class, fn () => $this->app->make(HealthChecker::class)->check());
    }
}
