<?php
namespace App;

use PDO;

/** Registro de auditoria na tabela `logs` (era registrarLog). */
final class Logger
{
    /** @var PDO */
    private $db;
    /** @var callable|null  retorna o nome do usuário logado */
    private $userProvider;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function setUserProvider(callable $provider): void
    {
        $this->userProvider = $provider;
    }

    public function log(string $acao, string $detalhe, ?string $usuario = null): void
    {
        if ($usuario === null) {
            $usuario = $this->userProvider ? call_user_func($this->userProvider) : null;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO logs (data_hora, ip, usuario, acao, detalhe)
             VALUES (DATETIME('now', 'localtime'), ?, ?, ?, ?)"
        );
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? 'cli',
            $usuario ?: 'Visitante',
            $acao,
            function_exists('mb_substr') ? mb_substr($detalhe, 0, 500) : substr($detalhe, 0, 500),
        ]);
    }

    public function recent(int $limit = 50): array
    {
        $stmt = $this->db->prepare("SELECT * FROM logs ORDER BY id DESC LIMIT " . (int) $limit);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Apaga logs mais antigos que $days dias. */
    public function purge(int $days): void
    {
        $stmt = $this->db->prepare("DELETE FROM logs WHERE data_hora < DATETIME('now', 'localtime', ?)");
        $stmt->execute(['-' . max(1, $days) . ' days']);
    }
}
