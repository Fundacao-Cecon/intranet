<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserAdministration
{
    public function __construct(private AuditLogger $audit) {}

    public function create(User $actor, array $data, ?string $ip): User
    {
        return DB::transaction(function () use ($actor, $data, $ip) {
            DB::select('SELECT pg_advisory_xact_lock(62101402)');
            if (User::where('username', $data['username'])->exists()) {
                throw ValidationException::withMessages(['username' => 'Este usuário já existe.']);
            }
            $user = User::create(['username' => $data['username'], 'password_hash' => $data['password'], 'role' => $data['role']]);
            $this->audit->record('USER_CREATED', $user->username, 'success', $actor, context: ['username' => $user->username], ip: $ip);

            return $user;
        });
    }

    public function delete(User $actor, User $user, ?string $ip): void
    {
        DB::transaction(function () use ($actor, $user, $ip) {
            // Exclusão altera FKs documentais: respeitar a ordem global antes de bloquear usuários/itens.
            DB::select('SELECT pg_advisory_xact_lock(62101401)');
            DB::select('SELECT pg_advisory_xact_lock(62101402)');
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $user);
            $this->audit->record('USER_DELETED', $user->username, 'success', $actor, ip: $ip);
            $user->delete();
        });
    }
}
