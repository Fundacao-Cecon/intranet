<?php
/**
 * Backup e restauração: faz backup com o sistema LIGADO (escritor concorrente no banco),
 * confere, restaura em pasta nova, sobe o sistema na pasta restaurada e compara tudo.
 *
 *   php tests/backup_test.php
 */

require __DIR__ . '/helpers.php';
$config = require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/http_client.php';

use App\Auth;
use App\Backup;
use App\Database;
use App\Logger;
use App\Services\FileMeta;

$tmp = mktmp('intranet-bkp-');
register_shutdown_function(function () use ($tmp) {
    foreach ($GLOBALS['__servers'] ?? [] as $p) { if (is_resource($p)) { proc_terminate($p); proc_close($p); } }
    rrm($tmp);
});
require __DIR__ . '/http_server.php';
const PW = 'Senha-Teste-123';

function runCli(string $script, array $args): array
{
    $p = proc_open(array_merge([PHP_BINARY], coverageFlags(), [dirname(__DIR__) . '/bin/' . $script], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
        null, array_merge(getenv(), $GLOBALS['cliEnv'] ?? []));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    return [proc_close($p), $out];
}

/** Retrata a árvore: caminho => sha256. */
function tree(string $dir): array
{
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile()) {
            $out[ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen(str_replace('\\', '/', $dir))), '/')] = hash_file('sha256', $f->getPathname());
        }
    }
    ksort($out);
    return $out;
}

// --- dados "de produção" ---------------------------------------------------------------------
$prod = "$tmp/prod";
mkdir("$tmp/sessions", 0775, true); // $tmp/sessions: session.save_path dos servidores de teste
foreach (["$prod/uploads/Norma Zero", "$prod/uploads/Relatórios 2026/Interna", "$prod/trash", "$prod/logs"] as $d) { mkdir($d, 0775, true); }
file_put_contents("$prod/uploads/Norma Zero/Norma 001 - Ação.pdf", "%PDF-1.4\n" . random_bytes(20000));
file_put_contents("$prod/uploads/Relatórios 2026/Interna/ç ã é.txt", 'acentos');
file_put_contents("$prod/uploads/leia-me.txt", 'oi');
file_put_contents("$prod/trash/velho.txt", 'lixo');
file_put_contents("$prod/logs/php-error.log", 'regenerável');

$db = Database::connect("$prod/database.db");
$auth = new Auth($db, new Logger($db), $config['auth']);
$auth->createUser('chefe', PW, 'admin');
$auth->createUser('maria', PW, 'user');
$meta = new FileMeta($db);
$meta->created('Norma Zero/Norma 001 - Ação.pdf', 'chefe');
$meta->created('leia-me.txt', 'maria');
$meta->rememberOrigin('.lixeira/velho.txt', 'Norma Zero/velho.txt');
$cliEnv = ['INTRANET_STORAGE' => $prod];

// =====================================================================================
suite('Backup com o sistema ligado');
// =====================================================================================

$bkp = "$tmp/backups/b1";
$antes = tree($prod);

test('backup concorrente com escritas no banco: termina, passa na conferência e o banco é consistente', function () use ($tmp, $prod, $bkp, $cliEnv) {
    // escritor em segundo plano (largada imediata) enquanto o backup roda
    $flag = "$tmp/go-bkp";
    touch($flag);
    $w = proc_open([PHP_BINARY, __DIR__ . '/fixtures/race_worker.php', 'meta', $prod, '7', '250', $flag], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $wp);
    [$code, $out] = runCli('backup.php', [$bkp]);
    $wout = stream_get_contents($wp[1]) . stream_get_contents($wp[2]);
    $wcode = proc_close($w);
    check($wcode === 0, 'escritor falhou durante o backup: ' . $wout);
    check($code === 0 && strpos($out, 'OK:') === 0, "backup: código $code — $out");
    check(strpos($out, 'Conferido contra o manifest.json') !== false, 'sem a confirmação de conferência');
    check(Backup::verify($bkp) === [], 'verify: ' . json_encode(Backup::verify($bkp)));
});

test('o backup contém banco, uploads (com acentos) e lixeira; os logs ficam de fora', function () use ($bkp) {
    foreach (['database.db', 'manifest.json', 'uploads/Norma Zero/Norma 001 - Ação.pdf', 'uploads/Relatórios 2026/Interna/ç ã é.txt', 'uploads/leia-me.txt', 'trash/velho.txt'] as $p) {
        check(file_exists("$bkp/$p"), "faltando no backup: $p");
    }
    check(!file_exists("$bkp/logs") && !file_exists("$bkp/php-error.log"), 'logs entraram no backup');
    $m = json_decode(file_get_contents("$bkp/manifest.json"), true);
    check($m['counts']['users'] === 2 && $m['counts']['files_meta'] >= 2, 'contagens do manifest: ' . json_encode($m['counts']));
    check(count($m['files']) === 5 && $m['bytes'] > 20000, 'inventário do manifest: ' . count($m['files']) . ' arquivos');
});

test('o backup não altera a produção (arquivos intactos)', function () use ($prod, $antes) {
    $depois = tree($prod);
    foreach ($antes as $p => $h) {
        if ($p === 'database.db') { continue; }  // o banco recebeu escritas do escritor concorrente
        check(($depois[$p] ?? null) === $h, "mudou na produção: $p");
    }
});

test('backup recusa destino existente', function () use ($bkp) {
    [$code, $out] = runCli('backup.php', [$bkp]);
    check($code === 1 && strpos($out, 'já existe') !== false, "código $code: $out");
});

test('verify detecta arquivo alterado, removido e acrescentado', function () use ($tmp, $bkp) {
    $c = "$tmp/backups/corrompido";
    mkdir($c, 0775, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bkp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $f) {
        $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen(str_replace('\\', '/', $bkp)));
        $f->isDir() ? @mkdir($c . $rel, 0775, true) : copy($f->getPathname(), $c . $rel);
    }
    check(Backup::verify($c) === [], 'cópia fiel deveria passar');

    file_put_contents("$c/uploads/leia-me.txt", 'ADULTERADO');
    check(count(array_filter(Backup::verify($c), function ($p) { return strpos($p, 'leia-me.txt') !== false; })) === 1, 'alteração não detectada');
    unlink("$c/trash/velho.txt");
    check(count(array_filter(Backup::verify($c), function ($p) { return strpos($p, 'faltando') === 0; })) === 1, 'remoção não detectada');
    file_put_contents("$c/uploads/intruso.txt", 'x');
    check(count(array_filter(Backup::verify($c), function ($p) { return strpos($p, 'fora do manifest') !== false; })) === 1, 'arquivo extra não detectado');
    [$code, $out] = runCli('backup.php', ['--verify', $c]);
    check($code === 1 && strpos($out, 'PROBLEMAS') !== false, 'CLI --verify deveria falhar: ' . $out);
    [$code, $out] = runCli('backup.php', ['--verify', $GLOBALS['bkp']]);
    check($code === 0, 'CLI --verify do backup bom deveria passar: ' . $out);
});

test('verify recusa backup sem manifest e com banco corrompido', function () use ($tmp) {
    mkdir("$tmp/backups/vazio", 0775, true);
    check(Backup::verify("$tmp/backups/vazio") !== [], 'pasta sem manifest');
    $bad = "$tmp/backups/db-ruim";
    mkdir($bad, 0775, true);
    file_put_contents("$bad/database.db", str_repeat('NAO-E-SQLITE', 500));
    file_put_contents("$bad/manifest.json", json_encode(['files' => [['p' => 'database.db', 'size' => filesize("$bad/database.db"), 'sha256' => hash_file('sha256', "$bad/database.db")]]]));
    $p = Backup::verify($bad);
    check($p !== [] && count(array_filter($p, function ($x) { return strpos($x, 'banco') !== false; })) >= 1, 'banco inválido não detectado: ' . json_encode($p));
});

// =====================================================================================
suite('Restauração');
// =====================================================================================

$restored = "$tmp/restaurado";

test('restaura em pasta nova: árvore idêntica ao backup (menos o manifest) e banco íntegro', function () use ($bkp, $restored) {
    [$code, $out] = runCli('restore.php', [$bkp, $restored]);
    check($code === 0 && strpos($out, 'OK:') === 0, "restore: código $code — $out");
    $orig = tree($bkp);
    unset($orig['manifest.json']);
    $rest = tree($restored);
    check($rest === $orig, 'diferenças: ' . json_encode(array_diff_assoc($orig, $rest)) . ' / ' . json_encode(array_diff_assoc($rest, $orig)));
    check(is_dir("$restored/logs"), 'pasta de logs deveria ser recriada vazia');
});

test('restore recusa destino com conteúdo, backup adulterado e não sobrescreve nada', function () use ($tmp, $bkp, $restored) {
    [$code, $out] = runCli('restore.php', [$bkp, $restored]);
    check($code === 1 && strpos($out, 'vazio') !== false, "destino cheio: $code $out");
    $c = "$tmp/backups/corrompido";   // adulterado no teste anterior
    [$code, $out] = runCli('restore.php', [$c, "$tmp/nunca-criado"]);
    check($code === 1 && strpos($out, 'nada foi restaurado') !== false && !file_exists("$tmp/nunca-criado/database.db"), "backup ruim: $code $out");
});

// =====================================================================================
suite('Sistema ligado na pasta restaurada');
// =====================================================================================

test('login, listagem, autoria e download funcionam idênticos ao original', function () use ($restored) {
    $b = startServer($restored);
    $c = new Client($b);
    $r = $c->post('/', ['action' => 'login', 'user' => 'chefe', 'pass' => PW]);
    check($r->status === 302, 'login do chefe no restaurado');
    $home = $c->follow($r);
    check(strpos($home->body, '>Norma Zero<') !== false && strpos($home->body, '>leia-me.txt<') !== false, 'listagem');
    check(strpos($home->body, '<small>maria</small>') !== false, 'autoria "maria" perdida');
    $dir = $c->get('/index.php?dir=Norma+Zero');
    check(strpos($dir->body, '<small>chefe</small>') !== false, 'autoria "chefe" perdida');
    $dl = $c->get('/index.php?' . http_build_query(['action' => 'download', 'dir' => 'Relatórios 2026/Interna', 'file' => 'ç ã é.txt']));
    check($dl->status === 200 && $dl->body === 'acentos', 'download de nome acentuado');
    $trash = $c->get('/index.php?mode=trash');
    check(strpos($trash->body, 'velho.txt') !== false, 'lixeira restaurada');
    $u = new Client($b);
    check($u->post('/', ['action' => 'login', 'user' => 'maria', 'pass' => PW])->status === 302, 'login da maria');
});

test('restaurar a lixeira preserva a origem: restaurar velho.txt volta para "Norma Zero"', function () use ($restored) {
    $b = startServer($restored);
    $c = new Client($b);
    $c->follow($c->post('/', ['action' => 'login', 'user' => 'chefe', 'pass' => PW]));
    $c->post('/index.php?mode=trash', ['action' => 'restore', 'item' => 'velho.txt']);
    check(is_file("$restored/uploads/Norma Zero/velho.txt"), 'origem da lixeira perdida no backup/restore');
});

finish();
