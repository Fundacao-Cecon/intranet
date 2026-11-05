<?php

use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

function tentar(string $usuario, string $senha)
{
    app('auth')->forgetGuards();

    return test()->withHeaders(['Referer' => 'http://localhost', 'Accept' => 'application/json'])
        ->postJson('/api/v1/auth/login', ['username' => $usuario, 'password' => $senha]);
}

test('login com sucesso grava LOGIN no formato do legado', function () {
    User::factory()->create(['username' => 'ana', 'password' => password_hash('senha-de-teste', PASSWORD_BCRYPT, ['cost' => 10])]);

    tentar('ana', 'senha-de-teste')->assertOk();

    $log = DB::table('logs')->first();
    expect($log->acao)->toBe('LOGIN')
        ->and($log->usuario)->toBe('ana')
        ->and($log->detalhe)->toBe('Entrou no sistema')
        ->and($log->ip)->not->toBeEmpty()
        ->and($log->data_hora)->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});

test('senha errada de usuário real grava LOGIN_FALHA com o login e usuário Visitante', function () {
    User::factory()->create(['username' => 'ana']);

    tentar('ana', 'errada')->assertStatus(422);

    $log = DB::table('logs')->first();
    expect($log->acao)->toBe('LOGIN_FALHA')->and($log->usuario)->toBe('Visitante')->and($log->detalhe)->toBe('ana');
});

test('usuário inexistente nunca grava o texto digitado (comum digitar a senha no campo de usuário)', function () {
    tentar('MinhaSenhaSecreta123', 'x')->assertStatus(422);

    $log = DB::table('logs')->first();
    expect($log->acao)->toBe('LOGIN_FALHA')
        ->and($log->detalhe)->toMatch('/^desconhecido#[0-9a-f]{8}$/')
        ->and(json_encode(DB::table('logs')->get()))->not->toContain('MinhaSenhaSecreta123');
});

test('o mesmo texto digitado dá sempre o mesmo rótulo, sem diferenciar maiúsculas', function () {
    $audit = app(AuditLog::class);

    expect($audit->label('Fulano', null))->toBe($audit->label('fulano', null))
        ->and($audit->label('fulano', null))->not->toBe($audit->label('beltrano', null));
});

test('o detalhe é cortado em 500 caracteres', function () {
    app(AuditLog::class)->record('TESTE', str_repeat('á', 800), 'ana');

    expect(mb_strlen(DB::table('logs')->value('detalhe')))->toBe(500);
});

test('a retenção apaga o que passou do prazo e guarda o resto', function () {
    DB::table('logs')->insert([
        ['data_hora' => DB::raw("DATETIME('now','localtime','-181 days')"), 'ip' => 'x', 'usuario' => 'a', 'acao' => 'VELHO', 'detalhe' => ''],
        ['data_hora' => DB::raw("DATETIME('now','localtime','-179 days')"), 'ip' => 'x', 'usuario' => 'a', 'acao' => 'RECENTE', 'detalhe' => ''],
    ]);

    expect(app(AuditLog::class)->purge(180))->toBe(1)
        ->and(DB::table('logs')->pluck('acao')->all())->toBe(['RECENTE']);
});

test('o comando usa 180 dias por padrão e aceita --days', function () {
    DB::table('logs')->insert([
        ['data_hora' => DB::raw("DATETIME('now','localtime','-200 days')"), 'ip' => 'x', 'usuario' => 'a', 'acao' => 'A', 'detalhe' => ''],
        ['data_hora' => DB::raw("DATETIME('now','localtime','-100 days')"), 'ip' => 'x', 'usuario' => 'a', 'acao' => 'B', 'detalhe' => ''],
        ['data_hora' => DB::raw("DATETIME('now','localtime','-5 days')"), 'ip' => 'x', 'usuario' => 'a', 'acao' => 'C', 'detalhe' => ''],
    ]);

    expect(config('intranet.log_retention_days'))->toBe(180);
    test()->artisan('intranet:logs-purge')->expectsOutputToContain('1 registro(s) apagado(s) (retenção: 180 dias)')->assertSuccessful();
    expect(DB::table('logs')->pluck('acao')->all())->toBe(['B', 'C']);

    test()->artisan('intranet:logs-purge', ['--days' => 30])->expectsOutputToContain('1 registro(s)')->assertSuccessful();
    expect(DB::table('logs')->pluck('acao')->all())->toBe(['C']);
});

test('a limpeza está no agendador, uma vez por dia', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'intranet:logs-purge'));

    expect($evento)->not->toBeNull()->and($evento->expression)->toBe('0 0 * * *');
});
