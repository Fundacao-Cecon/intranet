<?php
namespace App\Services;

use PDO;

/**
 * Permissões por pasta (ADR 0006).
 *
 * Uma pasta pode ter uma regra: lista de usuários e de grupos admitidos. Para entrar numa
 * pasta é preciso passar por TODAS as regras da cadeia de ancestrais (a regra de uma
 * subpasta só estreita, nunca reabre o que o pai fechou). Sem regra em nenhum nível, a
 * pasta é livre. Regra sem usuários nem grupos = só admins. Admin ignora regras.
 *
 * Os caminhos são os de FileStorage::metaPath() (sem "/" nas pontas).
 */
final class FolderAccess
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** 'a/b/c' => ['a', 'a/b', 'a/b/c'] ('' => []). */
    public static function chain(string $path): array
    {
        $out = [];
        $acc = '';
        foreach (explode('/', trim($path, '/')) as $part) {
            if ($part === '') {
                continue;
            }
            $acc .= ($acc === '' ? '' : '/') . $part;
            $out[] = $acc;
        }
        return $out;
    }

    /**
     * @param array|null $user ['id','role'] como em Auth::user()
     */
    public function allows(?array $user, string $path): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        $chain = self::chain($path);
        if (!$chain) {
            return true;
        }
        $ok = $this->evaluate((int) $user['id'], $chain);
        return !in_array(false, $ok, true);
    }

    /**
     * Quais subpastas diretas de $sub estão fechadas para o usuário. $sub já foi liberada
     * (location() confere a cadeia), então só as regras dos filhos importam.
     *
     * @param string[] $names nomes das subpastas
     * @return array<string,bool> nome => bloqueada?
     */
    public function lockedMap(?array $user, string $sub, array $names): array
    {
        $locked = array_fill_keys($names, false);
        if ($user === null || $user['role'] === 'admin' || !$names) {
            return $locked;
        }
        $byPath = [];
        foreach ($names as $n) {
            $byPath[trim($sub . '/' . $n, '/')] = $n;
        }
        foreach (array_chunk(array_keys($byPath), 400) as $chunk) {
            foreach ($this->evaluate((int) $user['id'], $chunk) as $path => $ok) {
                if (!$ok) {
                    $locked[$byPath[$path]] = true;
                }
            }
        }
        return $locked;
    }

    /**
     * Regra da própria pasta (sem herança).
     *
     * @return array|null ['users' => int[], 'groups' => int[]]; null = sem regra
     */
    public function rule(string $path): ?array
    {
        $stmt = $this->db->prepare("SELECT 1 FROM folder_rules WHERE path = ?");
        $stmt->execute([$path]);
        if (!$stmt->fetchColumn()) {
            return null;
        }
        $u = $this->db->prepare("SELECT user_id FROM folder_rule_users WHERE path = ? ORDER BY user_id");
        $u->execute([$path]);
        $g = $this->db->prepare("SELECT group_id FROM folder_rule_groups WHERE path = ? ORDER BY group_id");
        $g->execute([$path]);
        return [
            'users'  => array_map('intval', $u->fetchAll(PDO::FETCH_COLUMN)),
            'groups' => array_map('intval', $g->fetchAll(PDO::FETCH_COLUMN)),
        ];
    }

    /**
     * Regras de várias pastas de uma vez (uma consulta por tabela, para a listagem).
     *
     * @param string[] $paths
     * @return array<string,array> path => ['users' => int[], 'groups' => int[]]
     */
    public function rules(array $paths): array
    {
        $out = [];
        foreach (array_chunk(array_values($paths), 400) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->db->prepare("SELECT path FROM folder_rules WHERE path IN ($marks)");
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $p) {
                $out[$p] = ['users' => [], 'groups' => []];
            }
            if (!$out) {
                continue;
            }
            foreach (['users' => ['folder_rule_users', 'user_id'], 'groups' => ['folder_rule_groups', 'group_id']] as $key => [$table, $col]) {
                $s = $this->db->prepare("SELECT path, $col FROM $table WHERE path IN ($marks)");
                $s->execute($chunk);
                foreach ($s->fetchAll(PDO::FETCH_NUM) as [$p, $id]) {
                    if (isset($out[$p])) {
                        $out[$p][$key][] = (int) $id;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Cria ou substitui a regra. Ids inexistentes são ignorados.
     *
     * @param int[] $userIds
     * @param int[] $groupIds
     */
    public function setRule(string $path, array $userIds, array $groupIds): void
    {
        $this->transaction(function () use ($path, $userIds, $groupIds) {
            $this->deleteRule($path);
            $this->db->prepare("INSERT INTO folder_rules (path) VALUES (?)")->execute([$path]);

            $u = $this->db->prepare("INSERT OR IGNORE INTO folder_rule_users (path, user_id) SELECT ?, id FROM users WHERE id = ?");
            foreach (array_unique(array_map('intval', $userIds)) as $id) {
                $u->execute([$path, $id]);
            }
            $g = $this->db->prepare("INSERT OR IGNORE INTO folder_rule_groups (path, group_id) SELECT ?, id FROM groups WHERE id = ?");
            foreach (array_unique(array_map('intval', $groupIds)) as $id) {
                $g->execute([$path, $id]);
            }
        });
    }

    /** Volta a pasta a herdar (apaga só a regra dela, não a dos filhos). */
    public function clearRule(string $path): void
    {
        $this->transaction(function () use ($path) {
            $this->deleteRule($path);
        });
    }

    /** Move a regra da pasta e as de todas as subpastas para o novo caminho (renomear, lixeira, mover). */
    public function renamed(string $old, string $new): void
    {
        if ($old === $new) {
            return;
        }
        $this->transaction(function () use ($old, $new) {
            $this->removedNow($new); // sobra de um item antigo com esse nome não pode se agarrar ao novo
            $like = self::escapeLike($old) . '/%';
            foreach (['folder_rules', 'folder_rule_users', 'folder_rule_groups'] as $table) {
                $this->db->prepare("UPDATE $table SET path = ? WHERE path = ?")->execute([$new, $old]);
                $this->db->prepare("UPDATE $table SET path = ? || substr(path, ?) WHERE path LIKE ? ESCAPE '\\'")
                    ->execute([$new, strlen($old) + 1, $like]);
            }
        });
    }

    /** Apaga a regra da pasta e de tudo dentro dela (exclusão definitiva). */
    public function removed(string $path): void
    {
        $this->transaction(function () use ($path) {
            $this->removedNow($path);
        });
    }

    // ------------------------------------------------------------------

    /**
     * @param string[] $paths
     * @return array<string,bool> path (só os que têm regra) => usuário admitido?
     */
    private function evaluate(int $userId, array $paths): array
    {
        $marks = implode(',', array_fill(0, count($paths), '?'));
        $stmt = $this->db->prepare(
            "SELECT r.path,
                    (EXISTS (SELECT 1 FROM folder_rule_users u WHERE u.path = r.path AND u.user_id = ?)
                     OR EXISTS (SELECT 1 FROM folder_rule_groups g
                                JOIN group_members m ON m.group_id = g.group_id
                                WHERE g.path = r.path AND m.user_id = ?)) AS ok
             FROM folder_rules r WHERE r.path IN ($marks)"
        );
        $stmt->execute(array_merge([$userId, $userId], array_values($paths)));
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['path']] = (bool) $row['ok'];
        }
        return $out;
    }

    private function deleteRule(string $path): void
    {
        foreach (['folder_rules', 'folder_rule_users', 'folder_rule_groups'] as $table) {
            $this->db->prepare("DELETE FROM $table WHERE path = ?")->execute([$path]);
        }
    }

    private function removedNow(string $path): void
    {
        $like = self::escapeLike($path) . '/%';
        foreach (['folder_rules', 'folder_rule_users', 'folder_rule_groups'] as $table) {
            $this->db->prepare("DELETE FROM $table WHERE path = ? OR path LIKE ? ESCAPE '\\'")->execute([$path, $like]);
        }
    }

    /** Trava de escrita desde o início: dois admins editando regras não se atropelam. */
    private function transaction(callable $fn): void
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $fn();
            $this->db->exec('COMMIT');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->exec('ROLLBACK');
            }
            throw $e;
        }
    }

    private static function escapeLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }
}
