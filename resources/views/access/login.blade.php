@extends('layouts.main')
@section('mainClass', 'access')
@section('content')
<p class="eyebrow">Portal institucional</p>
<h1>Intranet FCECON</h1>
<p class="intro">Entre para consultar os documentos da instituição.</p>
<section class="panel">
    <h2>Acessar a intranet</h2>
    <form method="post" action="{{ route('login') }}" class="stack">
        @csrf
        <label for="username">Usuário</label>
        <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" maxlength="255" required autofocus>
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button>Entrar</button>
    </form>
    <p class="muted">Precisa trocar sua senha? <a href="{{ route('password') }}">Alterar com a senha atual</a></p>
</section>
@endsection
