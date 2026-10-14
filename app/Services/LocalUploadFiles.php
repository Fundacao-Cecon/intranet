<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LocalUploadFiles
{
    public function stage(UploadedFile $file, string $key): void
    {
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            if (! is_resource($stream) || ! Storage::disk('local')->put($key, $stream)) {
                throw new RuntimeException('Staging indisponível.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function journal(string $key, array $data): void
    {
        $disk = Storage::disk('local');
        if (! $disk->put($key.'.tmp', json_encode($data, JSON_THROW_ON_ERROR)) || ! $disk->move($key.'.tmp', $key)) {
            throw new RuntimeException('Manifesto indisponível.');
        }
    }

    public function publish(string $stage, string $final): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('documents');
        $privateRoot = realpath($disk->path(''));
        if (realpath($disk->path('documents')) !== $privateRoot.DIRECTORY_SEPARATOR.'documents'
            || ! @link($disk->path($stage), $disk->path($final))) {
            throw new RuntimeException('Publicação indisponível.');
        }
        // Hard link atômico no mesmo volume: recusa destino existente em vez de sobrescrevê-lo.
        // Staging só é removido após confirmação; ambos os links ficam no volume privado.
    }

    public function cleanup(array $keys): void
    {
        foreach ($keys as $key) {
            if (! Storage::disk('local')->delete($key)) {
                throw new RuntimeException('Limpeza pendente.');
            }
        }
    }
}
