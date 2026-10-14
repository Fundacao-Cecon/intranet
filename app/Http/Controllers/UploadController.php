<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\DocumentMutations;
use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UploadController extends Controller
{
    public function __invoke(Request $request, UploadService $uploads, DocumentMutations $documents)
    {
        Gate::authorize('create', Item::class);
        $data = $request->validate(['pasta' => 'nullable|integer|min:1', 'arquivos' => 'required|array|min:1|max:'.config('documents.max_files')], [
            'arquivos.required' => 'Selecione pelo menos um arquivo.', 'arquivos.max' => 'Envie no máximo dez arquivos por lote.',
        ]);
        $files = $request->file('arquivos', []);
        $total = 0;
        foreach ($files as $file) {
            abort_unless($file instanceof UploadedFile, 422, 'Lote inválido.');
            if ($file->isValid()) {
                $total += $file->getSize();
            }
        }
        abort_unless(count($files) === count($data['arquivos']), 422, 'Lote inválido.');
        abort_if($total > config('documents.max_batch_bytes'), 413, 'O lote excede 64 MiB.');
        DB::transaction(function () use ($documents, $data) {
            $documents->lock();
            $documents->destination($data['pasta'] ?? null);
        });
        $results = [];
        foreach ($files as $file) {
            $results[] = $uploads->one($request->user(), $data['pasta'] ?? null, $file, $request->ip());
        }
        $success = count(array_filter($results, fn ($result) => $result['status'] === 'success'));
        $infrastructure = count(array_filter($results, fn ($result) => $result['infrastructure_failure']));
        $status = $success === count($results) ? 'success' : ($success > 0 ? 'warning' : 'error');
        $http = $success > 0 ? 200 : ($infrastructure > 0 ? 503 : 422);
        foreach ($results as &$result) {
            unset($result['infrastructure_failure']);
        }

        return response()->json(['status' => $status, 'msg' => $success.' de '.count($results).' arquivo(s) publicado(s).', 'results' => $results], $http);
    }
}
