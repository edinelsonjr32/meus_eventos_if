<x-app-layout>
    <style>
        .mapa-wrap { overflow-x: auto; }
        .mapa { min-width: 760px; }
        .mapa-linha { display: grid; grid-template-columns: 170px 1fr; border-top: 1px solid #e2e8f0; }
        .mapa-rot { padding: .5rem .75rem; font-size: .82rem; background: #f8fafc; border-right: 1px solid #e2e8f0; min-width: 0; }
        .mapa-trilho { position: relative; background-image: linear-gradient(to right, #e2e8f0 1px, transparent 1px); background-size: var(--passo) 100%; }
        .mapa-barra { position: absolute; height: 30px; border-radius: 6px; padding: 0 .4rem; font-size: .72rem; line-height: 30px; color: #064e3b; background: #d1fae5; border: 1px solid #34d399; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; text-decoration: none; }
        .mapa-barra.conflito { background: #fee2e2; border-color: #ef4444; color: #7f1d1d; }
        .mapa-cab { display: grid; grid-template-columns: 170px 1fr; font-size: .72rem; color: #64748b; }
        .mapa-horas { display: grid; grid-template-columns: repeat(var(--n), 1fr); }
        .mapa-horas span { padding: .25rem 0 .25rem .25rem; border-left: 1px solid #e2e8f0; }
    </style>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
            <div><h3 class="fw-bold m-0">Locais e mapa de atividades</h3><small class="text-secondary">{{ $evento->titulo }}</small></div>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div> @endif

    <div x-data="{ aba: '{{ $errors->any() ? 'locais' : (request('aba') ?: ($locais->isEmpty() ? 'locais' : 'mapa')) }}' }">
        <ul class="nav nav-pills mb-3 gap-1">
            <li class="nav-item"><button type="button" class="nav-link" :class="{ 'active': aba === 'mapa' }" @click="aba = 'mapa'" style="color:inherit"><i class="fa-solid fa-table-cells me-1"></i> Mapa de atividades</button></li>
            <li class="nav-item"><button type="button" class="nav-link" :class="{ 'active': aba === 'locais' }" @click="aba = 'locais'" style="color:inherit"><i class="fa-solid fa-location-dot me-1"></i> Locais ({{ $locais->count() }})</button></li>
        </ul>

        {{-- ===== Mapa ===== --}}
        <div x-show="aba === 'mapa'" x-cloak>
            <div class="glass-card">
                @if($semLocal > 0)
                    <div class="alert alert-warning small"><i class="fa-solid fa-triangle-exclamation me-1"></i> {{ $semLocal }} atividade(s) do evento estão sem local cadastrado. Edite-as para escolher um local na lista.</div>
                @endif

                @if($dias->isEmpty())
                    <p class="text-muted small mb-0">O evento ainda não tem atividades na programação.</p>
                @else
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach($dias as $d)
                            @php $c = \Illuminate\Support\Carbon::parse($d); @endphp
                            <a href="{{ route('admin.locais.index', [$evento->id, 'dia' => $d, 'aba' => 'mapa']) }}" class="btn btn-sm {{ $d === $dia ? 'btn-primary-custom' : 'btn-light border' }}">{{ $c->format('d/m') }} <span class="opacity-75">{{ $c->translatedFormat('D') }}</span></a>
                        @endforeach
                    </div>

                    @if(empty($mapa['linhas']))
                        <p class="text-muted small mb-0">Nenhuma atividade neste dia.</p>
                    @else
                        <div class="small text-muted mb-2"><span class="d-inline-block rounded me-1" style="width:12px;height:12px;background:#d1fae5;border:1px solid #34d399"></span> atividade <span class="d-inline-block rounded ms-3 me-1" style="width:12px;height:12px;background:#fee2e2;border:1px solid #ef4444"></span> conflito (mesmo local e horário)</div>

                        {{-- Linha do tempo (telas médias e grandes) --}}
                        @php $n = $mapa['h1'] - $mapa['h0']; @endphp
                        <div class="mapa-wrap d-none d-md-block">
                            <div class="mapa" style="--n: {{ $n }}; --passo: {{ 100 / $n }}%">
                                <div class="mapa-cab"><div></div><div class="mapa-horas">@foreach(range($mapa['h0'], $mapa['h1'] - 1) as $h)<span>{{ sprintf('%02d', $h) }}h</span>@endforeach</div></div>
                                @foreach($mapa['linhas'] as $linha)
                                    <div class="mapa-linha">
                                        <div class="mapa-rot">
                                            <div class="fw-bold texto-quebra">{{ $linha['local']?->nome ?? 'Sem local definido' }}</div>
                                            <div class="text-muted">{{ $linha['total'] }} ativ. · {{ $linha['horas'] }} h @if($linha['local']?->capacidade) · cap. {{ $linha['local']->capacidade }}@endif</div>
                                        </div>
                                        <div class="mapa-trilho" style="height: {{ $linha['raias'] * 34 + 6 }}px">
                                            @foreach($linha['barras'] as $b)
                                                @php $a = $b['atividade']; $dica = $a->titulo . ' · ' . $a->data_inicio->format('H:i') . ' a ' . $a->data_fim->format('H:i') . ($a->responsavel ? ' · ' . $a->responsavel : ''); @endphp
                                                <a href="{{ route('admin.atividades.edit', $a->id) }}" class="mapa-barra {{ $b['conflito'] ? 'conflito' : '' }}" style="left: {{ $b['esq'] }}%; width: {{ $b['larg'] }}%; top: {{ 3 + $b['raia'] * 34 }}px" title="{{ $dica }}">{{ $a->data_inicio->format('H:i') }} {{ $a->titulo }}</a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Lista por local (celular) --}}
                        <div class="d-md-none">
                            @foreach($mapa['linhas'] as $linha)
                                <div class="border rounded-3 p-2 mb-2">
                                    <div class="fw-bold small">{{ $linha['local']?->nome ?? 'Sem local definido' }} <span class="text-muted fw-normal">· {{ $linha['total'] }} ativ. · {{ $linha['horas'] }} h</span></div>
                                    @foreach($linha['barras'] as $b)
                                        @php $a = $b['atividade']; @endphp
                                        <a href="{{ route('admin.atividades.edit', $a->id) }}" class="d-block small text-decoration-none rounded px-2 py-1 mt-1 {{ $b['conflito'] ? 'bg-danger-subtle text-danger-emphasis' : 'bg-success-subtle text-success-emphasis' }}">{{ $a->data_inicio->format('H:i') }}–{{ $a->data_fim->format('H:i') }} · {{ $a->titulo }}@if($b['conflito']) <strong>(conflito)</strong>@endif</a>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- ===== Locais ===== --}}
        <div x-show="aba === 'locais'" x-cloak>
            <div class="glass-card" x-data="{ nova: {{ $locais->isEmpty() || $errors->any() ? 'true' : 'false' }} }">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-success m-0">Locais do evento</h6>
                    <button type="button" class="btn btn-sm btn-primary-custom" @click="nova = !nova"><i class="fa-solid fa-plus"></i> Novo local</button>
                </div>
                <div x-show="nova" x-cloak class="mb-4 p-3 rounded-3 bg-light border">@include('admin.locais._form', ['acao' => route('admin.locais.store', $evento->id), 'local' => null])</div>

                @forelse($locais as $l)
                    <div class="border rounded-3 p-3 mb-2" x-data="{ editar: false }">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="fw-bold texto-quebra">{{ $l->nome }} @unless($l->ativo)<span class="badge bg-secondary">inativo</span>@endunless</div>
                                <div class="small text-secondary">{{ $l->tipoRotulo() }} @if($l->bloco) · {{ $l->bloco }} @endif @if($l->capacidade) · {{ $l->capacidade }} pessoas @endif · {{ $l->atividades_count }} atividade(s) @if($l->demandas_count) · {{ $l->demandas_count }} demanda(s) de voluntários @endif</div>
                                @if($l->equipamentos)<div class="small texto-quebra"><i class="fa-solid fa-screwdriver-wrench me-1 text-success"></i> {{ $l->equipamentos }}</div>@endif
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-light border" @click="editar = !editar"><i class="fa-solid fa-pen"></i> Editar</button>
                                <form action="{{ route('admin.locais.destroy', [$evento->id, $l->id]) }}" method="POST" onsubmit="return confirm('Excluir este local?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light border" title="Excluir"><i class="fa-solid fa-trash"></i></button></form>
                            </div>
                        </div>
                        <div x-show="editar" x-cloak class="mt-3 p-3 rounded-3 bg-light border">@include('admin.locais._form', ['acao' => route('admin.locais.update', [$evento->id, $l->id]), 'local' => $l])</div>
                    </div>
                @empty
                    <p class="small text-muted mb-0">Cadastre as salas, laboratórios e auditórios do evento. Eles aparecem como lista de escolha ao criar atividades, aprovar minicursos e montar demandas de voluntários.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
