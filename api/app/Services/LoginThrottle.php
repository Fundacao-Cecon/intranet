<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Limitador de login em camadas (ADR 0005, decisão 1, opção B), sobre a tabela `login_attempts`
 * do legado, para que as duas pilhas somem as mesmas falhas.
 *
 *  - usuário+IP: `login_max_attempts` falhas na janela bloqueiam o par;
 *  - IP: `login_ip_max_attempts` falhas na janela bloqueiam o endereço (barra o spraying);
 *  - o usuário sozinho NÃO bloqueia: um ataque vindo de muitos IPs não pode travar o dono da
 *    conta (o alerta por usuário é o T021).
 *
 * A reserva é um único INSERT condicional: o SQLite serializa a escrita, então 30 tentativas
 * simultâneas não passam do limite (fecha a corrida "verifica e depois grava" do legado).
 * Cada tentativa reserva a sua linha antes de a senha ser conferida; o login certo apaga as
 * linhas do par, e o que sobra é o registro das falhas.
 */
final class LoginThrottle
{
    /** Reserva uma tentativa. false = bloqueado (nada é gravado). */
    public function reserve(string $username, string $ip): bool
    {
        $agora = now()->timestamp;
        $desde = $agora - $this->janela();
        $chave = self::chave($username);

        $gravou = DB::affectingStatement(
            'INSERT INTO login_attempts (ip, username, attempted_at)
             SELECT ?, ?, ?
              WHERE (SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ip = ? AND attempted_at > ?) < ?
                AND (SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?) < ?',
            [
                $ip, $chave, $agora,
                $chave, $ip, $desde, (int) config('intranet.login_max_attempts'),
                $ip, $desde, (int) config('intranet.login_ip_max_attempts'),
            ]
        );

        $this->limparAntigos();

        return $gravou === 1;
    }

    /** Login certo: zera as falhas daquele usuário e IP (as de outros logins do IP continuam). */
    public function clear(string $username, string $ip): void
    {
        DB::table('login_attempts')->where('username', self::chave($username))->where('ip', $ip)->delete();
    }

    /** Segundos até o bloqueio aliviar, para o cabeçalho Retry-After (no mínimo 1). */
    public function retryAfter(string $username, string $ip): int
    {
        $agora = now()->timestamp;
        $desde = $agora - $this->janela();
        $par = DB::table('login_attempts')->where('username', self::chave($username))->where('ip', $ip)->where('attempted_at', '>', $desde);
        $doIp = DB::table('login_attempts')->where('ip', $ip)->where('attempted_at', '>', $desde);

        $espera = 1;
        foreach ([[$par, 'login_max_attempts'], [$doIp, 'login_ip_max_attempts']] as [$consulta, $limite]) {
            if ($consulta->count() >= (int) config("intranet.$limite")) {
                $espera = max($espera, (int) $consulta->min('attempted_at') + $this->janela() - $agora);
            }
        }

        return min(max(1, $espera), $this->janela());
    }

    /** Chave normalizada: minúscula e limitada, para "Ana" e "ANA" dividirem o mesmo orçamento. */
    private static function chave(string $username): string
    {
        return mb_strtolower(mb_substr($username, 0, 100));
    }

    private function janela(): int
    {
        return (int) config('intranet.login_lockout_minutes') * 60;
    }

    /** Limpeza esporádica (1 em 50 tentativas): registros com mais de um dia já não contam. */
    private function limparAntigos(): void
    {
        if (random_int(1, 50) === 1) {
            DB::table('login_attempts')->where('attempted_at', '<', now()->timestamp - 86400)->delete();
        }
    }
}
