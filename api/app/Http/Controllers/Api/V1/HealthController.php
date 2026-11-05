<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Saúde para o monitor da TI e para o `intranet:smoke` (revisão de arquitetura, D5 e D6).
 * Pública e sem dados sensíveis: só diz se a API responde e se o banco compartilhado está de pé
 * com o esquema adotado. O motivo de uma falha vai para o log técnico, não para a resposta.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $banco = $this->banco();
        $ok = $banco === 'ok';

        return response()->json(
            ['status' => $ok ? 'ok' : 'degraded', 'checks' => ['database' => $banco]],
            $ok ? 200 : 503,
            ['Cache-Control' => 'no-store'],
        );
    }

    private function banco(): string
    {
        try {
            $tabela = DB::selectOne("SELECT 1 AS ok FROM sqlite_master WHERE type = 'table' AND name = 'users'");

            return $tabela ? 'ok' : 'falha';
        } catch (Throwable $e) {
            Log::error('health: banco indisponível', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return 'falha';
        }
    }
}
