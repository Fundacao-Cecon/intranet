<?php
namespace App\Services;

use PDO;

/**
 * Metadados de arquivos (quem criou/editou) e origem dos itens da lixeira.
 * Chaves são os caminhos lógicos de FileStorage::metaPath().
 */
final class FileMeta
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Registra criação (substitui metadado anterior). */
    public function created(string $path, string $user): void
    {
        $stmt = $this->db->prepare(
            "INSERT OR REPLACE INTO files_meta (filepath, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, DATETIME('now', 'localtime'), DATETIME('now', 'localtime'))"
        );
        $stmt->execute([$path, $user, $user]);
    }

    /** Move o metadado (e o de todos os filhos, se for pasta) para o novo caminho. */
    public function renamed(string $old, string $new, string $user): void
    {
        $stmt = $this->db->prepare(
            "UPDATE files_meta SET filepath = ?, updated_by = ?, updated_at = DATETIME('now', 'localtime')
             WHERE filepath = ?"
        );
        $stmt->execute([$new, $user, $old]);

        // Filhos: troca o prefixo "old/" por "new/".
        $children = $this->db->prepare(
            "UPDATE files_meta SET filepath = ? || substr(filepath, length(?))
             WHERE substr(filepath, 1, length(?)) = ?"
        );
        // substr() compara byte a byte; LIKE ignora a caixa (ASCII) e misturaria "A/" com "a/" no Linux.
        // length() do SQLite conta caracteres, como o substr() dele: strlen() do PHP conta bytes e
        // quebraria os filhos de "Relatórios".
        $prefix = $old . '/';
        $children->execute([$new, $prefix, $prefix, $prefix]);
    }

    /** Apaga o metadado do item e de tudo dentro dele. */
    public function removed(string $path): void
    {
        $stmt = $this->db->prepare("DELETE FROM files_meta WHERE filepath = ? OR substr(filepath, 1, length(?)) = ?");
        $prefix = $path . '/';
        $stmt->execute([$path, $prefix, $prefix]);
    }

    /**
     * Busca em lote (evita 1 query por linha da listagem).
     *
     * @param string[] $paths
     * @return array<string,array>  filepath => ['created_by','updated_by']
     */
    public function lookup(array $paths): array
    {
        if (!$paths) {
            return [];
        }
        $out = [];
        // SQLite limita variáveis por consulta; processa em blocos.
        foreach (array_chunk(array_values($paths), 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->db->prepare(
                "SELECT filepath, created_by, updated_by FROM files_meta WHERE filepath IN ($marks)"
            );
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll() as $row) {
                $out[$row['filepath']] = $row;
            }
        }
        return $out;
    }

    // --- origem na lixeira -------------------------------------------------

    public function rememberOrigin(string $trashPath, string $originPath): void
    {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO trash_origin (trash_path, origin_path) VALUES (?, ?)");
        $stmt->execute([$trashPath, $originPath]);
    }

    public function origin(string $trashPath): ?string
    {
        $stmt = $this->db->prepare("SELECT origin_path FROM trash_origin WHERE trash_path = ?");
        $stmt->execute([$trashPath]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    public function forgetOrigin(string $trashPath): void
    {
        $stmt = $this->db->prepare("DELETE FROM trash_origin WHERE trash_path = ?");
        $stmt->execute([$trashPath]);
    }

    public function forgetAllOrigins(): void
    {
        $this->db->exec("DELETE FROM trash_origin");
    }
}
