@extends('layouts.main')
@section('content')
<section class="panel"><h1>Serviço temporariamente indisponível</h1><p>Não foi possível concluir a operação. Tente novamente em alguns instantes.</p><p><a href="{{ route('documents.index') }}">Voltar aos documentos</a></p></section>
@endsection
