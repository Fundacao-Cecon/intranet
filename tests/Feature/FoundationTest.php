<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_foundation_page_uses_local_assets_and_no_document_listing(): void
    {
        $this->get('/')->assertOk()->assertSee('Intranet FCECON')->assertSee('/build/assets/')->assertDontSee('fonts.googleapis.com');
        $this->get('/uploads/documento.pdf')->assertNotFound();
        $this->get('/storage/documento.txt')->assertNotFound();
    }

    public function test_health_checks_database_and_private_storage(): void
    {
        $this->get('/up')->assertOk();
        $this->assertSame([], Storage::disk('local')->files('.health'));
    }

    public function test_unhealthy_response_does_not_disclose_internal_details(): void
    {
        $manager = DB::getFacadeRoot();
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('password=DO_NOT_LEAK host=internal-db'));
        try {
            $this->get('/up')->assertStatus(500)->assertDontSee('DO_NOT_LEAK')->assertDontSee('internal-db');
        } finally {
            DB::swap($manager);
        }
    }

    public function test_health_reports_unwritable_storage_without_internal_details(): void
    {
        $manager = Storage::getFacadeRoot();
        $disk = \Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->once()->andReturn($disk);
        try {
            $this->get('/up')->assertStatus(500)->assertDontSee('/var/www/html')->assertDontSee('Storage indisponível.');
        } finally {
            Storage::swap($manager);
        }
    }

    public function test_user_authentication_hash_is_hidden_and_not_rehashed(): void
    {
        $hash = Hash::make('fixture-only');
        $user = User::factory()->create(['password_hash' => $hash]);
        $this->assertSame('password_hash', $user->getAuthPasswordName());
        $this->assertSame($hash, $user->getAuthPassword());
        $this->assertTrue(Hash::check('fixture-only', $user->getAuthPassword()));
        $this->assertArrayNotHasKey('password_hash', $user->toArray());
    }

    public function test_database_rejects_unknown_profile(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['role' => 'master']);
    }

    public function test_usernames_are_unique(): void
    {
        User::factory()->create(['username' => 'duplicado']);
        $this->expectException(QueryException::class);
        User::factory()->create(['username' => 'duplicado']);
    }

    public function test_root_active_names_are_unique(): void
    {
        Item::factory()->create(['name' => 'Raiz']);
        $this->expectException(QueryException::class);
        Item::factory()->create(['name' => 'Raiz']);
    }

    public function test_sibling_names_are_unique_but_different_parents_are_allowed(): void
    {
        $a = Item::factory()->create();
        $b = Item::factory()->create();
        Item::factory()->create(['parent_id' => $a->id, 'name' => 'Filho']);
        Item::factory()->create(['parent_id' => $b->id, 'name' => 'Filho']);
        $this->expectException(QueryException::class);
        Item::factory()->create(['parent_id' => $a->id, 'name' => 'Filho']);
    }

    public function test_deleted_name_can_be_reused_and_identity_remains_stable(): void
    {
        $item = Item::factory()->file()->create(['name' => 'Documento.txt']);
        $id = $item->id;
        $key = $item->storage_key;
        $item->update(['name' => 'Novo.txt']);
        $this->assertSame($id, $item->fresh()->id);
        $this->assertSame($key, $item->fresh()->storage_key);
        $item->update(['deleted_at' => now(), 'trash_name' => 'Novo.txt']);
        Item::factory()->file()->create(['name' => 'Novo.txt']);
        $this->assertSame(2, Item::count());
    }

    public function test_database_rejects_invalid_parent(): void
    {
        $this->expectException(QueryException::class);
        Item::factory()->create(['parent_id' => 999999999]);
    }

    public function test_parent_cannot_be_deleted_while_children_exist(): void
    {
        $parent = Item::factory()->create();
        Item::factory()->create(['parent_id' => $parent->id]);
        $this->expectException(QueryException::class);
        $parent->delete();
    }

    public function test_database_rejects_file_without_storage_key(): void
    {
        $this->expectException(QueryException::class);
        Item::factory()->create(['kind' => 'file', 'size_bytes' => 10]);
    }

    public function test_negative_file_sizes_are_rejected(): void
    {
        $this->expectException(QueryException::class);
        Item::factory()->file()->create(['size_bytes' => -1]);
    }

    public function test_folders_cannot_have_file_bytes(): void
    {
        $this->expectException(QueryException::class);
        Item::factory()->create(['storage_key' => 'not-a-folder', 'size_bytes' => 10]);
    }

    public function test_an_item_cannot_be_its_own_parent(): void
    {
        $item = Item::factory()->create();
        $this->expectException(QueryException::class);
        $item->update(['parent_id' => $item->id]);
    }

    public function test_indirect_hierarchy_cycles_are_rejected(): void
    {
        $parent = Item::factory()->create();
        $child = Item::factory()->create(['parent_id' => $parent->id]);
        $grandchild = Item::factory()->create(['parent_id' => $child->id]);
        $this->expectException(QueryException::class);
        $parent->update(['parent_id' => $grandchild->id]);
    }

    public function test_files_cannot_be_parents(): void
    {
        $file = Item::factory()->file()->create();
        $this->expectException(QueryException::class);
        Item::factory()->create(['parent_id' => $file->id]);
    }

    public function test_parent_folder_cannot_turn_into_file(): void
    {
        $parent = Item::factory()->create();
        Item::factory()->create(['parent_id' => $parent->id]);
        $this->expectException(QueryException::class);
        $parent->update(['kind' => 'file', 'storage_key' => 'invalid-conversion.txt', 'size_bytes' => 0]);
    }

    public function test_user_and_item_deletion_preserve_audit_snapshots(): void
    {
        $user = User::factory()->admin()->create();
        $item = Item::factory()->file()->create(['created_by_id' => $user->id, 'created_by_snapshot' => $user->username]);
        $event = app(AuditLogger::class)->record('UPLOAD', $item->name, 'success', $user, $item, ['file_count' => 1], '127.0.0.1');
        $name = $user->username;
        $user->delete();
        $this->assertNull($item->fresh()->created_by_id);
        $this->assertSame($name, $item->fresh()->created_by_snapshot);
        $item->delete();
        $event->refresh();
        $this->assertNull($event->actor_id);
        $this->assertNull($event->item_id);
        $this->assertSame($name, $event->actor_name_snapshot);
        $this->assertSame('success', $event->outcome);
        $this->assertSame(['file_count' => 1], json_decode($event->detail, true));
    }

    public function test_audit_joins_callers_transaction(): void
    {
        DB::beginTransaction();
        app(AuditLogger::class)->record('TEST', 'Transação', 'success');
        DB::rollBack();
        $this->assertSame(0, AuditLog::count());
    }

    public function test_audit_refuses_secret_context_before_inserting(): void
    {
        try {
            app(AuditLogger::class)->record('LOGIN', 'Usuário', 'success', context: ['password' => 'never-log-this']);
            $this->fail('Contexto de segredo deveria ser recusado.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, AuditLog::count());
        }
    }

    public function test_runtime_cannot_create_tables(): void
    {
        $this->expectException(QueryException::class);
        DB::statement('CREATE TABLE forbidden_ddl (id integer)');
    }

    public function test_only_domain_and_migration_tables_exist(): void
    {
        $tables = collect(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"))->pluck('tablename')->all();
        $this->assertSame(['audit_logs', 'items', 'migrations', 'users'], $tables);
        $identity = DB::selectOne("SELECT is_identity FROM information_schema.columns WHERE table_name = 'items' AND column_name = 'id'");
        $this->assertSame('YES', $identity->is_identity);
    }

    public function test_fixture_file_uses_only_test_storage(): void
    {
        $this->assertSame('db-test', config('database.connections.pgsql.host'));
        $this->assertSame('intranet_test', config('database.connections.pgsql.database'));
        Storage::fake('local');
        Storage::disk('local')->put('fixture.txt', file_get_contents(base_path('tests/Fixtures/document.txt')));
        Storage::disk('local')->assertExists('fixture.txt');
    }
}
