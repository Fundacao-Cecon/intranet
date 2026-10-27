<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Login, logout e "quem sou eu" da SPA (Sanctum por cookie de sessão, mesmo domínio).
 * Limitador, alerta, senha temporária e teto de sessão entram nas tarefas T020 a T025 e T016.
 */
class AuthController extends Controller
{
    /**
     * Hash bcrypt (custo 10) de uma senha aleatória descartada, o mesmo do legado
     * (App\Auth::DUMMY_HASH). Roda a verificação mesmo quando o login não existe, para
     * igualar o tempo de resposta e impedir enumerar usuários por cronometragem.
     */
    private const DUMMY_HASH = '$2y$10$VHvJoBNQ3REF96YP9b/vnOqqwi7d.zFLi1OCi0RDqW1cOM2jief8W';

    public function login(Request $request, AuditLog $audit): JsonResponse
    {
        // Origem fora de SANCTUM_STATEFUL_DOMAINS: a rota não ganha sessão. Recusa com clareza
        // em vez de estourar 500 mais adiante.
        if (! $request->hasSession()) {
            return response()->json(['message' => 'Origem não autorizada para login.'], 403);
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        /** @var User|null $user */
        $user = User::where('username', $data['username'])->first();

        $ok = Hash::check($data['password'], $user->password ?? self::DUMMY_HASH);
        if (! $user || ! $ok) {
            $audit->record('LOGIN_FALHA', $audit->label($data['username'], $user), 'Visitante');

            return response()->json(['message' => 'Usuário ou senha incorretos.'], 422);
        }

        // Hash antigo ou de outro custo: regrava no custo atual (o legado continua verificando).
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($data['password'])])->save();
        }

        // Novo ID de sessão: evita session fixation.
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $agora = now()->timestamp;
        $request->session()->put(['login_at' => $agora, 'last_activity' => $agora]);

        // Mesmo formato do legado (hora local do servidor).
        DB::table('users')->where('id', $user->id)
            ->update(['last_login' => DB::raw("DATETIME('now', 'localtime')")]);
        $audit->record('LOGIN', 'Entrou no sistema', $user->username);

        return response()->json($this->payload($user));
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    /** @return array{id:int, username:string, role:string} */
    private function payload(User $user): array
    {
        return ['id' => (int) $user->id, 'username' => $user->username, 'role' => $user->role];
    }
}
