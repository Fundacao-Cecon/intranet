<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ValidSession
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            $fingerprint = $request->session()->get('credential_fingerprint');
            if (! is_string($fingerprint) || ! hash_equals(hash('sha256', $user->password_hash), $fingerprint)
                || ! in_array($user->role, ['user', 'admin'], true)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                if ($request->expectsJson()) {
                    abort(401, 'Sessão encerrada. Entre novamente.');
                }

                return redirect()->route('login')->with('status', 'Sessão encerrada. Entre novamente.');
            }
        }

        return $next($request);
    }
}
