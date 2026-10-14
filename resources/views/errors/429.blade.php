@extends('layouts.main')
@section('content')
<section class="panel"><h1>Aguarde um momento</h1><p>O limite de tentativas foi atingido. Aguarde um minuto antes de tentar novamente.</p><p><a href="{{ route('login') }}">Voltar ao acesso</a></p></section>
@endsection
