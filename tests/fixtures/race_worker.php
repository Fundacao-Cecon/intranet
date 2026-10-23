<?php
/**
 * Trabalhador dos testes de concorrência (tests/concurrency_test.php). Uso:
 *
 *   php race_worker.php upload  <pasta> <nome> <id> <arquivo-largada> [legacy]
 *   php race_worker.php meta    <storage> <id> <iteracoes> <arquivo-largada>
 *   php race_worker.php delete  <storage> <id-quem-age> <id-alvo> <arquivo-largada>
 *   php race_worker.php login   <storage> <usuario> <senha> <ip> <arquivo-largada>
 *
 * Todos esperam o arquivo de largada existir para começarem juntos.
 */

require dirname(__DIR__, 2) . '/src/bootstrap.php';
$config = require dirname(__DIR__, 2) . '/config/config.php';

use App\Auth;
use App\Database;
use App\Logger;
use App\Services\FileMeta;
use App\Services\FileStorage;

function waitStart(string $flag): void
{
    $t = microtime(true);
    while (!file_exists($flag)) {
        usleep(500);
        if (microtime(true) - $t > 30) { fwrite(STDERR, "timeout na largada\n"); exit(3); }
    }
}

$mode = $argv[1] ?? '';

if ($mode === 'upload') {
    [, , $dir, $name, $id, $flag] = $argv;
    $legacy = ($argv[6] ?? '') === 'legacy';
    $fs = new FileStorage($dir, $dir . '-trash', $config['hidden_names']);
    $abs = $fs->root(FileStorage::AREA_FILES);
    waitStart($flag);
    if ($legacy) {
        $final = $fs->uniqueName($abs, $name);   // comportamento antigo: só escolhe o nome
    } else {
        $final = $fs->reserveName($abs, $name);  // comportamento novo: reserva atomicamente
    }
    usleep(40000);                                // a cópia de um arquivo grande leva tempo
    file_put_contents($abs . $final, "conteudo-$id");
    echo $final;
    exit(0);
}

if ($mode === 'meta') {
    [, , $storage, $id, $n, $flag] = $argv;
    $db = Database::connect($storage . '/database.db');
    $meta = new FileMeta($db);
    $log = new Logger($db);
    waitStart($flag);
    try {
        for ($i = 0; $i < (int) $n; $i++) {
            $meta->created("w$id/arquivo-$i.txt", "worker$id");
            $log->log('CARGA', "w$id-$i", "worker$id");
            $db->prepare('INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, ?)')->execute(["10.0.0.$id", "u$id", time()]);
        }
    } catch (Throwable $e) {
        fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
        exit(1);
    }
    exit(0);
}

if ($mode === 'delete') {
    [, , $storage, $actor, $target, $flag] = $argv;
    $db = Database::connect($storage . '/database.db');
    $logger = new Logger($db);
    $auth = new Auth($db, $logger, $config['auth']);
    $_SESSION = ['user_id' => (int) $actor, 'last_activity' => time(), 'login_at' => time()];
    waitStart($flag);
    [$ok, $msg] = $auth->deleteUser((int) $target);
    echo $ok ? 'ok' : 'recusado';
    exit(0);
}

if ($mode === 'login') {
    [, , $storage, $user, $pass, $ip, $flag] = $argv;
    $db = Database::connect($storage . '/database.db');
    $auth = new Auth($db, new Logger($db), $config['auth']);
    $_SERVER['REMOTE_ADDR'] = $ip;
    ob_start();
    session_save_path(sys_get_temp_dir());
    session_id(bin2hex(random_bytes(16)));
    session_start();
    waitStart($flag);
    [$ok, $msg] = $auth->attempt($user, $pass);
    echo $ok ? 'ok' : (strpos($msg, 'Muitas tentativas') !== false ? 'bloqueado' : 'falha');
    exit(0);
}

fwrite(STDERR, "modo desconhecido\n");
exit(2);
