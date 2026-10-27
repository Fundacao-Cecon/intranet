<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limites de sessão do legado (App\Auth::user): ociosidade e teto absoluto desde o login.
 *
 * - Ociosa há mais de `intranet.session_idle` (8 h): encerra.
 * - Logada há mais de `intranet.session_absolute` (12 h), mesmo com uso contínuo: encerra.
 *
 * Ao expirar, a sessão é invalidada (novo ID e novo token CSRF) e a requisição responde 401 com
 * `code: session_expired`, para a tela avisar "sua sessão expirou". A exceção é o próprio login:
 * quem volta depois de expirar precisa conseguir entrar sem esbarrar nesse 401.
 */
class EnforceSessionLimits
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! Auth::guard('web')->check()) {
            return $next($request);
        }

        $session = $request->session();
        $agora = now()->timestamp;

        $loginAt = (int) $session->get('login_at');
        if (! $loginAt) { // sessão anterior ao teto absoluto: passa a contar daqui
            $loginAt = $agora;
            $session->put('login_at', $agora);
        }
        $ultimo = (int) $session->get('last_activity');

        $ociosa = $ultimo && ($agora - $ultimo) > (int) config('intranet.session_idle');
        $vencida = ($agora - $loginAt) > (int) config('intranet.session_absolute');

        if ($ociosa || $vencida) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            if ($request->is('api/v1/auth/login')) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Sua sessão expirou. Entre novamente.',
                'code' => 'session_expired',
            ], 401);
        }

        $session->put('last_activity', $agora);

        return $next($request);
    }
}
