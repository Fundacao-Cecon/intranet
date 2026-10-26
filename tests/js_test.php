<?php
/**
 * Testes do JavaScript e de acessibilidade: sobe o sistema, salva o HTML REAL de cada tela e roda
 * `node --test tests/js/` (jsdom + axe-core) sobre essas páginas.
 *
 *   cd tests/js && npm install     (uma vez)
 *   php tests/js_test.php
 */

require __DIR__ . '/helpers.php';
$config = require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/http_client.php';

use App\Auth;
use App\Database;
use App\Logger;
use App\Services\FolderAccess;
use App\Services\Groups;

$node = trim((string) @shell_exec(DIRECTORY_SEPARATOR === '\\' ? 'where node 2>NUL' : 'command -v node'));
$node = $node ? strtok($node, "\r\n") : '';
if ($node === '') {
    fwrite(STDOUT, "Node.js não encontrado no PATH (necessário para os testes de JS).\n");
    exit(2);
}
if (!is_dir(__DIR__ . '/js/node_modules/jsdom') || !is_dir(__DIR__ . '/js/node_modules/axe-core')) {
    fwrite(STDOUT, "Dependências ausentes: rode  cd tests/js && npm install\n");
    exit(2);
}

$tmp = mktmp('intranet-js-');
register_shutdown_function(function () use ($tmp) { rrm($tmp); });
require __DIR__ . '/http_server.php';
const PW = 'Senha-Teste-123';

// --- dados --------------------------------------------------------------------------------
$storage = "$tmp/storage";
foreach (["$storage/uploads/Norma Zero/Sub", "$storage/uploads/Imagens", "$storage/uploads/Restrita", "$storage/trash", "$tmp/sessions", "$tmp/html"] as $d) { mkdir($d, 0775, true); }
file_put_contents("$storage/uploads/leia-me.txt", 'oi');
file_put_contents("$storage/uploads/Norma Zero/protocolo.pdf", "%PDF-1.4\nx");
file_put_contents("$storage/uploads/Imagens/foto.png", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
file_put_contents("$storage/trash/velho.txt", 'lixo');
$db = Database::connect("$storage/database.db");
$auth = new Auth($db, new Logger($db), $config['auth']);
$auth->createUser('js_admin', PW, 'admin');
$auth->createUser('js_user', PW, 'user');
$auth->createUser('js_outro', PW, 'user');
// "Restrita": liberada só para js_outro e para o grupo "Equipe" (js_user fica de fora e vê o cadeado).
$ids = array_column($db->query("SELECT username, id FROM users")->fetchAll(), 'id', 'username');
(new Groups($db))->create('Equipe');
$gid = (int) $db->query("SELECT id FROM groups WHERE name = 'Equipe'")->fetchColumn();
(new FolderAccess($db))->setRule('Restrita', [(int) $ids['js_outro']], [$gid]);

// --- páginas -----------------------------------------------------------------------------------
$base = startServer($storage);
$save = function (string $name, string $html) use ($tmp) { file_put_contents("$tmp/html/$name.html", $html); };

$anon = new Client($base);
$save('login', $anon->get('/')->body);
$save('login_change', $anon->get('/?form=change')->body);

$admin = new Client($base);
$admin->follow($admin->post('/', ['action' => 'login', 'user' => 'js_admin', 'pass' => PW]));
$save('admin', $admin->get('/')->body);
$save('trash', $admin->get('/index.php?mode=trash')->body);
$save('images', $admin->get('/index.php?dir=Imagens')->body);
$save('sub', $admin->get('/index.php?dir=' . rawurlencode('Norma Zero/Sub'))->body);

$user = new Client($base);
$user->follow($user->post('/', ['action' => 'login', 'user' => 'js_user', 'pass' => PW]));
$save('user', $user->get('/')->body);

// Página com um flash HOSTIL, para provar que o JS só escreve texto (nunca HTML).
$denied = $user->follow($user->post('/', ['action' => 'mkdir', 'nome_pasta' => 'x']));
$flash = str_replace('data-flash-text="Acesso negado."', 'data-flash-text="&lt;img src=x onerror=alert(1)&gt;"', $denied->body, $n);
check($n === 1, 'não achei o data-flash-text para substituir');
$save('flash', $flash);

// --- node --test ----------------------------------------------------------------------------
fwrite(STDOUT, "Páginas salvas: " . implode(', ', array_map('basename', glob("$tmp/html/*.html"))) . "\n\n");
$cmd = array_merge([$node, '--test', '--test-reporter=spec'], glob(__DIR__ . '/js/*.test.js'));
$p = proc_open($cmd, [1 => STDOUT, 2 => STDERR], $pipes, __DIR__ . '/js', array_merge(getenv(), ['HTML_DIR' => "$tmp/html"]));
$code = proc_close($p);
exit($code === 0 ? 0 : 1);
