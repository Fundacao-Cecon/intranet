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

$GLOBALS['__tests'] = ['total' => 0, 'failed' => 0];

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

function suite(string $title): void
{
    fwrite(STDOUT, "\n$title\n");
}

function finish(): void
{
    $t = $GLOBALS['__tests'];
    fwrite(STDOUT, "\n" . ($t['total'] - $t['failed']) . " de {$t['total']} testes passaram.\n");
    exit($t['failed'] ? 1 : 0);
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
