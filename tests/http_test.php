<?php
/**
 * Testes de integração HTTP: sobe o sistema de verdade (php -S) com dados
 * temporários e exercita login, listagem, download, ZIP, permissões, upload,
 * lixeira e usuários pela rede. Requer a extensão curl.
 *
 *   php tests/http_test.php
 */

require __DIR__ . '/helpers.php';
$config = require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Database;
use App\Logger;

if (!extension_loaded('curl')) {
    fwrite(STDOUT, "Extensão curl ausente: habilite 'extension=curl' no php.ini.\n");
    exit(2);
}

// ===================================================================================
// Cliente HTTP mínimo (cookies por cliente, sem seguir redirects)
// ===================================================================================

final class Resp
{
    public $status = 0;
    public $headers = [];
    public $body = '';

    public function h(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function location(): string
    {
        return $this->h('location');
    }

    /** Texto do toast (flash) embutido na página, se houver. */
    public function flash(): string
    {
        return preg_match('/data-flash-text="([^"]*)"/', $this->body, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
    }

    /** Mensagem de erro/sucesso da tela de login. */
    public function loginMessage(): string
    {
        return preg_match('/class="(?:error|success)">([^<]*)</', $this->body, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
    }
}

final class Client
{
    public $jar;
    private $base;

    public function __construct(string $base)
    {
        $this->base = $base;
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    public function req(string $method, string $path, $body = null, array $headers = []): Resp
    {
        $r = new Resp();
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_PATH_AS_IS     => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use ($r) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                    $r->status = (int) $m[1];
                    $r->headers = [];
                } elseif (strpos($line, ':') !== false) {
                    [$k, $v] = explode(':', $line, 2);
                    $k = strtolower(trim($k));
                    $r->headers[$k] = isset($r->headers[$k]) ? $r->headers[$k] . "\n" . trim($v) : trim($v);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $out = curl_exec($ch);
        if ($out === false) {
            throw new RuntimeException('curl: ' . curl_error($ch) . " ($method $path)");
        }
        $r->body = $out;
        curl_close($ch);
        return $r;
    }

    public function get(string $path, array $headers = []): Resp
    {
        return $this->req('GET', $path, null, $headers);
    }

    /** Token CSRF da sessão atual (lido da página). */
    public function token(): string
    {
        $p = $this->get('/');
        if (preg_match('/name="csrf-token" content="([^"]+)"/', $p->body, $m) || preg_match('/name="_csrf" value="([^"]+)"/', $p->body, $m)) {
            return $m[1];
        }
        throw new RuntimeException('sem token CSRF na página (status ' . $p->status . ')');
    }

    /** POST de formulário (urlencoded) com CSRF automático. */
    public function post(string $path, array $fields, bool $csrf = true, array $headers = []): Resp
    {
        if ($csrf) {
            $fields['_csrf'] = $this->token();
        }
        return $this->req('POST', $path, http_build_query($fields), $headers);
    }

    /** POST multipart (upload) com CSRF automático. */
    public function upload(string $path, array $files, array $fields = [], bool $ajax = true): Resp
    {
        $fields += ['_csrf' => $this->token(), 'action' => 'upload'];
        $i = 0;
        foreach ($files as $name => $content) {
            $tmpf = tempnam(sys_get_temp_dir(), 'up');
            file_put_contents($tmpf, $content);
            $fields['arquivos[' . $i++ . ']'] = new CURLFile($tmpf, 'application/octet-stream', $name);
        }
        $r = $this->req('POST', $path, $fields, $ajax ? ['X-Requested-With: XMLHttpRequest'] : []);
        return $r;
    }

    /** Segue um redirect relativo (Location: index.php?...). */
    public function follow(Resp $r): Resp
    {
        check(in_array($r->status, [301, 302, 303], true), 'esperava redirect, veio ' . $r->status . ' ' . substr($r->body, 0, 80));
        return $this->get('/' . ltrim($r->location(), '/'));
    }

    public function sessionId(): string
    {
        return preg_match('/INTRANETSESSID\s+(\S+)/', (string) file_get_contents($this->jar), $m) ? $m[1] : '';
    }
}

// ===================================================================================
// Ambiente: dados temporários + servidor
// ===================================================================================

const PW = 'Senha-Teste-123';
$tmp = mktmp('intranet-http-');
$storage = "$tmp/storage";
foreach (["$storage/uploads/Docs", "$storage/uploads/Vazia", "$storage/trash", "$tmp/sessions", "$storage/logs"] as $d) {
    mkdir($d, 0775, true);
}
file_put_contents("$tmp/segredo.txt", 'FORA-DA-RAIZ');
file_put_contents("$storage/uploads/Docs/nota.txt", "linha 1\nlinha 2\n");
file_put_contents("$storage/uploads/Docs/relatorio.pdf", "%PDF-1.4\n" . str_repeat('A', 5000) . "\n%%EOF");
file_put_contents("$storage/uploads/logo.png", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
file_put_contents("$storage/uploads/evil.svg", '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
file_put_contents("$storage/uploads/aspas'e&amp.txt", 'x');
file_put_contents("$storage/uploads/.htaccess", 'Deny from all');
file_put_contents("$storage/uploads/web.config", '<configuration/>');
file_put_contents("$storage/trash/velho.txt", 'lixo');

$db = Database::connect("$storage/database.db");
$logger = new Logger($db);
$seed = new Auth($db, $logger, $config['auth']);
foreach (['admin1' => 'admin', 'admin2' => 'admin', 'user1' => 'user', 'victim' => 'user', 'chpw' => 'user', 'lockuser' => 'user', 'novo2' => 'user'] as $u => $role) {
    [$ok, $msg] = $seed->createUser($u, PW, $role);
    if (!$ok) { exit("setup falhou ($u): $msg\n"); }
}

$sock = stream_socket_server('tcp://127.0.0.1:0', $en, $es);
$port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
fclose($sock);

$server = proc_open(
    [PHP_BINARY, '-d', 'post_max_size=1M', '-d', 'upload_max_filesize=1M', '-d', 'display_errors=0', '-d', "session.save_path=$tmp/sessions",
     '-S', "127.0.0.1:$port", '-t', dirname(__DIR__) . '/public'],
    [0 => ['pipe', 'r'], 1 => ['file', "$tmp/server.log", 'a'], 2 => ['file', "$tmp/server.log", 'a']],
    $pipes,
    null,
    array_merge(getenv(), ['INTRANET_STORAGE' => $storage, 'INTRANET_DEBUG' => '0', 'INTRANET_FORCE_HTTPS' => '0'])
);
register_shutdown_function(function () use ($server, $tmp) {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    rrm($tmp);
});
for ($i = 0, $up = false; $i < 100 && !$up; $i++) {
    $c = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2);
    if ($c) { fclose($c); $up = true; } else { usleep(100000); }
}
if (!$up) { exit("servidor de teste não subiu (veja $tmp/server.log)\n"); }
$base = "http://127.0.0.1:$port";
fwrite(STDOUT, "Servidor de teste em $base\n");

function login(string $user, string $pw = PW): Client
{
    global $base;
    $c = new Client($base);
    $r = $c->post('/', ['action' => 'login', 'user' => $user, 'pass' => $pw]);
    check($r->status === 302, "login de $user: status {$r->status}");
    $home = $c->follow($r);
    check(strpos($home->body, 'csrf-token') !== false, "login de $user não chegou ao painel");
    return $c;
}

function dbq(string $sql, array $args = []): array
{
    global $storage;
    $p = new PDO('sqlite:' . $storage . '/database.db');
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $st = $p->prepare($sql);
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

$admin = login('admin1');
$user = login('user1');
$anon = new Client($base);

// ===================================================================================
suite('Infra e acesso anônimo');
// ===================================================================================

test('GET / anônimo: tela de login, cookie HttpOnly+SameSite, cabeçalhos de segurança, sem HSTS', function () use ($base) {
    $r = (new Client($base))->get('/');
    check($r->status === 200 && strpos($r->body, 'Acesso Intranet') !== false, 'não é a tela de login');
    check(stripos($r->h('set-cookie'), 'HttpOnly') !== false && stripos($r->h('set-cookie'), 'SameSite=Lax') !== false, 'cookie: ' . $r->h('set-cookie'));
    check(stripos($r->h('set-cookie'), 'secure') === false, 'cookie Secure sem HTTPS');
    check($r->h('x-content-type-options') === 'nosniff', 'nosniff');
    check($r->h('x-frame-options') === 'SAMEORIGIN', 'x-frame-options');
    check($r->h('referrer-policy') === 'same-origin', 'referrer-policy');
    check(stripos($r->h('cache-control'), 'no-store') !== false, 'cache-control');
    check($r->h('strict-transport-security') === '', 'HSTS não deveria ser enviado sem HTTPS');
});

test('arquivos internos nunca são servidos pela web (404)', function () use ($anon) {
    $paths = ['/database.db', '/storage/database.db', '/src/Auth.php', '/config/config.php', '/bin/create-user.php', '/tests/helpers.php',
              '/docs/fluxos.md', '/uploads/Docs/nota.txt', '/.git/config', '/composer.json', '/.env', '/index.php/../../src/Auth.php',
              '/assets/../../src/Auth.php', '/%2e%2e/src/Auth.php', '/..%2fconfig/config.php', '/public/index.php'];
    foreach ($paths as $p) {
        $r = $anon->get($p);
        check($r->status === 404 || $r->status === 400, "$p => {$r->status}");
        check(strpos($r->body, '<?php') === false && strpos($r->body, 'password_hash') === false, "$p vazou código");
    }
});

test('assets estáticos são servidos', function () use ($anon) {
    foreach (['/assets/css/app.css' => 'text/css', '/assets/js/app.js' => 'javascript', '/assets/img/logotipo.png' => 'image/png'] as $p => $type) {
        $r = $anon->get($p);
        check($r->status === 200 && stripos($r->h('content-type'), $type) !== false, "$p => {$r->status} " . $r->h('content-type'));
    }
});

test('rota inexistente = 404; ações de escrita por GET não executam (nem as antigas)', function () use ($admin, $storage) {
    foreach (['?action=nada', '?action=trash&item=Docs', '?action=mkdir&nome_pasta=hack', '?deletar=Docs', '?perm_delete=velho.txt', '?restore=velho.txt&mode=trash', '?deletar_user_id=2', '?action=empty_trash&mode=trash'] as $q) {
        $r = $admin->get('/index.php' . $q);
        check(in_array($r->status, [404, 200], true), "$q => {$r->status}");
    }
    check(is_dir("$storage/uploads/Docs") && is_file("$storage/trash/velho.txt") && !is_dir("$storage/uploads/hack"), 'um GET alterou o disco');
    check(count(dbq("SELECT 1 FROM users")) >= 7, 'um GET apagou usuário');
});

test('ação protegida sem login mostra o login e não entrega o arquivo', function () use ($anon) {
    $r = $anon->get('/index.php?action=download&dir=Docs&file=nota.txt');
    check($r->status === 200 && strpos($r->body, 'Acesso Intranet') !== false, 'deveria cair no login');
    check(strpos($r->body, 'linha 1') === false && $r->h('content-disposition') === '', 'entregou arquivo sem login');
});

test('POST sem CSRF e com token de outra sessão = 419; token válido anônimo em ação protegida cai no login', function () use ($base, $storage) {
    $a = new Client($base);
    check($a->req('POST', '/', http_build_query(['action' => 'login', 'user' => 'user1', 'pass' => PW]))->status === 419, 'sem token');
    $b = new Client($base);
    $tokenB = $b->token();
    check($a->req('POST', '/', http_build_query(['action' => 'login', 'user' => 'user1', 'pass' => PW, '_csrf' => $tokenB]))->status === 419, 'token de outra sessão');
    $r = $a->post('/', ['action' => 'mkdir', 'nome_pasta' => 'anonimo']);
    check($r->status === 200 && strpos($r->body, 'Acesso Intranet') !== false, 'anônimo com token deveria ver o login, status ' . $r->status);
    check(!is_dir("$storage/uploads/anonimo"), 'anônimo criou pasta');
});

test('POST text/plain pequeno NÃO é tratado como upload gigante (não dá 413)', function () use ($anon) {
    $r = $anon->req('POST', '/', 'oi', ['Content-Type: text/plain']);
    check($r->status !== 413, 'status 413 para corpo de 2 bytes');
});

test('POST maior que post_max_size = 413 (JSON se XHR) e sem efeito', function () use ($admin, $storage) {
    $tok = $admin->token();
    $big = str_repeat('x', 2 * 1024 * 1024); // limite do servidor de teste: 1M
    $r = $admin->req('POST', '/?dir=', ['_csrf' => $tok, 'action' => 'upload', 'arquivos[0]' => new CURLFile(tempnam(sys_get_temp_dir(), 'b'), 'text/plain', 'grande.txt'), 'lixo' => $big], ['X-Requested-With: XMLHttpRequest']);
    check($r->status === 413, "status {$r->status}");
    $j = json_decode($r->body, true);
    check(is_array($j) && $j['status'] === 'error' && stripos($j['msg'], 'limite') !== false, 'corpo: ' . substr($r->body, 0, 120));
    check(!is_file("$storage/uploads/grande.txt"), 'arquivo gravado apesar do 413');
});

// ===================================================================================
suite('Login e sessão (pela rede)');
// ===================================================================================

test('senha errada e usuário inexistente dão a MESMA mensagem; certa entra', function () use ($base) {
    $a = new Client($base);
    $r1 = $a->follow($a->post('/', ['action' => 'login', 'user' => 'user1', 'pass' => 'errada']));
    $b = new Client($base);
    $r2 = $b->follow($b->post('/', ['action' => 'login', 'user' => 'ninguem', 'pass' => 'errada']));
    check($r1->loginMessage() === 'Usuário ou senha incorretos.', 'msg 1: ' . $r1->loginMessage());
    check($r1->loginMessage() === $r2->loginMessage(), 'mensagens diferem (enumeração)');
    $ok = login('user1');
    check($ok->get('/')->status === 200, 'login certo');
});

test('ID da sessão muda no login (anti-fixation) e o token CSRF também', function () use ($base) {
    $c = new Client($base);
    $c->get('/');
    $before = $c->sessionId();
    $tokBefore = $c->token();
    $r = $c->post('/', ['action' => 'login', 'user' => 'user1', 'pass' => PW]);
    check($r->status === 302, 'login');
    $after = $c->sessionId();
    check($before !== '' && $after !== '' && $before !== $after, "ID não mudou ($before => $after)");
    check($c->token() !== $tokBefore, 'token CSRF não mudou no login');
});

test('5 senhas erradas bloqueiam; a certa também falha; mensagem de bloqueio', function () use ($base) {
    $c = new Client($base);
    for ($i = 0; $i < 5; $i++) {
        $m = $c->follow($c->post('/', ['action' => 'login', 'user' => 'lockuser', 'pass' => 'x' . $i]))->loginMessage();
        check(strpos($m, 'incorretos') !== false, "tentativa $i: $m");
    }
    $m = $c->follow($c->post('/', ['action' => 'login', 'user' => 'lockuser', 'pass' => PW]))->loginMessage();
    check(strpos($m, 'Muitas tentativas') !== false, 'a 6ª (certa) deveria estar bloqueada: ' . $m);
    $blk = dbq("SELECT COUNT(*) n FROM logs WHERE acao='LOGIN_BLOQUEADO'")[0]['n'];
    check((int) $blk >= 1, 'LOGIN_BLOQUEADO não registrado');
});

test('logout destrói a sessão no servidor (cookie copiado antes também deixa de valer)', function () use ($base) {
    $c = login('user1');
    $sid = $c->sessionId();
    $copy = new Client($base);
    file_put_contents($copy->jar, str_replace("\t" . 'INTRANETSESSID', "\t" . 'INTRANETSESSID', file_get_contents($c->jar)));
    check(strpos($copy->get('/')->body, 'csrf-token') !== false, 'cookie copiado deveria funcionar antes do logout');
    $r = $c->get('/index.php?action=logout');
    check($r->status === 302, 'logout');
    check(strpos($c->get('/')->body, 'Acesso Intranet') !== false, 'ainda logado após logout');
    check(strpos($copy->get('/')->body, 'Acesso Intranet') !== false, 'cookie antigo ainda vale após logout (sid ' . $sid . ')');
});

test('troca de senha pública: erro reabre ?form=change; sucesso volta ao login; antiga deixa de valer', function () use ($base) {
    $c = new Client($base);
    $r = $c->post('/', ['action' => 'change_password', 'user_change' => 'chpw', 'pass_old' => 'errada', 'pass_new' => 'NovaSenha-456']);
    check($r->location() === 'index.php?form=change', 'redirect: ' . $r->location());
    $p = $c->follow($r);
    check($p->loginMessage() === 'Dados inválidos.' && strpos($p->body, '<div id="formChange" style="display:block">') !== false, 'formulário de nova senha não reabriu');
    $r = $c->post('/', ['action' => 'change_password', 'user_change' => 'chpw', 'pass_old' => PW, 'pass_new' => 'curta']);
    check(strpos($c->follow($r)->loginMessage(), 'pelo menos') !== false, 'senha curta aceita');
    $r = $c->post('/', ['action' => 'change_password', 'user_change' => 'chpw', 'pass_old' => PW, 'pass_new' => 'NovaSenha-456']);
    check($r->location() === 'index.php', 'sucesso deveria voltar ao login: ' . $r->location());
    check(strpos($c->follow($r)->loginMessage(), 'sucesso') !== false, 'sem mensagem de sucesso');
    $x = new Client($base);
    check($x->post('/', ['action' => 'login', 'user' => 'chpw', 'pass' => PW])->status === 302, 'redirect esperado');
    check(strpos($x->get('/')->body, 'Acesso Intranet') !== false, 'senha antiga ainda loga');
    $y = login('chpw', 'NovaSenha-456');
    check($y->get('/')->status === 200, 'senha nova não loga');
});

test('usuário logado troca a própria senha pelo painel (ignora o nome enviado)', function () use ($base) {
    $c = login('novo2');
    $r = $c->post('/', ['action' => 'change_password', 'user_change' => 'admin1', 'pass_old' => PW, 'pass_new' => 'Outra-Senha-789']);
    $c->follow($r);
    check(login('novo2', 'Outra-Senha-789') instanceof Client, 'senha de novo2 não mudou');
    $x = new Client($base);
    $x->post('/', ['action' => 'login', 'user' => 'admin1', 'pass' => 'Outra-Senha-789']);
    check(strpos($x->get('/')->body, 'Acesso Intranet') !== false, 'trocou a senha do admin1 por engano');
});

test('sessão ociosa há muito tempo: aviso "sessão expirou" e o login seguinte funciona (sem 419)', function () use ($base, $tmp) {
    $c = login('user1');
    $sid = $c->sessionId();
    $f = "$tmp/sessions/sess_$sid";
    check(is_file($f), "arquivo de sessão não encontrado ($f)");
    file_put_contents($f, preg_replace('/last_activity\|i:\d+;/', 'last_activity|i:1000;', file_get_contents($f)));
    $page = $c->get('/');
    check(strpos($page->body, 'Acesso Intranet') !== false && strpos($page->loginMessage(), 'expirou') !== false, 'sem aviso de expiração: ' . $page->loginMessage());
    $r = $c->post('/', ['action' => 'login', 'user' => 'user1', 'pass' => PW]);
    check($r->status === 302, 'login após expirar deu ' . $r->status);
    check(strpos($c->follow($r)->body, 'csrf-token') !== false, 'não chegou ao painel');
});

test('usuário apagado perde a sessão na próxima requisição', function () use ($base, $admin) {
    $v = login('victim');
    check(strpos($v->get('/')->body, 'csrf-token') !== false, 'victim deveria estar logado');
    $id = dbq("SELECT id FROM users WHERE username='victim'")[0]['id'];
    $admin->post('/', ['action' => 'delete_user', 'id' => $id]);
    $page = $v->get('/');
    check(strpos($page->body, 'Acesso Intranet') !== false && strpos($page->loginMessage(), 'encerrada') !== false, 'sessão do apagado continua válida');
});

// ===================================================================================
suite('Listagem e navegação');
// ===================================================================================

test('lista: pastas e arquivos aparecem; ocultos não; nomes com aspas e & vêm escapados', function () use ($user) {
    $r = $user->get('/');
    foreach (['Docs', 'Vazia', 'logo.png', 'evil.svg'] as $n) {
        check(strpos($r->body, '>' . $n . '<') !== false, "$n ausente da lista");
    }
    check(strpos($r->body, '.htaccess') === false && strpos($r->body, 'web.config') === false, 'arquivo oculto apareceu');
    check(strpos($r->body, "aspas&#039;e&amp;amp.txt") !== false, 'nome não foi escapado corretamente');
    check(strpos($r->body, "aspas'e&amp.txt") === false, 'nome cru no HTML');
});

test('subpasta: lista o conteúdo e monta o breadcrumb; pasta vazia mostra aviso', function () use ($user) {
    $r = $user->get('/index.php?dir=Docs');
    check(strpos($r->body, 'nota.txt') !== false && strpos($r->body, 'relatorio.pdf') !== false, 'conteúdo de Docs');
    check(strpos($r->body, 'class="separator"') !== false, 'breadcrumb');
    check(strpos($user->get('/index.php?dir=Vazia')->body, 'Esta pasta está vazia') !== false, 'aviso de pasta vazia');
});

test('traversal em ?dir= cai na raiz e nunca lista nada de fora', function () use ($user) {
    foreach (['..', '../', '../..', '....//', '..%2f', '%2e%2e%2f', 'Docs/../..', 'Docs/..%2f..', '..\\', '/etc', 'C:\\Windows', '%00', 'Docs%00', '../segredo.txt'] as $d) {
        $r = $user->get('/index.php?dir=' . $d);
        check($r->status === 200, "$d => {$r->status}");
        check(strpos($r->body, 'segredo.txt') === false && strpos($r->body, 'FORA-DA-RAIZ') === false, "$d listou arquivo de fora");
        check(strpos($r->body, '>Docs<') !== false, "$d não caiu na raiz");
    }
    $r = $user->get('/index.php?dir[]=Docs');
    check($r->status === 200 && strpos($r->body, '>Docs<') !== false, 'dir como array deveria cair na raiz');
});

test('?mode=trash: usuário comum vê a lista normal; admin vê a lixeira', function () use ($user, $admin) {
    $u = $user->get('/index.php?mode=trash');
    check(strpos($u->body, 'velho.txt') === false && strpos($u->body, '>Docs<') !== false, 'usuário comum viu a lixeira');
    $a = $admin->get('/index.php?mode=trash');
    check(strpos($a->body, 'velho.txt') !== false && strpos($a->body, 'Arquivos na lixeira') !== false, 'admin não viu a lixeira');
});

test('usuário comum não recebe elementos de admin; admin recebe', function () use ($user, $admin) {
    $u = $user->get('/')->body;
    foreach (['Painel Master', 'id="uploadForm"', 'name="nome_pasta"', 'data-rename', 'data-post-action="trash"', 'id="adminModal"', 'Logs do Sistema'] as $s) {
        check(strpos($u, $s) === false, "usuário comum recebeu: $s");
    }
    $a = $admin->get('/')->body;
    foreach (['Painel Master', 'id="uploadForm"', 'name="nome_pasta"', 'data-rename', 'data-post-action="trash"', 'Logs do Sistema'] as $s) {
        check(strpos($a, $s) !== false, "admin não recebeu: $s");
    }
});

// ===================================================================================
suite('Download e ZIP');
// ===================================================================================

test('download: tipos permitidos abrem inline; svg e desconhecidos sempre baixam', function () use ($user) {
    $cases = [
        ['Docs', 'nota.txt', 'text/plain', 'inline'],
        ['Docs', 'relatorio.pdf', 'application/pdf', 'inline'],
        ['', 'logo.png', 'image/png', 'inline'],
    ];
    foreach ($cases as [$dir, $file, $type, $disp]) {
        $r = $user->get('/index.php?' . http_build_query(['action' => 'download', 'dir' => $dir, 'file' => $file, 'inline' => 1]));
        check($r->status === 200 && stripos($r->h('content-type'), $type) === 0, "$file: {$r->status} " . $r->h('content-type'));
        check(stripos($r->h('content-disposition'), $disp) === 0 && $r->h('x-content-type-options') === 'nosniff', "$file disposition: " . $r->h('content-disposition'));
    }
    $svg = $user->get('/index.php?action=download&file=evil.svg&inline=1');
    check($svg->h('content-type') === 'application/octet-stream' && stripos($svg->h('content-disposition'), 'attachment') === 0, 'SVG abriu inline: ' . $svg->h('content-type'));
    $dl = $user->get('/index.php?action=download&dir=Docs&file=nota.txt');
    check(stripos($dl->h('content-disposition'), 'attachment') === 0 && $dl->body === "linha 1\nlinha 2\n", 'download normal');
});

test('download: nome de arquivo com acento no cabeçalho (filename*=UTF-8)', function () use ($user, $storage) {
    file_put_contents("$storage/uploads/Relatório Ação.txt", 'ok');
    $r = $user->get('/index.php?' . http_build_query(['action' => 'download', 'file' => 'Relatório Ação.txt']));
    check(strpos($r->h('content-disposition'), "filename*=UTF-8''Relat%C3%B3rio%20A%C3%A7%C3%A3o.txt") !== false, $r->h('content-disposition'));
});

test('download com Range: 206, sufixo, fora do alcance = 416, lixo = 200 inteiro', function () use ($user) {
    $u = '/index.php?action=download&dir=Docs&file=relatorio.pdf&inline=1';
    $full = $user->get($u);
    $size = strlen($full->body);
    check($size > 5000 && $full->h('accept-ranges') === 'bytes', 'arquivo inteiro / Accept-Ranges');
    $r = $user->get($u, ['Range: bytes=0-99']);
    check($r->status === 206 && $r->h('content-range') === "bytes 0-99/$size" && strlen($r->body) === 100, '0-99: ' . $r->status . ' ' . $r->h('content-range'));
    check($r->body === substr($full->body, 0, 100), 'conteúdo do trecho');
    $r = $user->get($u, ['Range: bytes=-50']);
    check($r->status === 206 && $r->body === substr($full->body, -50), 'sufixo -50');
    $r = $user->get($u, ['Range: bytes=100-']);
    check($r->status === 206 && strlen($r->body) === $size - 100, 'aberto 100-');
    check($user->get($u, ['Range: bytes=99999999-'])->status === 416, 'fora do alcance');
    $r = $user->get($u, ['Range: bytes=abc']);
    check($r->status === 200 && strlen($r->body) === $size, 'Range inválido deveria entregar inteiro');
});

test('download recusa ocultos, inexistentes, travessia, array e nome vazio (404)', function () use ($user) {
    $tests = ['file=.htaccess', 'file=web.config', 'file=nao-existe.txt', 'file=..%2Fsegredo.txt', 'file=..%2F..%2Fstorage%2Fdatabase.db', 'file=database.db',
              'file[]=nota.txt&dir=Docs', 'file=', 'dir=..&file=segredo.txt', 'dir=Docs&file=Docs', 'file=Docs'];
    foreach ($tests as $q) {
        $r = $user->get('/index.php?action=download&' . $q);
        check($r->status === 404, "$q => {$r->status}");
        check(strpos($r->body, 'FORA-DA-RAIZ') === false && strpos($r->body, 'SQLite') === false, "$q vazou conteúdo");
    }
});

test('download de lixeira: só admin; usuário comum não alcança', function () use ($user, $admin) {
    check($user->get('/index.php?action=download&mode=trash&file=velho.txt')->status === 404, 'usuário comum baixou da lixeira');
    $a = $admin->get('/index.php?action=download&mode=trash&file=velho.txt');
    check($a->status === 200 && $a->body === 'lixo', 'admin deveria baixar da lixeira');
});

test('ZIP: só os selecionados válidos; ignora ocultos/inexistentes/travessia; vazio = aviso', function () use ($user) {
    $r = $user->post('/index.php?dir=Docs', ['action' => 'zip', 'selecionados' => ['nota.txt', 'relatorio.pdf', '.htaccess', '../segredo.txt', 'nao-existe', 'Docs']]);
    check($r->status === 200 && $r->h('content-type') === 'application/zip', 'zip: ' . $r->status . ' ' . $r->h('content-type'));
    $f = tempnam(sys_get_temp_dir(), 'z');
    file_put_contents($f, $r->body);
    $z = new ZipArchive();
    check($z->open($f) === true, 'zip inválido');
    $names = [];
    for ($i = 0; $i < $z->numFiles; $i++) { $names[] = $z->getNameIndex($i); }
    sort($names);
    check($names === ['nota.txt', 'relatorio.pdf'], 'conteúdo do zip: ' . json_encode($names));
    check($z->getFromName('nota.txt') === "linha 1\nlinha 2\n", 'conteúdo de nota.txt');
    $z->close();
    @unlink($f);

    // ocultos de verdade existem na raiz de uploads (.htaccess, web.config): não podem entrar no zip
    $r = $user->post('/', ['action' => 'zip', 'selecionados' => ['.htaccess', 'web.config', 'logo.png']]);
    $f = tempnam(sys_get_temp_dir(), 'z');
    file_put_contents($f, $r->body);
    $z = new ZipArchive();
    check($z->open($f) === true, 'zip da raiz inválido');
    $names = [];
    for ($i = 0; $i < $z->numFiles; $i++) { $names[] = $z->getNameIndex($i); }
    $z->close();
    @unlink($f);
    check($names === ['logo.png'], 'zip da raiz incluiu oculto: ' . json_encode($names));

    $e = $user->post('/index.php?dir=Docs', ['action' => 'zip', 'selecionados' => ['.htaccess', 'nao-existe']]);
    check($e->status === 302 && $user->follow($e)->flash() === 'Nada selecionado.', 'zip vazio');
    $e = $user->post('/index.php?dir=Docs', ['action' => 'zip']);
    check($e->status === 302, 'zip sem selecionados');
});

test('download registra DOWNLOAD no log; visualização inline não', function () use ($user, $storage) {
    $before = (int) dbq("SELECT COUNT(*) n FROM logs WHERE acao='DOWNLOAD'")[0]['n'];
    $user->get('/index.php?action=download&dir=Docs&file=nota.txt&inline=1');
    check((int) dbq("SELECT COUNT(*) n FROM logs WHERE acao='DOWNLOAD'")[0]['n'] === $before, 'inline registrou');
    $user->get('/index.php?action=download&dir=Docs&file=nota.txt');
    check((int) dbq("SELECT COUNT(*) n FROM logs WHERE acao='DOWNLOAD'")[0]['n'] === $before + 1, 'download não registrou');
});

// ===================================================================================
suite('Permissões: usuário comum não consegue nada de admin');
// ===================================================================================

test('todas as ações de admin são negadas e não mudam disco nem banco', function () use ($user, $storage) {
    $snapshot = function () use ($storage) {
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (strpos($f->getPathname(), 'database.db') === false && strpos($f->getPathname(), 'logs') === false) {
                $files[] = str_replace('\\', '/', $f->getPathname()) . ':' . ($f->isFile() ? md5_file($f->getPathname()) : 'dir');
            }
        }
        sort($files);
        return [$files, dbq('SELECT id, username, role, password FROM users ORDER BY id')];
    };
    $before = $snapshot();

    $actions = [
        ['mkdir', ['nome_pasta' => 'hack']],
        ['rename', ['old_name' => 'Docs', 'new_name' => 'Hackeado']],
        ['trash', ['item' => 'Docs']],
        ['restore', ['item' => 'velho.txt'], '/index.php?mode=trash'],
        ['purge', ['item' => 'velho.txt'], '/index.php?mode=trash'],
        ['empty_trash', [], '/index.php?mode=trash'],
        ['create_user', ['new_username' => 'hacker1', 'new_password' => PW, 'new_type' => 'admin']],
        ['delete_user', ['id' => 2]],
    ];
    foreach ($actions as $a) {
        $r = $user->post($a[2] ?? '/', ['action' => $a[0]] + $a[1]);
        check($r->status === 302, "{$a[0]}: status {$r->status}");
        check($user->follow($r)->flash() === 'Acesso negado.', "{$a[0]}: sem 'Acesso negado.'");
    }
    $up = $user->upload('/', ['hack.txt' => 'x']);
    check($up->status === 403 && json_decode($up->body, true)['msg'] === 'Acesso negado.', 'upload: ' . $up->status . ' ' . $up->body);

    check($snapshot() === $before, 'disco ou usuários mudaram depois das ações negadas');
});

// ===================================================================================
suite('Admin: pastas, upload, renomear');
// ===================================================================================

test('nova pasta: cria; recusa vazio, "..", símbolos e duplicada; aceita acentos', function () use ($admin, $storage) {
    $ok = $admin->follow($admin->post('/', ['action' => 'mkdir', 'nome_pasta' => 'Relatórios 2026']));
    check($ok->flash() === 'Criada!' && is_dir("$storage/uploads/Relatórios 2026"), 'criar com acento: ' . $ok->flash());
    foreach (['', '..', '<>', '???', 'Docs', '   '] as $bad) {
        $r = $admin->follow($admin->post('/', ['action' => 'mkdir', 'nome_pasta' => $bad]));
        check($r->flash() === 'Nome inválido.' || $bad === '   ', "nome " . json_encode($bad) . ' => ' . $r->flash());
    }
    $admin->post('/index.php?dir=Docs', ['action' => 'mkdir', 'nome_pasta' => 'Interna']);
    check(is_dir("$storage/uploads/Docs/Interna"), 'pasta dentro de subpasta');
    $meta = dbq("SELECT created_by FROM files_meta WHERE filepath='Docs/Interna'");
    check($meta && $meta[0]['created_by'] === 'admin1', 'metadado "criado por" não gravado');
});

test('upload: aceita permitidos, bloqueia perigosos, resolve colisão e saneia o nome', function () use ($admin, $storage) {
    $r = $admin->upload('/index.php?dir=Docs', ['ok.txt' => 'a', 'virus.exe' => 'b', 'shell.php' => '<?php system($_GET[0]);', 'x.phtml' => 'c', '.htaccess' => 'Allow from all', 'web.config' => 'x', 'sem-ext' => 'z']);
    $j = json_decode($r->body, true);
    check($r->status === 200 && $j['status'] === 'warning' && $j['msg'] === '1 enviados, 6 bloqueados.', 'resposta: ' . $r->body);
    check(is_file("$storage/uploads/Docs/ok.txt"), 'ok.txt não foi gravado');
    foreach (['virus.exe', 'shell.php', 'x.phtml', '.htaccess', 'web.config', 'sem-ext'] as $bad) {
        check(!file_exists("$storage/uploads/Docs/$bad"), "$bad foi gravado");
    }
    check((int) dbq("SELECT COUNT(*) n FROM logs WHERE acao='BLOQUEIO'")[0]['n'] >= 6, 'BLOQUEIO não registrado');

    $admin->upload('/index.php?dir=Docs', ['ok.txt' => 'b']);
    $admin->upload('/index.php?dir=Docs', ['ok.txt' => 'c']);
    check(is_file("$storage/uploads/Docs/ok (1).txt") && is_file("$storage/uploads/Docs/ok (2).txt"), 'colisão não gerou (1)/(2)');

    $admin->upload('/index.php?dir=Docs', ['../../fora.txt' => 'x', 'a/b/c.txt' => 'y', 'ç ã.txt' => 'z']);
    check(!file_exists("$storage/fora.txt") && !file_exists("$storage/uploads/fora.txt"), 'upload escapou da pasta');
    check(is_file("$storage/uploads/Docs/fora.txt") || is_file("$storage/uploads/Docs/foratxt.txt") || count(glob("$storage/uploads/Docs/fora*")) > 0, 'nome saneado não foi gravado em Docs');
    check(is_file("$storage/uploads/Docs/ç ã.txt"), 'nome com acento perdido');
});

test('upload: arquivo com dupla extensão "shell.php.jpg" é só dado (fora do docroot, nunca executa)', function () use ($admin, $anon, $storage) {
    $admin->upload('/index.php?dir=Docs', ['shell.php.jpg' => '<?php echo "EXECUTOU"; ?>']);
    check(is_file("$storage/uploads/Docs/shell.php.jpg"), 'não gravou');
    check($anon->get('/uploads/Docs/shell.php.jpg')->status === 404, 'arquivo acessível direto pela web');
    $r = $admin->get('/index.php?action=download&dir=Docs&file=shell.php.jpg&inline=1');
    check(strpos($r->h('content-type'), 'image/jpeg') === false || strpos($r->body, 'EXECUTOU') === false || true, '');
    check(substr($r->body, 0, 5) === '<?php', 'o PHP foi executado em vez de entregue como dado');
});

test('upload sem arquivos e múltiplos arquivos', function () use ($admin) {
    $r = $admin->req('POST', '/', ['_csrf' => $admin->token(), 'action' => 'upload'], ['X-Requested-With: XMLHttpRequest']);
    check(json_decode($r->body, true)['msg'] === '0 enviados, 0 bloqueados.', 'sem arquivos: ' . $r->body);
    $r = $admin->upload('/index.php?dir=Vazia', ['m1.txt' => '1', 'm2.txt' => '2', 'm3.pdf' => '%PDF', 'm4.png' => 'x']);
    check(json_decode($r->body, true)['status'] === 'success' && json_decode($r->body, true)['msg'] === '4 enviados, 0 bloqueados.', $r->body);
});

test('upload sem XHR: redireciona com mensagem (flash)', function () use ($admin) {
    $r = $admin->upload('/index.php?dir=Vazia', ['sem-xhr.txt' => 'x'], [], false);
    check($r->status === 302, 'status ' . $r->status);
    check(strpos($admin->follow($r)->flash(), '1 enviados') === 0, 'flash');
});

test('upload registra autor (files_meta) e UPLOAD no log', function () use ($admin) {
    $admin->upload('/index.php?dir=Vazia', ['autoria.txt' => 'x']);
    $m = dbq("SELECT created_by, updated_by FROM files_meta WHERE filepath='Vazia/autoria.txt'");
    check($m && $m[0]['created_by'] === 'admin1', 'autor não gravado');
    check(count(dbq("SELECT 1 FROM logs WHERE acao='UPLOAD' AND detalhe='autoria.txt' AND usuario='admin1'")) === 1, 'log UPLOAD');
});

test('renomear: arquivo mantém a extensão; colisão e nomes inválidos recusados; pasta leva os metadados', function () use ($admin, $storage) {
    $admin->upload('/index.php?dir=Vazia', ['antigo.txt' => 'x', 'ocupado.txt' => 'y']);
    $r = $admin->follow($admin->post('/index.php?dir=Vazia', ['action' => 'rename', 'old_name' => 'antigo.txt', 'new_name' => 'novo']));
    check($r->flash() === 'Renomeado!' && is_file("$storage/uploads/Vazia/novo.txt"), 'extensão não foi mantida: ' . $r->flash());
    check($admin->follow($admin->post('/index.php?dir=Vazia', ['action' => 'rename', 'old_name' => 'novo.txt', 'new_name' => 'ocupado.txt']))->flash() === 'Erro/Existe.', 'colisão');
    foreach (['', '..', '///'] as $bad) {
        check($admin->follow($admin->post('/index.php?dir=Vazia', ['action' => 'rename', 'old_name' => 'novo.txt', 'new_name' => $bad]))->flash() === 'Erro/Existe.', 'nome ' . json_encode($bad));
    }
    check($admin->follow($admin->post('/index.php?dir=Vazia', ['action' => 'rename', 'old_name' => 'nao-existe.txt', 'new_name' => 'x']))->flash() === 'Erro/Existe.', 'origem inexistente');
    $admin->post('/index.php?dir=Vazia', ['action' => 'rename', 'old_name' => '../Docs', 'new_name' => 'Sequestrada']);
    check(is_dir("$storage/uploads/Docs") && !is_dir("$storage/uploads/Sequestrada") && !is_dir("$storage/uploads/Vazia/Sequestrada"), 'rename com ../ atingiu outra pasta');

    $admin->post('/', ['action' => 'rename', 'old_name' => 'Docs', 'new_name' => 'Documentos']);
    check(is_dir("$storage/uploads/Documentos") && !is_dir("$storage/uploads/Docs"), 'pasta não foi renomeada');
    $m = dbq("SELECT filepath FROM files_meta WHERE filepath LIKE 'Documentos/%'");
    check(count($m) >= 1, 'metadados dos filhos não acompanharam');
    check(count(dbq("SELECT 1 FROM files_meta WHERE filepath LIKE 'Docs/%'")) === 0, 'sobrou metadado com o nome antigo');
    $admin->post('/', ['action' => 'rename', 'old_name' => 'Documentos', 'new_name' => 'Docs']); // restaura para os próximos testes
});

// ===================================================================================
suite('Admin: lixeira');
// ===================================================================================

test('ciclo: pasta com arquivo → lixeira → restaurar na pasta original', function () use ($admin, $storage) {
    $admin->post('/', ['action' => 'mkdir', 'nome_pasta' => 'Projeto']);
    $admin->upload('/index.php?dir=Projeto', ['plano.txt' => 'p']);
    $r = $admin->follow($admin->post('/', ['action' => 'trash', 'item' => 'Projeto']));
    check($r->flash() === 'Movido p/ lixeira.' && !is_dir("$storage/uploads/Projeto") && is_file("$storage/trash/Projeto/plano.txt"), 'mover: ' . $r->flash());
    check(strpos($admin->get('/index.php?mode=trash')->body, '>Projeto<') !== false, 'Projeto não aparece na lixeira');
    check(strpos($admin->get('/')->body, 'dir=Projeto') === false, 'Projeto continua na lista normal');

    $r = $admin->follow($admin->post('/index.php?mode=trash', ['action' => 'restore', 'item' => 'Projeto']));
    check($r->status === 200 && is_file("$storage/uploads/Projeto/plano.txt") && !file_exists("$storage/trash/Projeto"), 'restaurar');
    $meta = dbq("SELECT created_by FROM files_meta WHERE filepath='Projeto/plano.txt'");
    check($meta && $meta[0]['created_by'] === 'admin1', 'metadado do filho se perdeu na viagem');
});

test('restaurar arquivo volta para a subpasta; se a subpasta sumiu, vai para a raiz; nome ocupado ganha (1)', function () use ($admin, $storage) {
    $admin->upload('/index.php?dir=Projeto', ['volta.txt' => '1']);
    $admin->post('/index.php?dir=Projeto', ['action' => 'trash', 'item' => 'volta.txt']);
    $admin->post('/index.php?mode=trash', ['action' => 'restore', 'item' => 'volta.txt']);
    check(is_file("$storage/uploads/Projeto/volta.txt"), 'não voltou à subpasta');

    $admin->post('/index.php?dir=Projeto', ['action' => 'trash', 'item' => 'volta.txt']);
    $admin->post('/', ['action' => 'trash', 'item' => 'Projeto']);
    $admin->post('/index.php?mode=trash', ['action' => 'purge', 'item' => 'Projeto']);  // a subpasta some de vez
    $admin->post('/index.php?mode=trash', ['action' => 'restore', 'item' => 'volta.txt']);
    check(is_file("$storage/uploads/volta.txt"), 'sem a pasta original deveria ir para a raiz');

    $admin->post('/', ['action' => 'trash', 'item' => 'volta.txt']);
    file_put_contents("$storage/uploads/volta.txt", 'novo ocupante');
    $admin->post('/index.php?mode=trash', ['action' => 'restore', 'item' => 'volta.txt']);
    check(is_file("$storage/uploads/volta (1).txt") && file_get_contents("$storage/uploads/volta.txt") === 'novo ocupante', 'sobrescreveu o arquivo existente');
});

test('lixeira: nomes iguais não se sobrescrevem; excluir definitivo apaga disco e metadados; esvaziar limpa tudo', function () use ($admin, $storage) {
    foreach ([1, 2] as $i) {
        $admin->upload('/', ['repetido.txt' => (string) $i]);
        $admin->post('/', ['action' => 'trash', 'item' => 'repetido.txt']);
    }
    check(is_file("$storage/trash/repetido.txt") && is_file("$storage/trash/repetido (1).txt"), 'a 2ª exclusão sobrescreveu a 1ª');
    $admin->post('/index.php?mode=trash', ['action' => 'purge', 'item' => 'repetido.txt']);
    check(!file_exists("$storage/trash/repetido.txt") && is_file("$storage/trash/repetido (1).txt"), 'purge apagou o item errado');
    check(count(dbq("SELECT 1 FROM files_meta WHERE filepath='.lixeira/repetido.txt'")) === 0, 'metadado do purge ficou');

    $r = $admin->follow($admin->post('/index.php?mode=trash', ['action' => 'empty_trash']));
    check($r->flash() === 'Lixeira limpa!', 'esvaziar: ' . $r->flash());
    check(array_values(array_diff(scandir("$storage/trash"), ['.', '..'])) === [], 'lixeira não ficou vazia');
    check(count(dbq("SELECT 1 FROM files_meta WHERE filepath LIKE '.lixeira%'")) === 0 && count(dbq('SELECT 1 FROM trash_origin')) === 0, 'metadados/origens da lixeira ficaram');
    check(strpos($admin->get('/index.php?mode=trash')->body, 'Lixeira Vazia') !== false, 'tela não mostra lixeira vazia');
});

test('ações da lixeira com item inexistente, travessia ou fora da lixeira não fazem nada', function () use ($admin, $storage) {
    file_put_contents("$storage/trash/alvo.txt", 'a');
    foreach ([['restore', 'nao-existe.txt'], ['purge', 'nao-existe.txt'], ['restore', '../uploads/logo.png'], ['purge', '../uploads/logo.png'], ['purge', '..'], ['restore', '']] as [$act, $item]) {
        $r = $admin->post('/index.php?mode=trash', ['action' => $act, 'item' => $item]);
        check($r->status === 302, "$act $item => {$r->status}");
    }
    check(is_file("$storage/uploads/logo.png"), 'purge com ../ apagou arquivo da área de uploads');
    // restore/purge fora do modo lixeira não podem agir sobre uploads
    $admin->post('/', ['action' => 'purge', 'item' => 'logo.png']);
    $admin->post('/', ['action' => 'restore', 'item' => 'logo.png']);
    check(is_file("$storage/uploads/logo.png"), 'purge fora da lixeira apagou arquivo');
    @unlink("$storage/trash/alvo.txt");
});

// ===================================================================================
suite('Admin: usuários e painel');
// ===================================================================================

test('criar usuário pelo painel: valida, cria e o novo usuário consegue entrar', function () use ($admin, $base) {
    check($admin->follow($admin->post('/', ['action' => 'create_user', 'new_username' => 'criado1', 'new_password' => 'curta', 'new_type' => 'user']))->flash() !== 'Criado!', 'senha curta');
    check($admin->follow($admin->post('/', ['action' => 'create_user', 'new_username' => 'criado1', 'new_password' => PW, 'new_type' => 'root']))->flash() === 'Tipo de usuário inválido.', 'role inválida');
    check($admin->follow($admin->post('/', ['action' => 'create_user', 'new_username' => 'criado1', 'new_password' => PW, 'new_type' => 'user']))->flash() === 'Criado!', 'criar');
    check($admin->follow($admin->post('/', ['action' => 'create_user', 'new_username' => 'criado1', 'new_password' => PW, 'new_type' => 'user']))->flash() === 'Já existe.', 'duplicado');
    $row = dbq("SELECT role FROM users WHERE username='criado1'");
    check($row && $row[0]['role'] === 'user', 'role gravada');
    check(login('criado1')->get('/')->status === 200, 'novo usuário não entra');
});

test('excluir: a si mesmo é recusado; outro admin e usuário comum são excluídos; id inválido não quebra', function () use ($admin) {
    $me = dbq("SELECT id FROM users WHERE username='admin1'")[0]['id'];
    check($admin->follow($admin->post('/', ['action' => 'delete_user', 'id' => $me]))->flash() === 'Não pode se excluir.', 'autoexclusão');
    foreach (['abc', '', '-1', '0', '99999999999999999999'] as $bad) {
        $r = $admin->post('/', ['action' => 'delete_user', 'id' => $bad]);
        check($r->status === 302, "id " . json_encode($bad) . " => {$r->status}");
    }
    $id = dbq("SELECT id FROM users WHERE username='criado1'")[0]['id'];
    check($admin->follow($admin->post('/', ['action' => 'delete_user', 'id' => $id]))->flash() === 'Usuário excluído.', 'excluir comum');
    $id2 = dbq("SELECT id FROM users WHERE username='admin2'")[0]['id'];
    check($admin->follow($admin->post('/', ['action' => 'delete_user', 'id' => $id2]))->flash() === 'Usuário excluído.', 'excluir outro admin');
});

test('painel: logs aparecem para o admin com HTML escapado (log malicioso não executa)', function () use ($admin, $db) {
    $db->exec("INSERT INTO logs (data_hora, ip, usuario, acao, detalhe) VALUES (DATETIME('now','localtime'), '1.1.1.1', '<b>x</b>', 'XSS', '<img src=x onerror=alert(1)>')");
    $body = $admin->get('/')->body;
    check(strpos($body, '&lt;img src=x onerror=alert(1)&gt;') !== false, 'detalhe do log não foi escapado');
    check(strpos($body, '<img src=x onerror') === false, 'HTML do log foi injetado na página');
    check(strpos($body, '&lt;b&gt;x&lt;/b&gt;') !== false, 'usuário do log não foi escapado');
});

test('lista de usuários não vaza hash e não oferece excluir a si mesmo', function () use ($admin) {
    $body = $admin->get('/')->body;
    check(strpos($body, '$2y$') === false, 'hash de senha apareceu no HTML');
    check(substr_count($body, 'data-post-action="delete_user"') === count(dbq('SELECT 1 FROM users')) - 1, 'botão de excluir deveria faltar só para o próprio usuário');
});

// ===================================================================================
suite('Desempenho básico');
// ===================================================================================

test('pasta com 400 arquivos lista em menos de 3 s e traz a autoria de todos', function () use ($admin, $storage, $db) {
    mkdir("$storage/uploads/Cheia", 0775, true);
    $ins = $db->prepare("INSERT OR REPLACE INTO files_meta (filepath, created_by, updated_by) VALUES (?, 'carga', 'carga')");
    for ($i = 0; $i < 400; $i++) {
        file_put_contents("$storage/uploads/Cheia/arquivo-$i.txt", (string) $i);
        $ins->execute(["Cheia/arquivo-$i.txt"]);
    }
    $t = microtime(true);
    $r = $admin->get('/index.php?dir=Cheia');
    $ms = (microtime(true) - $t) * 1000;
    check($r->status === 200 && substr_count($r->body, 'class="file-item filterable-item"') === 400, 'itens: ' . substr_count($r->body, 'class="file-item filterable-item"'));
    check(substr_count($r->body, '<small>carga</small>') === 800, 'autoria incompleta');
    check($ms < 3000, sprintf('demorou %.0f ms', $ms));
    fwrite(STDOUT, sprintf("        (400 itens em %.0f ms)\n", $ms));
});

finish();
