<x-app-layout>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <div class="row justify-content-center">
        <div class="col-xl-11">

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                    <div>
                        <h3 class="fw-bold m-0"><i class="fa-solid fa-people-group text-success me-1"></i> Equipe do evento</h3>
                        <small class="text-secondary">{{ $evento->titulo }}</small>
                    </div>
                </div>
                @if($membros->isNotEmpty())
                    <form action="{{ route('admin.eventos.equipe.emitir-todos', $evento->id) }}" method="POST">@csrf
                        <button class="btn btn-tone-amber fw-bold" data-confirmar="Emitir o certificado de todos os membros que ainda não têm?"><i class="fa-solid fa-certificate"></i> Emitir certificados de todos</button>
                    </form>
                @endif
            </div>

            @if(session('success')) <div class="alert alert-success small">{{ session('success') }}</div> @endif
            @if(session('warning')) <div class="alert alert-warning small">{{ session('warning') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger small">{{ session('error') }}</div> @endif
            @if($errors->any())     <div class="alert alert-danger small">{{ $errors->first() }}</div> @endif
            @if(session('erros_lote'))
                <div class="alert alert-warning small"><strong>Ignorados:</strong><ul class="mb-0 ps-3">@foreach(session('erros_lote') as $erro) <li>{{ $erro }}</li> @endforeach</ul></div>
            @endif

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="glass-card bg-white mb-4">
                        <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-user-plus me-2"></i> Adicionar membro</h6>
                        <form action="{{ route('admin.eventos.equipe.store', $evento->id) }}" method="POST" data-validar>
                            @csrf
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary">Participante (nome, CPF, e-mail ou matrícula) *</label>
                                <x-select-pessoa :evento="$evento" tipo="participante" name="cpf_busca" :selecionado="old('cpf_busca')" placeholder="Digite para buscar…" />
                                <div class="form-text small">A pessoa precisa estar cadastrada no sistema.</div>
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary" for="funcao">Função *</label>
                                <select id="funcao" name="funcao" class="form-select" required>
                                    @foreach($funcoes as $f) <option value="{{ $f }}" @selected(old('funcao') === $f)>{{ $f }}</option> @endforeach
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="small fw-bold text-secondary" for="ch">Carga horária (horas)</label>
                                <input id="ch" type="number" name="carga_horaria" class="form-control" min="1" max="1000" value="{{ old('carga_horaria', \App\Models\Equipe::CARGA_PADRAO) }}">
                                <div class="form-text small">Horas que constarão no certificado da equipe.</div>
                            </div>
                            <button type="submit" class="btn btn-success w-100 fw-bold"><i class="fa-solid fa-plus"></i> Adicionar à equipe</button>
                        </form>
                    </div>

                    <details class="glass-card bg-white">
                        <summary class="fw-bold text-success" style="cursor:pointer;"><i class="fa-solid fa-pen-ruler me-1"></i> Texto do certificado da equipe</summary>
                        <form action="{{ route('admin.eventos.equipe.modelo', $evento->id) }}" method="POST" class="mt-3">
                            @csrf @method('PUT')
                            <textarea name="cert_equipe_corpo" rows="6" class="form-control font-monospace small">{{ $corpoModelo }}</textarea>
                            <div class="form-text small">Variáveis: {PARTICIPANTE} {CPF} {FUNCAO} {EVENTO} {DATA_EVENTO} {CARGA_HORARIA} {DATA_EMISSAO}. Tags: &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;br&gt;. Cabeçalho, rodapé, fundo e assinaturas vêm de <a href="{{ route('admin.eventos.certificado.edit', $evento->id) }}">Certificado do evento</a>.</div>
                            <button class="btn btn-sm btn-success fw-bold mt-2">Salvar texto</button>
                        </form>
                    </details>
                </div>

                <div class="col-lg-8">
                    <div class="glass-card p-0 overflow-hidden">
                        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold m-0">Membros</h6>
                            <span class="small text-muted">{{ $membros->count() }} pessoa(s) · {{ $membros->filter->certificadoEmitido()->count() }} certificado(s) emitido(s) · {{ $membros->sum('carga_horaria_trabalho') }}h no total</span>
                        </div>

                        @if($membros->isEmpty())
                            <div class="text-center py-5">
                                <i class="fa-solid fa-users-slash fs-1 text-secondary opacity-25 mb-3"></i>
                                <p class="text-muted small m-0">Ninguém na equipe ainda.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 small">
                                    <thead><tr><th class="ps-3">Membro</th><th>Função</th><th>C.H.</th><th>Certificado</th><th class="text-end pe-3">Ações</th></tr></thead>
                                    <tbody>
                                    @foreach($membros as $m)
                                        <tr>
                                            <td class="ps-3 py-3">
                                                <div class="fw-bold">{{ $m->participante->nome_completo }}</div>
                                                <div class="text-muted"><i class="fa-regular fa-envelope me-1"></i>{{ $m->participante->email }}</div>
                                                <div class="text-muted"><i class="fa-regular fa-id-card me-1"></i>{{ $m->participante->cpf }}</div>
                                            </td>
                                            <td><span class="sb sb-aluno">{{ $m->funcao }}</span></td>
                                            <td class="fw-bold text-nowrap">{{ $m->carga_horaria_trabalho }}h</td>
                                            <td class="text-nowrap">
                                                @if($m->certificadoEmitido())
                                                    <span class="sb sb-ok"><i class="fa-solid fa-certificate"></i> Emitido {{ $m->certificado_emitido_em->format('d/m') }}</span>
                                                @else
                                                    <span class="sb sb-pend"><i class="fa-solid fa-hourglass-half"></i> Não emitido</span>
                                                @endif
                                            </td>
                                            <td class="text-end pe-3 text-nowrap">
                                                @if($m->certificadoEmitido())
                                                    <a href="{{ route('admin.equipe.certificado', $m->id) }}" target="_blank" class="btn btn-sm btn-tone-amber" title="Ver/baixar certificado"><i class="fa-solid fa-file-pdf"></i></a>
                                                    <form action="{{ route('admin.equipe.revogar', $m->id) }}" method="POST" class="d-inline">@csrf
                                                        <button class="btn btn-sm btn-outline-warning" title="Revogar certificado" data-confirmar="Revogar o certificado? O código atual deixa de validar."><i class="fa-solid fa-ban"></i></button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('admin.equipe.emitir', $m->id) }}" method="POST" class="d-inline">@csrf
                                                        <button class="btn btn-sm btn-success" title="Emitir certificado"><i class="fa-solid fa-certificate"></i> Emitir</button>
                                                    </form>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editar-{{ $m->id }}" title="Editar"><i class="fa-solid fa-pen"></i></button>
                                                <form action="{{ route('admin.equipe.destroy', $m->id) }}" method="POST" class="d-inline">@csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" title="Remover" data-confirmar="Remover {{ $m->participante->nome_completo }} da equipe?{{ $m->certificadoEmitido() ? ' O certificado emitido deixará de validar.' : '' }}"><i class="fa-solid fa-trash"></i></button>
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

    @foreach($membros as $m)
        <div class="modal fade" id="editar-{{ $m->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('admin.equipe.update', $m->id) }}" method="POST" class="modal-content rounded-4 border-0 shadow-lg" data-validar>
                    @csrf @method('PUT')
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Editar membro</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-3">{{ $m->participante->nome_completo }}</p>
                        <div class="mb-3">
                            <label class="small fw-bold text-secondary">Função *</label>
                            <select name="funcao" class="form-select" required>
                                @foreach($funcoes as $f) <option value="{{ $f }}" @selected($m->funcao === $f)>{{ $f }}</option> @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold text-secondary">Carga horária (horas) *</label>
                            <input type="number" name="carga_horaria" class="form-control" min="1" max="1000" value="{{ $m->carga_horaria_trabalho }}" required>
                        </div>
                        @if($m->certificadoEmitido()) <div class="form-text small">O certificado já emitido passa a refletir a alteração.</div> @endif
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-success fw-bold"><i class="fa-regular fa-floppy-disk"></i> Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</x-app-layout>
