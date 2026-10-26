<?php
namespace App;

use PDO;
use RuntimeException;

/**
 * Backup e restauração do storage (banco + uploads + lixeira).
 *
 *  - O banco é copiado de forma consistente COM O SISTEMA LIGADO (VACUUM INTO; em SQLite
 *    antigo, cópia do arquivo sob trava de escrita).
 *  - Cada backup traz um manifest.json (tamanho e SHA-256 de tudo) que permite conferir
 *    a integridade antes de restaurar.
 *  - Logs (storage/logs) ficam de fora: são regeneráveis.
 */
final class Backup
{
    const MANIFEST = 'manifest.json';

    /**
     * @param array  $paths ['uploads','trash','database'] (como em config['paths'])
     * @return array manifest
     */
    public static function create(array $paths, string $dest): array
    {
        if (file_exists($dest)) {
            throw new RuntimeException("O destino já existe: $dest");
        }
        self::mkdir($dest);

        self::copyDatabase($paths['database'], $dest . '/database.db');
        self::copyTree($paths['uploads'], $dest . '/uploads');
        self::copyTree($paths['trash'], $dest . '/trash');

        $manifest = [
            'version'    => 1,
            'created_at' => date('c'),
            'php'        => PHP_VERSION,
            'files'      => self::inventory($dest),
        ];
        $db = new PDO('sqlite:' . $dest . '/database.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $manifest['counts'] = [
            'users'      => (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'files_meta' => (int) $db->query('SELECT COUNT(*) FROM files_meta')->fetchColumn(),
            'logs'       => (int) $db->query('SELECT COUNT(*) FROM logs')->fetchColumn(),
        ];
        $manifest['bytes'] = array_sum(array_column($manifest['files'], 'size'));

        file_put_contents($dest . '/' . self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $manifest;
    }

    /**
     * Confere um backup contra o manifest.
     *
     * @return string[] problemas encontrados (vazio = íntegro)
     */
    public static function verify(string $dir): array
    {
        $problems = [];
        $mf = $dir . '/' . self::MANIFEST;
        if (!is_file($mf)) {
            return ["manifest.json ausente em $dir"];
        }
        $manifest = json_decode((string) file_get_contents($mf), true);
        if (!is_array($manifest) || empty($manifest['files'])) {
            return ['manifest.json ilegível'];
        }

        $expected = [];
        foreach ($manifest['files'] as $f) {
            $expected[$f['p']] = $f;
            $path = $dir . '/' . $f['p'];
            if (!is_file($path)) {
                $problems[] = "faltando: {$f['p']}";
            } elseif (filesize($path) !== $f['size']) {
                $problems[] = "tamanho diferente: {$f['p']}";
            } elseif (hash_file('sha256', $path) !== $f['sha256']) {
                $problems[] = "conteúdo alterado: {$f['p']}";
            }
        }
        foreach (self::inventory($dir) as $f) {
            if (!isset($expected[$f['p']])) {
                $problems[] = "arquivo fora do manifest: {$f['p']}";
            }
        }

        if (is_file($dir . '/database.db')) {
            try {
                $db = new PDO('sqlite:' . $dir . '/database.db');
                if ($db->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
                    $problems[] = 'banco com integrity_check diferente de ok';
                }
            } catch (\Throwable $e) {
                $problems[] = 'banco não abre: ' . $e->getMessage();
            }
        }
        return $problems;
    }

    /**
     * Restaura um backup verificado numa pasta NOVA (ou vazia). Nunca sobrescreve.
     *
     * @return array manifest do backup restaurado
     */
    public static function restore(string $backup, string $target): array
    {
        $problems = self::verify($backup);
        if ($problems) {
            throw new RuntimeException("Backup com problemas, nada foi restaurado:\n - " . implode("\n - ", $problems));
        }
        if (file_exists($target) && (!is_dir($target) || count(array_diff(scandir($target), ['.', '..'])) > 0)) {
            throw new RuntimeException("O destino precisa não existir ou estar vazio: $target");
        }
        self::mkdir($target);

        copy($backup . '/database.db', $target . '/database.db');
        self::copyTree($backup . '/uploads', $target . '/uploads');
        self::copyTree($backup . '/trash', $target . '/trash');
        self::mkdir($target . '/logs');

        $db = new PDO('sqlite:' . $target . '/database.db');
        if ($db->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
            throw new RuntimeException('O banco restaurado falhou no integrity_check');
        }
        return json_decode((string) file_get_contents($backup . '/' . self::MANIFEST), true);
    }

    // ------------------------------------------------------------------

    private static function copyDatabase(string $src, string $dst): void
    {
        $db = new PDO('sqlite:' . $src);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA busy_timeout = 10000');

        if (version_compare((string) $db->query('SELECT sqlite_version()')->fetchColumn(), '3.27.0', '>=')) {
            $db->exec('VACUUM INTO ' . $db->quote(str_replace('\\', '/', $dst)));
            return;
        }
        // SQLite antigo: trava as escritas, copia o arquivo e solta.
        $db->exec('BEGIN IMMEDIATE');
        try {
            copy($src, $dst);
        } finally {
            $db->exec('ROLLBACK');
        }
    }

    private static function copyTree(string $src, string $dst): void
    {
        self::mkdir($dst);
        if (!is_dir($src)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            // getSubPathname() não depende de como $src foi escrito (no Windows, nome curto
            // RUNNER~1 e nome longo têm tamanhos diferentes e cortavam o caminho no lugar errado).
            $rel = '/' . str_replace('\\', '/', $it->getSubPathname());
            if ($item->isLink()) {
                continue; // nunca segue symlink
            }
            if ($item->isDir()) {
                self::mkdir($dst . $rel);
            } elseif (!@copy($item->getPathname(), $dst . $rel)) {
                throw new RuntimeException('Falha ao copiar ' . $item->getPathname());
            }
        }
    }

    /** @return array[] lista ordenada de ['p' => caminho relativo, 'size' => bytes, 'sha256' => hash] */
    private static function inventory(string $dir): array
    {
        $root = rtrim(str_replace('\\', '/', realpath($dir)), '/');
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen($root)), '/');
            if ($rel === self::MANIFEST || !$f->isFile()) {
                continue;
            }
            $out[] = ['p' => $rel, 'size' => $f->getSize(), 'sha256' => hash_file('sha256', $f->getPathname())];
        }
        usort($out, function ($a, $b) { return strcmp($a['p'], $b['p']); });
        return $out;
    }

    private static function mkdir(string $d): void
    {
        if (!is_dir($d) && !@mkdir($d, 0775, true) && !is_dir($d)) {
            throw new RuntimeException("Não foi possível criar $d");
        }
    }
}
