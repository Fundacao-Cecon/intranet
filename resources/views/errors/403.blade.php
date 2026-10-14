@extends('layouts.main')
@section('content')
<section class="panel"><h1>Acesso não permitido</h1><p>Você não tem permissão para esta operação ou o item está removido.</p><p><a href="{{ route('documents.index') }}">Voltar aos documentos</a></p></section>
@endsection
