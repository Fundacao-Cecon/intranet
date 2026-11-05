<?php

use App\Models\User;
use App\Services\LoginThrottle;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
| T020 — limitador de login em camadas (ADR 0005, decisão 1, opção B):
|   - usuário+IP: 5 falhas em 15 min bloqueiam aquele par;
|   - IP: 30 falhas em 15 min bloqueiam o IP (barra spraying de um só endereço);
|   - usuário sozinho NÃO bloqueia (um estranho não pode travar a conta de ninguém).
| Os dados ficam em `login_attempts`, a mesma tabela do legado, então os dois sistemas somam.
*/

const SENHA_T020 = 'senha-de-teste';
const MSG_BLOQUEIO = 'Muitas tentativas. Aguarde alguns minutos e tente de novo.';

/** Uma requisição de login vinda de um IP específico (cada chamada é uma requisição nova). */
function loginDe(string $ip, string $usuario, string $senha): TestResponse
{
    app('auth')->forgetGuards();

    /** @var TestCase $t */
    $t = test();

    return $t->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeaders(['Referer' => 'http://localhost', 'Accept' => 'application/json'])
        ->postJson('/api/v1/auth/login', ['username' => $usuario, 'password' => $senha]);
}

function falhas(string $ip, ?string $usuario = null): int
{
    $q = DB::table('login_attempts')->where('ip', $ip);

    return $usuario === null ? $q->count() : $q->where('username', $usuario)->count();
}

test('5 falhas do mesmo usuário e IP bloqueiam o par, até com a senha certa', function () {
    User::factory()->create(['username' => 'ana']);

    foreach (range(1, 5) as $_) {
        loginDe('10.0.0.1', 'ana', 'errada')->assertStatus(422);
    }

    loginDe('10.0.0.1', 'ana', SENHA_T020)
        ->assertStatus(429)
        ->assertJson(['message' => MSG_BLOQUEIO]);
});

test('a 4ª falha ainda deixa entrar com a senha certa', function () {
    User::factory()->create(['username' => 'ana']);

    foreach (range(1, 4) as $_) {
        loginDe('10.0.0.1', 'ana', 'errada')->assertStatus(422);
    }

    loginDe('10.0.0.1', 'ana', SENHA_T020)->assertOk();
});

test('o bloqueio de um par não alcança outro IP nem outro usuário', function () {
    User::factory()->create(['username' => 'ana']);
    User::factory()->create(['username' => 'maria']);
    foreach (range(1, 5) as $_) {
        loginDe('10.0.0.1', 'ana', 'errada');
    }

    loginDe('10.0.0.2', 'ana', SENHA_T020)->assertOk();   // outro IP
    loginDe('10.0.0.1', 'maria', SENHA_T020)->assertOk(); // outro usuário, mesmo IP
});

test('o bloqueio responde 429 com Retry-After', function () {
    foreach (range(1, 5) as $_) {
        loginDe('10.0.0.1', 'ana', 'errada');
    }

    $r = loginDe('10.0.0.1', 'ana', 'errada')->assertStatus(429);
    expect((int) $r->headers->get('Retry-After'))->toBeBetween(1, 15 * 60);
});

test('a 32ª tentativa vinda do mesmo IP é barrada, mesmo com logins todos diferentes', function () {
    User::factory()->create(['username' => 'maria']);

    foreach (range(1, 30) as $i) {
        loginDe('10.50.0.1', "alvo{$i}", 'errada')->assertStatus(422); // spraying: cada login tem o seu orçamento
    }

    loginDe('10.50.0.1', 'maria', SENHA_T020)->assertStatus(429)->assertJson(['message' => MSG_BLOQUEIO]);
    loginDe('10.50.0.2', 'maria', SENHA_T020)->assertOk(); // outro IP não é afetado
});

test('ataque vindo de muitos IPs não bloqueia o dono da conta (sem negação de serviço)', function () {
    User::factory()->create(['username' => 'joao']);

    foreach (range(1, 25) as $i) {
        loginDe("10.51.0.{$i}", 'joao', 'errada')->assertStatus(422);
    }

    loginDe('10.51.9.9', 'joao', SENHA_T020)->assertOk();
});

test('login certo zera as falhas daquele usuário e IP', function () {
    User::factory()->create(['username' => 'ana']);

    foreach (range(1, 4) as $_) {
        loginDe('10.0.0.1', 'ana', 'errada');
    }
    loginDe('10.0.0.1', 'ana', SENHA_T020)->assertOk();
    expect(falhas('10.0.0.1', 'ana'))->toBe(0);

    foreach (range(1, 4) as $_) {
        loginDe('10.0.0.1', 'ana', 'errada')->assertStatus(422); // recomeçou do zero: nenhuma é 429
    }
});

test('a janela de 15 minutos expira e o login volta a funcionar', function () {
    User::factory()->create(['username' => 'ana']);
    foreach (range(1, 5) as $_) {
        loginDe('10.0.0.1', 'ana', 'errada');
    }
    loginDe('10.0.0.1', 'ana', SENHA_T020)->assertStatus(429);

    $this->travel(16)->minutes();

    loginDe('10.0.0.1', 'ana', SENHA_T020)->assertOk();
});

test('usuário inexistente e usuário real bloqueados dão respostas idênticas', function () {
    User::factory()->create(['username' => 'ana']);
    foreach (range(1, 5) as $_) {
        loginDe('10.0.0.1', 'ana', 'errada');
        loginDe('10.0.0.1', 'fantasma', 'errada');
    }

    $real = loginDe('10.0.0.1', 'ana', 'errada');
    $fantasma = loginDe('10.0.0.1', 'fantasma', 'errada');

    expect($real->status())->toBe(429)->and($fantasma->status())->toBe(429);
    expect($fantasma->getContent())->toBe($real->getContent());
});

test('a diferença de caixa no login conta no mesmo orçamento', function () {
    foreach (['ana', 'ANA', 'Ana', 'aNa', 'anA'] as $digitado) {
        loginDe('10.0.0.1', $digitado, 'errada');
    }

    loginDe('10.0.0.1', 'ana', 'errada')->assertStatus(429);
});

test('reserva atômica: 30 tentativas seguidas no mesmo par gravam só 5 falhas', function () {
    /** @var LoginThrottle $limitador */
    $limitador = app(LoginThrottle::class);

    $liberadas = 0;
    foreach (range(1, 30) as $_) {
        $liberadas += $limitador->reserve('ana', '10.52.0.1') ? 1 : 0;
    }

    expect($liberadas)->toBe(5)->and(falhas('10.52.0.1', 'ana'))->toBe(5);
});

test('o bloqueio entra no log uma vez por janela e IP, sem o texto digitado', function () {
    $digitado = 'Minha-Senha-Secreta-no-campo-errado';
    foreach (range(1, 5) as $_) {
        loginDe('10.0.0.1', $digitado, 'errada');
    }
    foreach (range(1, 4) as $_) {
        loginDe('10.0.0.1', $digitado, 'errada')->assertStatus(429);
    }

    $linhas = DB::table('logs')->where('acao', 'LOGIN_BLOQUEADO')->get();
    expect($linhas)->toHaveCount(1);
    expect($linhas[0]->detalhe)->toStartWith('desconhecido#')->not->toContain('Secreta');
    expect($linhas[0]->ip)->toBe('10.0.0.1');
});

test('tentativa bloqueada não grava LOGIN_FALHA nem soma ao contador', function () {
    foreach (range(1, 5) as $_) {
        loginDe('10.0.0.1', 'ana', 'errada');
    }
    $antes = falhas('10.0.0.1', 'ana');
    $falhasNoLog = DB::table('logs')->where('acao', 'LOGIN_FALHA')->count();

    loginDe('10.0.0.1', 'ana', 'errada')->assertStatus(429);

    expect(falhas('10.0.0.1', 'ana'))->toBe($antes);
    expect(DB::table('logs')->where('acao', 'LOGIN_FALHA')->count())->toBe($falhasNoLog);
});
