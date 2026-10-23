<?php
/**
 * auto_prepend_file das medições de cobertura (PCOV). Só age se COVERAGE_DIR estiver
 * definido e a extensão pcov carregada; ao final do processo (ou de cada requisição do
 * servidor embutido) grava um JSON {arquivo: {linha: 1|-1}} em COVERAGE_DIR.
 * Veja tests/coverage.php.
 */

$__dir = getenv('COVERAGE_DIR');
if ($__dir && function_exists('pcov\\start')) {
    \pcov\start();
    register_shutdown_function(function () use ($__dir) {
        \pcov\stop();
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src', FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (substr($f->getFilename(), -4) === '.php') {
                $files[] = $f->getRealPath();
            }
        }
        $raw = \pcov\collect(\pcov\inclusive, $files);
        \pcov\clear();

        $out = [];
        foreach ($raw as $file => $lines) {
            foreach ($lines as $n => $state) {
                if ($state === 1 || $state === -1) { // 1 = executada, -1 = executável e não executada
                    $out[str_replace('\\', '/', $file)][$n] = $state;
                }
            }
        }
        if ($out) {
            @file_put_contents($__dir . '/' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.json', json_encode($out));
        }
    });
}
unset($__dir);
