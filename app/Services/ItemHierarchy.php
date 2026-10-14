<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Collection;

class ItemHierarchy
{
    public function readable(Item $item): bool
    {
        return $this->ancestors($item) !== null;
    }

    /** Item e ancestrais, da raiz ao item; null se qualquer trecho não puder ser lido. */
    public function ancestors(Item $item): ?Collection
    {
        $chain = collect();
        $seen = [];
        while ($item) {
            if ($item->deleted_at !== null || isset($seen[$item->id])) {
                return null;
            }
            $seen[$item->id] = true;
            $chain->prepend($item);
            if ($item->parent_id === null) {
                return $chain;
            }
            $item = Item::find($item->parent_id);
            if (! $item || $item->kind !== 'folder') {
                return null;
            }
        }

        return null;
    }
}
