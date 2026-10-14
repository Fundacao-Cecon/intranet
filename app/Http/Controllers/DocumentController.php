<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\ItemHierarchy;
use App\Services\PrivateFileReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function index(Request $request, ItemHierarchy $hierarchy)
    {
        Gate::authorize('viewAny', Item::class);
        $data = $request->validate(['pasta' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:255']);
        $folder = isset($data['pasta']) ? Item::findOrFail($data['pasta']) : null;
        $breadcrumbs = collect();
        if ($folder) {
            abort_unless($folder->kind === 'folder', 404);
            Gate::authorize('view', $folder);
            $breadcrumbs = $hierarchy->ancestors($folder);
        }
        $query = Item::where('parent_id', $folder?->id)->whereNull('deleted_at');
        if (($data['q'] ?? '') !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $data['q']);
            $query->whereRaw('name ILIKE ?', ['%'.$escaped.'%']);
        }
        $items = $query->orderByRaw("CASE WHEN kind = 'folder' THEN 0 ELSE 1 END")
            ->orderByRaw('lower(name) COLLATE "C"')->orderBy('id')->get();

        return view('documents.index', compact('folder', 'items', 'breadcrumbs'));
    }

    public function read(Request $request, Item $item, PrivateFileReader $reader)
    {
        Gate::authorize('view', $item);
        abort_unless($item->kind === 'file', 404);

        return $reader->respond($request, $item, $request->routeIs('files.download'), $request->routeIs('files.thumbnail'));
    }
}
