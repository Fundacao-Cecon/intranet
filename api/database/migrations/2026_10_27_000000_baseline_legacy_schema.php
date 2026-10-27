<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Baseline: adota o esquema do sistema legado (src/Database.php) sem alterá-lo.
 *
 * O banco é compartilhado com o PHP atual durante a transição (docs/transicao.md), então
 * esta migration repete o DDL do legado byte a byte e só cria o que falta (IF NOT EXISTS):
 * num banco que já existe nada muda; num banco novo (CI, desenvolvimento) nasce igual.
 *
 * Regras de convivência: só mudanças aditivas depois deste ponto, e o down() é vazio de
 * propósito, porque desfazer a baseline apagaria as tabelas que o legado ainda usa.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const STATEMENTS = [
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        )",
        "CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip TEXT,
            usuario TEXT,
            acao TEXT,
            detalhe TEXT
        )",
        "CREATE TABLE IF NOT EXISTS files_meta (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filepath TEXT UNIQUE,
            created_by TEXT,
            updated_by TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            username TEXT NOT NULL,
            attempted_at INTEGER NOT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_login_attempts_user_ip
                    ON login_attempts (username, ip, attempted_at)",
        "CREATE TABLE IF NOT EXISTS trash_origin (
            trash_path TEXT PRIMARY KEY,
            origin_path TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE COLLATE NOCASE
        )",
        "CREATE TABLE IF NOT EXISTS group_members (
            group_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            PRIMARY KEY (group_id, user_id)
        )",
        "CREATE TABLE IF NOT EXISTS folder_rules (
            path TEXT PRIMARY KEY
        )",
        "CREATE TABLE IF NOT EXISTS folder_rule_users (
            path TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            PRIMARY KEY (path, user_id)
        )",
        "CREATE TABLE IF NOT EXISTS folder_rule_groups (
            path TEXT NOT NULL,
            group_id INTEGER NOT NULL,
            PRIMARY KEY (path, group_id)
        )",
    ];

    public function up(): void
    {
        foreach (self::STATEMENTS as $sql) {
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        // Intencionalmente vazio: ver o comentário da classe.
    }
};
