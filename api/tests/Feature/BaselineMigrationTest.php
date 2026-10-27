<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * T013: a baseline adota o esquema do legado e convive com ele no mesmo arquivo SQLite.
 * O "legado" aqui é o src/Database.php de verdade, rodado em outro processo PHP
 * (o namespace App do legado colide com o do Laravel, então não dá para carregá-lo junto).
 */
class BaselineMigrationTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/baseline-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function legacy(string $db, string $code = ''): string
    {
        $src = realpath(base_path('../src/Database.php'));
        $script = $this->dir . '/legado-' . bin2hex(random_bytes(3)) . '.php';
        file_put_contents($script, '<?php require ' . var_export($src, true) . ';'
            . '$pdo = App\Database::connect(' . var_export($db, true) . ');' . $code);
        $out = [];
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $out, $status);
        $this->assertSame(0, $status, implode("\n", $out));
        return implode("\n", $out);
    }

    private function migrate(string $db): void
    {
        config(['database.connections.sqlite.database' => $db]);
        DB::purge('sqlite');
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
    }

    /** @return array<string,string> nome => SQL, sem as tabelas internas */
    private function schema(string $db): array
    {
        $pdo = new PDO('sqlite:' . $db);
        $rows = $pdo->query("SELECT name, sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' AND name <> 'migrations' AND sql IS NOT NULL ORDER BY name")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        // O fim de linha do DDL depende do checkout (CRLF no Windows); não faz parte do esquema.
        return array_map(fn (string $sql) => str_replace("\r\n", "\n", $sql), $rows);
    }

    public function test_banco_novo_nasce_com_o_mesmo_esquema_do_legado(): void
    {
        $legacy = $this->dir . '/legado.db';
        $novo = $this->dir . '/novo.db';
        $this->legacy($legacy);
        $this->migrate($novo);

        $esperado = $this->schema($legacy);
        $this->assertCount(11, $esperado); // 10 tabelas + 1 índice, sem autoindex
        $this->assertSame($esperado, $this->schema($novo));
    }

    public function test_banco_existente_e_adotado_sem_perder_dados(): void
    {
        $db = $this->dir . '/vivo.db';
        $this->legacy($db, <<<'PHP'
$pdo->exec("INSERT INTO users (username, password, role) VALUES ('ana', 'hash', 'admin')");
$pdo->exec("INSERT INTO files_meta (filepath, created_by) VALUES ('Relatórios/Sub/a.pdf', 'ana')");
$pdo->exec("INSERT INTO folder_rules (path) VALUES ('Relatórios')");
PHP);
        $antes = $this->schema($db);

        $this->migrate($db);
        $this->migrate($db); // idempotente

        $this->assertSame($antes, $this->schema($db));
        $pdo = new PDO('sqlite:' . $db);
        $this->assertSame('ana', $pdo->query('SELECT username FROM users')->fetchColumn());
        $this->assertSame('Relatórios/Sub/a.pdf', $pdo->query('SELECT filepath FROM files_meta')->fetchColumn());
        $this->assertSame('Relatórios', $pdo->query('SELECT path FROM folder_rules')->fetchColumn());
        $this->assertSame('1', (string) $pdo->query("SELECT COUNT(*) FROM migrations WHERE migration LIKE '%baseline_legacy_schema'")->fetchColumn());
    }

    public function test_conexao_usa_wal_e_espera_15_segundos(): void
    {
        $db = $this->dir . '/wal.db';
        $this->migrate($db);

        $this->assertSame('wal', strtolower((string) DB::selectOne('PRAGMA journal_mode')->journal_mode));
        $this->assertSame(15000, (int) DB::selectOne('PRAGMA busy_timeout')->timeout);
    }

    public function test_legado_continua_lendo_e_gravando_depois_do_laravel(): void
    {
        $db = $this->dir . '/convive.db';
        $this->migrate($db);
        DB::table('users')->insert(['username' => 'bia', 'password' => 'h', 'role' => 'user']);
        DB::purge('sqlite');

        $saida = $this->legacy($db, <<<'PHP'
$pdo->exec("INSERT INTO logs (usuario, acao) VALUES ('bia', 'login')");
echo $pdo->query("SELECT username FROM users")->fetchColumn();
PHP);
        $this->assertSame('bia', $saida);

        $this->assertSame('login', DB::table('logs')->where('usuario', 'bia')->value('acao'));
    }

    public function test_rollback_nao_apaga_as_tabelas_do_legado(): void
    {
        $db = $this->dir . '/rollback.db';
        $this->migrate($db);
        DB::table('users')->insert(['username' => 'caio', 'password' => 'h', 'role' => 'user']);

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--force' => true]));

        $this->assertSame(1, DB::table('users')->count());
    }
}
