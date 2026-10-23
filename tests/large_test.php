<?php
/**
 * Upload e download de arquivo GRANDE com o servidor limitado a 64 MB de memória: prova que
 * nada é carregado inteiro na RAM (upload em disco, download em blocos, Range correto).
 * Fica de fora do `all.php` padrão (demora e usa disco): rode com
 *
 *   php tests/large_test.php [--mb=300]        ou     php tests/all.php large
 */

require __DIR__ . '/helpers.php';
$config = require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/http_client.php';

use App\Auth;
use App\Database;
use App\Logger;

$opts = getopt('', ['mb::']);
$mb = max(5, (int) ($opts['mb'] ?? 300));

$tmp = mktmp('intranet-large-');
register_shutdown_function(function () use ($tmp) { rrm($tmp); });
require __DIR__ . '/http_server.php';
const PW = 'Senha-Teste-123';

$storage = "$tmp/storage";
mkdir("$tmp/sessions", 0775, true);
mkdir($storage, 0775, true);
$db = Database::connect("$storage/database.db");
(new Auth($db, new Logger($db), $config['auth']))->createUser('grande_admin', PW, 'admin');

/** Arquivo de teste: blocos de 1 MB não repetitivos (índice + aleatório), para pegar truncamento/embaralhamento. */
function makeBigFile(string $path, int $mb): string
{
    $h = fopen($path, 'wb');
    $ctx = hash_init('sha256');
    for ($i = 0; $i < $mb; $i++) {
        $block = pack('J', $i) . random_bytes(1048576 - 8);
        fwrite($h, $block);
        hash_update($ctx, $block);
    }
    fclose($h);
    return hash_final($ctx);
}

fwrite(STDOUT, "Gerando arquivo de $mb MB...\n");
$src = "$tmp/origem.bin.zip";
$sha = makeBigFile($src, $mb);
$size = $mb * 1048576;

$base = startServer($storage, ['post_max_size=2100M', 'upload_max_filesize=2048M', 'memory_limit=64M', 'max_execution_time=900', 'max_input_time=900']);
$c = new Client($base);
$c->timeout = 900;
$c->follow($c->post('/', ['action' => 'login', 'user' => 'grande_admin', 'pass' => PW]));

suite("Arquivo de $mb MB com memory_limit=64M no servidor");

test("upload de $mb MB conclui, o arquivo em disco tem o mesmo tamanho e SHA-256", function () use ($c, $src, $storage, $sha, $size, $mb) {
    $t = microtime(true);
    $r = $c->uploadPaths('/', ['grande.zip' => $src]);
    $s = microtime(true) - $t;
    $j = json_decode($r->body, true);
    check($r->status === 200 && $j && $j['status'] === 'success' && $j['msg'] === '1 enviados, 0 bloqueados.', 'resposta: ' . $r->status . ' ' . substr($r->body, 0, 200));
    check(is_file("$storage/uploads/grande.zip") && filesize("$storage/uploads/grande.zip") === $size, 'tamanho no servidor: ' . @filesize("$storage/uploads/grande.zip"));
    check(hash_file('sha256', "$storage/uploads/grande.zip") === $sha, 'conteúdo diferente do enviado');
    fwrite(STDOUT, sprintf("        (%.0f MB em %.1f s = %.0f MB/s)\n", $mb, $s, $mb / max($s, 0.001)));
});

test('download completo em blocos: mesmo tamanho e SHA-256, Content-Length correto', function () use ($c, $tmp, $sha, $size, $mb) {
    $out = "$tmp/baixado.bin";
    $t = microtime(true);
    $r = $c->downloadTo('/index.php?action=download&file=grande.zip', $out);
    $s = microtime(true) - $t;
    check($r->status === 200 && (int) ($r->headers['content-length'] ?? -1) === $size, 'status/Content-Length: ' . $r->status . ' ' . ($r->headers['content-length'] ?? '?'));
    check(filesize($out) === $size && hash_file('sha256', $out) === $sha, 'arquivo baixado difere do original');
    unlink($out);
    fwrite(STDOUT, sprintf("        (%.0f MB em %.1f s = %.0f MB/s)\n", $mb, $s, $mb / max($s, 0.001)));
});

test('Range no meio do arquivo grande devolve exatamente os bytes pedidos', function () use ($c, $tmp, $src, $size) {
    $start = intdiv($size, 2) + 12345;
    $len = 5 * 1048576 + 7;
    $out = "$tmp/trecho.bin";
    $r = $c->downloadTo('/index.php?action=download&file=grande.zip&inline=0', $out, ['Range: bytes=' . $start . '-' . ($start + $len - 1)]);
    check($r->status === 206 && ($r->headers['content-range'] ?? '') === 'bytes ' . $start . '-' . ($start + $len - 1) . '/' . $size, 'cabeçalhos: ' . $r->status . ' ' . ($r->headers['content-range'] ?? ''));
    $h = fopen($src, 'rb');
    fseek($h, $start);
    $esperado = fread($h, $len);
    fclose($h);
    check(file_get_contents($out) === $esperado, 'bytes do trecho diferem');
    $r2 = $c->downloadTo('/index.php?action=download&file=grande.zip', $out, ['Range: bytes=-1000']);
    check($r2->status === 206 && filesize($out) === 1000, 'sufixo final de 1000 bytes');
    unlink($out);
});

test('após o upload grande o sistema continua respondendo e o ZIP de seleção funciona', function () use ($c) {
    $home = $c->get('/');
    check($home->status === 200 && strpos($home->body, 'grande.zip') !== false, 'listagem');
    $z = $c->post('/', ['action' => 'zip', 'selecionados' => ['grande.zip']]);
    check($z->status === 200 && $z->h('content-type') === 'application/zip' && strlen($z->body) > 1000000, 'ZIP do arquivo grande: ' . $z->status);
});

finish();
