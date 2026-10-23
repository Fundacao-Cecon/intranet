<?php
/**
 * Concorrência: vários processos PHP ao mesmo tempo sobre o mesmo storage.
 * (O servidor embutido do PHP no Windows atende uma requisição por vez, então a
 * concorrência é exercitada com processos de verdade, não por HTTP.)
 *
 *   php tests/concurrency_test.php
 */

require __DIR__ . '/helpers.php';
$config = require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Database;
use App\Logger;
use App\Services\FileStorage;

$tmp = mktmp('intranet-conc-');
register_shutdown_function(function () use ($tmp) { rrm($tmp); });
const PW = 'Senha-Teste-123';

// =====================================================================================
suite('Concorrência — nomes no upload');
// =====================================================================================

test('8 uploads simultâneos com o MESMO nome: nenhum sobrescreve o outro (reserva atômica)', function () use ($tmp) {
    $dir = "$tmp/uploads-a";
    mkdir($dir, 0775, true);
    $res = parallel(8, function ($i, $flag) use ($dir) { return ['upload', $dir, 'relatorio.pdf', (string) $i, $flag]; });

    $names = [];
    foreach ($res as $i => $r) {
        check($r['code'] === 0, "worker $i falhou: " . $r['err']);
        $names[] = $r['out'];
    }
    check(count(array_unique($names)) === 8, 'nomes repetidos entre processos: ' . json_encode($names));

    $contents = [];
    foreach (glob("$dir/*") as $f) { $contents[] = file_get_contents($f); }
    sort($contents);
    $esperado = [];
    for ($i = 0; $i < 8; $i++) { $esperado[] = "conteudo-$i"; }
    sort($esperado);
    check($contents === $esperado, 'arquivos perdidos/sobrescritos: ' . json_encode($contents));
    check(is_file("$dir/relatorio.pdf") && is_file("$dir/relatorio (1).pdf") && is_file("$dir/relatorio (7).pdf"), 'sequência (1)…(7) incompleta');
});

test('reserveName: não reaproveita nome existente, pula buracos e respeita extensão', function () use ($config, $tmp) {
    $dir = "$tmp/uploads-b";
    mkdir($dir, 0775, true);
    file_put_contents("$dir/a.txt", 'x');
    file_put_contents("$dir/a (2).txt", 'x');
    $fs = new FileStorage($dir, "$dir-t", $config['hidden_names']);
    $abs = $fs->root('files');
    check($fs->reserveName($abs, 'a.txt') === 'a (1).txt', 'primeira lacuna');
    check($fs->reserveName($abs, 'a.txt') === 'a (3).txt', 'segue depois do (2)');
    check(is_file("$dir/a (1).txt") && filesize("$dir/a (1).txt") === 0, 'o nome deveria ficar reservado (arquivo vazio)');
    check($fs->reserveName($abs, '???.pdf') === 'arquivo.pdf', 'base vazia');
    check($fs->reserveName($abs, 'sem-extensao') === 'sem-extensao', 'sem extensão');
});

// =====================================================================================
suite('Concorrência — SQLite com vários escritores');
// =====================================================================================

test('8 processos gravando metadados, logs e tentativas ao mesmo tempo: sem "database is locked" e sem perda', function () use ($tmp) {
    $st = "$tmp/db-storage";
    mkdir($st, 0775, true);
    Database::connect("$st/database.db");
    $workers = 8;
    $iters = 40;
    $res = parallel($workers, function ($i, $flag) use ($st, $iters) { return ['meta', $st, (string) $i, (string) $iters, $flag]; });
    foreach ($res as $i => $r) {
        check($r['code'] === 0, "worker $i: " . trim($r['err']));
    }
    $db = Database::connect("$st/database.db");
    $n = function ($sql) use ($db) { return (int) $db->query($sql)->fetchColumn(); };
    check($n("SELECT COUNT(*) FROM files_meta WHERE filepath LIKE 'w%/arquivo-%'") === $workers * $iters, 'files_meta: ' . $n('SELECT COUNT(*) FROM files_meta'));
    check($n("SELECT COUNT(*) FROM logs WHERE acao='CARGA'") === $workers * $iters, 'logs perdidos');
    check($n("SELECT COUNT(*) FROM login_attempts") === $workers * $iters, 'login_attempts perdidos');
    check($db->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'banco corrompido');
});

// =====================================================================================
suite('Concorrência — usuários');
// =====================================================================================

test('dois admins se excluindo ao mesmo tempo: sempre sobra pelo menos um admin', function () use ($tmp, $config) {
    $st = "$tmp/del-storage";
    mkdir($st, 0775, true);
    $db = Database::connect("$st/database.db");
    $auth = new Auth($db, new Logger($db), $config['auth']);
    $zeroAdmins = 0;
    $rounds = 15;
    for ($r = 0; $r < $rounds; $r++) {
        $db->exec('DELETE FROM users');
        $auth->createUser('adm_a', PW, 'admin');
        $auth->createUser('adm_b', PW, 'admin');
        $a = (int) $db->query("SELECT id FROM users WHERE username='adm_a'")->fetchColumn();
        $b = (int) $db->query("SELECT id FROM users WHERE username='adm_b'")->fetchColumn();
        parallel(2, function ($i, $flag) use ($st, $a, $b) { return $i === 0 ? ['delete', $st, (string) $a, (string) $b, $flag] : ['delete', $st, (string) $b, (string) $a, $flag]; });
        if ((int) $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() === 0) {
            $zeroAdmins++;
        }
    }
    check($zeroAdmins === 0, "em $zeroAdmins de $rounds rodadas o sistema ficou SEM nenhum admin");
});

finish();
