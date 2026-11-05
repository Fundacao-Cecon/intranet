<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
| T021 — alerta por usuário (ADR 0005, decisão 1): o usuário sozinho NÃO bloqueia, porque um
| estranho não pode travar a conta de ninguém. Em vez disso, passando de 20 falhas em 1 hora
| (somando todos os IPs) o sistema grava LOGIN_ALERTA no log para o administrador ver.
*/

const SENHA_T021 = 'senha-de-teste';

/** Uma requisição de login vinda de um IP específico (cada chamada é uma requisição nova). */
function tentarDe(string $ip, string $usuario, string $senha): TestResponse
{
    app('auth')->forgetGuards();

    /** @var TestCase $t */
    $t = test();

    return $t->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeaders(['Referer' => 'http://localhost', 'Accept' => 'application/json'])
        ->postJson('/api/v1/auth/login', ['username' => $usuario, 'password' => $senha]);
}

/** Falhas contra $usuario vindas de $quantos IPs diferentes (uma por IP, sem bater no limite por IP). */
function atacarDeVariosIps(string $usuario, int $quantos, string $faixa = '10.60.0'): void
{
    foreach (range(1, $quantos) as $i) {
        tentarDe("{$faixa}.{$i}", $usuario, 'errada')->assertStatus(422);
    }
}

function alertas(): Collection
{
    return DB::table('logs')->where('acao', 'LOGIN_ALERTA')->get();
}

test('20 falhas contra o mesmo usuário, de IPs diferentes, geram LOGIN_ALERTA', function () {
    User::factory()->create(['username' => 'joao']);

    atacarDeVariosIps('joao', 19);
    expect(alertas())->toHaveCount(0);

    atacarDeVariosIps('joao', 1, '10.61.0');
    expect(alertas())->toHaveCount(1);
    expect(alertas()[0]->detalhe)->toContain('joao')->toContain('20');
});

test('o dono da conta continua entrando durante o ataque (o usuário sozinho não bloqueia)', function () {
    User::factory()->create(['username' => 'joao']);

    atacarDeVariosIps('joao', 25);

    expect(alertas())->not->toBeEmpty();
    tentarDe('10.62.9.9', 'joao', SENHA_T021)->assertOk();
});

test('o alerta é gravado uma vez por janela, mesmo com o ataque continuando', function () {
    User::factory()->create(['username' => 'joao']);

    atacarDeVariosIps('joao', 30);

    expect(alertas())->toHaveCount(1);
});

test('passada a janela, um alerta novo é gravado', function () {
    User::factory()->create(['username' => 'joao']);
    atacarDeVariosIps('joao', 20);
    expect(alertas())->toHaveCount(1);

    // O alerta antigo sai da janela de 1 hora: o ataque que continua merece outro aviso.
    DB::table('logs')->where('acao', 'LOGIN_ALERTA')->update(['data_hora' => DB::raw("DATETIME('now', 'localtime', '-2 hours')")]);

    tentarDe('10.63.0.1', 'joao', 'errada');

    expect(alertas())->toHaveCount(2);
});

test('falhas com mais de 1 hora não contam', function () {
    User::factory()->create(['username' => 'joao']);
    $antigas = now()->subHours(2)->timestamp;
    foreach (range(1, 25) as $i) {
        DB::table('login_attempts')->insert(['ip' => "10.64.0.{$i}", 'username' => 'joao', 'attempted_at' => $antigas]);
    }

    tentarDe('10.64.9.9', 'joao', 'errada');

    expect(alertas())->toHaveCount(0);
});

test('a caixa do login não escapa da contagem', function () {
    User::factory()->create(['username' => 'joao']);

    foreach (range(1, 20) as $i) {
        tentarDe("10.65.0.{$i}", $i % 2 ? 'JOAO' : 'Joao', 'errada');
    }

    expect(alertas())->toHaveCount(1);
});

test('usuário inexistente também gera alerta, mas o texto digitado nunca vai para o log', function () {
    $digitado = 'Minha-Senha-Secreta-no-campo-errado';

    atacarDeVariosIps($digitado, 20);

    $linhas = alertas();
    expect($linhas)->toHaveCount(1);
    expect($linhas[0]->detalhe)->toContain('desconhecido#')->not->toContain('Secreta');
});

test('logins diferentes não somam entre si', function () {
    User::factory()->create(['username' => 'ana']);
    User::factory()->create(['username' => 'maria']);

    atacarDeVariosIps('ana', 12, '10.66.0');
    atacarDeVariosIps('maria', 12, '10.66.1');

    expect(alertas())->toHaveCount(0);
});

test('login certo não conta como falha e não gera alerta', function () {
    User::factory()->create(['username' => 'ana']);

    foreach (range(1, 25) as $i) {
        tentarDe("10.67.0.{$i}", 'ana', SENHA_T021)->assertOk();
    }

    expect(alertas())->toHaveCount(0);
});

test('tentativa bloqueada pelo limitador não soma à contagem do alerta', function () {
    User::factory()->create(['username' => 'ana']);
    foreach (range(1, 5) as $_) {
        tentarDe('10.68.0.1', 'ana', 'errada');   // 5 falhas gravadas
    }
    foreach (range(1, 30) as $_) {
        tentarDe('10.68.0.1', 'ana', 'errada')->assertStatus(429); // bloqueadas: nada é gravado
    }

    expect(alertas())->toHaveCount(0);
});
