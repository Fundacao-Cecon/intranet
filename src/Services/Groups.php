<?php
namespace App\Services;

use App\Database;
use PDO;

/**
 * Grupos de usuários (ex.: "RH", "TI") usados nas regras de pasta (FolderAccess).
 * Os métodos de escrita devolvem [bool ok, string mensagem], como Auth.
 */
final class Groups
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array [bool ok, string mensagem] */
    public function create(string $name): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name)); // UTF-8 inválido: preg devolve null
        if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._-]{1,49}$/u', $name)) {
            return [false, 'Nome inválido (2-50 letras, números, espaço, ponto, hífen ou _).'];
        }
        $stmt = $this->db->prepare("SELECT 1 FROM groups WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn()) {
            return [false, 'Já existe um grupo com esse nome.'];
        }
        try {
            $this->db->prepare("INSERT INTO groups (name) VALUES (?)")->execute([$name]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') { // só a violação de UNIQUE (dois admins, mesmo nome); "database is locked" etc. sobem
                throw $e;
            }
            return [false, 'Já existe um grupo com esse nome.'];
        }
        return [true, 'Grupo criado!'];
    }

    /** @return array [bool ok, string mensagem] */
    public function delete(int $id): array
    {
        return Database::transaction($this->db, function () use ($id) {
            $stmt = $this->db->prepare("SELECT 1 FROM groups WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetchColumn()) {
                return [false, 'Grupo não encontrado.'];
            }
            foreach (['group_members', 'folder_rule_groups'] as $table) {
                $this->db->prepare("DELETE FROM $table WHERE group_id = ?")->execute([$id]);
            }
            $this->db->prepare("DELETE FROM groups WHERE id = ?")->execute([$id]);
            return [true, 'Grupo excluído.'];
        });
    }

    /**
     * Substitui os membros. Ids que não são usuários são ignorados.
     *
     * @param int[] $userIds
     * @return array [bool ok, string mensagem]
     */
    public function setMembers(int $id, array $userIds): array
    {
        return Database::transaction($this->db, function () use ($id, $userIds) {
            $stmt = $this->db->prepare("SELECT 1 FROM groups WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetchColumn()) {
                return [false, 'Grupo não encontrado.'];
            }
            $this->db->prepare("DELETE FROM group_members WHERE group_id = ?")->execute([$id]);
            $ins = $this->db->prepare("INSERT OR IGNORE INTO group_members (group_id, user_id) SELECT ?, id FROM users WHERE id = ?");
            foreach (array_unique(array_map('intval', $userIds)) as $uid) {
                $ins->execute([$id, $uid]);
            }
            return [true, 'Membros atualizados.'];
        });
    }

    /** @return array[] [['id','name','members' => int[]], ...] ordenado por nome */
    public function all(): array
    {
        $groups = $this->db->query("SELECT id, name FROM groups ORDER BY name COLLATE NOCASE")->fetchAll();
        $members = [];
        foreach ($this->db->query("SELECT group_id, user_id FROM group_members ORDER BY user_id")->fetchAll(PDO::FETCH_NUM) as [$g, $u]) {
            $members[(int) $g][] = (int) $u;
        }
        foreach ($groups as &$g) {
            $g['id'] = (int) $g['id'];
            $g['members'] = $members[$g['id']] ?? [];
        }
        unset($g);
        return $groups;
    }
}
