@extends('layouts.main')
@section('mainClass', 'access')
@section('title', 'Alterar senha · Intranet FCECON')
@section('content')
<h1>Alterar senha</h1>
<p class="intro">Confirme sua senha atual para definir uma nova.</p>
<section class="panel">
    <form method="post" action="{{ route('password') }}" class="stack">
        @csrf
        <label for="username">Usuário</label>
        <input id="username" name="username" value="{{ auth()->user()?->username ?? old('username') }}" autocomplete="username" maxlength="255" required @readonly(auth()->check())>
        <label for="current_password">Senha atual</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
        <label for="password">Nova senha</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required aria-describedby="password-help">
        <p id="password-help" class="muted">Use pelo menos 12 caracteres, até 72 bytes.</p>
        <label for="password_confirmation">Confirmar nova senha</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
        <button>Alterar senha</button>
    </form>
    <p><a href="{{ auth()->check() ? route('documents.index') : route('login') }}">Voltar</a></p>
</section>
@endsection
