<?php
/**
 * Testes do fluxo de login (sem dependências). Uso:  php tests/run.php
 *
 * Usa um banco SQLite temporário e simula requisições com session_id() manual.
 * Sai com código 1 se algum teste falhar.
 */

if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}

require __DIR__ . '/helpers.php';

ob_start(); // sem isto o PHP considera os headers enviados e session_regenerate_id falha

$config = require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Logger;

// --- ambiente de teste ------------------------------------------------------

$tmp = mktmp();
mkdir($tmp . '/sessions', 0775, true);
ini_set('session.save_path', $tmp . '/sessions');
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_strict_mode', '0');
ini_set('session.cache_limiter', '');

$db = Database::connect($tmp . '/test.db');
$logger = new Logger($db);
$authCfg = $config['auth'];

function newAuth(): Auth
{
    global $db, $logger, $authCfg;
    return new Auth($db, $logger, $authCfg);
}

/** Simula uma requisição: abre a sessão $sid (ou nova), roda $fn, fecha. Devolve [resultado, sid final]. */
function req(?string $sid, callable $fn): array
{
    session_id($sid ?: bin2hex(random_bytes(16)));
    session_start();
    $result = $fn();
    $final = session_id();
    session_write_close();
    return [$result, $final];
}

function ip(string $addr): void
{
    $_SERVER['REMOTE_ADDR'] = $addr;
}


function logRows(string $acao): array
{
    global $db;
    $st = $db->prepare('SELECT * FROM logs WHERE acao = ?');
    $st->execute([$acao]);
    return $st->fetchAll();
}

// --- dados ---------------------------------------------------------------------

const PW = 'Senha-Teste-123';
ip('10.0.0.1');
$setup = newAuth();
foreach (['maria' => 'user', 'joao' => 'admin', 'ana' => 'user'] as $u => $role) {
    [$ok, $msg] = $setup->createUser($u, PW, $role);
    if (!$ok) { exit("setup falhou ($u): $msg\n"); }
}

echo "Login — Fase 1 da ADR-0005\n";

// --- 1. login e sessão -----------------------------------------------------------

test('login correto grava sessão e troca o ID da sessão (anti-fixation)', function () {
    ip('10.0.1.1');
    $sidBefore = null;
    [$res, $sidAfter] = req(null, function () use (&$sidBefore) {
        $sidBefore = session_id();
        return newAuth()->attempt('maria', PW);
    });
    check($res[0] === true, 'attempt deveria retornar ok');
    check($sidAfter !== $sidBefore, 'session_regenerate_id não trocou o ID');
    [$data] = req($sidAfter, fn() => [$_SESSION['user_id'] ?? null, $_SESSION['login_at'] ?? null]);
    check($data[0] !== null && $data[1] !== null, 'user_id/login_at ausentes na sessão');
});

// --- 2. achado 1: expiração não pode perder o token CSRF -----------------------------

test('sessão ociosa expirada: login seguinte NÃO dá 419 (token CSRF persiste)', function () {
    ip('10.0.1.2');
    [, $sid] = req(null, fn() => newAuth()->attempt('maria', PW));
    req($sid, function () { $_SESSION['last_activity'] = time() - 99999; });

    // requisição do usuário que volta: a home vê a sessão expirada e mostra o login
    $token = null;
    [$user, $sid2] = req($sid, function () use (&$token) {
        $u = newAuth()->user();
        $token = Csrf::token(); // o que Csrf::field() embute no formulário de login
        return $u;
    });
    check($user === null, 'user() deveria ser null');
    check($sid2 !== $sid, 'o ID da sessão deveria ter sido trocado');

    // POST do formulário de login com esse token
    [$verified] = req($sid2, function () use ($token) {
        $_POST[Csrf::FIELD] = $token;
        $v = Csrf::verifyRequest();
        $_POST = [];
        return $v;
    });
    check($verified === true, 'token CSRF do formulário não bateu (seria 419)');
});

test('sessão expirada deixa aviso "Sua sessão expirou"', function () {
    ip('10.0.1.3');
    [, $sid] = req(null, fn() => newAuth()->attempt('maria', PW));
    req($sid, function () { $_SESSION['last_activity'] = time() - 99999; });
    [, $sid2] = req($sid, fn() => newAuth()->user());
    [$flash] = req($sid2, fn() => $_SESSION['flash'] ?? null);
    check($flash && strpos($flash['text'], 'expirou') !== false, 'flash de expiração ausente');
});

test('teto absoluto: sessão ativa mas com mais de 12h desde o login expira', function () use ($authCfg) {
    ip('10.0.1.4');
    [, $sid] = req(null, fn() => newAuth()->attempt('maria', PW));
    req($sid, function () use ($authCfg) {
        $_SESSION['login_at'] = time() - $authCfg['session_absolute'] - 60;
        $_SESSION['last_activity'] = time(); // usando agora mesmo
    });
    [$user] = req($sid, fn() => newAuth()->user());
    check($user === null, 'sessão além do teto absoluto deveria cair');
});

test('sessão dentro dos limites continua válida', function () {
    ip('10.0.1.5');
    [, $sid] = req(null, fn() => newAuth()->attempt('maria', PW));
    [$user] = req($sid, fn() => newAuth()->user());
    check($user !== null && $user['username'] === 'maria', 'sessão válida foi derrubada');
});

test('usuário apagado perde a sessão na hora, com aviso', function () use ($db) {
    ip('10.0.1.6');
    $setupAuth = newAuth();
    $setupAuth->createUser('temporario', PW, 'user');
    [, $sid] = req(null, fn() => newAuth()->attempt('temporario', PW));
    $db->exec("DELETE FROM users WHERE username = 'temporario'");
    [$user, $sid2] = req($sid, fn() => newAuth()->user());
    check($user === null, 'usuário apagado ainda logado');
    [$flash] = req($sid2, fn() => $_SESSION['flash'] ?? null);
    check($flash && strpos($flash['text'], 'encerrada') !== false, 'aviso de sessão encerrada ausente');
});

test('logout encerra a sessão', function () {
    ip('10.0.1.7');
    [, $sid] = req(null, fn() => newAuth()->attempt('maria', PW));
    req($sid, fn() => newAuth()->logout());
    [$user] = req($sid, fn() => newAuth()->user());
    check($user === null, 'sessão sobreviveu ao logout');
});

// --- 3. achado 4: enumeração por tempo ------------------------------------------------

test('login inexistente gasta o mesmo tempo de bcrypt que senha errada de login real', function () {
    $n = 8;
    $known = $unknown = 0.0;
    for ($i = 0; $i < $n; $i++) {
        ip("10.1.0.$i"); // IP diferente a cada tentativa: evita bloqueio
        $t = microtime(true);
        req(null, fn() => newAuth()->attempt('ana', 'errada-' . $i));
        $known += microtime(true) - $t;

        ip("10.2.0.$i");
        $t = microtime(true);
        req(null, fn() => newAuth()->attempt('nao_existe_' . $i, 'errada-' . $i));
        $unknown += microtime(true) - $t;
    }
    $known = $known / $n * 1000;
    $unknown = $unknown / $n * 1000;
    check($unknown > 15, sprintf('login inexistente só levou %.1f ms: password_verify não rodou', $unknown));
    check(abs($known - $unknown) < 30, sprintf('diferença de tempo %.1f ms (existente %.1f / inexistente %.1f)', abs($known - $unknown), $known, $unknown));
});

test('hash fictício tem o mesmo custo dos hashes reais', function () use ($db) {
    $dummy = password_get_info(Auth::DUMMY_HASH);
    $real = $db->query("SELECT password FROM users WHERE username = 'maria'")->fetchColumn();
    $realInfo = password_get_info($real);
    check($dummy['algoName'] === 'bcrypt' && $dummy['options']['cost'] === Auth::HASH_COST, 'DUMMY_HASH com custo diferente de HASH_COST');
    check($realInfo['options']['cost'] === Auth::HASH_COST, 'hash novo não usa HASH_COST');
    check(!password_needs_rehash($real, PASSWORD_BCRYPT, ['cost' => Auth::HASH_COST]), 'hash recém-criado pediria rehash');
});

test('hash antigo (PASSWORD_DEFAULT) ainda loga e é migrado se o custo mudar', function () use ($db) {
    ip('10.0.2.1');
    $db->prepare('UPDATE users SET password = ? WHERE username = ?')
       ->execute([password_hash(PW, PASSWORD_BCRYPT, ['cost' => 4]), 'ana']);
    [$res] = req(null, fn() => newAuth()->attempt('ana', PW));
    check($res[0] === true, 'hash antigo não logou');
    $now = $db->query("SELECT password FROM users WHERE username = 'ana'")->fetchColumn();
    check(password_get_info($now)['options']['cost'] === Auth::HASH_COST, 'hash não foi migrado para o custo atual');
});

// --- 4. achado 5: log -------------------------------------------------------------------

test('log de falha nunca contém o texto digitado quando o login não existe', function () use ($db) {
    ip('10.0.3.1');
    $typed = 'MinhaSenhaSecreta#2026';
    req(null, fn() => newAuth()->attempt($typed, 'qualquer'));
    $all = json_encode($db->query('SELECT * FROM logs')->fetchAll());
    check(strpos($all, 'MinhaSenhaSecreta') === false, 'senha digitada no campo usuário foi parar no log');
    $rows = logRows('LOGIN_FALHA');
    $hit = array_filter($rows, fn($r) => strpos($r['detalhe'], 'desconhecido#') === 0);
    check((bool) $hit, 'esperava rótulo desconhecido#<hash>');
});

test('log de falha de login real mantém o nome do usuário', function () {
    ip('10.0.3.2');
    req(null, fn() => newAuth()->attempt('maria', 'senha-errada'));
    $rows = array_filter(logRows('LOGIN_FALHA'), fn($r) => $r['detalhe'] === 'maria');
    check((bool) $rows, 'LOGIN_FALHA de "maria" não foi registrado');
});

test('bloqueio: 5 falhas bloqueiam (mesmo com a senha certa) e gera UM log por janela', function () use ($db) {
    ip('10.0.4.1');
    for ($i = 0; $i < 5; $i++) {
        [$r] = req(null, fn() => newAuth()->attempt('joao', 'errada'));
        check($r[0] === false && strpos($r[1], 'incorretos') !== false, "tentativa $i deveria falhar normalmente");
    }
    for ($i = 0; $i < 8; $i++) {
        [$r] = req(null, fn() => newAuth()->attempt('joao', $i === 3 ? PW : 'errada'));
        check($r[0] === false && strpos($r[1], 'Muitas tentativas') !== false, 'deveria estar bloqueado (inclusive com a senha certa)');
    }
    $n = count(array_filter(logRows('LOGIN_BLOQUEADO'), fn($r) => $r['ip'] === '10.0.4.1' && $r['detalhe'] === 'joao'));
    check($n === 1, "esperava 1 LOGIN_BLOQUEADO, havia $n");
});

test('bloqueio de login inexistente também é registrado uma vez, sem o texto digitado', function () {
    ip('10.0.4.2');
    for ($i = 0; $i < 12; $i++) {
        req(null, fn() => newAuth()->attempt('SegredoDigitado', 'x'));
    }
    $rows = array_filter(logRows('LOGIN_BLOQUEADO'), fn($r) => $r['ip'] === '10.0.4.2');
    check(count($rows) === 1, 'esperava 1 registro de bloqueio, havia ' . count($rows));
    check(strpos(json_encode(array_values($rows)), 'SegredoDigitado') === false, 'texto digitado vazou no log de bloqueio');
});

// --- 5. achado 3: configuração de sessão (processo separado) --------------------------------

function sessionIni(array $env): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/session_ini.php');
    $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $spec, $pipes, null, array_merge(getenv(), $env));
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return json_decode(trim($out), true) ?: [];
}

test('gc_maxlifetime acompanha session_lifetime (8h) e o cookie é HttpOnly/SameSite=Lax', function () use ($authCfg) {
    $r = sessionIni(['INTRANET_FORCE_HTTPS' => '0']);
    check(($r['gc'] ?? null) === (string) $authCfg['session_lifetime'], 'gc_maxlifetime = ' . json_encode($r['gc'] ?? null));
    check(($r['httponly'] ?? false) === true, 'cookie sem HttpOnly');
    check(($r['samesite'] ?? '') === 'Lax', 'cookie sem SameSite=Lax');
    check(($r['secure'] ?? true) === false, 'sem HTTPS o cookie não deveria ser Secure');
});

test('INTRANET_FORCE_HTTPS=1 liga cookie Secure (proxy que termina o TLS)', function () {
    $r = sessionIni(['INTRANET_FORCE_HTTPS' => '1']);
    check(($r['secure'] ?? false) === true, 'cookie deveria ser Secure com force_https');
    check(($r['https'] ?? false) === true, 'isHttps() deveria ser true');
});

// --- 6. achado 10: tela de login ------------------------------------------------------------

function renderLogin(bool $showChange): string
{
    ob_start();
    view('login', ['flash' => null, 'hasUsers' => true, 'showChange' => $showChange]);
    return ob_get_clean();
}

test('tela de login: alternadores são <button> focáveis (não <a> sem href)', function () {
    $html = renderLogin(false);
    check(substr_count($html, '<button type="button" class="link-action" data-toggle-forms>') === 2, 'esperava 2 botões link-action');
    check(strpos($html, '<a class="link-action"') === false, 'ainda existe <a class="link-action">');
});

test('?form=change abre o formulário de nova senha e esconde o de login', function () {
    $on = renderLogin(true);
    check(strpos($on, '<div id="formLogin" style="display:none">') !== false, 'formLogin deveria estar oculto');
    check(strpos($on, '<div id="formChange" style="display:block">') !== false, 'formChange deveria estar visível');
    $off = renderLogin(false);
    check(strpos($off, '<div id="formLogin">') !== false && strpos($off, '<div id="formChange">') !== false, 'estado padrão alterado');
});

// --- fim ---------------------------------------------------------------------------------------

$db = null;
rrm($tmp);
ob_end_clean();
finish();
