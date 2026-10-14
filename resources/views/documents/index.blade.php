@extends('layouts.main')
@section('title', 'Documentos · Intranet FCECON')
@section('content')
<p class="eyebrow">Acervo institucional</p>
<h1>{{ $folder?->name ?? 'Documentos' }}</h1>
<nav aria-label="Caminho da pasta" class="breadcrumbs">
    <a href="{{ route('documents.index') }}">Raiz</a>
    @foreach($breadcrumbs as $crumb)
        <span aria-hidden="true">/</span><a href="{{ route('documents.index', ['pasta' => $crumb->id]) }}" @if($loop->last) aria-current="page" @endif>{{ $crumb->name }}</a>
    @endforeach
</nav>
<section class="panel documents">
    <form method="get" action="{{ route('documents.index') }}" class="filter-form" role="search">
        @if($folder)<input type="hidden" name="pasta" value="{{ $folder->id }}">@endif
        <label for="name-filter">Filtrar nomes nesta pasta</label>
        <div class="filter-controls"><input id="name-filter" name="q" value="{{ request('q') }}" placeholder="Digite o nome de uma pasta ou arquivo" maxlength="255" autocomplete="off"><button class="secondary">Filtrar</button>@if(request('q'))<a href="{{ route('documents.index', $folder ? ['pasta' => $folder->id] : []) }}">Limpar filtro</a>@endif</div>
    </form>
    <p id="item-count" class="muted" aria-live="polite">{{ $items->count() }} item(ns)</p>
    <div class="table-scroll">
    <table>
        <thead><tr><th scope="col">Nome</th><th scope="col">Tamanho</th><th scope="col">Criado por / em</th><th scope="col">Atualizado por / em</th><th scope="col">Ações</th></tr></thead>
        <tbody>
        @foreach($items as $item)
        <tr class="document-row" data-name="{{ $item->name }}">
            <td class="item-name">
                @if($item->kind === 'folder')
                    <span class="file-icon" aria-hidden="true">▣</span><a href="{{ route('documents.index', ['pasta' => $item->id]) }}">{{ $item->name }}</a><small>Pasta</small>
                @else
                    @if(in_array(strtolower(pathinfo($item->name, PATHINFO_EXTENSION)), ['png','jpg','jpeg','gif','webp']))
                        <img class="thumbnail" src="{{ route('files.thumbnail', $item->id) }}" alt="" loading="lazy">
                    @else<span class="file-icon" aria-hidden="true">▤</span>@endif
                    <a href="{{ route('files.view', $item->id) }}" target="_blank" rel="noopener">{{ $item->name }}</a><small>Arquivo</small>
                @endif
            </td>
            <td data-label="Tamanho">{{ $item->size_bytes === null ? '-' : number_format($item->size_bytes, 0, ',', '.') . ' bytes' }}</td>
            <td data-label="Criado por / em">{{ $item->created_by_snapshot ?? '-' }}<small>{{ $item->created_at?->timezone('America/Manaus')->format('d/m/Y H:i') ?? '-' }}</small></td>
            <td data-label="Atualizado por / em">{{ $item->updated_by_snapshot ?? '-' }}<small>{{ $item->updated_at?->timezone('America/Manaus')->format('d/m/Y H:i') ?? '-' }}</small></td>
            <td>@if($item->kind === 'file')<div class="row-actions"><a href="{{ route('files.view', $item->id) }}" target="_blank" rel="noopener">Visualizar</a><a href="{{ route('files.download', $item->id) }}">Baixar</a></div>@else<a href="{{ route('documents.index', ['pasta' => $item->id]) }}">Abrir pasta</a>@endif</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <p id="empty-state" class="empty" @if($items->isNotEmpty()) hidden @endif>Nenhum item nesta pasta corresponde ao filtro.</p>
</section>
@endsection
