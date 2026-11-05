<?php

/*
| Regras próprias da Intranet FCECON. Os valores padrão repetem os do legado (config/config.php),
| para a troca de sistema não mudar o comportamento para quem usa.
*/
return [

    // Auditoria (tabela `logs`): dias que um registro é guardado antes de ser apagado.
    'log_retention_days' => (int) env('INTRANET_LOG_RETENTION_DAYS', 180),

    // Limitador de login (ADR 0005). Valores do legado: 5 falhas por usuário+IP em 15 minutos;
    // o limite por IP (30) barra quem testa muitos logins do mesmo endereço.
    'login_max_attempts' => (int) env('INTRANET_LOGIN_MAX_ATTEMPTS', 5),
    'login_ip_max_attempts' => (int) env('INTRANET_LOGIN_IP_MAX_ATTEMPTS', 30),
    'login_lockout_minutes' => (int) env('INTRANET_LOGIN_LOCKOUT_MINUTES', 15),

    // Sessão: tempo máximo sem uso e tempo máximo desde o login, mesmo com uso contínuo (segundos).
    'session_idle' => (int) env('INTRANET_SESSION_IDLE', 28800),      // 8 h
    'session_absolute' => (int) env('INTRANET_SESSION_ABSOLUTE', 43200), // 12 h

];
