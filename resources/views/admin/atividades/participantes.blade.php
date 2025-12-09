<x-app-layout>
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.eventos.show', $atividade->evento_id) }}" class="btn btn-light rounded-circle shadow-sm" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h4 class="fw-bold m-0 text-dark">{{ $atividade->titulo }}</h4>
            <p class="text-secondary m-0 small">Gerenciamento de Presença • {{ $atividade->evento->titulo }}</p>
        </div>
    </div>

    <div x-data="{ showModal: false, search: '' }">

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="glass-card p-3 d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded p-3">
                        <i class="fa-solid fa-clipboard-check fs-4"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold m-0">{{ $lista->where('status', 'presente')->count() }}</h3>
                        <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.65rem;">Confirmados (Presentes)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card p-3 d-flex align-items-center gap-3">
                    <div class="bg-info bg-opacity-10 text-info rounded p-3">
                        <i class="fa-solid fa-user-clock fs-4"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold m-0">{{ $lista->where('status', 'inscrito')->count() }}</h3>
                        <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.65rem;">Pendentes (Só Inscritos)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card p-3 d-flex align-items-center gap-3">
                    <div class="bg-success bg-opacity-10 text-success rounded p-3">
                        <i class="fa-solid fa-users fs-4"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold m-0">{{ $lista->count() }}</h3>
                        <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.65rem;">Total na Lista</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card p-3 mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div class="position-relative w-100 w-md-50">
                    <i class="fa-solid fa-magnifying-glass position-absolute text-secondary" style="top: 12px; left: 15px;"></i>
                    <input x-model="search" type="text" class="form-control ps-5 border-0 bg-light" placeholder="Buscar na lista...">
                </div>
                <div class="d-flex gap-2 w-100 w-md-auto">
                    <a href="{{ route('admin.atividades.exportar', $atividade->id) }}" target="_blank" class="btn btn-success fw-bold text-white shadow-sm btn-sm px-3">
                        <i class="fa-solid fa-file-excel me-2"></i> Exportar
                    </a>
                    <a href="{{ route('admin.atividades.manual', $atividade->id) }}" class="btn btn-dark fw-bold shadow-sm btn-sm px-3">
                        <i class="fa-solid fa-user-plus me-2"></i> Adicionar Extra
                    </a>
                </div>
            </div>
        </div>

        <div class="glass-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-secondary small text-uppercase">Status</th>
                            <th class="text-secondary small text-uppercase">Participante</th>
                            <th class="text-secondary small text-uppercase">Vínculo</th>
                            <th class="text-secondary small text-uppercase">Função</th>
                            <th class="text-end pe-4 text-secondary small text-uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        @foreach($lista as $item)
                        <tr x-show="$el.innerText.toLowerCase().includes(search.toLowerCase())" class="transition">

                            <td class="ps-4">
                                @if($item->status == 'presente')
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 border border-success border-opacity-25">
                                        <i class="fa-solid fa-check me-1"></i> Presente
                                    </span>
                                @else
                                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-2 border border-info border-opacity-25">
                                        <i class="fa-regular fa-clock me-1"></i> Inscrito
                                    </span>
                                @endif
                            </td>

                            <td>
                                <div class="fw-bold text-dark">{{ $item->participante->nome_completo }}</div>
                                <div class="small text-secondary">{{ $item->participante->cpf }}</div>
                            </td>

                            <td>
                                <span class="badge bg-light text-dark border fw-normal">{{ ucfirst($item->participante->tipo_vinculo) }}</span>
                                @if($item->participante->turma)
                                    <div class="small text-muted mt-1" style="font-size: 0.7rem;">{{ Str::limit($item->participante->turma->nome_completo, 30) }}</div>
                                @endif
                            </td>

                            <td>
                                @if($item->status == 'presente')
                                    <form action="{{ route('admin.frequencias.role', $item->frequencia->id) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <select name="role" onchange="this.form.submit()"
                                                class="form-select form-select-sm border-0 fw-bold cursor-pointer bg-transparent shadow-none p-0 ps-1" style="width: auto;">
                                            <option value="ouvinte" {{ $item->tipo_participacao == 'ouvinte' ? 'selected' : '' }}>Ouvinte</option>
                                            <option value="palestrante" {{ $item->tipo_participacao == 'palestrante' ? 'selected' : '' }}>⭐ Palestrante</option>
                                            <option value="mediador" {{ $item->tipo_participacao == 'mediador' ? 'selected' : '' }}>🎤 Mediador</option>
                                            <option value="voluntario" {{ $item->tipo_participacao == 'voluntario' ? 'selected' : '' }}>🤝 Voluntário</option>
                                        </select>
                                    </form>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>

                            <td class="text-end pe-4">
                                @if($item->status == 'presente')
                                    <a href="{{ route('certificado.download', $item->frequencia->hash_validacao) }}" target="_blank" class="btn btn-sm btn-link text-warning p-0 me-3" title="Baixar Certificado">
                                        <i class="fa-solid fa-file-pdf fs-5"></i>
                                    </a>
                                    <form action="{{ route('admin.frequencias.destroy', $item->frequencia->id) }}" method="POST" onsubmit="return confirm('Remover presença?');" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-link text-danger p-0" title="Remover Presença">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.atividades.checkin', ['atividade' => $atividade->id, 'participante' => $item->participante->id]) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm fw-bold shadow-sm px-3">
                                            <i class="fa-solid fa-check me-1"></i> Dar Presença
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($lista->isEmpty())
                <div class="text-center py-5">
                    <i class="fa-solid fa-clipboard-user fs-1 text-secondary opacity-25 mb-3"></i>
                    <h5 class="text-secondary">Ninguém na lista</h5>
                    <p class="text-muted small">Nenhum inscrito ou presente até o momento.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
