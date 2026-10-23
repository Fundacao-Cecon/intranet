<?php
/**
 * Roda todas as suítes, cada uma em um processo próprio, e resume.
 *
 *   php tests/all.php            tudo
 *   php tests/all.php unit http  só as suítes citadas
 */

if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}

$suites = [
    'login' => ['run.php',        'Login (sessão, tempo, logs, tela)'],
    'unit'  => ['unit_test.php',  'Unitários (storage, meta, auth, csrf, logger)'],
    'http'  => ['http_test.php',  'Integração HTTP (sistema no ar)'],
];

$want = array_slice($argv, 1) ?: array_keys($suites);
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
