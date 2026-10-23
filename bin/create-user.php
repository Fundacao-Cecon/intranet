<?php
/**
 * Cria um usuário, ou redefine a senha de um existente (--reset).
 *
 *   php bin/create-user.php <login> [admin|user]     cria (padrão: user)
 *   php bin/create-user.php <login> --reset          redefine a senha
 *
 * A senha é lida da variável INTRANET_NEW_PASSWORD ou digitada no prompt
 * (nunca como argumento, para não ficar no histórico do shell).
 */

if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}

$config = require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Database;
use App\Logger;

$login = $argv[1] ?? '';
$arg2 = $argv[2] ?? 'user';
if ($login === '') {
    exit("Uso: php bin/create-user.php <login> [admin|user|--reset]\n");
}

$password = getenv('INTRANET_NEW_PASSWORD');
if ($password === false || $password === '') {
    fwrite(STDOUT, 'Senha (mín. ' . $config['auth']['min_password_length'] . ' caracteres; aparece na tela): ');
    $password = rtrim((string) fgets(STDIN), "\r\n");
}

$db = Database::connect($config['paths']['database']);
$logger = new Logger($db);
$auth = new Auth($db, $logger, $config['auth']);

if ($arg2 === '--reset') {
    list($ok, $msg) = $auth->resetPassword($login, $password);
    $logger->log('SENHA', "Redefinida via CLI: $login", 'CLI');
} else {
    list($ok, $msg) = $auth->createUser($login, $password, $arg2);
    if ($ok) {
        $logger->log('USER', "Criou via CLI: $login ($arg2)", 'CLI');
    }
}

echo ($ok ? 'OK: ' : 'ERRO: ') . $msg . "\n";
exit($ok ? 0 : 1);
