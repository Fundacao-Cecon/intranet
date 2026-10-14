<?php

namespace App\Providers;

use App\Models\User;
use App\Services\HealthChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::define('admin', fn (User $user) => $user->role === 'admin');
        foreach (['login', 'password-change'] as $name) {
            RateLimiter::for($name, fn (Request $request) => [
                Limit::perMinute(30)->by($name.':ip:'.$request->ip()),
                Limit::perMinute(5)->by($name.':account:'.hash('sha256', $request->ip().'|'.(is_string($request->input('username')) ? substr($request->input('username'), 0, 255) : 'invalid'))),
            ]);
        }
    }
}
