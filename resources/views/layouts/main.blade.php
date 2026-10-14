<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Intranet FCECON')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<header class="topbar">
    <a class="brand" href="{{ route('documents.index') }}">FCECON <span>Intranet</span></a>
    <nav aria-label="Conta">
        <button type="button" class="secondary theme-toggle" aria-label="Alternar tema">Tema escuro</button>
        @auth
            <span class="account">{{ auth()->user()->username }} · {{ auth()->user()->role === 'admin' ? 'Administrador' : 'Usuário' }}</span>
            <a href="{{ route('documents.index') }}">Documentos</a>
            @can('admin')<a href="{{ route('users.index') }}">Usuários</a><a href="{{ route('audit.index') }}">Auditoria</a>@endcan
            <a href="{{ route('password') }}">Alterar senha</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary">Sair</button></form>
        @endauth
    </nav>
</header>
<main class="workspace @yield('mainClass')">
    @if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())
        <div class="error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
<footer class="page-footer">Fundação Centro de Controle de Oncologia · Documentos institucionais</footer>
</body>
</html>
