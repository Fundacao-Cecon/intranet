<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HealthChecker
{
    public function check(): void
    {
        $probe = '.health/'.Str::uuid();
        try {
            DB::select('SELECT 1');
            $disk = Storage::disk('local');
            if (! $disk->put($probe, 'ok') || ! $disk->delete($probe)) {
                throw new RuntimeException('Storage indisponível.');
            }
        } catch (Throwable) {
            throw new RuntimeException('Ambiente indisponível.');
        }
    }
}
