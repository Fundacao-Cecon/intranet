<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

const SENHA = 'senha-de-teste';

/** A SPA fala com a API a partir do mesmo domínio: Sanctum só liga a sessão para origens "stateful". */
function spa(): TestCase
{
    // Cada chamada é uma requisição nova: o guard guardaria o usuário da anterior dentro do mesmo teste.
    app('auth')->forgetGuards();

    return test()->withHeaders(['Referer' => 'http://localhost', 'Accept' => 'application/json']);
}

function entrar(string $usuario = 'ana', string $senha = SENHA): TestResponse
{
    return spa()->postJson('/api/v1/auth/login', ['username' => $usuario, 'password' => $senha]);
}

test('login com senha certa devolve o usuário e abre a sessão', function () {
    User::factory()->create(['username' => 'ana']);

    entrar()->assertOk()->assertExactJson([
        'id' => User::where('username', 'ana')->value('id'),
        'username' => 'ana',
        'role' => 'user',
    ]);

    spa()->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('username', 'ana');
});

test('a resposta nunca traz o hash da senha', function () {
    User::factory()->create(['username' => 'ana']);

    expect(entrar()->getContent())->not->toContain('password')->not->toContain('$2y$');
});

test('senha errada e usuário inexistente dão a mesma resposta (sem enumerar usuários)', function () {
    User::factory()->create(['username' => 'ana']);

    $errada = entrar('ana', 'outra-senha');
    $inexistente = entrar('ninguem', 'outra-senha');

    $errada->assertStatus(422)->assertJson(['message' => 'Usuário ou senha incorretos.']);
    expect($inexistente->status())->toBe(422);
    expect($inexistente->getContent())->toBe($errada->getContent());
    spa()->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('login sem usuário ou sem senha é recusado antes de consultar o banco', function () {
    spa()->postJson('/api/v1/auth/login', ['username' => 'ana'])->assertStatus(422)->assertJsonValidationErrors('password');
    spa()->postJson('/api/v1/auth/login', ['password' => 'x'])->assertStatus(422)->assertJsonValidationErrors('username');
    spa()->postJson('/api/v1/auth/login', ['username' => ['a'], 'password' => 'x'])->assertStatus(422);
});

test('me sem sessão responde 401 em JSON', function () {
    spa()->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonStructure(['message']);
});

test('logout encerra a sessão', function () {
    User::factory()->create(['username' => 'ana']);
    entrar()->assertOk();

    spa()->postJson('/api/v1/auth/logout')->assertNoContent();

    spa()->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('logout sem sessão responde 401', function () {
    spa()->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

test('hash bcrypt gerado pelo PHP do legado entra no Laravel', function () {
    // Mesmo comando de App\Auth::hash(): password_hash com bcrypt e custo 10.
    DB::table('users')->insert([
        'username' => 'legado',
        'password' => password_hash('Senha do legado!', PASSWORD_BCRYPT, ['cost' => 10]),
        'role' => 'admin',
    ]);

    entrar('legado', 'Senha do legado!')->assertOk()->assertJsonPath('role', 'admin');
});

test('hash de outro custo é regravado no custo atual e o legado continua entrando', function () {
    DB::table('users')->insert([
        'username' => 'antigo',
        'password' => password_hash(SENHA, PASSWORD_BCRYPT, ['cost' => 5]),
        'role' => 'user',
    ]);

    entrar('antigo')->assertOk();

    $hash = DB::table('users')->where('username', 'antigo')->value('password');
    expect(password_get_info($hash)['options']['cost'])->toBe(10);
    expect(password_verify(SENHA, $hash))->toBeTrue(); // o que o legado faz
});

test('o login registra last_login no formato do legado', function () {
    User::factory()->create(['username' => 'ana']);
    expect(DB::table('users')->value('last_login'))->toBeNull();

    entrar()->assertOk();

    expect(DB::table('users')->value('last_login'))->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});

test('o login troca o ID da sessão (fixation)', function () {
    User::factory()->create(['username' => 'ana']);
    test()->startSession();
    $antes = session()->getId();

    entrar()->assertOk();

    expect(session()->getId())->not->toBe($antes);
});

test('o papel admin chega ao front', function () {
    User::factory()->admin()->create(['username' => 'chefe']);

    entrar('chefe')->assertOk()->assertJsonPath('role', 'admin');
});

describe('com a proteção CSRF ligada (o teste do Laravel a desliga por padrão)', function () {
    beforeEach(function () {
        $this->app['env'] = 'production'; // runningUnitTests() deixa de pular a verificação
        User::factory()->create(['username' => 'ana']);
    });

    test('login sem token CSRF é recusado com 419', function () {
        entrar()->assertStatus(419);
    });

    test('login com o token da sessão passa', function () {
        $this->withSession(['_token' => 'token-de-teste']);

        spa()->withHeader('X-CSRF-TOKEN', 'token-de-teste')
            ->postJson('/api/v1/auth/login', ['username' => 'ana', 'password' => SENHA])
            ->assertOk();
    });

    test('o endpoint de Sanctum entrega o cookie XSRF-TOKEN', function () {
        spa()->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
    });
});

test('login de origem fora dos domínios da SPA é recusado com 403, não com erro 500', function () {
    User::factory()->create(['username' => 'ana']);
    app('auth')->forgetGuards();

    test()->withHeaders(['Referer' => 'http://outro-site.example', 'Accept' => 'application/json'])
        ->postJson('/api/v1/auth/login', ['username' => 'ana', 'password' => SENHA])
        ->assertForbidden();

    test()->postJson('/api/v1/auth/login', ['username' => 'ana', 'password' => SENHA])->assertForbidden();
});
