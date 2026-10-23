<?php
/**
 * Testes da Fase 2 da ADR-0005 (docs/decisoes/0005-endurecimento-do-login.md) escritos ANTES do
 * código. Cada um está como todo(): enquanto a funcionalidade não existir aparece "pend" e não
 * reprova a suíte. Ao implementar, o teste passa a avisar "PASSOU": troque todo() por test().
 *
 *   php tests/pending_phase2_test.php
 */

require __DIR__ . '/helpers.php';
$config = require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Database;
use App\Logger;

ob_start();
$tmp = mktmp('intranet-p2-');
mkdir("$tmp/sessions", 0775, true);
ini_set('session.save_path', "$tmp/sessions");
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.cache_limiter', '');
session_start();
register_shutdown_function(function () use ($tmp) { rrm($tmp); });

const PW = 'Senha-Teste-123';
$db = Database::connect("$tmp/database.db");
$logger = new Logger($db);
$mk = function () use ($db, $logger, $config) { return new Auth($db, $logger, $config['auth']); };
$seed = $mk();
foreach (['joao' => 'admin', 'maria' => 'user', 'ana' => 'user', 'adm' => 'admin'] as $u => $r) { $seed->createUser($u, PW, $r); }
foreach (['u1', 'u2', 'u3'] as $u) { $seed->createUser($u, PW, 'user'); }

$ip = function (string $a) { $_SERVER['REMOTE_ADDR'] = $a; };

suite('ADR-0005 Fase 2 — login (escritos antes do código)');

todo('limite por IP: 31 falhas de logins diferentes no mesmo IP bloqueiam o IP (a 32ª, certa, também)', function () use ($mk, $ip, $db) {
    $db->exec('DELETE FROM login_attempts');
    $ip('10.50.0.1');
    $a = $mk();
    for ($i = 0; $i < 31; $i++) { $a->attempt('alvo' . $i, 'errada'); }   // spraying: cada login tem seu próprio orçamento
    [$ok, $msg] = $a->attempt('maria', PW);
    check($ok === false && strpos($msg, 'Muitas tentativas') !== false, 'o IP continua livre para entrar depois de 31 falhas');
    $ip('10.50.0.2');
    check($mk()->attempt('maria', PW)[0] === true, 'outro IP não pode ser afetado');
});

todo('limite por usuário NÃO vira DoS: 25 falhas de IPs diferentes contra "joao" geram LOGIN_ALERTA, e o dono ainda entra', function () use ($mk, $ip, $db) {
    $db->exec('DELETE FROM login_attempts');
    for ($i = 0; $i < 25; $i++) { $ip("10.51.0.$i"); $mk()->attempt('joao', 'errada'); }
    $n = (int) $db->query("SELECT COUNT(*) FROM logs WHERE acao='LOGIN_ALERTA'")->fetchColumn();
    check($n >= 1, 'nenhum LOGIN_ALERTA registrado');
    $ip('10.51.9.9');
    check($mk()->attempt('joao', PW)[0] === true, 'o dono da conta foi bloqueado por ataque vindo de outros IPs');
});

todo('corrida: 30 tentativas erradas simultâneas gravam no máximo 6 falhas (o resto já nasce bloqueado)', function () use ($tmp) {
    $db = Database::connect("$tmp/database.db");
    $db->exec('DELETE FROM login_attempts');
    $res = parallel(30, function ($i, $flag) use ($tmp) { return ['login', $tmp, 'ana', 'errada', '10.52.0.1', $flag]; });
    $outs = array_count_values(array_column($res, 'out'));
    $rows = (int) $db->query("SELECT COUNT(*) FROM login_attempts WHERE ip='10.52.0.1'")->fetchColumn();
    check($rows <= 6, "$rows falhas gravadas (esperado ≤ 6): " . json_encode($outs));
});

todo('auth_version: trocar a senha derruba as OUTRAS sessões do usuário e mantém a atual', function () use ($mk, $ip, $db) {
    $ip('10.53.0.1');
    $mk()->attempt('maria', PW);  $sidA = session_id();  session_write_close();
    session_id(bin2hex(random_bytes(16))); session_start();
    $mk()->attempt('maria', PW);  $sidB = session_id();  session_write_close();

    session_id($sidA); session_start();
    check($mk()->changePassword('maria', PW, 'NovaSenha-Segura-77')[0] === true, 'troca de senha');
    $sidA2 = session_id();  $stillA = $mk()->user() !== null;  session_write_close();
    check($stillA, 'a sessão que trocou a senha deveria continuar logada');

    session_id($sidB); session_start();
    $bAlive = $mk()->user() !== null;  session_write_close();
    check($bAlive === false, 'a OUTRA sessão do mesmo usuário continua logada após a troca de senha');
    void($sidA2);
    session_id(bin2hex(random_bytes(16))); session_start();
});

todo('senha temporária: bin/create-user.php --reset liga must_change_password e o login manda trocar antes de tudo', function () use ($db, $mk) {
    $cols = array_column($db->query('PRAGMA table_info(users)')->fetchAll(), 'name');
    check(in_array('must_change_password', $cols, true), 'coluna users.must_change_password não existe');
    $mk()->resetPassword('ana', 'Temporaria-123');
    $flag = (int) $db->query("SELECT must_change_password FROM users WHERE username='ana'")->fetchColumn();
    check($flag === 1, 'reset administrativo deveria marcar a senha como temporária');
});

todo('política de senha: 12345678, password, igual ao login e qwertyui são recusadas; "Tr0ca-Segura-2026" é aceita', function () use ($mk) {
    $a = $mk();
    foreach (['12345678', 'password', 'qwertyui', 'abcdefgh1234'] as $fraca) {
        check($a->createUser('novo_' . bin2hex(random_bytes(2)), $fraca, 'user')[0] === false, "aceitou a senha fraca: $fraca");
    }
    check($a->createUser('igual-ao-login', 'igual-ao-login', 'user')[0] === false, 'aceitou senha igual ao login');
    check($a->createUser('forte_ok', 'Tr0ca-Segura-2026', 'user')[0] === true, 'recusou uma senha forte');
});

todo('logins sem diferença de maiúsculas: criar "Adm" quando existe "adm" é recusado; entrar como "ADM" entra em "adm"', function () use ($mk, $ip) {
    check($mk()->createUser('Adm', PW, 'user')[0] === false, 'criou "Adm" ao lado de "adm"');
    $ip('10.54.0.1');
    [$ok] = $mk()->attempt('ADM', PW);
    check($ok === true, 'login "ADM" não entrou na conta "adm"');
});

finish();
