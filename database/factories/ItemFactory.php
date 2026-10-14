<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Item> */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        return ['kind' => 'folder', 'name' => 'Pasta '.Str::uuid(), 'parent_id' => null];
    }

    public function file(): static
    {
        return $this->state(fn () => [
            'kind' => 'file', 'name' => 'Documento '.Str::uuid().'.txt',
            'storage_key' => 'documents/'.Str::uuid().'.txt', 'size_bytes' => 9, 'media_type' => 'text/plain',
        ]);
    }

    public function trashed(): static
    {
        return $this->state(fn () => ['deleted_at' => now(), 'trash_name' => 'Removido '.Str::uuid()]);
    }
}
