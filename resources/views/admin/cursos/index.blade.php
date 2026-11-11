<x-app-layout>
    <div class="mb-4 d-flex flex-wrap justify-content-between align-items-end gap-3">
        <div>
            <h3 class="fw-bold mb-1">Cursos e Turmas</h3>
            <p class="text-secondary mb-0">Cadastre os cursos e as turmas (por ano) que aparecem na inscrição, no credenciamento e no registro de presença dos alunos.</p>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>
    @endif

    <div class="row g-3 mb-4">
        @foreach([['fa-school', $totais['cursos'], 'cursos cadastrados'], ['fa-people-group', $totais['turmas'], 'turmas'], ['fa-user-graduation', $totais['alunos'], 'alunos vinculados a turmas']] as [$ico, $valor, $rotulo])
            <div class="col-md-4">
                <div class="glass-card d-flex align-items-center gap-3 py-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:46px;height:46px;background:#ecfdf5;color:#059669;"><i class="fa-solid {{ $ico }} fs-5"></i></div>
                    <div><div class="fs-4 fw-bold lh-1">{{ $valor }}</div><small class="text-secondary">{{ $rotulo }}</small></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        {{-- Novo curso + busca --}}
        <div class="col-lg-4">
            <div class="glass-card mb-3" style="position: sticky; top: 1rem;">
                <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-plus me-1"></i> Novo curso</h6>
                <form action="{{ route('admin.cursos.store') }}" method="POST">
                    @csrf
                    <label class="small fw-bold" for="nome-curso">Nome do curso</label>
                    <input id="nome-curso" type="text" name="nome" class="form-control mb-3" maxlength="150" placeholder="Ex.: Técnico em Informática" value="{{ old('nome') }}" required>
                    <button class="btn btn-primary-custom w-100"><i class="fa-solid fa-check me-1"></i> Cadastrar curso</button>
                </form>

                <hr class="my-4">
                <form method="GET" action="{{ route('admin.cursos.index') }}">
                    <label class="small fw-bold" for="busca-curso">Buscar curso</label>
                    <div class="input-group">
                        <input id="busca-curso" type="search" name="q" value="{{ $busca }}" class="form-control" placeholder="Nome do curso">
                        <button class="btn btn-light border"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                    @if($busca !== '')<a href="{{ route('admin.cursos.index') }}" class="small d-inline-block mt-2">limpar busca</a>@endif
                </form>
            </div>
        </div>

        {{-- Lista de cursos --}}
        <div class="col-lg-8">
            @forelse($cursos as $curso)
                @php $alunosCurso = $curso->turmas->sum('participantes_count'); @endphp
                <div class="glass-card mb-3" x-data="{ editando: false, novaTurma: false }">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                        <div class="min-w-0" x-show="!editando">
                            <h5 class="fw-bold mb-1 texto-quebra">{{ $curso->nome }}</h5>
                            <small class="text-secondary">{{ $curso->turmas->count() }} turma(s) · {{ $alunosCurso }} aluno(s)</small>
                        </div>
                        <div x-show="editando" x-cloak class="flex-grow-1">
                            <form action="{{ route('admin.cursos.update', $curso) }}" method="POST" class="d-flex flex-wrap gap-2">
                                @csrf @method('PUT')
                                <input type="text" name="nome" class="form-control" style="max-width: 360px;" value="{{ $curso->nome }}" maxlength="150" required aria-label="Nome do curso">
                                <button class="btn btn-primary-custom btn-sm">Salvar</button>
                                <button type="button" class="btn btn-light btn-sm border" @click="editando = false">Cancelar</button>
                            </form>
                        </div>

                        <div x-show="!editando"><div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-light border" @click="editando = true" title="Renomear o curso"><i class="fa-solid fa-pen"></i> Renomear</button>
                            <form action="{{ route('admin.cursos.destroy', $curso) }}" method="POST" onsubmit="return confirm('Excluir o curso &quot;{{ addslashes($curso->nome) }}&quot; e suas turmas? Só é possível se não houver alunos vinculados.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light border" title="Excluir o curso"><i class="fa-solid fa-trash"></i> Excluir</button>
                            </form>
                        </div></div>
                    </div>

                    {{-- Turmas --}}
                    @if($curso->turmas->isNotEmpty())
                        <div class="table-responsive mb-3">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>Turma (ano)</th><th>Alunos</th><th class="text-end">Ações</th></tr></thead>
                                <tbody>
                                    @foreach($curso->turmas as $turma)
                                        <tr x-data="{ editar: false }">
                                            <td>
                                                <span x-show="!editar" class="fw-semibold">{{ $turma->ano }}</span>
                                                <div x-show="editar" x-cloak>
                                                    <form action="{{ route('admin.turmas.update', $turma) }}" method="POST" class="d-flex gap-2">
                                                        @csrf @method('PUT')
                                                        <input type="text" name="ano" class="form-control form-control-sm" style="width: 130px;" value="{{ $turma->ano }}" maxlength="20" required aria-label="Ano da turma">
                                                        <button class="btn btn-primary-custom btn-sm">Salvar</button>
                                                        <button type="button" class="btn btn-light btn-sm border" @click="editar = false">Cancelar</button>
                                                    </form>
                                                </div>
                                            </td>
                                            <td>{{ $turma->participantes_count }}</td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-light border" x-show="!editar" @click="editar = true" title="Alterar o ano"><i class="fa-solid fa-pen"></i></button>
                                                <form action="{{ route('admin.turmas.destroy', $turma) }}" method="POST" class="d-inline" onsubmit="return confirm('Excluir a turma {{ $turma->ano }}?')">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-light border" title="Excluir a turma"><i class="fa-solid fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="small text-muted mb-3"><i class="fa-regular fa-folder-open me-1"></i> Este curso ainda não tem turmas.</div>
                    @endif

                    {{-- Nova turma --}}
                    <button type="button" class="btn btn-sm btn-outline-success" x-show="!novaTurma" @click="novaTurma = true; $nextTick(() => $refs.ano.focus())"><i class="fa-solid fa-plus"></i> Adicionar turma</button>
                    <div x-show="novaTurma" x-cloak>
                        <form action="{{ route('admin.turmas.store', $curso) }}" method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                            @csrf
                            <input x-ref="ano" type="text" name="ano" class="form-control form-control-sm" style="width: 150px;" placeholder="Ano (ex.: {{ date('Y') }})" maxlength="20" required aria-label="Ano da nova turma">
                            <button class="btn btn-primary-custom btn-sm">Adicionar</button>
                            <button type="button" class="btn btn-light btn-sm border" @click="novaTurma = false">Cancelar</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="glass-card text-center py-5">
                    <i class="fa-solid fa-school fs-1 text-secondary opacity-25 mb-3 d-block"></i>
                    <p class="text-muted mb-0">{{ $busca !== '' ? 'Nenhum curso encontrado para esta busca.' : 'Nenhum curso cadastrado ainda. Use o formulário ao lado para criar o primeiro.' }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
