<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LocalUploadFiles;
use App\Services\UploadCommitter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function login(string $role = 'admin'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)->withSession(['credential_fingerprint' => hash('sha256', $user->password_hash)]);

        return $user;
    }

    private function upload(string $name = 'Documento.txt', string $bytes = 'Arquivo fictício para teste.'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    public function test_user_cannot_access_any_administrative_operation(): void
    {
        $this->login('user');
        $item = Item::factory()->create();
        $target = User::factory()->create();
        foreach (['/usuarios', '/auditoria'] as $uri) {
            $this->get($uri)->assertForbidden();
        }
        $this->post('/usuarios', [])->assertForbidden();
        $this->delete('/usuarios/'.$target->id)->assertForbidden();
        $this->post('/pastas', [])->assertForbidden();
        $this->patch('/itens/'.$item->id, [])->assertForbidden();
        $this->postJson('/documentos/upload', ['arquivos' => [$this->upload()]])->assertForbidden()->assertJsonPath('status', 'error');
        $this->assertSame(1, Item::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_create_user_hashes_password_and_validates_duplicate_role_and_confirmation(): void
    {
        $this->login();
        $data = ['username' => 'usuario.teste', 'password' => 'Senha-ficticia-12345', 'password_confirmation' => 'Senha-ficticia-12345', 'role' => 'user'];
        $this->post('/usuarios', $data)->assertRedirect('/usuarios');
        $user = User::where('username', $data['username'])->firstOrFail();
        $this->assertTrue(Hash::check($data['password'], $user->password_hash));
        $this->postJson('/usuarios', $data)->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->postJson('/usuarios', array_merge($data, ['role' => 'master']))->assertJsonValidationErrors('role');
        $this->postJson('/usuarios', array_merge($data, ['username' => 'outro', 'password_confirmation' => 'diferente']))->assertJsonValidationErrors('password');
        $this->assertStringNotContainsString($data['password'], AuditLog::first()->toJson());
        $this->get('/usuarios')->assertOk()->assertDontSee($user->password_hash);
    }

    public function test_user_deletion_preserves_document_authorship_audit_and_blocks_self_deletion(): void
    {
        $actor = $this->login();
        $target = User::factory()->create();
        $item = Item::factory()->create(['created_by_id' => $target->id, 'created_by_snapshot' => $target->username]);
        app(AuditLogger::class)->record('FOLDER_CREATED', $item->name, 'success', $target, $item);
        $this->delete('/usuarios/'.$actor->id)->assertForbidden();
        $this->delete('/usuarios/'.$target->id)->assertRedirect('/usuarios');
        $this->assertNull($item->fresh()->created_by_id);
        $this->assertSame($target->username, $item->fresh()->created_by_snapshot);
        $this->assertSame($target->username, AuditLog::where('action', 'FOLDER_CREATED')->first()->actor_name_snapshot);
        $this->assertNull(User::find($target->id));
    }

    public function test_folder_creation_rejects_bad_names_duplicates_and_removed_destination(): void
    {
        $this->login();
        $this->post('/pastas', ['nome' => 'Pasta nova'])->assertRedirect('/documentos');
        $this->postJson('/pastas', ['nome' => 'Pasta nova'])->assertJsonValidationErrors('nome');
        foreach (['', '../externo', 'pasta/b', '.oculta', '<script>'] as $name) {
            $this->postJson('/pastas', ['nome' => $name])->assertUnprocessable();
        }
        $removed = Item::factory()->trashed()->create();
        $this->postJson('/pastas', ['nome' => 'Filha', 'pasta' => $removed->id])->assertJsonValidationErrors('pasta');
        $this->assertSame(1, AuditLog::where('action', 'FOLDER_CREATED')->count());
    }

    public function test_renaming_preserves_extension_key_children_and_original_author(): void
    {
        $actor = $this->login();
        $folder = Item::factory()->create();
        $file = Item::factory()->file()->create(['parent_id' => $folder->id, 'name' => 'Antigo.TXT', 'created_by_snapshot' => 'Autor original']);
        $this->patch('/itens/'.$folder->id, ['nome' => 'Pasta renomeada'])->assertRedirect('/documentos');
        $this->patch('/itens/'.$file->id, ['nome' => 'Novo'])->assertRedirect();
        $fresh = $file->fresh();
        $this->assertSame('Novo.TXT', $fresh->name);
        $this->assertSame($file->storage_key, $fresh->storage_key);
        $this->assertSame($folder->id, $fresh->parent_id);
        $this->assertSame('Autor original', $fresh->created_by_snapshot);
        $this->assertSame($actor->username, $fresh->updated_by_snapshot);
        Item::factory()->file()->create(['parent_id' => $folder->id, 'name' => 'Colisão.TXT']);
        $this->patchJson('/itens/'.$file->id, ['nome' => 'Colisão'])->assertJsonValidationErrors('nome');
    }

    public function test_upload_partial_batch_publishes_only_valid_files_and_preserves_exact_bytes(): void
    {
        $actor = $this->login();
        $response = $this->postJson('/documentos/upload', ['arquivos' => [$this->upload(), $this->upload('script.php', '<?php echo 1;')]])->assertOk()->assertJsonPath('status', 'warning');
        $item = Item::where('kind', 'file')->firstOrFail();
        $this->assertSame('Arquivo fictício para teste.', Storage::disk('local')->get($item->storage_key));
        $this->assertSame($actor->username, $item->created_by_snapshot);
        $this->assertSame(1, Item::count());
        $this->assertSame('error', $response->json('results.1.status'));
        $this->assertSame([], Storage::disk('local')->files('staging'));
        $this->assertSame([], Storage::disk('local')->files('upload-operations'));
    }

    public function test_upload_collision_suffix_never_replaces_bytes(): void
    {
        $this->login();
        $this->postJson('/documentos/upload', ['arquivos' => [$this->upload('Guia.txt', 'Primeiro arquivo'), $this->upload('Guia.txt', 'Segundo arquivo')]])->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame(['Guia.txt', 'Guia (1).txt'], Item::orderBy('id')->pluck('name')->all());
        $this->assertSame(['Primeiro arquivo', 'Segundo arquivo'], Item::orderBy('id')->get()->map(fn ($item) => Storage::disk('local')->get($item->storage_key))->all());
    }

    public function test_upload_rejects_mime_spoof_size_and_too_many_files(): void
    {
        $this->login();
        $this->postJson('/documentos/upload', ['arquivos' => [$this->upload('falso.pdf', '<html>não é pdf</html>')]])->assertUnprocessable()->assertJsonPath('status', 'error');
        $this->postJson('/documentos/upload', ['arquivos' => [UploadedFile::fake()->create('Grande.txt', 20481)]])->assertUnprocessable();
        $this->postJson('/documentos/upload', ['arquivos' => array_map(fn () => $this->upload(), range(1, 11))])->assertUnprocessable();
        $this->assertSame(0, Item::count());
        $this->assertSame([], Storage::disk('local')->files('documents'));
    }

    public function test_removed_destination_during_staging_is_rechecked(): void
    {
        $this->login();
        $folder = Item::factory()->create();
        $files = Mockery::mock(LocalUploadFiles::class)->makePartial();
        $files->shouldReceive('stage')->once()->andReturnUsing(function ($file, $key) use ($folder) {
            (new LocalUploadFiles)->stage($file, $key);
            $folder->update(['deleted_at' => now(), 'trash_name' => 'Removida durante envio']);
        });
        $this->app->instance(LocalUploadFiles::class, $files);
        $this->postJson('/documentos/upload', ['pasta' => $folder->id, 'arquivos' => [$this->upload()]])->assertUnprocessable();
        $this->assertSame(0, Item::where('kind', 'file')->count());
        $this->assertSame([], Storage::disk('local')->files('staging'));
    }

    public function test_disk_publication_failure_rolls_back_metadata_and_staging(): void
    {
        $this->login();
        $files = Mockery::mock(LocalUploadFiles::class)->makePartial();
        $files->shouldReceive('publish')->andThrow(new RuntimeException('Disco indisponível.'));
        $this->app->instance(LocalUploadFiles::class, $files);
        $this->postJson('/documentos/upload', ['arquivos' => [$this->upload()]])->assertStatus(503)->assertJsonPath('status', 'error');
        $this->assertSame(0, Item::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_audit_failure_after_publication_compensates_disk_and_database(): void
    {
        $this->login();
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->andThrow(new RuntimeException('Banco indisponível.'));
        $this->app->instance(AuditLogger::class, $audit);
        $this->postJson('/documentos/upload', ['arquivos' => [$this->upload()]])->assertStatus(503);
        $this->assertSame(0, Item::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_ambiguous_commit_preserves_bytes_and_manifest_without_claiming_success(): void
    {
        $this->login();
        $committer = Mockery::mock(UploadCommitter::class);
        $committer->shouldReceive('commit')->andThrow(new RuntimeException('Confirmação perdida.'));
        $this->app->instance(UploadCommitter::class, $committer);
        $this->postJson('/documentos/upload', ['arquivos' => [$this->upload()]])->assertStatus(503)->assertJsonPath('results.0.status', 'pending');
        $this->assertCount(1, Storage::disk('local')->files('documents'));
        $journals = Storage::disk('local')->files('upload-operations');
        $this->assertCount(1, $journals);
        $manifest = json_decode(Storage::disk('local')->get($journals[0]), true);
        $this->assertSame('committing', $manifest['phase']);
        $this->assertSame(hash('sha256', 'Arquivo fictício para teste.'), $manifest['sha256']);
        $this->assertSame(0, Item::count());
    }

    public function test_auditor_only_sees_the_latest_fifty_events_and_user_sees_no_admin_controls(): void
    {
        $admin = $this->login();
        for ($i = 0; $i < 55; $i++) {
            app(AuditLogger::class)->record('TEST_EVENT', 'Evento '.$i, 'success', $admin);
        }
        $response = $this->get('/auditoria')->assertOk();
        $this->assertCount(50, $response->viewData('events'));
        $this->assertSame('Evento 54', $response->viewData('events')->first()->target_snapshot);
        $this->login('user');
        $this->get('/documentos')->assertDontSee('id="upload-form"', false)->assertDontSee('Nova pasta')->assertDontSee('/usuarios')->assertDontSee('/auditoria');
    }

    public function test_transport_error_returns_individual_failure_without_staging(): void
    {
        $this->login();
        $source = $this->upload();
        $invalid = new UploadedFile($source->getRealPath(), 'Incompleto.txt', 'text/plain', UPLOAD_ERR_PARTIAL, true);
        $this->postJson('/documentos/upload', ['arquivos' => [$invalid]])->assertUnprocessable()->assertJsonPath('results.0.status', 'error');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_global_batch_size_is_rejected_before_any_publication(): void
    {
        $this->login();
        config(['documents.max_batch_bytes' => 10]);
        $this->postJson('/documentos/upload', ['arquivos' => [$this->upload()]])->assertStatus(413)->assertJsonPath('status', 'error');
        $this->assertSame(0, Item::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_physical_key_collision_does_not_delete_existing_bytes(): void
    {
        $this->login();
        $uuid = '11111111-1111-4111-8111-111111111111';
        Storage::disk('local')->put('documents/'.$uuid, 'Bytes já existentes');
        Str::createUuidsUsing(fn () => $uuid);
        try {
            $this->postJson('/documentos/upload', ['arquivos' => [$this->upload()]])->assertStatus(503);
            $this->assertSame('Bytes já existentes', Storage::disk('local')->get('documents/'.$uuid));
            $this->assertSame(0, Item::count());
        } finally {
            Str::createUuidsNormally();
        }
    }

    public function test_folder_and_user_creation_roll_back_on_audit_failure(): void
    {
        $this->login();
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->andThrow(new RuntimeException('Auditoria indisponível.'));
        $this->app->instance(AuditLogger::class, $audit);
        $this->post('/pastas', ['nome' => 'Não publicada'])->assertStatus(500);
        $this->post('/usuarios', ['username' => 'nao.publicado', 'password' => 'Senha-ficticia-12345', 'password_confirmation' => 'Senha-ficticia-12345', 'role' => 'admin'])->assertStatus(500);
        $this->assertSame(0, Item::count());
        $this->assertFalse(User::where('username', 'nao.publicado')->exists());
    }

    public function test_staging_write_failure_leaves_no_metadata_or_manifest(): void
    {
        $this->login();
        $files = Mockery::mock(LocalUploadFiles::class)->makePartial();
        $files->shouldReceive('stage')->andThrow(new RuntimeException('Disco cheio no staging.'));
        $this->app->instance(LocalUploadFiles::class, $files);
        $this->postJson('/documentos/upload', ['arquivos' => [$this->upload()]])->assertStatus(503);
        $this->assertSame(0, Item::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_cleanup_failure_after_commit_preserves_published_item_and_journal(): void
    {
        $this->login();
        $files = Mockery::mock(LocalUploadFiles::class)->makePartial();
        $files->shouldReceive('cleanup')->andThrow(new RuntimeException('Limpeza indisponível.'));
        $this->app->instance(LocalUploadFiles::class, $files);
        $this->postJson('/documentos/upload', ['arquivos' => [$this->upload()]])->assertOk()->assertJsonPath('status', 'success');
        $item = Item::where('kind', 'file')->firstOrFail();
        $this->assertSame('Arquivo fictício para teste.', Storage::disk('local')->get($item->storage_key));
        $this->assertCount(1, Storage::disk('local')->files('upload-operations'));
    }

    public function test_office_zip_structure_must_match_the_declared_extension(): void
    {
        $this->login();
        $path = tempnam(sys_get_temp_dir(), 'intranet-office-test-');
        try {
            $zip = new \ZipArchive;
            $zip->open($path, \ZipArchive::OVERWRITE);
            $zip->addFromString('word/document.xml', '<document>Conteúdo fictício</document>');
            $zip->close();
            $bytes = file_get_contents($path);
            $this->postJson('/documentos/upload', ['arquivos' => [$this->upload('Modelo.docx', $bytes), $this->upload('Falso.xlsx', $bytes)]])->assertOk()->assertJsonPath('status', 'warning')->assertJsonPath('results.0.status', 'success')->assertJsonPath('results.1.status', 'error');
            $this->assertSame(1, Item::where('kind', 'file')->count());
        } finally {
            unlink($path);
        }
    }
}
