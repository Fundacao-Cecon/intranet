<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\User;
use App\Rules\NewPassword;
use App\Services\DocumentMutations;
use App\Services\UserAdministration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdministrationController extends Controller
{
    public function users()
    {
        Gate::authorize('viewAny', User::class);

        return view('admin.users', ['users' => User::orderBy('username')->get()]);
    }

    public function createUser(Request $request, UserAdministration $users)
    {
        Gate::authorize('create', User::class);
        $data = $request->validate(['username' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\p{N}._-]+$/uD'], 'password' => ['required', 'string', 'confirmed', new NewPassword], 'role' => 'required|in:user,admin'], [
            'required' => 'Preencha :attribute.', 'username.regex' => 'O login aceita letras, números, ponto, hífen e sublinhado.',
            'role.in' => 'Escolha usuário ou administrador.', 'password.confirmed' => 'A confirmação da senha não confere.',
        ]);
        $users->create($request->user(), $data, $request->ip());

        return redirect()->route('users.index')->with('status', 'Usuário cadastrado.');
    }

    public function deleteUser(Request $request, User $user, UserAdministration $users)
    {
        Gate::authorize('delete', $user);
        $users->delete($request->user(), $user, $request->ip());

        return redirect()->route('users.index')->with('status', 'Usuário excluído. Documentos e histórico foram preservados.');
    }

    public function createFolder(Request $request, DocumentMutations $documents)
    {
        Gate::authorize('create', Item::class);
        $data = $request->validate(['nome' => 'required|string|max:255', 'pasta' => 'nullable|integer|min:1'], ['required' => 'Informe o nome da pasta.']);
        $documents->createFolder($request->user(), $data['pasta'] ?? null, $data['nome'], $request->ip());

        return redirect()->route('documents.index', isset($data['pasta']) ? ['pasta' => $data['pasta']] : [])->with('status', 'Pasta criada.');
    }

    public function rename(Request $request, Item $item, DocumentMutations $documents)
    {
        Gate::authorize('update', $item);
        $data = $request->validate(['nome' => 'required|string|max:255'], ['required' => 'Informe o novo nome.']);
        $item = $documents->rename($request->user(), $item, $data['nome'], $request->ip());

        return redirect()->route('documents.index', $item->parent_id ? ['pasta' => $item->parent_id] : [])->with('status', 'Item renomeado.');
    }

    public function audit()
    {
        Gate::authorize('admin');

        return view('admin.audit', ['events' => AuditLog::orderByRaw('occurred_at DESC NULLS LAST')->orderByDesc('id')->limit(50)->get()]);
    }
}
