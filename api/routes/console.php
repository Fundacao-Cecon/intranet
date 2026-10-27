<?php

use Illuminate\Support\Facades\Schedule;

// Retenção da auditoria (o legado limpava 1 em cada ~50 requisições). Precisa do cron do
// agendador: `* * * * * php artisan schedule:run` (docs/transicao.md).
Schedule::command('intranet:logs-purge')->daily();
