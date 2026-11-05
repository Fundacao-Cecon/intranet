<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\TrustedProxies;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
| Proxies confiáveis. O limitador de login (T020) conta falhas por IP, então "qual é o IP" não
| pode ser decidido pelo cliente:
|   - sem proxy confiável (padrão), X-Forwarded-For é IGNORADO: vale o endereço da conexão;
|   - com o proxy da instituição listado em INTRANET_TRUSTED_PROXIES, vale o IP do cliente que
|     ele repassa; um estranho que fale direto com a API continua sem poder forjar o cabeçalho.
| Sem isso, atrás do proxy todos pareceriam ter o IP dele e o limite de 30 por IP travaria todos.
*/

afterEach(fn () => TrustProxies::flushState());

/** Login vindo da conexão $conexao, com X-Forwarded-For opcional. */
function loginVia(string $conexao, string $usuario, string $senha, ?string $encaminhado = null): TestResponse
{
    app('auth')->forgetGuards();

    /** @var TestCase $t */
    $t = test();
    $cabecalhos = ['Referer' => 'http://localhost', 'Accept' => 'application/json'];
    if ($encaminhado !== null) {
        $cabecalhos['X-Forwarded-For'] = $encaminhado;
    }

    return $t->withServerVariables(['REMOTE_ADDR' => $conexao])->withHeaders($cabecalhos)
        ->postJson('/api/v1/auth/login', ['username' => $usuario, 'password' => $senha]);
}

function ipsGravados(): array
{
    return DB::table('login_attempts')->pluck('ip')->unique()->values()->all();
}

describe('sem proxy confiável (padrão)', function () {
    test('X-Forwarded-For é ignorado: a falha fica no IP da conexão', function () {
        loginVia('10.0.0.5', 'ana', 'errada', '203.0.113.9');

        expect(ipsGravados())->toBe(['10.0.0.5']);
    });

    test('forjar um IP novo a cada tentativa não escapa do limite de usuário+IP', function () {
        User::factory()->create(['username' => 'ana']);
        foreach (range(1, 5) as $i) {
            loginVia('10.0.0.5', 'ana', 'errada', "203.0.113.{$i}")->assertStatus(422);
        }

        loginVia('10.0.0.5', 'ana', 'senha-de-teste', '203.0.113.99')->assertStatus(429);
    });

    test('forjar IPs diferentes também não escapa do limite por IP', function () {
        User::factory()->create(['username' => 'maria']);
        foreach (range(1, 30) as $i) {
            loginVia('10.0.0.5', "alvo{$i}", 'errada', "203.0.113.{$i}")->assertStatus(422);
        }

        loginVia('10.0.0.5', 'maria', 'senha-de-teste', '203.0.113.200')->assertStatus(429);
    });
});

describe('com o proxy da instituição confiável', function () {
    beforeEach(fn () => TrustProxies::at(['10.0.0.5']));

    test('vale o IP do cliente que o proxy repassa', function () {
        loginVia('10.0.0.5', 'ana', 'errada', '203.0.113.9');

        expect(ipsGravados())->toBe(['203.0.113.9']);
    });

    test('clientes diferentes atrás do mesmo proxy têm orçamentos separados', function () {
        User::factory()->create(['username' => 'ana']);
        foreach (range(1, 5) as $_) {
            loginVia('10.0.0.5', 'ana', 'errada', '203.0.113.9');
        }

        loginVia('10.0.0.5', 'ana', 'senha-de-teste', '203.0.113.9')->assertStatus(429); // o cliente bloqueado
        loginVia('10.0.0.5', 'ana', 'senha-de-teste', '203.0.113.10')->assertOk();        // outro cliente, mesmo proxy
    });

    test('quem fala direto com a API (fora do proxy) continua sem poder forjar o cabeçalho', function () {
        loginVia('198.51.100.7', 'ana', 'errada', '203.0.113.9');

        expect(ipsGravados())->toBe(['198.51.100.7']);
    });
});

describe('ligação da configuração ao provider', function () {
    test('o AppServiceProvider aplica a lista de config("intranet.trusted_proxies")', function () {
        config(['intranet.trusted_proxies' => ['10.0.0.5']]);

        (new AppServiceProvider(app()))->boot();
        loginVia('10.0.0.5', 'ana', 'errada', '203.0.113.9');

        expect(ipsGravados())->toBe(['203.0.113.9']);
    });

    test('lista vazia na configuração não confia em ninguém', function () {
        config(['intranet.trusted_proxies' => []]);

        (new AppServiceProvider(app()))->boot();
        loginVia('10.0.0.5', 'ana', 'errada', '203.0.113.9');

        expect(ipsGravados())->toBe(['10.0.0.5']);
    });
});

describe('configuração por INTRANET_TRUSTED_PROXIES', function () {
    test('lista separada por vírgula, com espaços e itens vazios ignorados', function () {
        expect(TrustedProxies::parse(' 10.0.0.5 , 10.0.1.0/24,, '))->toBe(['10.0.0.5', '10.0.1.0/24']);
    });

    test('vazio ou ausente não confia em ninguém', function () {
        expect(TrustedProxies::parse(''))->toBe([]);
        expect(TrustedProxies::parse(null))->toBe([]);
    });

    test('o curinga * confia em qualquer origem e só vale se for pedido de forma explícita', function () {
        expect(TrustedProxies::parse('*'))->toBe(['*']);
    });
});
