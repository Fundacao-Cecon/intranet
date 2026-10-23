<?php
/**
 * Restaura um backup (de bin/backup.php) numa pasta NOVA ou vazia. Nunca sobrescreve um
 * storage existente: para voltar a produção, aponte INTRANET_STORAGE para a pasta restaurada
 * (ou mova-a para o lugar de storage/ com o sistema parado).
 *
 *   php bin/restore.php <backup> <pasta-nova>
 */

if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Backup;

if (count($argv) < 3) {
    fwrite(STDERR, "Uso: php bin/restore.php <backup> <pasta-nova>\n"); exit(2);
}

try {
    $m = Backup::restore($argv[1], $argv[2]);
    printf("OK: restaurado em %s\n    %d arquivos; %d usuários, %d registros de autoria.\n    Para usar: INTRANET_STORAGE=%s\n",
        $argv[2], count($m['files']), $m['counts']['users'], $m['counts']['files_meta'], $argv[2]);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
    exit(1);
}
