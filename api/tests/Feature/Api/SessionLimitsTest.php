<?php

use App\Models\User;
use Illuminate\Auth\SessionGuard;

function logar(string $usuario = 'ana')
{
    app('auth')->forgetGuards();

    return test()->withHeaders(['Referer' => 'http://localhost', 'Accept' => 'application/json'])
        ->postJson('/api/v1/auth/login', ['username' => $usuario, 'password' => 'senha-de-teste']);
}

function quem()
{
    app('auth')->forgetGuards();

    return test()->withHeaders(['Referer' => 'http://localhost', 'Accept' => 'application/json'])
        ->getJson('/api/v1/auth/me');
}

beforeEach(function () {
    User::factory()->create(['username' => 'ana']);
});

test('os limites padrão são os do legado: 8 h parada e 12 h no total', function () {
    expect(config('intranet.session_idle'))->toBe(28800)
        ->and(config('intranet.session_absolute'))->toBe(43200)
        ->and(config('session.lifetime'))->toBe(480);
});

test('com uso dentro de 8 h a sessão continua', function () {
    logar()->assertOk();

    test()->travel(7)->hours();
    quem()->assertOk();
});

test('parada há mais de 8 h, a sessão expira com aviso e código', function () {
    logar()->assertOk();

    test()->travel(8)->hours();
    test()->travel(1)->minutes();
    quem()->assertUnauthorized()
        ->assertJson(['message' => 'Sua sessão expirou. Entre novamente.', 'code' => 'session_expired']);

    quem()->assertUnauthorized(); // e continua fora
});

test('cada requisição renova a ociosidade', function () {
    logar()->assertOk();

    foreach ([5, 5] as $horas) { // 10 h no total, nunca 8 h paradas
        test()->travel($horas)->hours();
        quem()->assertOk();
    }
});

test('o teto de 12 h vale mesmo com uso contínuo', function () {
    logar()->assertOk();

    foreach ([7, 4] as $horas) { // 11 h, sempre ativo
        test()->travel($horas)->hours();
        quem()->assertOk();
    }
    test()->travel(61)->minutes(); // passa de 12 h desde o login
    quem()->assertUnauthorized()->assertJsonPath('code', 'session_expired');
});

test('quem volta depois de expirar consegue entrar de novo', function () {
    logar()->assertOk();
    test()->travel(9)->hours();

    logar()->assertOk()->assertJsonPath('username', 'ana');
    quem()->assertOk();
});

test('sessão sem horário de login (anterior ao teto) passa a contar a partir da primeira requisição', function () {
    test()->withSession(['login_web_'.sha1(SessionGuard::class) => User::first()->id]);

    quem()->assertOk();
    expect(session('login_at'))->toBeInt();
});

test('requisição sem sessão não é afetada', function () {
    quem()->assertUnauthorized()->assertJsonMissing(['code' => 'session_expired']);
});

describe('com a proteção CSRF ligada', function () {
    beforeEach(function () {
        $this->app['env'] = 'production';
    });

    test('depois de expirar, o login com o token da sessão nova passa (sem 419)', function () {
        $this->withSession(['_token' => 'token-1']);
        app('auth')->forgetGuards();
        $this->withHeaders(['Referer' => 'http://localhost', 'Accept' => 'application/json', 'X-CSRF-TOKEN' => 'token-1'])
            ->postJson('/api/v1/auth/login', ['username' => 'ana', 'password' => 'senha-de-teste'])->assertOk();

        test()->travel(9)->hours();

        // Expirada: a requisição ainda traz um token válido, e o login deve seguir adiante.
        app('auth')->forgetGuards();
        $this->withHeaders(['Referer' => 'http://localhost', 'Accept' => 'application/json', 'X-CSRF-TOKEN' => session('_token')])
            ->postJson('/api/v1/auth/login', ['username' => 'ana', 'password' => 'senha-de-teste'])->assertOk();
    });
});
