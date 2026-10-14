<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class UploadConcurrencyTest extends TestCase
{
    private User $actor;

    private Item $folder;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actor = User::factory()->create(['role' => 'admin']);
        $this->folder = Item::factory()->create();
    }

    protected function tearDown(): void
    {
        $children = Item::where('parent_id', $this->folder->id)->get();
        AuditLog::where('actor_id', $this->actor->id)->delete();
        foreach ($children as $item) {
            $item->delete();
        }
        $this->folder->delete();
        $this->actor->delete();
        Storage::disk('local')->deleteDirectory('documents');
        Storage::disk('local')->deleteDirectory('staging');
        Storage::disk('local')->deleteDirectory('upload-operations');
        Storage::disk('local')->delete('ready');
        parent::tearDown();
    }

    private function worker(string $label, string $mode = ''): Process
    {
        return new Process([PHP_BINARY, base_path('tests/Support/upload-worker.php'), (string) $this->actor->id, (string) $this->folder->id, $label, $mode], base_path(), ['UPLOAD_TEST_ROOT' => Storage::disk('local')->path('')], timeout: 15);
    }

    public function test_two_real_postgresql_connections_publish_same_name_without_overwrite(): void
    {
        $first = $this->worker('A');
        $second = $this->worker('B');
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();
        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
        $this->assertSame('success', json_decode($first->getOutput(), true)['status']);
        $this->assertSame('success', json_decode($second->getOutput(), true)['status']);
        $items = Item::where('parent_id', $this->folder->id)->orderBy('id')->get();
        $this->assertSame(['Mesmo nome.txt', 'Mesmo nome (1).txt'], $items->pluck('name')->all());
        $bytes = $items->map(fn ($item) => Storage::disk('local')->get($item->storage_key))->all();
        sort($bytes);
        $this->assertSame(['Bytes sintéticos A', 'Bytes sintéticos B'], $bytes);
        $this->assertCount(2, $items->pluck('storage_key')->unique());
    }

    public function test_lost_confirmation_after_real_commit_keeps_confirmed_item_and_bytes(): void
    {
        $worker = $this->worker('Commit confirmado', 'lost-confirmation');
        $worker->run();
        $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
        $this->assertSame('pending', json_decode($worker->getOutput(), true)['status']);
        $item = Item::where('parent_id', $this->folder->id)->firstOrFail();
        $this->assertSame('Bytes sintéticos Commit confirmado', Storage::disk('local')->get($item->storage_key));
        $journals = Storage::disk('local')->files('upload-operations');
        $this->assertCount(1, $journals);
        $manifest = json_decode(Storage::disk('local')->get($journals[0]), true);
        $this->assertSame($item->id, $manifest['item_id']);
        $this->assertSame('committing', $manifest['phase']);
    }

    public function test_other_connection_cannot_list_an_item_before_publication_commit(): void
    {
        $worker = $this->worker('Publicação em andamento', 'pause-before-commit');
        $worker->start();
        try {
            $deadline = microtime(true) + 5;
            while (! Storage::disk('local')->exists('ready') && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertTrue(Storage::disk('local')->exists('ready'));
            $this->assertSame(0, Item::where('parent_id', $this->folder->id)->count());
            $this->assertCount(1, Storage::disk('local')->files('documents'));
        } finally {
            $worker->wait();
        }
        $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
        $this->assertSame(1, Item::where('parent_id', $this->folder->id)->count());
    }
}
