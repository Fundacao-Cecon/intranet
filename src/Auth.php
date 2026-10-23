<?php
namespace App;

use PDO;

/**
 * Autenticação, sessão e gestão de usuários.
 *
 * - O usuário/role é recarregado do banco a cada requisição (usuário apagado
 *   ou rebaixado perde acesso imediatamente).
 * - Falhas de login e de troca de senha entram no mesmo rate limit.
 */
final class Auth
{
    const ROLES = ['admin', 'user'];

    /** @var PDO */
    private $db;
    /** @var Logger */
    private $logger;
    /** @var array */
    private $config;
    /** @var array|null|false  false = ainda não carregado */
    private $user = false;

    public function __construct(PDO $db, Logger $logger, array $authConfig)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $authConfig;
    }

    // ------------------------------------------------------------------
    // Estado da sessão
    // ------------------------------------------------------------------

    /** @return array|null  ['id','username','role'] */
    public function user(): ?array
    {
        if ($this->user !== false) {
            return $this->user;
        }
        $this->user = null;

        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }

        $last = $_SESSION['last_activity'] ?? 0;
        if ($last && (time() - $last) > $this->config['session_lifetime']) {
            $this->destroySession();
            return null;
        }

        $stmt = $this->db->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->destroySession();
            return null;
        }

        $_SESSION['last_activity'] = time();
        return $this->user = $row;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        $u = $this->user();
        return $u !== null && $u['role'] === 'admin';
    }

    public function username(): ?string
    {
        $u = $this->user();
        return $u ? $u['username'] : null;
    }

    public function id(): ?int
    {
        $u = $this->user();
        return $u ? (int) $u['id'] : null;
    }

    public function hasUsers(): bool
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0;
    }

    // ------------------------------------------------------------------
    // Login / logout / senha
    // ------------------------------------------------------------------

    /** @return array [bool ok, string erro] */
    public function attempt(string $username, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';

        if ($this->isLockedOut($username, $ip)) {
            $this->logger->log('LOGIN_BLOQUEADO', $username, 'Visitante');
            return [false, 'Muitas tentativas. Aguarde alguns minutos e tente de novo.'];
        }

        $user = $this->findByUsername($username);
        if (!$user || !password_verify($password, $user['password'])) {
            $this->recordFailure($username, $ip);
            $this->logger->log('LOGIN_FALHA', $username, 'Visitante');
            return [false, 'Usuário ou senha incorretos.'];
        }

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->setPasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        // Evita session fixation: novo ID de sessão e novo token CSRF.
        session_regenerate_id(true);
        Csrf::regenerate();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        $this->user = false;

        $upd = $this->db->prepare("UPDATE users SET last_login = DATETIME('now', 'localtime') WHERE id = ?");
        $upd->execute([$user['id']]);
        $this->clearFailures($username, $ip);

        $this->logger->log('LOGIN', 'Entrou no sistema', $user['username']);
        return [true, ''];
    }

    public function logout(): void
    {
        $this->destroySession();
    }

    /**
     * Troca a senha exigindo a senha atual. Serve ao formulário público
     * (login) e ao modal logado; ambos contam no rate limit.
     *
     * @return array [bool ok, string mensagem]
     */
    public function changePassword(string $username, string $old, string $new): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';

        if ($this->isLockedOut($username, $ip)) {
            return [false, 'Muitas tentativas. Aguarde alguns minutos e tente de novo.'];
        }

        $user = $this->findByUsername($username);
        if (!$user || !password_verify($old, $user['password'])) {
            $this->recordFailure($username, $ip);
            return [false, 'Dados inválidos.'];
        }

        if ($err = $this->validatePassword($new)) {
            return [false, $err];
        }

        $this->setPasswordHash((int) $user['id'], password_hash($new, PASSWORD_DEFAULT));
        $this->clearFailures($username, $ip);
        $this->logger->log('SENHA', "Alterou a própria senha: $username", $username);
        return [true, 'Senha atualizada com sucesso!'];
    }

    // ------------------------------------------------------------------
    // Gestão de usuários (admin)
    // ------------------------------------------------------------------

    /** @return array [bool ok, string mensagem] */
    public function createUser(string $username, string $password, string $role): array
    {
        $username = trim($username);
        if (!preg_match('/^[\p{L}\p{N}._-]{3,50}$/u', $username)) {
            return [false, 'Login inválido (3-50 letras, números, ponto, hífen ou _).'];
        }
        if (!in_array($role, self::ROLES, true)) {
            return [false, 'Tipo de usuário inválido.'];
        }
        if ($err = $this->validatePassword($password)) {
            return [false, $err];
        }
        if ($this->findByUsername($username)) {
            return [false, 'Já existe.'];
        }

        $stmt = $this->db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
        return [true, 'Criado!'];
    }

    /** Define a senha de um usuário existente sem exigir a antiga (uso CLI). */
    public function resetPassword(string $username, string $password): array
    {
        $user = $this->findByUsername($username);
        if (!$user) {
            return [false, 'Usuário não encontrado.'];
        }
        if ($err = $this->validatePassword($password)) {
            return [false, $err];
        }
        $this->setPasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        return [true, 'Senha redefinida.'];
    }

    /** @return array [bool ok, string mensagem] */
    public function deleteUser(int $id): array
    {
        if ($id === $this->id()) {
            return [false, 'Não pode se excluir.'];
        }

        $stmt = $this->db->prepare("SELECT id, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            return [false, 'Usuário não encontrado.'];
        }

        if ($target['role'] === 'admin') {
            $admins = (int) $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($admins <= 1) {
                return [false, 'Não é possível excluir o último admin.'];
            }
        }

        $del = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $del->execute([$id]);
        return [true, 'Usuário excluído.'];
    }

    public function listUsers(): array
    {
        return $this->db->query("SELECT id, username, role, last_login FROM users ORDER BY username ASC")->fetchAll();
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function setPasswordHash(int $id, string $hash): void
    {
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $id]);
    }

    private function validatePassword(string $password): ?string
    {
        $min = (int) $this->config['min_password_length'];
        if (strlen($password) < $min) {
            return "A senha precisa ter pelo menos $min caracteres.";
        }
        return null;
    }

    private function destroySession(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->user = null;
    }

    private function isLockedOut(string $username, string $ip): bool
    {
        $since = time() - ((int) $this->config['lockout_minutes']) * 60;
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ip = ? AND attempted_at > ?"
        );
        $stmt->execute([self::key($username), $ip, $since]);
        return (int) $stmt->fetchColumn() >= (int) $this->config['max_attempts'];
    }

    private function recordFailure(string $username, string $ip): void
    {
        $stmt = $this->db->prepare("INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, ?)");
        $stmt->execute([$ip, self::key($username), time()]);
        // limpeza oportunista de registros velhos
        $this->db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?")->execute([time() - 86400]);
    }

    private function clearFailures(string $username, string $ip): void
    {
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE username = ? AND ip = ?");
        $stmt->execute([self::key($username), $ip]);
    }

    /** Chave normalizada (minúscula, limitada) para o rate limit. */
    private static function key(string $s): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower(mb_substr($s, 0, 100)) : strtolower(substr($s, 0, 100));
    }
}
