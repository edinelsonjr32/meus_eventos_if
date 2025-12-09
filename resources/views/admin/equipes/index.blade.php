<x-app-layout>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div>
                        <h3 class="fw-bold m-0">Gestão de Staff</h3>
                        <small class="text-secondary">{{ $evento->titulo }}</small>
                    </div>
                </div>
            </div>

            <div class="row g-4">

                <div class="col-md-4">
                    <div class="glass-card bg-white position-sticky" style="top: 20px;">
                        <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-user-plus me-2"></i> Adicionar Membro</h6>

                        @if(session('error'))
                            <div class="alert alert-danger small p-2 mb-3">{{ session('error') }}</div>
                        @endif
                        @if(session('warning'))
                            <div class="alert alert-warning small p-2 mb-3">{{ session('warning') }}</div>
                        @endif
                        @if(session('success'))
                            <div class="alert alert-success small p-2 mb-3">{{ session('success') }}</div>
                        @endif

                        <form action="{{ route('admin.eventos.equipe.store', $evento->id) }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label class="small fw-bold text-secondary">Buscar Participante por CPF</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-regular fa-id-card text-muted"></i></span>
                                    <input type="text" name="cpf_busca" id="searchField" class="form-control border-start-0" placeholder="000.000.000-00" required>
                                </div>
                                <div class="form-text small" style="font-size: 11px;">
                                    O participante precisa estar cadastrado no sistema.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="small fw-bold text-secondary">Função / Cargo</label>
                                <select name="funcao" class="form-select">
                                    <option value="Organizador">Organizador</option>
                                    <option value="Coordenador">Coordenador</option>
                                    <option value="Monitor">Monitor</option>
                                    <option value="Apoio Técnico">Apoio Técnico</option>
                                    <option value="Palestrante">Palestrante</option>
                                    <option value="Voluntário">Voluntário</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="small fw-bold text-secondary">Carga Horária (Staff)</label>
                                <input type="number" name="carga_horaria" class="form-control" value="20">
                                <div class="form-text small" style="font-size: 11px;">Horas que constarão no certificado de equipe.</div>
                            </div>

                            <button type="submit" class="btn btn-primary-custom w-100 shadow-sm fw-bold">
                                <i class="fa-solid fa-plus me-1"></i> Adicionar
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="glass-card p-0 overflow-hidden">
                        <div class="p-4 border-bottom bg-light d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold m-0 text-dark">Membros da Equipe</h6>
                            <span class="badge bg-secondary">{{ $membros->count() }} Pessoas</span>
                        </div>

                        @if($membros->isEmpty())
                            <div class="text-center py-5">
                                <i class="fa-solid fa-users-slash fs-1 text-secondary opacity-25 mb-3"></i>
                                <p class="text-muted small m-0">Ninguém na equipe ainda.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-white">
                                        <tr>
                                            <th class="ps-4 small text-secondary">Nome / Email</th>
                                            <th class="small text-secondary">Função</th>
                                            <th class="small text-secondary">Horas</th>
                                            <th class="text-end pe-4 small text-secondary">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($membros as $membro)
                                            <tr>
                                                <td class="ps-4 py-3">
                                                    <div class="fw-bold text-dark">{{ $membro->participante->nome_completo }}</div>
                                                    <div class="small text-muted d-flex gap-2">
                                                        <span><i class="fa-regular fa-envelope me-1"></i> {{ $membro->participante->email }}</span>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <span><i class="fa-regular fa-id-card me-1"></i> {{ $membro->participante->cpf }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3">
                                                        {{ $membro->funcao }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-dark">{{ $membro->carga_horaria ?? 'N/A' }}h</span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <form action="{{ route('admin.equipe.destroy', $membro->id) }}" method="POST" onsubmit="return confirm('Remover este membro da equipe?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-link text-danger p-0" title="Remover">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        $(document).ready(function(){
            // Aplica a máscara de forma estável, sem complexidade de input/unmask/mask
            $('#searchField').mask('000.000.000-00');
        });
    </script>
</x-app-layout>
