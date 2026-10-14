<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;
use App\Services\ItemHierarchy;

class ItemPolicy
{
    public function __construct(private ItemHierarchy $hierarchy) {}

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['user', 'admin'], true);
    }

    public function view(User $user, Item $item): bool
    {
        return $this->viewAny($user) && $this->hierarchy->readable($item);
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Item $item): bool
    {
        return $this->create($user) && $this->hierarchy->readable($item);
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->update($user, $item);
    }

    public function restore(User $user, Item $item): bool
    {
        return $this->create($user);
    }

    public function forceDelete(User $user, Item $item): bool
    {
        return $this->create($user);
    }
}
