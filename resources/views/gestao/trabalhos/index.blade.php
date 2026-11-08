<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-11">

            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold m-0">Trabalhos submetidos</h3>
                    <small class="text-secondary">{{ $evento->titulo }}</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('gestao.trabalhos.exportar', array_merge(['evento' => $evento->id, 'formato' => 'xlsx'], request()->only(['status', 'tipo', 'area', 'q']))) }}" class="btn btn-outline-success"><i class="fa-solid fa-file-excel"></i> Excel</a>
                    <a href="{{ route('gestao.trabalhos.exportar', array_merge(['evento' => $evento->id, 'formato' => 'csv'], request()->only(['status', 'tipo', 'area', 'q']))) }}" class="btn btn-tone-teal"><i class="fa-solid fa-file-csv"></i> CSV</a>
                    <a href="{{ route('gestao.trabalhos.anais.edit', $evento) }}" class="btn btn-tone-indigo"><i class="fa-solid fa-book"></i> Anais</a>
                    <a href="{{ route('gestao.trabalhos.apresentacoes.index', $evento) }}" class="btn btn-outline-primary"><i class="fa-solid fa-person-chalkboard"></i> Apresentações</a>
                    <a href="{{ route('gestao.trabalhos.ranking', $evento) }}" class="btn btn-tone-amber"><i class="fa-solid fa-ranking-star"></i> Notas e ranking</a>
                    <a href="{{ route('gestao.trabalhos.certificados.index', $evento) }}" class="btn btn-tone-purple"><i class="fa-solid fa-award"></i> Certificados</a>
                    <a href="{{ route('gestao.trabalhos.config', $evento) }}" class="btn btn-tone-slate"><i class="fa-solid fa-sliders"></i> {{ $config ? 'Editar chamada' : 'Criar chamada' }}</a>
                    @if($config)
                        <a href="{{ route('gestao.trabalhos.novo', $evento) }}" class="btn btn-success" title="Cadastrar um trabalho recebido por outro meio"><i class="fa-solid fa-file-circle-plus"></i> Cadastrar trabalho</a>
                    @endif
                </div>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
            @if($errors->any())     <div class="alert alert-danger small">{{ $errors->first() }}</div> @endif
            @if(session('erros_lote'))
                <div class="alert alert-warning small"><strong>Ignorados:</strong><ul class="mb-0 ps-3">@foreach(session('erros_lote') as $erro) <li>{{ $erro }}</li> @endforeach</ul></div>
            @endif

            @php $sit = \App\Models\TrabalhoConfig::situacaoDe($config); @endphp
            <div class="alert alert-{{ $sit['cor'] === 'dark' ? 'secondary' : $sit['cor'] }} d-flex flex-wrap justify-content-between align-items-center gap-2 small">
                <div><strong>{{ $sit['rotulo'] }}.</strong> {{ $sit['descricao'] }}</div>
                @if(!$config)
                    <a href="{{ route('gestao.trabalhos.config', $evento) }}" class="btn btn-sm btn-primary-custom fw-bold"><i class="fa-solid fa-plus me-1"></i> Criar chamada de trabalhos</a>
                @elseif($sit['chave'] === 'rascunho')
                    <a href="{{ route('gestao.trabalhos.config', $evento) }}" class="btn btn-sm btn-dark fw-bold">Revisar e publicar</a>
                @endif
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach($contagem as $status => $total)
                    <a href="{{ request()->fullUrlWithQuery(['status' => $status]) }}" class="text-decoration-none" title="Filtrar por este status">
                        <x-badge tipo="trabalho" :valor="$status" :extra="$total" />
                    </a>
                @endforeach
            </div>

            <form method="GET" class="glass-card bg-white mb-3">
                <div class="row g-2">
                    <div class="col-md-4"><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Título, protocolo, autor ou CPF"></div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Todos os status</option>
                            @foreach(\App\Models\Trabalho::STATUS as $v => $r) <option value="{{ $v }}" @selected(request('status') === $v)>{{ $r }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="tipo" class="form-select form-select-sm">
                            <option value="">Todas as categorias</option>
                            @foreach(\App\Models\TrabalhoConfig::TIPOS as $v => $r) <option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $r }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="area" class="form-select form-select-sm">
                            <option value="">Todas as áreas</option>
                            @foreach($areas as $a) <option value="{{ $a->id }}" @selected((int) request('area') === $a->id)>{{ $a->nome }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button class="btn btn-sm btn-dark flex-fill"><i class="fa-solid fa-filter"></i> Filtrar</button>
                        <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-eraser"></i> Limpar</a>
                    </div>
                </div>
            </form>

            <x-lote-avaliadores :action="route('gestao.trabalhos.lote', $evento)" :avaliadores="$avaliadores" :restricoes="$restricoes" :acoes="[
                ['valor' => 'atribuir', 'rotulo' => 'Atribuir avaliadores', 'icone' => 'fa-user-plus', 'tom' => 'btn-outline-success'],
                ['valor' => 'triagem', 'rotulo' => 'Iniciar triagem', 'icone' => 'fa-filter', 'tom' => 'btn-outline-primary'],
                ['valor' => 'avaliacao', 'rotulo' => 'Encaminhar para avaliação', 'icone' => 'fa-share', 'tom' => 'btn-outline-warning'],
            ]" />

            <div class="glass-card bg-white p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr><th class="ps-3"><input type="checkbox" class="form-check-input sel-todos" title="Marcar todos desta página"></th><th>Protocolo</th><th>Título / Autores</th><th>Categoria</th><th>Área</th><th>Status</th><th>Aval.</th><th>Enviado em</th><th></th></tr>
                        </thead>
                        <tbody>
                        @forelse($trabalhos as $t)
                            @php
                                $ativas = $t->avaliacoes->where('status', '!=', 'recusada')->count();
                                $minimo = $config?->avaliadores_por_trabalho ?? 2;
                                $aptoAval = $t->status === 'em_triagem' && $ativas >= $minimo;
                            @endphp
                            <tr>
                                <td class="ps-3"><input type="checkbox" form="lote" name="trabalhos[]" value="{{ $t->id }}" class="form-check-input sel-trabalho" data-codigo="{{ $t->codigo }}" data-atribuir="{{ in_array($t->status, \App\Services\TrabalhoWorkflow::STATUS_ATRIBUICAO, true) ? 1 : 0 }}" data-triagem="{{ in_array($t->status, [\App\Models\Trabalho::SUBMETIDO, 'resubmetido'], true) ? 1 : 0 }}" data-avaliacao="{{ $aptoAval ? 1 : 0 }}" data-motivo-avaliacao="{{ $aptoAval ? '' : ($t->status !== 'em_triagem' ? 'não está em triagem' : 'faltam ' . ($minimo - $ativas) . ' avaliador(es)') }}"></td>
                                <td class="fw-bold text-nowrap">{{ $t->codigo }}</td>
                                <td>
                                    <div class="fw-bold">{{ \Illuminate\Support\Str::limit($t->titulo, 70) }}</div>
                                    <span class="text-muted">{{ $t->autores->pluck('nome_completo')->take(3)->implode(', ') }}{{ $t->autores->count() > 3 ? ' +' . ($t->autores->count() - 3) : '' }}</span>
                                </td>
                                <td>{{ \App\Models\TrabalhoConfig::TIPOS[$t->tipo_trabalho] ?? '—' }}</td>
                                <td>{{ $t->area->nome ?? '—' }}</td>
                                <td><x-badge tipo="trabalho" :valor="$t->statusFinal()" :extra="$t->extraSituacao()" /></td>
                                <td>{{ $t->avaliacoes->where('status', '!=', 'recusada')->count() }}/{{ $config?->avaliadores_por_trabalho ?? 2 }}</td>
                                <td>{{ $t->submetido_em?->format('d/m/Y H:i') }}</td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('gestao.trabalhos.show', $t) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-folder-open"></i> Abrir</a>
                                    @if($t->arquivoAtual) <a href="{{ route('trabalhos.arquivo', $t) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="PDF"><i class="fa-solid fa-file-pdf"></i></a> @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-5">Nenhum trabalho encontrado.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3">{{ $trabalhos->links() }}</div>
        </div>
    </div>
</x-app-layout>
