<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = ['username', 'password_hash', 'role', 'last_login'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['password_hash' => 'hashed', 'last_login' => 'immutable_datetime'];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }
}
