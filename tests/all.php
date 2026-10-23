<?php
/**
 * Roda todas as suítes, cada uma em um processo próprio, e resume.
 *
 *   php tests/all.php                  suítes padrão
 *   php tests/all.php unit http        só as suítes citadas
 *   php tests/all.php large            inclui a de arquivo grande (opt-in: lenta)
 *   php tests/all.php --list           lista as suítes
 */

if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}

// chave => [arquivo, título, padrão?]
$suites = [
    'login'  => ['run.php',              'Login (sessão, tempo, logs, tela)',                    true],
    'unit'   => ['unit_test.php',        'Unitários (storage, meta, auth, csrf, logger)',        true],
    'http'   => ['http_test.php',        'Integração HTTP (sistema no ar)',                      true],
    'conc'   => ['concurrency_test.php', 'Concorrência (processos paralelos)',                   true],
    'backup' => ['backup_test.php',      'Backup e restauração (com o sistema ligado)',          true],
    'smoke'  => ['smoke_test.php',       'bin/smoke.php contra instalação certa e errada',        true],
    'js'     => ['js_test.php',          'JavaScript e acessibilidade (jsdom + axe, exige Node)', true],
    'p2'     => ['pending_phase2_test.php', 'ADR-0005 Fase 2: testes escritos antes do código (todo)', true],
    'large'  => ['large_test.php',       'Arquivo grande (opt-in, lento)',                       false],
];

if (in_array('--list', $argv, true)) {
    foreach ($suites as $k => [$f, $t, $d]) { printf("%-8s %s%s\n", $k, $t, $d ? '' : '  [fora do padrão]'); }
    exit(0);
}

$want = array_values(array_filter(array_slice($argv, 1), function ($a) { return $a[0] !== '-'; }));
if (!$want) {
    $want = array_keys(array_filter($suites, function ($s) { return $s[2]; }));
}

$failed = [];
$start = microtime(true);

foreach ($want as $key) {
    if (!isset($suites[$key])) {
        fwrite(STDERR, "Suíte desconhecida: $key (use: " . implode(', ', array_keys($suites)) . ")\n");
        exit(2);
    }
    [$file, $title] = $suites[$key];
    echo str_repeat('=', 78) . "\n$title\n" . str_repeat('=', 78) . "\n";
    $t = microtime(true);
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $file), $code);
    printf("[%s] código %d em %.1fs\n\n", $key, $code, microtime(true) - $t);
    if ($code !== 0) {
        $failed[] = $key;
    }
}

printf("Total: %.1fs — %s\n", microtime(true) - $start, $failed ? 'FALHOU: ' . implode(', ', $failed) : 'tudo passou');
exit($failed ? 1 : 0);
