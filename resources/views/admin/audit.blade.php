@extends('layouts.main')
@section('title', 'Auditoria · Intranet FCECON')
@section('content')
<h1>Auditoria</h1><p class="intro">Os 50 eventos mais recentes. Eventos de leitura registram o início da transmissão.</p>
<section class="panel"><div class="table-scroll"><table><thead><tr><th>Data</th><th>Ator</th><th>Ação</th><th>Alvo</th><th>Resultado</th><th>Detalhe</th></tr></thead><tbody>
@forelse($events as $event)
<tr><td>{{ $event->occurred_at?->timezone('America/Manaus')->format('d/m/Y H:i:s') ?? '-' }}</td><td>{{ $event->actor_name_snapshot ?? '-' }}</td><td>{{ $event->action }}</td><td>{{ $event->target_snapshot }}</td><td>{{ ['success'=>'Sucesso','failure'=>'Falha','denied'=>'Recusado'][$event->outcome] ?? '-' }}</td><td>{{ $event->detail ?? '-' }}</td></tr>
@empty<tr><td colspan="6">Nenhum evento registrado.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
