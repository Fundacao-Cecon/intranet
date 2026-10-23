<?php
/**
 * Mede a cobertura de linhas de src/ rodando todas as suítes sob PCOV.
 *
 *   php -d extension=pcov tests/coverage.php [--min=85] [--only=unit,http] [--lines]
 *
 *   --min=N     falha (código 1) se a cobertura total ficar abaixo de N %
 *   --only=...  só essas suítes (login, unit, http)
 *   --lines     lista as linhas não cobertas de cada arquivo
 *
 * Arquivos críticos (P0) têm piso próprio, definido em $critical.
 */

if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
if (!extension_loaded('pcov')) {
    fwrite(STDERR, "PCOV não está carregado. Use: php -d extension=pcov tests/coverage.php\n");
    exit(2);
}

$opts = getopt('', ['min::', 'only::', 'lines']);
$min = isset($opts['min']) ? (float) $opts['min'] : 0.0;
$only = isset($opts['only']) ? explode(',', $opts['only']) : ['login', 'unit', 'http', 'conc', 'backup'];
$showLines = isset($opts['lines']);

// Pisos por arquivo (caminho relativo a src/). Arquivos sem piso só contam no total.
$critical = [
    'Services/FileStorage.php'        => 95,
    'Services/FileMeta.php'           => 95,
    'Csrf.php'                        => 95,
    'Auth.php'                        => 95,
    'Application.php'                 => 95,
    'Backup.php'                      => 85,
    'Controllers/FilesController.php' => 90,
    'Controllers/TrashController.php' => 90,
];

$suites = ['login' => 'run.php', 'unit' => 'unit_test.php', 'http' => 'http_test.php', 'conc' => 'concurrency_test.php', 'backup' => 'backup_test.php'];
$dir = sys_get_temp_dir() . '/intranet-cov-' . bin2hex(random_bytes(4));
mkdir($dir, 0775, true);

putenv('COVERAGE_DIR=' . str_replace('\\', '/', $dir));
$flags = ['-d', 'extension=pcov', '-d', 'display_startup_errors=0', '-d', 'pcov.enabled=1', '-d', 'pcov.directory=' . dirname(__DIR__) . '/src',
          '-d', 'auto_prepend_file=' . __DIR__ . '/coverage_prepend.php'];

$exit = 0;
foreach ($only as $key) {
    if (!isset($suites[$key])) {
        fwrite(STDERR, "Suíte desconhecida: $key\n");
        exit(2);
    }
    echo "== suíte $key ==\n";
    $cmd = array_merge([PHP_BINARY], $flags, [__DIR__ . '/' . $suites[$key]]);
    $p = proc_open($cmd, [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    $code = proc_close($p);
    preg_match('/\d+ de \d+ testes passaram\./', $out, $m);
    echo '   ' . ($m[0] ?? 'sem resumo') . ($code ? "  (código $code)" : '') . "\n";
    $exit = $exit ?: $code;
}

// --- mescla: uma linha conta como coberta se QUALQUER processo a executou ---------------------
$merged = [];
foreach (glob($dir . '/*.json') as $f) {
    foreach (json_decode(file_get_contents($f), true) ?: [] as $file => $lines) {
        foreach ($lines as $n => $state) {
            $cur = $merged[$file][$n] ?? -1;
            $merged[$file][$n] = max($cur, $state);
        }
    }
    @unlink($f);
}
@rmdir($dir);

$srcRoot = str_replace('\\', '/', dirname(__DIR__)) . '/src/';
ksort($merged);
$totalExec = $totalCov = 0;
$failed = [];

printf("\n%-34s %7s %9s %s\n", 'Arquivo (src/)', 'Linhas', 'Cobertas', 'Cobertura');
echo str_repeat('-', 70) . "\n";
foreach ($merged as $file => $lines) {
    $rel = str_replace($srcRoot, '', $file);
    $exec = count($lines);
    $cov = count(array_filter($lines, function ($s) { return $s === 1; }));
    $pct = $exec ? 100 * $cov / $exec : 100;
    $totalExec += $exec;
    $totalCov += $cov;

    $floor = $critical[$rel] ?? null;
    $flag = ($floor !== null && $pct + 1e-9 < $floor) ? "  < piso {$floor}%" : '';
    if ($flag) { $failed[] = "$rel ({$floor}%)"; }
    printf("%-34s %7d %9d %8.1f%%%s\n", $rel, $exec, $cov, $pct, $flag);

    if ($showLines) {
        $miss = array_keys(array_filter($lines, function ($s) { return $s === -1; }));
        sort($miss);
        $ranges = [];
        foreach ($miss as $n) {
            if ($ranges && $n === end($ranges)[1] + 1) { $ranges[count($ranges) - 1][1] = $n; } else { $ranges[] = [$n, $n]; }
        }
        if ($ranges) {
            echo '    não cobertas: ' . implode(', ', array_map(function ($r) { return $r[0] === $r[1] ? $r[0] : "{$r[0]}-{$r[1]}"; }, $ranges)) . "\n";
        }
    }
}
$total = $totalExec ? 100 * $totalCov / $totalExec : 0;
echo str_repeat('-', 70) . "\n";
printf("%-34s %7d %9d %8.1f%%\n", 'TOTAL', $totalExec, $totalCov, $total);

if ($min > 0 && $total < $min) {
    echo "\nFALHOU: cobertura total {$total}% abaixo da meta de {$min}%.\n";
    $exit = 1;
}
if ($failed) {
    echo "\nFALHOU: abaixo do piso: " . implode(', ', $failed) . "\n";
    $exit = 1;
}
exit($exit);
