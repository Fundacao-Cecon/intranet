<?php
/**
 * Testes unitários: FileStorage, FileMeta, Auth (usuários, senha, limites), Csrf,
 * Logger e Database. Sem navegador, sem servidor. Uso:  php tests/unit_test.php
 */

require __DIR__ . '/helpers.php';
$config = require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Logger;
use App\Services\FileMeta;
use App\Services\FileStorage;

ob_start(); // evita "headers already sent" ao usar sessões na CLI

$tmp = mktmp();
mkdir("$tmp/sessions", 0775, true);
ini_set('session.save_path', "$tmp/sessions");
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.cache_limiter', '');
session_start();
register_shutdown_function(function () use ($tmp) { rrm($tmp); });

// =====================================================================================
suite('FileStorage — caminhos');
// =====================================================================================

mkdir("$tmp/uploads/Docs/Sub", 0775, true);
mkdir("$tmp/uploads2", 0775, true);          // "irmã" com mesmo prefixo
mkdir("$tmp/trash", 0775, true);
file_put_contents("$tmp/segredo.txt", 'fora da raiz');
$fs = new FileStorage("$tmp/uploads", "$tmp/trash", $config['hidden_names']);
$root = $fs->root(FileStorage::AREA_FILES);

test('resolve: pasta vazia, ponto e barras caem na raiz', function () use ($fs, $root) {
    foreach (['', '.', './', '/', '//', ' '] as $in) {
        [$abs, $clean] = $fs->resolve('files', $in);
        check($abs === $root && $clean === '', 'entrada ' . json_encode($in) . ' => ' . json_encode([$abs, $clean]));
    }
});

test('resolve: subpastas válidas, com barras sobrando e barra invertida', function () use ($fs, $root) {
    foreach (['Docs', 'Docs/', '/Docs/', 'Docs//Sub', 'Docs\\Sub', './Docs/./Sub/'] as $in) {
        [$abs, $clean] = $fs->resolve('files', $in);
        check($clean !== '' && strpos($abs, $root) === 0 && substr($abs, -1) === '/', 'entrada ' . json_encode($in) . ' => ' . json_encode([$abs, $clean]));
    }
    check($fs->resolve('files', 'Docs//Sub')[1] === 'Docs/Sub', 'normalização de Docs//Sub');
});

test('resolve: tentativas de sair da raiz caem na raiz', function () use ($fs, $root) {
    $evil = ['..', '../', '../..', '....//', '..%2f', 'Docs/..', 'Docs/../..', 'Docs/Sub/../../..', '..\\', 'Docs\\..\\..',
             '../uploads2', '../uploads2/', 'Docs/../../uploads2', "Docs\0", "..\0", '/etc', 'C:\\Windows', 'C:/Windows', '\\\\servidor\\share',
             '../segredo.txt', '../../'];
    foreach ($evil as $in) {
        [$abs, $clean] = $fs->resolve('files', $in);
        check($abs === $root && $clean === '', 'escapou com ' . json_encode($in) . ' => ' . json_encode([$abs, $clean]));
    }
});

test('resolve: pasta inexistente ou arquivo não vira pasta', function () use ($fs, $root, $tmp) {
    file_put_contents("$tmp/uploads/arquivo.txt", 'x');
    foreach (['nao-existe', 'Docs/nao-existe', 'arquivo.txt'] as $in) {
        check($fs->resolve('files', $in)[0] === $root, "$in deveria cair na raiz");
    }
});

test('resolve: link simbólico que aponta para fora da raiz é recusado', function () use ($fs, $root, $tmp) {
    $link = "$tmp/uploads/saida";
    if (!@symlink($tmp, $link)) {
        fwrite(STDOUT, "        (pulado: sem permissão para criar symlink neste sistema)\n");
        return;
    }
    check($fs->resolve('files', 'saida')[0] === $root, 'symlink para fora foi aceito');
    @unlink($link);
});

test('resolve: lixeira e arquivos são raízes independentes', function () use ($fs) {
    check($fs->resolve('trash', 'Docs')[0] === $fs->root('trash'), 'Docs não existe na lixeira');
    check($fs->root('files') !== $fs->root('trash'), 'raízes iguais');
});

// =====================================================================================
suite('FileStorage — nomes');
// =====================================================================================

test('safeItem: só o último segmento; vazio/ponto/nulo viram ""', function () use ($fs) {
    $cases = ['a.txt' => 'a.txt', '../a.txt' => 'a.txt', 'x/y/z.pdf' => 'z.pdf', 'x\\y.pdf' => 'y.pdf',
              '' => '', '.' => '', '..' => '', '../' => '', "a\0b" => '', '/' => ''];
    foreach ($cases as $in => $want) {
        $got = $fs->safeItem($in);
        check($got === $want, 'safeItem(' . json_encode($in) . ") = " . json_encode($got) . ', esperado ' . json_encode($want));
    }
    foreach ([null, ['x'], ['a' => ['b']], 123.5, true] as $odd) {
        check($fs->safeItem($odd) === '' || is_string($fs->safeItem($odd)), 'tipo estranho quebrou: ' . json_encode($odd));
    }
    check($fs->safeItem(['x']) === '' && $fs->safeItem(null) === '', 'array/null deveriam virar vazio');
});

test('cleanName: tira símbolos e travessias, mantém letras acentuadas, números, espaço - _ .', function () use ($fs) {
    $cases = ['Relatório 2026 - final.pdf' => 'Relatório 2026 - final.pdf', 'a/b' => 'ab', 'a\\b' => 'ab', '..' => '', '....' => '',
              'a<b>c|d?.txt' => 'abcd.txt', "x'y\"z" => 'xyz', 'ç ã é' => 'ç ã é', '' => '', '   ' => '   '];
    foreach ($cases as $in => $want) {
        $got = $fs->cleanName($in);
        check($got === $want, 'cleanName(' . json_encode($in, JSON_UNESCAPED_UNICODE) . ') = ' . json_encode($got, JSON_UNESCAPED_UNICODE));
    }
});

test('uniqueName: sufixo (1), (2)...; base vazia vira "arquivo"; extensão preservada', function () use ($fs, $tmp) {
    $d = "$tmp/uploads/unico/";
    mkdir($d, 0775, true);
    check($fs->uniqueName($d, 'novo.pdf') === 'novo.pdf', 'nome livre deveria ficar igual');
    file_put_contents($d . 'novo.pdf', 'x');
    check($fs->uniqueName($d, 'novo.pdf') === 'novo (1).pdf', 'primeira colisão');
    file_put_contents($d . 'novo (1).pdf', 'x');
    check($fs->uniqueName($d, 'novo.pdf') === 'novo (2).pdf', 'segunda colisão');
    check($fs->uniqueName($d, '???.txt') === 'arquivo.txt', 'base só de símbolos');
    check($fs->uniqueName($d, 'semextensao') === 'semextensao', 'sem extensão');
});

test('isHidden: lista de nomes ocultos', function () use ($fs) {
    foreach (['.htaccess', 'web.config', '.lixeira', 'database.db', '.', '..'] as $n) {
        check($fs->isHidden($n), "$n deveria ser oculto");
    }
    check(!$fs->isHidden('relatorio.pdf'), 'relatorio.pdf não é oculto');
});

test('metaPath: lixeira ganha prefixo .lixeira/', function () use ($fs) {
    check($fs->metaPath('files', '', 'a.txt') === 'a.txt', 'raiz de arquivos');
    check($fs->metaPath('files', 'Docs/Sub', 'a.txt') === 'Docs/Sub/a.txt', 'subpasta');
    check($fs->metaPath('trash', '', 'a.txt') === '.lixeira/a.txt', 'lixeira');
    check($fs->metaPath('files', 'Docs', '') === 'Docs', 'só a pasta');
});

test('listDir: oculta itens ocultos, pastas e arquivos separados, ordem natural', function () use ($fs, $tmp) {
    $d = "$tmp/uploads/ord";
    mkdir("$d/b-pasta", 0775, true);
    mkdir("$d/a-pasta", 0775, true);
    foreach (['arq10.txt', 'arq2.txt', 'arq1.txt', '.htaccess', 'web.config'] as $f) {
        file_put_contents("$d/$f", 'x');
    }
    [$dirs, $files] = $fs->listDir("$d/");
    check($dirs === ['a-pasta', 'b-pasta'], 'pastas: ' . json_encode($dirs));
    check($files === ['arq1.txt', 'arq2.txt', 'arq10.txt'], 'arquivos (ordem natural, sem ocultos): ' . json_encode($files));
});

test('delete: remove pasta com conteúdo, não segue symlink para fora', function () use ($fs, $tmp) {
    mkdir("$tmp/uploads/apagar/x/y", 0775, true);
    file_put_contents("$tmp/uploads/apagar/x/y/z.txt", 'z');
    $fs->delete("$tmp/uploads/apagar");
    check(!file_exists("$tmp/uploads/apagar"), 'pasta ainda existe');
    $fs->delete("$tmp/uploads/nao-existe"); // não pode dar erro
});

// =====================================================================================
suite('Database e FileMeta');
// =====================================================================================

$db = Database::connect("$tmp/test.db");
$meta = new FileMeta($db);

test('Database::connect é idempotente e cria todas as tabelas', function () use ($tmp, $db) {
    Database::connect("$tmp/test.db"); // segunda vez não pode falhar
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['users', 'logs', 'files_meta', 'login_attempts', 'trash_origin'] as $t) {
        check(in_array($t, $tables, true), "tabela $t ausente");
    }
});

test('FileMeta: created + lookup em lote', function () use ($meta) {
    $meta->created('a.txt', 'ana');
    $meta->created('Docs/b.txt', 'joao');
    $r = $meta->lookup(['a.txt', 'Docs/b.txt', 'nao-tem']);
    check($r['a.txt']['created_by'] === 'ana' && $r['Docs/b.txt']['updated_by'] === 'joao', 'metadados errados');
    check(!isset($r['nao-tem']), 'item inexistente não deveria aparecer');
    check($meta->lookup([]) === [], 'lookup vazio');
});

test('FileMeta: lookup com mais de 500 caminhos (blocos)', function () use ($meta) {
    $paths = [];
    for ($i = 0; $i < 1200; $i++) { $paths[] = "lote/$i.txt"; }
    foreach (array_slice($paths, 0, 3) as $p) { $meta->created($p, 'lote'); }
    $r = $meta->lookup($paths);
    check(count($r) === 3, 'esperava 3, veio ' . count($r));
});

test('FileMeta: renomear pasta move os filhos; irmãs com mesmo prefixo ficam intactas', function () use ($meta) {
    foreach (['Doc', 'Doc/a.txt', 'Doc/sub/b.txt', 'Docs', 'Docs/c.txt'] as $p) { $meta->created($p, 'u'); }
    $meta->renamed('Doc', 'Novo', 'editor');
    $r = $meta->lookup(['Doc', 'Novo', 'Novo/a.txt', 'Novo/sub/b.txt', 'Docs', 'Docs/c.txt', 'Doc/a.txt']);
    check(isset($r['Novo'], $r['Novo/a.txt'], $r['Novo/sub/b.txt']), 'filhos não acompanharam: ' . json_encode(array_keys($r)));
    check(isset($r['Docs'], $r['Docs/c.txt']), 'pasta irmã "Docs" foi afetada');
    check(!isset($r['Doc']) && !isset($r['Doc/a.txt']), 'restou caminho antigo');
    check($r['Novo']['updated_by'] === 'editor', 'updated_by não atualizado');
});

test('FileMeta: curingas LIKE (% e _) no nome da pasta não atingem outras', function () use ($meta) {
    foreach (['a_b', 'a_b/x.txt', 'axb', 'axb/y.txt', '100%', '100%/z.txt', '100abc', '100abc/w.txt'] as $p) { $meta->created($p, 'u'); }
    $meta->removed('a_b');
    $meta->removed('100%');
    $r = $meta->lookup(['a_b', 'a_b/x.txt', 'axb', 'axb/y.txt', '100%', '100%/z.txt', '100abc', '100abc/w.txt']);
    check(array_keys($r) === ['axb', 'axb/y.txt', '100abc', '100abc/w.txt'] || (isset($r['axb'], $r['axb/y.txt'], $r['100abc'], $r['100abc/w.txt']) && count($r) === 4),
          'removeu/deixou o que não devia: ' . json_encode(array_keys($r)));
});

test('FileMeta: removed apaga o item e o conteúdo, não o vizinho de prefixo', function () use ($meta) {
    foreach (['P', 'P/q.txt', 'PP', 'PP/r.txt'] as $p) { $meta->created($p, 'u'); }
    $meta->removed('P');
    $r = $meta->lookup(['P', 'P/q.txt', 'PP', 'PP/r.txt']);
    check(isset($r['PP'], $r['PP/r.txt']) && !isset($r['P']) && !isset($r['P/q.txt']), json_encode(array_keys($r)));
});

test('FileMeta: origem da lixeira — lembrar, ler, esquecer, limpar tudo', function () use ($meta) {
    $meta->rememberOrigin('.lixeira/a.txt', 'Docs/a.txt');
    $meta->rememberOrigin('.lixeira/b.txt', 'b.txt');
    check($meta->origin('.lixeira/a.txt') === 'Docs/a.txt', 'origin');
    check($meta->origin('.lixeira/nao.txt') === null, 'origin inexistente');
    $meta->rememberOrigin('.lixeira/a.txt', 'Outra/a.txt'); // substitui
    check($meta->origin('.lixeira/a.txt') === 'Outra/a.txt', 'substituição');
    $meta->forgetOrigin('.lixeira/a.txt');
    check($meta->origin('.lixeira/a.txt') === null && $meta->origin('.lixeira/b.txt') === 'b.txt', 'forgetOrigin');
    $meta->forgetAllOrigins();
    check($meta->origin('.lixeira/b.txt') === null, 'forgetAllOrigins');
});

// =====================================================================================
suite('Logger');
// =====================================================================================

$logger = new Logger($db);

test('Logger: grava, limita o texto a 500 caracteres e lista do mais novo para o mais antigo', function () use ($logger) {
    $logger->log('TESTE', 'primeiro', 'ana');
    $logger->log('TESTE', str_repeat('x', 900), 'ana');
    $r = $logger->recent(2);
    check(strlen($r[0]['detalhe']) === 500, 'detalhe não foi truncado: ' . strlen($r[0]['detalhe']));
    check($r[1]['detalhe'] === 'primeiro', 'ordem');
});

test('Logger: usuário vem do provedor; sem ele, "Visitante"', function () use ($logger, $db) {
    $logger->setUserProvider(function () { return 'quem-logou'; });
    $logger->log('PROV', 'a');
    $logger->setUserProvider(function () { return null; });
    $logger->log('PROV', 'b');
    $rows = $db->query("SELECT usuario FROM logs WHERE acao='PROV' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    check($rows === ['quem-logou', 'Visitante'], json_encode($rows));
});

test('Logger::purge apaga só os registros mais velhos que o limite', function () use ($logger, $db) {
    $db->exec("INSERT INTO logs (data_hora, ip, usuario, acao, detalhe) VALUES (DATETIME('now','localtime','-400 days'), 'x', 'u', 'VELHO', 'v')");
    $db->exec("INSERT INTO logs (data_hora, ip, usuario, acao, detalhe) VALUES (DATETIME('now','localtime','-10 days'), 'x', 'u', 'RECENTE', 'r')");
    $logger->purge(180);
    check((int) $db->query("SELECT COUNT(*) FROM logs WHERE acao='VELHO'")->fetchColumn() === 0, 'velho permaneceu');
    check((int) $db->query("SELECT COUNT(*) FROM logs WHERE acao='RECENTE'")->fetchColumn() === 1, 'recente foi apagado');
});

// =====================================================================================
suite('Auth — usuários e senha');
// =====================================================================================

$authCfg = $config['auth'];
$mk = function () use ($db, $logger, $authCfg) { return new Auth($db, $logger, $authCfg); };
$_SERVER['REMOTE_ADDR'] = '10.7.0.1';
$a = $mk();
const PW = 'Senha-Teste-123';

test('createUser: aceita login válido (acentos, ponto, hífen, _) e cria com bcrypt custo fixo', function () use ($a, $db) {
    foreach (['maria', 'José.Silva', 'ana-paula_2', 'Comunicação'] as $u) {
        [$ok, $msg] = $a->createUser($u, PW, 'user');
        check($ok, "$u recusado: $msg");
    }
    $hash = $db->query("SELECT password FROM users WHERE username='maria'")->fetchColumn();
    check(password_get_info($hash)['options']['cost'] === Auth::HASH_COST, 'custo do hash');
    check($hash !== PW && strpos($hash, PW) === false, 'senha em texto puro?');
});

test('createUser: recusa login inválido, role inválida, senha curta e duplicado', function () use ($a) {
    $bad = ['ab', str_repeat('x', 51), 'com espaço', 'a/b', 'a;b', "x'--", '<script>', '', '  '];
    foreach ($bad as $u) {
        check($a->createUser($u, PW, 'user')[0] === false, 'aceitou login ' . json_encode($u));
    }
    foreach (['superadmin', 'ADMIN', '', 'user '] as $role) {
        check($a->createUser('novo-user', PW, $role)[0] === false, 'aceitou role ' . json_encode($role));
    }
    check($a->createUser('novo-user', '1234567', 'user')[0] === false, 'aceitou senha de 7 caracteres');
    check($a->createUser('novo-user', '12345678', 'user')[0] === true, 'recusou senha de 8 caracteres (mínimo atual)');
    check($a->createUser('maria', PW, 'user') === [false, 'Já existe.'], 'duplicado');
});

test('createUser: SQL injection no login não passa (regex) e no resto é parametrizado', function () use ($a, $db) {
    $a->createUser("x'); DROP TABLE users;--", PW, 'user');
    check((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0, 'tabela users sumiu');
    check($a->createUser("maria' OR '1'='1", PW, 'user')[0] === false, 'aceitou injeção');
});

test('resetPassword: usuário inexistente, senha curta e sucesso', function () use ($mk) {
    $a = $mk();
    check($a->resetPassword('fantasma', PW)[0] === false, 'usuário inexistente');
    check($a->resetPassword('maria', 'curta')[0] === false, 'senha curta');
    check($a->resetPassword('maria', 'Outra-Senha-9')[0] === true, 'sucesso');
    check($a->attempt('maria', 'Outra-Senha-9')[0] === true, 'nova senha não loga');
});

test('changePassword: senha atual errada, nova curta, sucesso; a antiga deixa de valer', function () use ($mk) {
    $_SERVER['REMOTE_ADDR'] = '10.7.0.2';
    $a = $mk();
    check($a->changePassword('ana-paula_2', 'errada', 'NovaSenha-123')[0] === false, 'aceitou senha atual errada');
    check($a->changePassword('ana-paula_2', PW, 'curta')[0] === false, 'aceitou nova curta');
    check($a->changePassword('fantasma', PW, 'NovaSenha-123')[0] === false, 'usuário inexistente');
    check($a->changePassword('ana-paula_2', PW, 'NovaSenha-123')[0] === true, 'troca válida falhou');
    $_SERVER['REMOTE_ADDR'] = '10.7.0.3';
    check($a->attempt('ana-paula_2', PW)[0] === false, 'senha antiga ainda vale');
    check($a->attempt('ana-paula_2', 'NovaSenha-123')[0] === true, 'senha nova não vale');
});

test('deleteUser: não apaga a si mesmo, nem o último admin; apaga usuário comum; id inexistente', function () use ($mk, $db) {
    $a0 = $mk();
    $a0->createUser('chefe', PW, 'admin');
    $chefe = (int) $db->query("SELECT id FROM users WHERE username='chefe'")->fetchColumn();
    $maria = (int) $db->query("SELECT id FROM users WHERE username='maria'")->fetchColumn();

    $_SESSION = ['user_id' => $chefe, 'last_activity' => time(), 'login_at' => time()];
    $a = $mk();
    check($a->deleteUser($chefe) === [false, 'Não pode se excluir.'], 'autoexclusão');
    $_SESSION = ['user_id' => $maria, 'last_activity' => time(), 'login_at' => time()];
    $a = $mk();
    check($a->deleteUser($chefe)[1] === 'Não é possível excluir o último admin.', 'último admin');
    check($a->deleteUser(999999)[0] === false, 'id inexistente');
    $ana = (int) $db->query("SELECT id FROM users WHERE username='ana-paula_2'")->fetchColumn();
    check($a->deleteUser($ana)[0] === true, 'usuário comum deveria ser apagado');
    $_SESSION = [];
});

test('listUsers: ordenado por login e sem expor o hash', function () use ($mk) {
    $list = $mk()->listUsers();
    check(count($list) >= 3, 'lista curta');
    foreach ($list as $u) { check(!isset($u['password']), 'listUsers expôs o hash'); }
});

// =====================================================================================
suite('Auth — limite de tentativas (fronteiras)');
// =====================================================================================

$fail = function ($user, $ip) use ($mk) {
    $_SERVER['REMOTE_ADDR'] = $ip;
    return $mk()->attempt($user, 'errada');
};

test('4 falhas não bloqueiam; a 5ª bloqueia; senha certa não passa bloqueado', function () use ($fail, $mk) {
    for ($i = 1; $i <= 4; $i++) {
        $r = $fail('maria', '10.8.0.1');
        check(strpos($r[1], 'incorretos') !== false, "falha $i já veio bloqueada");
    }
    $_SERVER['REMOTE_ADDR'] = '10.8.0.1';
    check($mk()->attempt('maria', 'Outra-Senha-9')[0] === true, 'com 4 falhas a senha certa deveria entrar');
    // entrou: contador zerado; agora 5 falhas seguidas
    for ($i = 1; $i <= 5; $i++) { $fail('maria', '10.8.0.1'); }
    $_SERVER['REMOTE_ADDR'] = '10.8.0.1';
    $r = $mk()->attempt('maria', 'Outra-Senha-9');
    check($r[0] === false && strpos($r[1], 'Muitas tentativas') !== false, 'a 6ª tentativa (certa) deveria estar bloqueada');
});

test('bloqueio é por IP: outro IP não é afetado', function () use ($mk) {
    $_SERVER['REMOTE_ADDR'] = '10.8.0.99';
    check($mk()->attempt('maria', 'Outra-Senha-9')[0] === true, 'outro IP foi bloqueado junto');
});

test('chave do limite ignora maiúsculas ("Maria" e "maria" contam juntos)', function () use ($fail, $db) {
    $db->exec('DELETE FROM login_attempts');
    for ($i = 0; $i < 3; $i++) { $fail('Maria', '10.8.0.2'); }
    for ($i = 0; $i < 2; $i++) { $fail('MARIA', '10.8.0.2'); }
    $r = $fail('maria', '10.8.0.2');
    check(strpos($r[1], 'Muitas tentativas') !== false, 'variações de caixa deveriam dividir o contador');
});

test('bloqueio expira após lockout_minutes', function () use ($db, $mk) {
    $db->exec('UPDATE login_attempts SET attempted_at = attempted_at - 3600');
    $_SERVER['REMOTE_ADDR'] = '10.8.0.2';
    check($mk()->attempt('maria', 'Outra-Senha-9')[0] === true, 'bloqueio deveria ter expirado');
});

test('login com sucesso limpa as falhas daquele usuário+IP', function () use ($db) {
    check((int) $db->query("SELECT COUNT(*) FROM login_attempts WHERE ip='10.8.0.2'")->fetchColumn() === 0, 'falhas não foram limpas');
});

test('nomes gigantes e estranhos não quebram nem enchem a tabela', function () use ($fail, $db) {
    $big = str_repeat('é', 5000);
    $fail($big, '10.8.0.3');
    $fail("nul\0byte", '10.8.0.3');
    $len = (int) $db->query("SELECT MAX(LENGTH(username)) FROM login_attempts")->fetchColumn();
    check($len <= 100, "username gravado com $len caracteres");
});

test('changePassword também respeita o bloqueio por tentativas (não vira atalho para adivinhar a senha)', function () use ($mk, $db) {
    $db->exec('DELETE FROM login_attempts');
    $_SERVER['REMOTE_ADDR'] = '10.9.0.1';
    $a = $mk();
    for ($i = 0; $i < 5; $i++) {
        check($a->changePassword('maria', 'errada-' . $i, 'NovaSenha-123') === [false, 'Dados inválidos.'], "tentativa $i");
    }
    [$ok, $msg] = $a->changePassword('maria', 'Outra-Senha-9', 'NovaSenha-123');   // a senha atual CERTA
    check($ok === false && strpos($msg, 'Muitas tentativas') !== false, 'bloqueado, mesmo com a senha atual certa: ' . $msg);
    check($a->attempt('maria', 'Outra-Senha-9')[0] === false, 'o bloqueio vale também para o login (mesma chave)');
});

test('sessão antiga sem login_at passa a contar a partir do primeiro acesso (não cai)', function () use ($mk, $db) {
    $id = (int) $db->query("SELECT id FROM users WHERE username='maria'")->fetchColumn();
    $_SESSION = ['user_id' => $id, 'last_activity' => time()];   // sem login_at
    $u = $mk()->user();
    check($u !== null && $u['username'] === 'maria', 'sessão válida foi derrubada');
    check(isset($_SESSION['login_at']) && abs($_SESSION['login_at'] - time()) <= 2, 'login_at não foi preenchido');
    $_SESSION = [];
});

// =====================================================================================
suite('Csrf');
// =====================================================================================

test('token: 64 hex, estável na sessão, regenerate troca', function () {
    $_SESSION = [];
    $t = Csrf::token();
    check(preg_match('/^[0-9a-f]{64}$/', $t) === 1, 'formato: ' . $t);
    check(Csrf::token() === $t, 'token mudou sem regenerar');
    Csrf::regenerate();
    check(Csrf::token() !== $t, 'regenerate não trocou');
});

test('verifyRequest: aceita campo e header; recusa ausente, errado, vazio e não-string', function () {
    $_SESSION = [];
    $t = Csrf::token();
    $_POST = [Csrf::FIELD => $t]; $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    check(Csrf::verifyRequest() === true, 'campo válido');
    $_POST = []; $_SERVER['HTTP_X_CSRF_TOKEN'] = $t;
    check(Csrf::verifyRequest() === true, 'header válido');
    $_POST = []; $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    check(Csrf::verifyRequest() === false, 'sem token');
    $_POST = [Csrf::FIELD => $t . 'x'];
    check(Csrf::verifyRequest() === false, 'token errado');
    $_POST = [Csrf::FIELD => ''];
    check(Csrf::verifyRequest() === false, 'token vazio');
    $_POST = [Csrf::FIELD => [$t]];
    check(Csrf::verifyRequest() === false, 'token como array');
    $_SESSION = []; $_POST = [Csrf::FIELD => ''];
    check(Csrf::verifyRequest() === false, 'sessão sem token e POST vazio não pode "bater"');
    $_POST = []; unset($_SERVER['HTTP_X_CSRF_TOKEN']);
});

test('field: HTML com o token escapado', function () {
    $_SESSION = [];
    $html = Csrf::field();
    check(strpos($html, 'name="_csrf"') !== false && strpos($html, Csrf::token()) !== false, $html);
});

finish();
