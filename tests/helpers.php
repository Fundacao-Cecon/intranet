<?php
/**
 * Mini framework de testes compartilhado pelas suítes (sem dependências).
 *
 *   check($cond, $msg)   falha o teste atual se $cond for falso
 *   test($nome, $fn)     roda um teste isolado (exceção = falha)
 *   finish()             imprime o resumo e sai com código 1 se algo falhou
 */

if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}

$GLOBALS['__tests'] = ['total' => 0, 'failed' => 0, 'pending' => 0, 'surprise' => 0];

function check(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests']['total']++;
    try {
        $fn();
        fwrite(STDOUT, "  ok    $name\n");
    } catch (Throwable $e) {
        $GLOBALS['__tests']['failed']++;
        fwrite(STDOUT, "  FALHA $name\n        " . str_replace("\n", "\n        ", $e->getMessage()) . "\n");
    }
}

/**
 * Teste escrito ANTES da funcionalidade (TDD). Enquanto falhar, aparece como "pend" e NÃO
 * reprova a suíte; quando passar, avisa "PASSOU" para você trocar `todo()` por `test()`.
 */
function todo(string $name, callable $fn): void
{
    try {
        $fn();
        $GLOBALS['__tests']['surprise']++;
        fwrite(STDOUT, "  PASSOU (todo) $name\n        a funcionalidade já existe: troque todo() por test()\n");
    } catch (Throwable $e) {
        $GLOBALS['__tests']['pending']++;
        $first = strtok($e->getMessage(), "\n");
        fwrite(STDOUT, "  pend  $name\n        ainda não implementado: " . $first . "\n");
    }
}
function suite(string $title): void
{
    fwrite(STDOUT, "\n$title\n");
}

function finish(): void
{
    $t = $GLOBALS['__tests'];
    fwrite(STDOUT, "\n" . ($t['total'] - $t['failed']) . " de {$t['total']} testes passaram." . ($t['pending'] ? " {$t['pending']} pendentes (todo)." : '') . "\n");
    exit($t['failed'] ? 1 : 0);
}

/**
 * Flags do PHP para medir cobertura (PCOV) em processos filhos. Vazio fora de tests/coverage.php.
 * Cada filho grava seu JSON em COVERAGE_DIR (ver tests/coverage_prepend.php).
 *
 * @return string[]
 */
function coverageFlags(): array
{
    if (!getenv('COVERAGE_DIR')) {
        return [];
    }
    return ['-d', 'extension=pcov', '-d', 'display_startup_errors=0', '-d', 'pcov.enabled=1', '-d', 'pcov.directory=' . dirname(__DIR__) . '/src',
            '-d', 'auto_prepend_file=' . __DIR__ . '/coverage_prepend.php'];
}

/** Pasta temporária única. */
function mktmp(string $prefix = 'intranet-tests-'): string
{
    $d = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(4));
    mkdir($d, 0775, true);
    return str_replace('\\', '/', $d);
}

/** Apaga uma pasta recursivamente. */
function rrm(string $d): void
{
    if (!is_dir($d)) {
        return;
    }
    foreach (scandir($d) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        is_dir("$d/$f") && !is_link("$d/$f") ? rrm("$d/$f") : @unlink("$d/$f");
    }
    @rmdir($d);
}

/**
 * Roda $n trabalhadores (tests/fixtures/race_worker.php) AO MESMO TEMPO, com largada por arquivo.
 *
 * @param callable $argsFor fn(int $i, string $flag): string[]  argumentos do worker
 * @return array[] cada item: ['out' => stdout, 'err' => stderr, 'code' => int]
 */
function parallel(int $n, callable $argsFor): array
{
    $flag = sys_get_temp_dir() . '/intranet-go-' . bin2hex(random_bytes(4));
    $procs = [];
    $pipes = [];
    for ($i = 0; $i < $n; $i++) {
        $cmd = array_merge([PHP_BINARY], coverageFlags(), [__DIR__ . '/fixtures/race_worker.php'], $argsFor($i, $flag));
        $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
    }
    usleep(400000);           // todos já estão esperando na largada
    touch($flag);
    $res = [];
    foreach ($procs as $i => $p) {
        $out = stream_get_contents($pipes[$i][1]);
        $err = stream_get_contents($pipes[$i][2]);
        $res[$i] = ['out' => $out, 'err' => $err, 'code' => proc_close($p)];
    }
    @unlink($flag);
    return $res;
}