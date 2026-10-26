<?php
namespace App;

use PDO;

/**
 * Conexão SQLite + criação/migração do esquema.
 * Esquema é idempotente (CREATE ... IF NOT EXISTS), então roda em todo boot.
 */
final class Database
{
    public static function connect(string $file): PDO
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // Espera por escritores concorrentes (rollback journal: um por vez). 5 s estourava
        // com 8 processos gravando em disco lento (runner do Windows): "database is locked".
        $pdo->exec('PRAGMA busy_timeout = 15000');

        self::migrate($pdo);
        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip TEXT,
            usuario TEXT,
            acao TEXT,
            detalhe TEXT
        )");

        // Quem criou/editou cada arquivo ou pasta. filepath é relativo à raiz
        // de uploads; itens na lixeira levam o prefixo ".lixeira/".
        $pdo->exec("CREATE TABLE IF NOT EXISTS files_meta (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filepath TEXT UNIQUE,
            created_by TEXT,
            updated_by TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Falhas de login recentes (rate limit). attempted_at = epoch.
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            username TEXT NOT NULL,
            attempted_at INTEGER NOT NULL
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_user_ip
                    ON login_attempts (username, ip, attempted_at)");

        // De onde cada item da lixeira veio, para restaurar na pasta original.
        $pdo->exec("CREATE TABLE IF NOT EXISTS trash_origin (
            trash_path TEXT PRIMARY KEY,
            origin_path TEXT NOT NULL
        )");

        // Permissões por pasta (ADR 0006). Uma pasta com linha em folder_rules só admite os
        // usuários de folder_rule_users e os membros dos grupos de folder_rule_groups.
        // path segue o mesmo formato de files_meta (".lixeira/..." na lixeira).
        $pdo->exec("CREATE TABLE IF NOT EXISTS groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE COLLATE NOCASE
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
            group_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            PRIMARY KEY (group_id, user_id)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS folder_rules (
            path TEXT PRIMARY KEY
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS folder_rule_users (
            path TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            PRIMARY KEY (path, user_id)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS folder_rule_groups (
            path TEXT NOT NULL,
            group_id INTEGER NOT NULL,
            PRIMARY KEY (path, group_id)
        )");
    }
}
