<?php

namespace App\Services;

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentMutations
{
    public function __construct(private DocumentNames $names, private ItemHierarchy $hierarchy, private AuditLogger $audit) {}

    public function lock(): void
    {
        // Mesma trava do trigger; todas as mutações documentais devem obtê-la antes de locks de linhas.
        DB::select('SELECT pg_advisory_xact_lock(62101401)');
    }

    public function destination(?int $parent): ?Item
    {
        if ($parent === null) {
            return null;
        }
        $folder = Item::whereKey($parent)->lockForUpdate()->first();
        if (! $folder || $folder->kind !== 'folder' || ! $this->hierarchy->readable($folder)) {
            throw ValidationException::withMessages(['pasta' => 'A pasta de destino não está disponível.']);
        }

        return $folder;
    }

    public function createFolder(User $actor, ?int $parent, string $name, ?string $ip): Item
    {
        $name = $this->names->validate($name);

        return DB::transaction(function () use ($actor, $parent, $name, $ip) {
            $this->lock();
            $this->destination($parent);
            $this->available($parent, $name);
            $item = Item::create(['kind' => 'folder', 'parent_id' => $parent, 'name' => $name, 'created_by_id' => $actor->id, 'created_by_snapshot' => $actor->username]);
            $this->audit->record('FOLDER_CREATED', $name, 'success', $actor, $item, ip: $ip);

            return $item;
        });
    }

    public function rename(User $actor, Item $item, string $base, ?string $ip): Item
    {
        return DB::transaction(function () use ($actor, $item, $base, $ip) {
            $this->lock();
            $item = Item::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if (! $this->hierarchy->readable($item)) {
                throw ValidationException::withMessages(['nome' => 'O item não está disponível.']);
            }
            $name = $this->names->validate($base);
            $extension = pathinfo($item->name, PATHINFO_EXTENSION);
            if ($item->kind === 'file' && $extension !== '') {
                $name .= '.'.$extension;
            }
            $name = $this->names->validate($name);
            $this->available($item->parent_id, $name, $item->id);
            $item->fill(['name' => $name, 'updated_by_id' => $actor->id, 'updated_by_snapshot' => $actor->username])->save();
            $this->audit->record('ITEM_RENAMED', $name, 'success', $actor, $item, ip: $ip);

            return $item;
        });
    }

    private function available(?int $parent, string $name, ?int $except = null): void
    {
        $query = Item::where('parent_id', $parent)->where('name', $name)->whereNull('deleted_at');
        if ($except !== null) {
            $query->where('id', '<>', $except);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['nome' => 'Já existe um item com esse nome nesta pasta.']);
        }
    }

    public function reserveName(?int $parent, string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : substr($name, 0, -strlen($extension) - 1);
        $candidate = $name;
        for ($suffix = 1; Item::where('parent_id', $parent)->where('name', $candidate)->whereNull('deleted_at')->exists(); $suffix++) {
            $tail = ' ('.$suffix.')'.($extension === '' ? '' : '.'.$extension);
            $candidate = mb_substr($base, 0, 255 - mb_strlen($tail)).$tail;
        }

        return $candidate;
    }
}
