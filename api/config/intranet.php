<?php

/*
| Regras próprias da Intranet FCECON. Os valores padrão repetem os do legado (config/config.php),
| para a troca de sistema não mudar o comportamento para quem usa.
*/
return [

    // Auditoria (tabela `logs`): dias que um registro é guardado antes de ser apagado.
    'log_retention_days' => (int) env('INTRANET_LOG_RETENTION_DAYS', 180),

    // Sessão: tempo máximo sem uso e tempo máximo desde o login, mesmo com uso contínuo (segundos).
    'session_idle' => (int) env('INTRANET_SESSION_IDLE', 28800),      // 8 h
    'session_absolute' => (int) env('INTRANET_SESSION_ABSOLUTE', 43200), // 12 h

];
