<?php

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentMutations;
use App\Services\DocumentNames;
use App\Services\ItemHierarchy;
use App\Services\UploadCommitter;
use App\Services\UploadService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.pgsql.host') !== 'db-test') {
    exit(2);
}
$root = getenv('UPLOAD_TEST_ROOT');
if (! is_string($root) || ! str_starts_with($root, storage_path('framework/testing/'))) {
    exit(3);
}
config(['filesystems.disks.local.root' => $root]);
$app->instance(DocumentMutations::class, new class(app(DocumentNames::class), app(ItemHierarchy::class), app(AuditLogger::class)) extends DocumentMutations
{
    public function lock(): void
    {
        parent::lock();
        usleep(250000);
    }
});
if (($argv[4] ?? '') === 'lost-confirmation') {
    $app->instance(UploadCommitter::class, new class extends UploadCommitter
    {
        public function commit(): void
        {
            DB::commit();
            throw new RuntimeException('Resposta de commit perdida após confirmação real.');
        }
    });
}
if (($argv[4] ?? '') === 'pause-before-commit') {
    $app->instance(UploadCommitter::class, new class($root) extends UploadCommitter
    {
        public function __construct(private string $root) {}

        public function commit(): void
        {
            file_put_contents($this->root.'/ready', 'ready');
            usleep(1000000);
            parent::commit();
        }
    });
}
$temporary = tempnam(sys_get_temp_dir(), 'intranet-test-');
try {
    file_put_contents($temporary, 'Bytes sintéticos '.$argv[3]);
    $file = new UploadedFile($temporary, 'Mesmo nome.txt', 'text/plain', UPLOAD_ERR_OK, true);
    $result = app(UploadService::class)->one(User::findOrFail((int) $argv[1]), (int) $argv[2], $file, '127.0.0.1');
    echo json_encode($result, JSON_THROW_ON_ERROR);
} finally {
    unlink($temporary);
}
