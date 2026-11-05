<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Usuário do esquema legado (tabela `users`: username, password, role, created_at, last_login).
 * Os hashes são bcrypt `$2y$`, os mesmos do legado, então os dois lados verificam a mesma senha.
 *
 * @property int $id
 * @property string $username
 * @property string $password
 * @property string $role
 * @property string|null $created_at
 * @property string|null $last_login
 */
#[Table('users', timestamps: false)]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
