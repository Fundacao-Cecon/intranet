<?php
/**
 * Testa o próprio bin/smoke.php: contra uma instalação correta (docroot em public/) ele
 * deve passar; contra uma instalação com o docroot ERRADO (raiz do projeto) ele deve
 * reprovar e apontar src/, config/, storage/ expostos.
 *
 *   php tests/smoke_test.php
 */

require __DIR__ . '/helpers.php';
$config = require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Database;
use App\Logger;

$tmp = mktmp('intranet-smoke-');
register_shutdown_function(function () use ($tmp) { rrm($tmp); });
require __DIR__ . '/http_server.php';
const PW = 'Senha-Teste-123';

$storage = "$tmp/storage";
mkdir("$tmp/sessions", 0775, true);
mkdir("$storage/uploads", 0775, true);
$db = Database::connect("$storage/database.db");
(new Auth($db, new Logger($db), $config['auth']))->createUser('smoke_user', PW, 'user');

function smoke(string $base, array $args = [], array $env = []): array
{
    $p = proc_open(array_merge([PHP_BINARY, dirname(__DIR__) . '/bin/smoke.php', $base], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
        null, array_merge(getenv(), $env));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    return [proc_close($p), $out];
}

suite('bin/smoke.php');

test('instalação correta (docroot = public/): nenhum FAIL e exit 0', function () use ($storage) {
    $b = startServer($storage);
    [$code, $out] = smoke($b);
    check($code === 0, "exit $code:\n$out");
    check(strpos($out, ' 0 FAIL') !== false, 'resumo: ' . substr($out, -120));
    foreach (['/src/Auth.php', '/database.db', '/.git/config', '/config/config.php'] as $p) {
        check(preg_match('/PASS\s+' . preg_quote($p, '/') . '/', $out) === 1, "$p deveria estar PASS");
    }
    check(strpos($out, 'WARN  site em HTTP puro') !== false, 'deveria avisar que o site está em HTTP');
});

test('login de verificação: entra, carrega o painel e o logout encerra a sessão', function () use ($storage) {
    $b = startServer($storage);
    [$code, $out] = smoke($b, ['--login'], ['SMOKE_USER' => 'smoke_user', 'SMOKE_PASS' => PW]);
    check($code === 0, "exit $code:\n$out");
    foreach (['login aceito', 'painel carrega após o login', 'logout encerra a sessão'] as $s) {
        check(strpos($out, "PASS  $s") !== false, "faltou: $s\n$out");
    }
    [$code, $out] = smoke($b, ['--login'], ['SMOKE_USER' => 'smoke_user', 'SMOKE_PASS' => 'errada-mesmo']);
    check($code === 1 && strpos($out, 'FAIL  painel não carregou') !== false, 'senha errada deveria reprovar');
    [$code, $out] = smoke($b, ['--login'], ['SMOKE_USER' => '', 'SMOKE_PASS' => '']);
    check($code === 1 && strpos($out, 'exige SMOKE_USER') !== false, 'sem credenciais deveria reprovar');
});

test('DOCROOT ERRADO (raiz do projeto): reprova e acusa o que ficou exposto', function () use ($storage) {
    $b = startServer($storage, [], [], dirname(__DIR__));
    [$code, $out] = smoke($b);
    check($code === 1, "deveria reprovar (exit $code):\n$out");
    check(strpos($out, 'FAIL') !== false, 'sem nenhum FAIL');
    // com o docroot na raiz, arquivos de código ficam acessíveis ou o index não é o do public/
    check(preg_match('/FAIL\s+\/(src\/Auth\.php|config\/config\.php|public\/index\.php|composer\.json|docs\/README\.md|tests\/helpers\.php)/', $out) === 1, "nenhum caminho interno foi acusado:\n$out");
});

test('URL inválida e servidor fora do ar: mensagem clara e exit 1', function () {
    [$code, $out] = smoke('isto-nao-e-url');
    check($code !== 0 && strpos($out, 'Uso:') !== false, 'URL inválida');
    [$code, $out] = smoke('http://127.0.0.1:1');
    check($code === 1 && strpos($out, 'servidor não respondeu') !== false, "porta fechada: $out");
});

finish();
