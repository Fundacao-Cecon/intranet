<?php

namespace Tests\Feature;

use App\Http\Middleware\StrictCsrf;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AccessAndDocumentsTest extends TestCase
{
    private const PASSWORD = 'Senha-ficticia-12345';

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        Cache::flush();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function account(string $role = 'user'): User
    {
        return User::factory()->create(['password_hash' => self::PASSWORD, 'role' => $role]);
    }

    private function signedIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['credential_fingerprint' => hash('sha256', $user->password_hash)]);
    }

    private function file(array $attributes = [], string $bytes = 'Documento fictício de teste.'): Item
    {
        $item = Item::factory()->file()->create(array_merge(['size_bytes' => strlen($bytes)], $attributes));
        Storage::disk('local')->put($item->storage_key, $bytes);

        return $item;
    }

    public function test_visitors_cannot_list_or_read_documents(): void
    {
        $file = $this->file();
        $this->get('/documentos')->assertRedirect('/login');
        $this->get('/arquivos/'.$file->id.'/download')->assertRedirect('/login');
        $this->getJson('/arquivos/'.$file->id.'/visualizar')->assertUnauthorized();
        $this->assertSame(0, AuditLog::where('outcome', 'success')->count());
    }

    public function test_both_profiles_login_and_regenerate_session_with_audit(): void
    {
        foreach (['user', 'admin'] as $role) {
            $user = $this->account($role);
            $this->withSession(['sentinel' => 'session-before-login']);
            $old = session()->getId();
            $this->post('/login', ['username' => $user->username, 'password' => self::PASSWORD])->assertRedirect('/documentos');
            $this->assertAuthenticatedAs($user);
            $this->assertNotSame($old, session()->getId());
            $this->assertNotNull($user->fresh()->last_login);
            $this->assertSame('success', AuditLog::where('action', 'LOGIN')->where('actor_id', $user->id)->value('outcome'));
            $this->get('/documentos')->assertOk();
            $this->post('/logout')->assertRedirect('/login');
        }
    }

    public function test_invalid_login_does_not_create_session_or_update_last_login(): void
    {
        $user = $this->account();
        $this->from('/login')->post('/login', ['username' => $user->username, 'password' => 'senha-incorreta'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login);
        $event = AuditLog::first();
        $this->assertSame('failure', $event->outcome);
        $this->assertStringNotContainsString('senha-incorreta', $event->toJson());
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_login_attempts_are_limited_and_malformed_username_is_validation_error(): void
    {
        $this->postJson('/login', ['username' => ['invalid'], 'password' => 'invalid'])->assertUnprocessable();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['username' => 'conta-inexistente', 'password' => 'invalida'])->assertUnprocessable();
        }
        $this->postJson('/login', ['username' => 'conta-inexistente', 'password' => 'invalida'])->assertStatus(429);
    }

    public function test_password_change_requires_current_password_before_login_for_both_roles(): void
    {
        foreach (['user', 'admin'] as $role) {
            $user = $this->account($role);
            $data = ['username' => $user->username, 'current_password' => 'incorreta', 'password' => 'Nova-senha-ficticia-123', 'password_confirmation' => 'Nova-senha-ficticia-123'];
            $hash = $user->password_hash;
            $this->from('/senha')->post('/senha', $data)->assertSessionHasErrors('username');
            $this->assertSame($hash, $user->fresh()->password_hash);
            $this->assertNull(session()->getOldInput('current_password'));
            $data['current_password'] = self::PASSWORD;
            $this->post('/senha', $data)->assertRedirect('/login');
            $this->assertTrue(Hash::check($data['password'], $user->fresh()->password_hash));
            $this->assertGuest();
        }
    }

    public function test_authenticated_password_change_cannot_target_another_account(): void
    {
        $user = $this->account();
        $other = $this->account();
        $otherHash = $other->password_hash;
        $this->signedIn($user)->post('/senha', ['username' => $other->username, 'current_password' => self::PASSWORD, 'password' => 'Minha-nova-senha-123', 'password_confirmation' => 'Minha-nova-senha-123'])->assertRedirect('/documentos');
        $this->assertSame($otherHash, $other->fresh()->password_hash);
        $this->assertTrue(Hash::check('Minha-nova-senha-123', $user->fresh()->password_hash));
        $this->get('/documentos')->assertOk();
    }

    public function test_password_change_rejects_mismatch_short_and_bcrypt_overlong_bytes(): void
    {
        $user = $this->account();
        $hash = $user->password_hash;
        foreach (['curta', str_repeat('á', 40)] as $password) {
            $this->postJson('/senha', ['username' => $user->username, 'current_password' => self::PASSWORD, 'password' => $password, 'password_confirmation' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->postJson('/senha', ['username' => $user->username, 'current_password' => self::PASSWORD, 'password' => 'Outra-senha-valida-123', 'password_confirmation' => 'diferente'])->assertJsonValidationErrors('password');
        $this->assertSame($hash, $user->fresh()->password_hash);
    }

    public function test_password_change_invalidates_previous_sessions(): void
    {
        $user = $this->account();
        $fingerprint = hash('sha256', $user->password_hash);
        $user->password_hash = 'Senha-alterada-fora-desta-sessao';
        $user->save();
        $this->actingAs($user)->withSession(['credential_fingerprint' => $fingerprint])->get('/documentos')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_missing_session_fingerprint_and_logout_prevent_further_access(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get('/documentos')->assertRedirect('/login');
        $this->signedIn($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/documentos')->assertRedirect('/login');
        $this->assertNull(session('credential_fingerprint'));
    }

    public function test_real_csrf_check_rejects_missing_invalid_and_same_origin_tokens(): void
    {
        $this->app->bind(StrictCsrf::class, fn ($app) => new class($app, $app['encrypter']) extends StrictCsrf
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $user = $this->account();
        $data = ['username' => $user->username, 'password' => self::PASSWORD];
        $this->withSession(['_token' => 'valid-token'])->post('/login', $data)->assertStatus(419);
        $this->withHeaders(['Sec-Fetch-Site' => 'same-origin'])->post('/login', $data + ['_token' => 'invalid'])->assertStatus(419);
        $this->post('/login', $data + ['_token' => 'valid-token'])->assertRedirect('/documentos');
        $this->post('/logout', ['_token' => 'invalid'])->assertStatus(419);
        $this->assertAuthenticated();
    }

    public function test_admin_policies_deny_user_and_prevent_self_deletion(): void
    {
        $user = $this->account();
        $admin = $this->account('admin');
        $item = Item::factory()->create();
        Route::post('/_test-admin', fn () => Gate::authorize('create', Item::class))->middleware(['web', 'auth']);
        $this->signedIn($user)->post('/_test-admin')->assertForbidden();
        $this->assertFalse(Gate::forUser($user)->allows('admin'));
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $item));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Item::class));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $user));
    }

    public function test_navigation_orders_folders_before_files_and_preserves_missing_metadata(): void
    {
        $user = $this->account();
        $file = $this->file(['name' => 'AAA arquivo.txt', 'created_at' => null, 'updated_at' => null, 'created_by_snapshot' => null]);
        $folder = Item::factory()->create(['name' => 'ZZZ pasta']);
        $this->signedIn($user)->get('/documentos')->assertOk()->assertSeeInOrder([$folder->name, $file->name])->assertSee('bytes')->assertSee('<td data-label="Criado por / em">-<small>-</small></td>', false);
    }

    public function test_breadcrumbs_and_filter_are_scoped_to_current_folder_and_case_insensitive(): void
    {
        $folder = Item::factory()->create(['name' => 'Normas fictícias']);
        $child = Item::factory()->create(['name' => 'Orientações fictícias', 'parent_id' => $folder->id]);
        $this->file(['name' => 'GUIA.txt', 'parent_id' => $child->id]);
        $this->file(['name' => 'Outro arquivo.txt', 'parent_id' => $child->id]);
        $this->file(['name' => 'Guia externo.txt']);
        $this->signedIn($this->account())->get('/documentos?pasta='.$child->id.'&q=guia')->assertOk()->assertSeeInOrder(['Raiz', $folder->name, $child->name])->assertSee('GUIA.txt')->assertDontSee('Guia externo.txt')->assertDontSee('Outro arquivo.txt');
    }

    public function test_filter_treats_sql_wildcards_literally_and_blade_escapes_names(): void
    {
        $this->file(['name' => '100% guia.txt']);
        $this->file(['name' => '1000 guia.txt']);
        $this->file(['name' => '<script>alert(1)</script>.txt']);
        $this->signedIn($this->account())->get('/documentos?q=%25')->assertSee('100% guia.txt')->assertDontSee('1000 guia.txt');
        $this->get('/documentos')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;.txt', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_removed_folder_hides_descendants_from_navigation_and_all_read_routes(): void
    {
        $removed = Item::factory()->trashed()->create();
        $child = Item::factory()->create(['parent_id' => $removed->id]);
        $file = $this->file(['parent_id' => $child->id]);
        $this->signedIn($this->account())->get('/documentos')->assertDontSee($removed->name);
        $this->get('/documentos?pasta='.$child->id)->assertForbidden();
        foreach (['download', 'visualizar', 'miniatura'] as $action) {
            $this->get('/arquivos/'.$file->id.'/'.$action)->assertForbidden();
        }
        $this->assertSame(0, AuditLog::where('outcome', 'success')->count());
    }

    public function test_missing_file_folder_and_traversal_ids_do_not_open_storage(): void
    {
        $this->signedIn($this->account())->get('/documentos?pasta=999999999')->assertNotFound();
        $this->get('/documentos?pasta=../uploads')->assertSessionHasErrors('pasta');
        $folder = Item::factory()->create();
        $this->get('/arquivos/'.$folder->id.'/download')->assertNotFound();
        $file = Item::factory()->file()->create(['storage_key' => 'documents/../../.env']);
        $this->get('/arquivos/'.$file->id.'/download')->assertNotFound();
        $this->get('/arquivos/not-an-id/download')->assertNotFound();
    }

    public function test_both_profiles_download_exact_bytes_and_safe_filename_with_started_audit(): void
    {
        $file = $this->file(['name' => 'Guia ação.txt'], 'Bytes fictícios 123.');
        foreach (['user', 'admin'] as $role) {
            $user = $this->account($role);
            $response = $this->signedIn($user)->get('/arquivos/'.$file->id.'/download');
            $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame('Bytes fictícios 123.', $response->streamedContent());
            $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString("filename*=utf-8''Guia%20a%C3%A7%C3%A3o.txt", $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->assertSame(2, AuditLog::where('action', 'DOWNLOAD_STARTED')->count());
        $this->assertStringNotContainsString('Bytes fictícios', AuditLog::first()->toJson());
    }

    public function test_text_and_pdf_inline_but_html_svg_and_mime_mismatch_are_attachments(): void
    {
        $this->signedIn($this->account());
        foreach ([['Guia.txt', 'Documento fictício', true], ['Guia.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF", true], ['pagina.html', '<html><script>alert(1)</script></html>', false], ['imagem.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', false], ['falso.pdf', '<html>conteúdo</html>', false]] as [$name, $bytes, $inline]) {
            $file = $this->file(['name' => $name], $bytes);
            $response = $this->get('/arquivos/'.$file->id.'/visualizar')->assertOk();
            $this->assertStringContainsString($inline ? 'inline;' : 'attachment;', $response->headers->get('Content-Disposition'));
            $this->assertSame($bytes, $response->streamedContent());
        }
    }

    public function test_raster_thumbnail_is_authenticated_and_non_raster_thumbnail_is_rejected(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=');
        $file = $this->file(['name' => 'miniatura.png'], $png);
        $this->get('/arquivos/'.$file->id.'/miniatura')->assertRedirect('/login');
        $response = $this->signedIn($this->account())->get('/arquivos/'.$file->id.'/miniatura')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($png, $response->streamedContent());
        $text = $this->file();
        $this->get('/arquivos/'.$text->id.'/miniatura')->assertNotFound();
    }

    public function test_byte_ranges_preserve_requested_bytes_and_reject_invalid_ranges(): void
    {
        $file = $this->file([], '0123456789');
        $this->signedIn($this->account());
        foreach (['bytes=2-5' => '2345', 'bytes=-3' => '789', 'bytes=7-' => '789'] as $range => $expected) {
            $response = $this->withHeader('Range', $range)->get('/arquivos/'.$file->id.'/download')->assertStatus(206);
            $this->assertSame($expected, $response->streamedContent());
        }
        $this->withHeader('Range', 'bytes=50-60')->get('/arquivos/'.$file->id.'/download')->assertStatus(416)->assertHeader('Content-Range', 'bytes */10');
        $this->withHeader('Range', 'bytes=0-1,5-6')->get('/arquivos/'.$file->id.'/download')->assertStatus(416);
    }

    public function test_missing_disk_file_and_audit_failure_never_stream_success(): void
    {
        $file = $this->file();
        Storage::disk('local')->delete($file->storage_key);
        $this->signedIn($this->account())->get('/arquivos/'.$file->id.'/download')->assertNotFound();
        Storage::disk('local')->put($file->storage_key, 'Documento fictício');
        $mock = Mockery::mock(AuditLogger::class);
        $mock->shouldReceive('record')->andThrow(new RuntimeException('password=NEVER_LEAK'));
        $this->app->instance(AuditLogger::class, $mock);
        $this->get('/arquivos/'.$file->id.'/download')->assertStatus(503)->assertDontSee('NEVER_LEAK')->assertDontSee($file->storage_key);
    }

    public function test_get_does_not_change_password_or_logout_or_publish_documents(): void
    {
        $user = $this->account();
        $hash = $user->password_hash;
        $this->signedIn($user)->get('/senha?password=invalid')->assertOk();
        $this->get('/logout')->assertStatus(405);
        $this->get('/documentos/upload')->assertNotFound();
        $this->assertSame($hash, $user->fresh()->password_hash);
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, Item::count());
    }

    public function test_removed_file_itself_cannot_deliver_bytes(): void
    {
        $file = $this->file(['deleted_at' => now(), 'trash_name' => 'Arquivo removido fictício']);
        $this->signedIn($this->account())->get('/arquivos/'.$file->id.'/download')->assertForbidden();
        $this->assertSame(0, AuditLog::where('action', 'DOWNLOAD_STARTED')->count());
        $this->assertSame(1, AuditLog::where('action', 'ACCESS_DENIED')->count());
    }

    public function test_authentication_and_password_changes_roll_back_when_audit_fails(): void
    {
        $user = $this->account();
        $hash = $user->password_hash;
        $mock = Mockery::mock(AuditLogger::class);
        $mock->shouldReceive('record')->andThrow(new RuntimeException('Auditoria indisponível.'));
        $this->app->instance(AuditLogger::class, $mock);
        $this->post('/login', ['username' => $user->username, 'password' => self::PASSWORD])->assertStatus(500);
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login);
        $this->post('/senha', ['username' => $user->username, 'current_password' => self::PASSWORD, 'password' => 'Senha-nova-ficticia-123', 'password_confirmation' => 'Senha-nova-ficticia-123'])->assertStatus(500);
        $this->assertSame($hash, $user->fresh()->password_hash);
    }

    public function test_mp4_inline_and_range_headers_are_supported(): void
    {
        // Assinatura de container sintético; reprodução de codecs depende do navegador.
        $bytes = pack('N', 24).'ftypisom'.pack('N', 0).'isommp42';
        $file = $this->file(['name' => 'video-ficticio.mp4'], $bytes);
        $response = $this->signedIn($this->account())->get('/arquivos/'.$file->id.'/visualizar')->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->assertStringContainsString('inline;', $response->headers->get('Content-Disposition'));
        $this->assertSame($bytes, $response->streamedContent());
        $partial = $this->withHeader('Range', 'bytes=0-7')->get('/arquivos/'.$file->id.'/visualizar')->assertStatus(206);
        $this->assertSame(substr($bytes, 0, 8), $partial->streamedContent());
    }

    public function test_logout_invalidates_session_even_when_audit_is_unavailable(): void
    {
        $user = $this->account();
        $mock = Mockery::mock(AuditLogger::class);
        $mock->shouldReceive('record')->andThrow(new RuntimeException('Auditoria indisponível.'));
        $this->app->instance(AuditLogger::class, $mock);
        $this->signedIn($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNull(session('credential_fingerprint'));
        $this->get('/documentos')->assertRedirect('/login');
    }

    public function test_symlink_cannot_escape_the_document_area(): void
    {
        $file = $this->file();
        $disk = Storage::disk('local');
        $disk->put('outside.txt', 'Não entregar este conteúdo.');
        $disk->delete($file->storage_key);
        symlink($disk->path('outside.txt'), $disk->path($file->storage_key));
        $this->signedIn($this->account())->get('/arquivos/'.$file->id.'/download')->assertNotFound()->assertDontSee('Não entregar este conteúdo.');
    }
}
