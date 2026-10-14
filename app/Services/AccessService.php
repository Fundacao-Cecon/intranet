<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccessService
{
    public function __construct(private AuditLogger $audit) {}

    public function login(string $username, string $password, ?string $ip): User
    {
        return DB::transaction(function () use ($username, $password, $ip) {
            $user = $this->credentials($username, $password);
            $user->last_login = now();
            if (Hash::needsRehash($user->password_hash)) {
                $user->password_hash = $password;
            }
            $user->save();
            $this->audit->record('LOGIN', 'Acesso à intranet', 'success', $user, ip: $ip);

            return $user;
        });
    }

    public function changePassword(string $username, string $current, string $password, ?string $ip): User
    {
        return DB::transaction(function () use ($username, $current, $password, $ip) {
            $user = $this->credentials($username, $current);
            $user->password_hash = $password;
            $user->save();
            $this->audit->record('PASSWORD_CHANGED', 'Senha da conta', 'success', $user, ip: $ip);

            return $user;
        });
    }

    private function credentials(string $username, string $password): User
    {
        $user = User::where('username', $username)->lockForUpdate()->first();
        // Hash de comparação sem conta associada, para não saltar a verificação em contas ausentes.
        $hash = $user?->password_hash ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (! Hash::check($password, $hash) || ! $user || ! in_array($user->role, ['user', 'admin'], true)) {
            throw ValidationException::withMessages(['username' => 'Usuário ou senha atual inválidos.']);
        }

        return $user;
    }
}
