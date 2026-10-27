<?php

namespace App\Console\Commands;

use App\Services\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('intranet:logs-purge {--days= : Dias a guardar (padrão: intranet.log_retention_days)}')]
#[Description('Apaga os registros de auditoria mais antigos que o período de retenção')]
class PurgeAuditLogs extends Command
{
    public function handle(AuditLog $audit): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('intranet.log_retention_days');

        $this->info($audit->purge($days)." registro(s) apagado(s) (retenção: {$days} dias).");

        return self::SUCCESS;
    }
}
