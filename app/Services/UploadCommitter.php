<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class UploadCommitter
{
    public function commit(): void
    {
        DB::commit();
    }
}
