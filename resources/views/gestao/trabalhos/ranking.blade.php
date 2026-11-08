@php
    $fmt = fn ($n) => $n === null ? '—' : number_format($n, 2, ',', '.');
    $filtros = request()->only(['tipo', 'area', 'status']);
@endphp
<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-11">

            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                    <div>
                        <h3 class="fw-bold m-0">Notas e ranking</h3>
                        <small class="text-secondary">{{ $evento->titulo }} · uso restrito à gestão (contém dados da avaliação)</small>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div class="btn-group">
                        <a href="{{ route('gestao.trabalhos.exportar-notas', array_merge(['evento' => $evento->id, 'relatorio' => 'resumo', 'formato' => 'xlsx'], $filtros)) }}" class="btn btn-outline-success fw-bold"><i class="fa-solid fa-file-excel me-1"></i> Ranking (Excel)</a>
                        <a href="{{ route('gestao.trabalhos.exportar-notas', array_merge(['evento' => $evento->id, 'relatorio' => 'resumo', 'formato' => 'csv'], $filtros)) }}" class="btn btn-outline-secondary fw-bold">CSV</a>
                    </div>
                    <div class="btn-group">
                        <a href="{{ route('gestao.trabalhos.exportar-notas', array_merge(['evento' => $evento->id, 'relatorio' => 'detalhado', 'formato' => 'xlsx'], $filtros)) }}" class="btn btn-outline-success fw-bold"><i class="fa-solid fa-file-excel me-1"></i> Pareceres (Excel)</a>
                        <a href="{{ route('gestao.trabalhos.exportar-notas', array_merge(['evento' => $evento->id, 'relatorio' => 'detalhado', 'formato' => 'csv'], $filtros)) }}" class="btn btn-outline-secondary fw-bold">CSV</a>
                    </div>
                </div>
            </div>

            <form method="GET" class="glass-card bg-white mb-3">
                <div class="row g-2">
                    <div class="col-md-3">
                        <select name="tipo" class="form-select form-select-sm">
                            <option value="">Todas as categorias</option>
                            @foreach(\App\Models\TrabalhoConfig::TIPOS as $v => $r) <option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $r }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="area" class="form-select form-select-sm">
                            <option value="">Todas as áreas</option>
                            @foreach($areas as $a) <option value="{{ $a->id }}" @selected((int) request('area') === $a->id)>{{ $a->nome }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Todos os status</option>
                            @foreach(\App\Models\Trabalho::STATUS as $v => $r) <option value="{{ $v }}" @selected(request('status') === $v)>{{ $r }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button class="btn btn-sm btn-dark flex-fill"><i class="fa-solid fa-filter"></i> Filtrar</button>
                        <a href="{{ route('gestao.trabalhos.ranking', $evento) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-eraser"></i> Limpar</a>
                    </div>
                </div>
            </form>

            <div class="glass-card bg-white p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">#</th><th>Protocolo</th><th>Título</th><th>Categoria</th><th class="text-center">Nota média</th><th class="text-center">Apresentação</th><th class="text-center">Geral</th>
                                <th>Notas individuais</th><th>Recomendações</th><th>Situação</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($linhas as $l)
                            @php $t = $l['trabalho']; @endphp
                            <tr>
                                <td class="ps-3 fw-bold">{{ $l['posicao'] ?? '—' }}</td>
                                <td class="fw-bold text-nowrap">{{ $t->codigo }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($t->titulo, 60) }}</td>
                                <td>{{ \App\Services\RelatorioNotas::categoria($t->tipo_trabalho) ?: '—' }}</td>
                                <td class="text-center">
                                    <span class="fw-bold {{ $l['nota'] !== null && $l['nota'] >= $notaMinima ? 'text-success' : 'text-dark' }}">{{ $fmt($l['nota']) }}</span>
                                    <div class="text-muted">{{ $l['concluidas'] }}/{{ $l['atribuidas'] }} concl.</div>
                                </td>
                                <td class="text-center">{{ $fmt($l['nota_apresentacao'] ?? null) }}</td>
                                <td class="text-center fw-bold">{{ $fmt($l['nota_geral'] ?? null) }}</td>
                                <td>
                                    {{ collect($l['notas'])->map(fn ($n) => number_format($n, 2, ',', '.'))->implode(' · ') ?: '—' }}
                                    @if($l['divergencia'] !== null && $l['divergencia'] > $limiteDivergencia)
                                        <div class="text-warning fw-bold"><i class="fa-solid fa-triangle-exclamation"></i> divergência {{ $fmt($l['divergencia']) }}</div>
                                    @endif
                                </td>
                                <td>{{ \App\Services\RelatorioNotas::rotuloRecomendacoes($l['recomendacoes']) ?: '—' }}</td>
                                <td>
                                    <x-badge tipo="trabalho" :valor="$t->statusFinal()" :extra="$t->extraSituacao()" />
                                    @if($t->decisao) <div class="text-muted">{{ ['aprovado' => 'Aprovado', 'aprovado_com_ressalvas' => 'Com ressalvas', 'reprovado' => 'Reprovado'][$t->decisao] }}</div> @endif
                                </td>
                                <td class="text-end pe-3"><a href="{{ route('gestao.trabalhos.show', $t) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-folder-open"></i> Abrir</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted py-5">Nenhum trabalho encontrado.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="small text-muted mt-2">A nota média considera as avaliações concluídas da rodada vigente (escala 0–10). Posições empatadas dividem o mesmo número.
                Em "Média por critério" e nos pareceres, use as exportações.</p>
        </div>
    </div>
</x-app-layout>
