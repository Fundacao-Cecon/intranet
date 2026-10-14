<?php

namespace App\Http\Controllers;

use App\Rules\NewPassword;
use App\Services\AccessService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AccessController extends Controller
{
    public function login(Request $request, AccessService $access)
    {
        $data = $request->validate(['username' => 'required|string|max:255', 'password' => 'required|string|max:1024'], [
            'required' => 'Preencha :attribute.', 'max' => ':attribute ultrapassa o limite permitido.',
        ]);
        try {
            $user = $access->login($data['username'], $data['password'], $request->ip());
        } catch (ValidationException $exception) {
            app(AuditLogger::class)->record('LOGIN', 'Acesso à intranet', 'failure', context: ['reason_code' => 'invalid_credentials'], ip: $request->ip());
            throw $exception;
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('credential_fingerprint', hash('sha256', $user->password_hash));

        return redirect()->intended(route('documents.index'));
    }

    public function changePassword(Request $request, AccessService $access)
    {
        $data = $request->validate([
            'username' => 'required|string|max:255', 'current_password' => 'required|string|max:1024',
            'password' => ['required', 'string', 'confirmed', new NewPassword],
        ], [
            'required' => 'Preencha :attribute.', 'password.min' => 'A nova senha deve ter pelo menos 12 caracteres.',
            'password.max' => 'A nova senha deve ter no máximo 72 caracteres.', 'password.confirmed' => 'A confirmação da nova senha não confere.',
        ]);
        try {
            $user = $access->changePassword($request->user()?->username ?? $data['username'], $data['current_password'], $data['password'], $request->ip());
        } catch (ValidationException $exception) {
            app(AuditLogger::class)->record('PASSWORD_CHANGED', 'Senha da conta', 'failure', context: ['reason_code' => 'invalid_credentials'], ip: $request->ip());
            throw $exception;
        }
        if ($request->user()) {
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('credential_fingerprint', hash('sha256', $user->password_hash));

            return redirect()->route('documents.index')->with('status', 'Senha alterada.');
        }

        return redirect()->route('login')->with('status', 'Senha alterada. Entre com a nova senha.');
    }

    public function logout(Request $request, AuditLogger $audit)
    {
        try {
            $audit->record('LOGOUT', 'Sessão da intranet', 'success', $request->user(), ip: $request->ip());
        } catch (\Throwable) {
            Log::warning('Auditoria de encerramento de sessão indisponível.');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
