<x-app-layout>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <h3 class="fw-bold m-0">Propostas de minicursos e oficinas</h3>
                <small class="text-secondary">{{ $evento->titulo }}</small>
            </div>
        </div>
        <a href="{{ route('admin.voluntarios.index', $evento->id) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-hand-holding-heart"></i> Voluntários</a>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div> @endif

    {{-- Chamadas --}}
    <div class="glass-card mb-4" x-data="{ nova: {{ $chamadas->isEmpty() || $errors->any() ? 'true' : 'false' }} }">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h6 class="fw-bold text-success m-0"><i class="fa-solid fa-bullhorn me-1"></i> Chamadas de propostas</h6>
            <button type="button" class="btn btn-sm btn-primary-custom" @click="nova = !nova"><i class="fa-solid fa-plus"></i> Nova chamada</button>
        </div>

        <div x-show="nova" x-cloak class="mb-4 p-3 rounded-3 bg-light border">
            @include('admin.propostas._form-chamada', ['acao' => route('admin.propostas.chamadas.store', $evento->id), 'metodo' => null, 'chamada' => null])
        </div>

        @forelse($chamadas as $c)
            <div class="border rounded-3 p-3 mb-2" x-data="{ editar: false }">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <div class="fw-bold texto-quebra">{{ $c->titulo }} <x-badge tipo="chamada" :valor="$c->situacao()" /></div>
                        <div class="small text-secondary">
                            {{ $c->inicio ? $c->inicio->format('d/m/Y H:i') : 'já aberta' }} → {{ $c->fim ? $c->fim->format('d/m/Y H:i') : 'sem prazo' }}
                            · aceita: {{ implode(', ', $c->tiposAceitos()) }} · até {{ $c->max_por_usuario }} por pessoa · {{ $c->propostas_count }} proposta(s)
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-light border" @click="editar = !editar"><i class="fa-solid fa-pen"></i> Editar</button>
                        <form action="{{ route('admin.propostas.chamadas.destroy', [$evento->id, $c->id]) }}" method="POST" onsubmit="return confirm('Excluir esta chamada?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-light border"><i class="fa-solid fa-trash"></i></button></form>
                    </div>
                </div>
                <div x-show="editar" x-cloak class="mt-3 p-3 rounded-3 bg-light border">
                    @include('admin.propostas._form-chamada', ['acao' => route('admin.propostas.chamadas.update', [$evento->id, $c->id]), 'metodo' => 'PUT', 'chamada' => $c])
                </div>
            </div>
        @empty
            <p class="small text-muted mb-0">Nenhuma chamada criada. Crie uma para receber propostas de minicursos e oficinas dos usuários.</p>
        @endforelse
    </div>

    {{-- Filtros --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('admin.propostas.index', $evento->id) }}" class="text-decoration-none"><span class="sb {{ $status ? 'sb-externo' : 'sb-ok' }}">Todas · {{ $contagem->sum() }}</span></a>
        @foreach(\App\Models\Proposta::STATUS as $chave => $rotulo)
            <a href="{{ route('admin.propostas.index', [$evento->id, 'status' => $chave]) }}" class="text-decoration-none"><x-badge tipo="proposta" :valor="$chave" :extra="$contagem->get($chave, 0)" /></a>
        @endforeach
        <form method="GET" class="ms-auto d-flex gap-2">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Título ou proponente">
            <button class="btn btn-sm btn-light border"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>

    {{-- Propostas --}}
    <div class="glass-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th class="ps-4">Proposta</th><th>Proponente</th><th>Carga / vagas</th><th>Situação</th><th>Programação</th><th class="text-end pe-4">Ações</th></tr></thead>
                <tbody>
                    @forelse($propostas as $p)
                        <tr>
                            <td class="ps-4"><div class="fw-bold texto-quebra">{{ $p->titulo }}</div><small class="text-muted">{{ $p->tipoRotulo() }} · enviada em {{ $p->created_at->format('d/m/Y') }}</small></td>
                            <td>{{ $p->proponente->name }}<div class="small text-muted">{{ $p->proponente->email }}</div></td>
                            <td>{{ $p->carga_horaria_prevista }} h / {{ $p->vagas_sugeridas }} vagas @if($p->requer_laboratorio)<div><span class="sb sb-pend"><i class="fa-solid fa-flask"></i> laboratório</span></div>@endif</td>
                            <td><x-badge tipo="proposta" :valor="$p->status" /></td>
                            <td class="small">@if($p->atividade){{ $p->atividade->data_inicio->format('d/m H:i') }}<div class="text-muted">{{ $p->atividade->local }}</div>@else<span class="text-muted">—</span>@endif</td>
                            <td class="text-end pe-4"><a href="{{ route('admin.propostas.show', [$evento->id, $p->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye"></i> Analisar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">Nenhuma proposta {{ $status ? 'com esta situação' : 'recebida ainda' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
