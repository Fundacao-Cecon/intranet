@extends('layouts.main')
@section('title', 'Usuários · Intranet FCECON')
@section('content')
<h1>Usuários</h1>
<section class="panel">
    <h2>Cadastrar usuário</h2>
    <form method="post" action="{{ route('users.index') }}" class="stack account-create">
        @csrf
        <label for="username">Login</label><input id="username" name="username" value="{{ old('username') }}" required maxlength="255" autocomplete="off">
        <label for="new-password">Senha inicial</label><input id="new-password" name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password">
        <p class="muted">Pelo menos 12 caracteres, até 72 bytes. O usuário poderá alterar com a senha atual.</p>
        <label for="confirm-password">Confirmar senha</label><input id="confirm-password" name="password_confirmation" type="password" required autocomplete="new-password">
        <label for="role">Perfil</label><select id="role" name="role"><option value="user">Usuário</option><option value="admin" @selected(old('role') === 'admin')>Administrador</option></select><button>Cadastrar</button>
    </form>
</section>
<section class="panel users-list">
    <h2>Contas cadastradas</h2><div class="table-scroll"><table><thead><tr><th>Login</th><th>Perfil</th><th>Último acesso</th><th>Ações</th></tr></thead><tbody>
    @foreach($users as $user)<tr><td>{{ $user->username }}</td><td>{{ $user->role === 'admin' ? 'Administrador' : 'Usuário' }}</td><td>{{ $user->last_login?->timezone('America/Manaus')->format('d/m/Y H:i') ?? '-' }}</td><td>
        @can('delete', $user)<form method="post" action="{{ route('users.delete', $user->id) }}" data-confirm="Excluir esta conta? Os documentos e o histórico serão preservados.">@csrf @method('DELETE')<button class="secondary">Excluir</button></form>@else Sua conta @endcan
    </td></tr>@endforeach
    </tbody></table></div>
</section>
@endsection
