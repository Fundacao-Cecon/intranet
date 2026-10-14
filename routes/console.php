<?php

use App\Models\Item;
use App\Services\HealthChecker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('intranet:ready', function (HealthChecker $health): int {
    try {
        $health->check();

        return 0;
    } catch (Throwable) {
        $this->error('Ambiente indisponível.');

        return 1;
    }
})->purpose('Verifica PostgreSQL e storage privado sem expor detalhes internos.');

Artisan::command('intranet:uploads:pending', function (): int {
    try {
        $disk = Storage::disk('local');
        $rows = [];
        foreach ($disk->files('upload-operations') as $key) {
            if (! str_ends_with($key, '.json')) {
                continue;
            }
            $manifest = json_decode($disk->get($key), true, flags: JSON_THROW_ON_ERROR);
            $reference = isset($manifest['storage_key']) ? Item::where('storage_key', $manifest['storage_key'])->exists() : false;
            $rows[] = [$manifest['operation_id'] ?? '-', $manifest['phase'] ?? '-', $reference ? 'Sim' : 'Não'];
        }
        if ($rows === []) {
            $this->info('Nenhum manifesto de upload pendente.');
        } else {
            $this->table(['Operação', 'Fase', 'Referência confirmada no banco'], $rows);
            $this->warn('Inspeção somente leitura. Não apaga bytes nem decide reconciliação.');
        }

        return 0;
    } catch (Throwable) {
        $this->error('Não foi possível consultar as pendências. Nenhum dado foi alterado.');

        return 1;
    }
})->purpose('Lista manifestos de upload e referências confirmadas, sem alterar dados.');
