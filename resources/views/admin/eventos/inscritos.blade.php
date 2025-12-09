<x-app-layout>
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h4 class="fw-bold m-0 text-dark">Lista Geral de Inscritos</h4>
            <p class="text-secondary m-0 small">{{ $evento->titulo }}</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="glass-card p-3 border-start border-4 border-primary">
                <h3 class="fw-bold m-0">{{ $evento->inscricoes()->count() }}</h3>
                <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.7rem;">Total Inscritos</small>
            </div>
        </div>
        </div>

    <div class="glass-card p-3 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">

            <form method="GET" class="position-relative w-100 w-md-50">
                <i class="fa-solid fa-magnifying-glass position-absolute text-secondary" style="top: 12px; left: 15px;"></i>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control ps-5 border-0 bg-light" placeholder="Buscar por nome, CPF ou email..." onblur="this.form.submit()">
            </form>

            <a href="{{ route('admin.eventos.inscritos.exportar', $evento->id) }}" target="_blank" class="btn btn-success fw-bold text-white shadow-sm d-flex align-items-center gap-2">
                <i class="fa-solid fa-file-csv"></i> Baixar Relatório (CSV)
            </a>
        </div>
    </div>

    <div class="glass-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 text-secondary small text-uppercase py-3">Participante</th>
                        <th class="text-secondary small text-uppercase">Vínculo</th>
                        <th class="text-secondary small text-uppercase" style="width: 40%;">Agenda (Atividades)</th>
                        <th class="text-secondary small text-uppercase text-end pe-4">Data Inscrição</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    @foreach($inscricoes as $inscricao)
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="fw-bold text-dark">{{ $inscricao->participante->nome_completo }}</div>
                            <div class="text-secondary small">
                                <i class="fa-regular fa-id-card me-1"></i> {{ $inscricao->participante->cpf }}
                            </div>
                        </td>
                        <td>
                            @php $tipo = $inscricao->participante->tipo_vinculo; @endphp
                            <span class="badge rounded-pill px-3 py-2
                                {{ $tipo == 'aluno' ? 'bg-success bg-opacity-10 text-success' :
                                  ($tipo == 'servidor' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-secondary bg-opacity-10 text-secondary') }}">
                                {{ ucfirst($tipo) }}
                            </span>
                            @if($inscricao->participante->turma)
                                <div class="text-muted small mt-1" style="font-size: 0.7rem;">
                                    {{ $inscricao->participante->turma->nome_completo }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($inscricao->participante->atividadesInscritas->isEmpty())
                                <span class="text-muted small fst-italic">Nenhuma atividade selecionada</span>
                            @else
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($inscricao->participante->atividadesInscritas->where('evento_id', $evento->id) as $ativ)
                                        <span class="badge bg-light text-dark border fw-normal" title="{{ $ativ->titulo }}">
                                            {{ Str::limit($ativ->titulo, 30) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="text-end pe-4 text-secondary small">
                            {{ $inscricao->created_at->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($inscricoes->isEmpty())
            <div class="text-center py-5">
                <i class="fa-solid fa-users-slash fs-1 text-secondary opacity-25 mb-3"></i>
                <h5 class="text-secondary">Nenhum inscrito encontrado</h5>
            </div>
        @else
            <div class="p-3 border-top">
                {{ $inscricoes->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
