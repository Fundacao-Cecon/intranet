<?php

namespace App\Services;

use App\Models\Item;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UploadService
{
    public function __construct(private LocalUploadFiles $files, private UploadValidator $validator, private DocumentMutations $documents, private AuditLogger $audit, private UploadCommitter $committer) {}

    public function one(User $actor, ?int $parent, UploadedFile $file, ?string $ip): array
    {
        $operation = (string) Str::uuid();
        $stage = 'staging/'.$operation;
        $final = 'documents/'.$operation;
        $journal = 'upload-operations/'.$operation.'.json';
        $transaction = false;
        $committing = false;
        $physicalOwned = false;
        $name = $file->getClientOriginalName();
        $manifest = ['version' => 1, 'operation_id' => $operation, 'staging_key' => $stage, 'storage_key' => $final, 'phase' => 'staging', 'created_at' => now()->toIso8601String()];
        try {
            $name = $this->validator->beforeStaging($file);
            $this->files->journal($journal, $manifest);
            $this->files->stage($file, $stage);
            $metadata = $this->validator->staged($stage, $name);
            DB::beginTransaction();
            $transaction = true;
            $this->documents->lock();
            $this->documents->destination($parent);
            $name = $this->documents->reserveName($parent, $name);
            $item = Item::create(['kind' => 'file', 'name' => $name, 'parent_id' => $parent, 'storage_key' => $final, 'size_bytes' => $metadata['size_bytes'], 'media_type' => $metadata['media_type'], 'created_by_id' => $actor->id, 'created_by_snapshot' => $actor->username]);
            $manifest += ['item_id' => $item->id, 'sha256' => $metadata['sha256'], 'size_bytes' => $metadata['size_bytes']];
            $manifest['phase'] = 'reserved';
            $this->files->journal($journal, $manifest);
            $this->files->publish($stage, $final);
            $physicalOwned = true;
            $this->audit->record('UPLOAD_PUBLISHED', $name, 'success', $actor, $item, ip: $ip);
            $manifest['phase'] = 'committing';
            $this->files->journal($journal, $manifest);
            $committing = true;
            $this->committer->commit();
            $transaction = false;
        } catch (Throwable $exception) {
            $pending = $committing;
            if ($transaction) {
                try {
                    DB::rollBack();
                } catch (Throwable) {
                    $pending = true;
                }
            }
            if (! $pending) {
                try {
                    $this->files->cleanup(array_merge([$stage], $physicalOwned ? [$final] : [], [$journal.'.tmp', $journal]));
                } catch (Throwable) {
                    $pending = true;
                }
            }
            Log::warning('Upload não confirmado.', ['operation_id' => $operation, 'pending' => $pending]);
            try {
                $this->audit->record($pending ? 'UPLOAD_PENDING' : 'UPLOAD_FAILED', 'Envio de arquivo', 'failure', $actor, context: ['reason_code' => $pending ? 'reconciliation_required' : 'upload_rejected'], ip: $ip);
            } catch (Throwable) {
                Log::warning('Auditoria de upload indisponível.', ['operation_id' => $operation]);
            }

            return ['name' => $name, 'status' => $pending ? 'pending' : 'error', 'msg' => $pending ? 'Confirmação pendente de reconciliação. Não reenvie este arquivo ainda.' : ($exception instanceof ValidationException ? collect($exception->errors())->flatten()->first() : 'Falha ao publicar o arquivo.'), 'operation_id' => $operation, 'infrastructure_failure' => ! $exception instanceof ValidationException];
        }
        try {
            $this->files->cleanup([$stage, $journal.'.tmp', $journal]);
        } catch (Throwable) {
            Log::warning('Limpeza de manifesto pendente.', ['operation_id' => $operation]);
        }

        return ['name' => $name, 'status' => 'success', 'msg' => 'Arquivo publicado.', 'item_id' => $item->id, 'operation_id' => $operation, 'infrastructure_failure' => false];
    }
}
