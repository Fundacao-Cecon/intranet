<?php
/**
 * Backup consistente do storage (banco, uploads e lixeira), com o sistema ligado.
 *
 *   php bin/backup.php [pasta-destino]      cria um backup (padrão: <pasta do storage>/../backups/intranet-AAAAMMDD-HHMMSS)
 *   php bin/backup.php --verify <backup>    confere um backup existente contra o manifest.json
 *
 * Códigos de saída: 0 ok, 1 problema.
 */

if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}

$config = require dirname(__DIR__) . '/src/bootstrap.php';

use App\Backup;

try {
    if (($argv[1] ?? '') === '--verify') {
        $dir = $argv[2] ?? '';
        if ($dir === '') { fwrite(STDERR, "Uso: php bin/backup.php --verify <backup>\n"); exit(2); }
        $problems = Backup::verify($dir);
        if ($problems) {
            echo "BACKUP COM PROBLEMAS:\n - " . implode("\n - ", $problems) . "\n";
            exit(1);
        }
        echo "OK: backup íntegro ($dir)\n";
        exit(0);
    }

    $dest = $argv[1] ?? dirname($config['paths']['storage']) . '/backups/intranet-' . date('Ymd-His');
    $m = Backup::create($config['paths'], $dest);
    $problems = Backup::verify($dest);
    if ($problems) {
        echo "BACKUP CRIADO MAS NÃO PASSOU NA CONFERÊNCIA:\n - " . implode("\n - ", $problems) . "\n";
        exit(1);
    }
    printf("OK: %s\n    %d arquivos, %.1f MB; %d usuários, %d registros de autoria, %d logs.\n    Conferido contra o manifest.json.\n",
        $dest, count($m['files']), $m['bytes'] / 1048576, $m['counts']['users'], $m['counts']['files_meta'], $m['counts']['logs']);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
    exit(1);
}
