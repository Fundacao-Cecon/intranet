<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Auditoria na tabela `logs`, no mesmo formato do legado (App\Logger): hora local do servidor,
 * IP, usuário, ação e detalhe de até 500 caracteres. O painel do legado lê estas linhas.
 */
final class AuditLog
{
    public const DETAIL_MAX = 500;

    public function record(string $acao, string $detalhe, ?string $usuario = null): void
    {
        DB::table('logs')->insert([
            'data_hora' => DB::raw("DATETIME('now', 'localtime')"),
            'ip' => request()->ip() ?? 'cli',
            'usuario' => $usuario ?: 'Visitante',
            'acao' => $acao,
            'detalhe' => mb_substr($detalhe, 0, self::DETAIL_MAX),
        ]);
    }

    /**
     * Grava só uma vez por janela para o mesmo detalhe (evita inundar o log). Por padrão a
     * chave inclui o IP; com `$porIp = false` vale para o sistema todo (alerta de um login
     * atacado por vários IPs). Devolve true se gravou.
     */
    public function recordOnce(string $acao, string $detalhe, ?string $usuario, int $janelaMinutos, bool $porIp = true): bool
    {
        $detalhe = mb_substr($detalhe, 0, self::DETAIL_MAX);
        $existe = DB::table('logs')
            ->where('acao', $acao)
            ->when($porIp, fn ($q) => $q->where('ip', request()->ip() ?? 'cli'))
            ->where('detalhe', $detalhe)
            ->whereRaw("data_hora > DATETIME('now', 'localtime', ?)", ['-'.max(1, $janelaMinutos).' minutes'])
            ->exists();

        if ($existe) {
            return false;
        }

        $this->record($acao, $detalhe, $usuario);

        return true;
    }

    /** Apaga os registros com mais de $days dias (no mínimo 1). Devolve quantos apagou. */
    public function purge(int $days): int
    {
        return DB::table('logs')
            ->whereRaw("data_hora < DATETIME('now', 'localtime', ?)", ['-'.max(1, $days).' days'])
            ->delete();
    }

    /**
     * Texto seguro para o log: o login real, ou "desconhecido#<hash curto>". Nunca o texto digitado
     * quando o usuário não existe, porque é comum digitar a senha no campo de usuário.
     */
    public function label(string $typed, ?User $user): string
    {
        if ($user) {
            return $user->username;
        }

        return 'desconhecido#'.substr(hash('sha256', mb_strtolower(mb_substr($typed, 0, 100))), 0, 8);
    }
}
