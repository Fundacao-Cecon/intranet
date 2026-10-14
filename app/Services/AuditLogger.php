<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\User;
use InvalidArgumentException;

class AuditLogger
{
    private const CONTEXT_KEYS = ['item_name', 'username', 'file_count', 'reason_code'];

    public function record(
        string $action,
        string $target,
        string $outcome,
        ?User $actor = null,
        ?Item $item = null,
        array $context = [],
        ?string $ip = null,
    ): AuditLog {
        if (strlen($action) > 64 || ! preg_match('/^[A-Z][A-Z0-9_.]*$/', $action) || $target === '') {
            throw new InvalidArgumentException('Ação ou alvo de auditoria inválido.');
        }
        if (! in_array($outcome, ['success', 'failure', 'denied'], true)) {
            throw new InvalidArgumentException('Resultado de auditoria inválido.');
        }
        foreach ($context as $key => $value) {
            if (! in_array($key, self::CONTEXT_KEYS, true) || (! is_scalar($value) && $value !== null)) {
                throw new InvalidArgumentException('Contexto de auditoria não permitido.');
            }
        }
        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('IP de auditoria inválido.');
        }

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'item_id' => $item?->id,
            'actor_name_snapshot' => $actor?->username,
            'action' => $action,
            'target_snapshot' => $target,
            'outcome' => $outcome,
            'detail' => $context === [] ? null : json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip' => $ip,
            'occurred_at' => now('UTC'),
        ]);
    }
}
