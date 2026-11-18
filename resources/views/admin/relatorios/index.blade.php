<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h3 class="fw-bold mb-4">📊 Relatório de Frequência Geral</h3>

            <div class="glass-card bg-white shadow-lg p-5">
                <p class="text-secondary mb-4">
                    Selecione os filtros desejados para exportar a listagem completa de presença de todos os eventos e atividades.
                </p>

                <form action="{{ route('admin.relatorios.frequencia.exportar') }}" method="POST">
                    @csrf

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary small">Filtrar por Evento</label>
                        <select name="evento_id" class="form-select">
                            <option value="">-- Todos os Eventos --</option>
                            @foreach($eventos as $evento)
                                <option value="{{ $evento->id }}">
                                    {{ $evento->titulo }} ({{ $evento->data_inicio->format('Y') }})
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Selecione um evento específico, ou deixe vazio para listar todos.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary small">Filtrar por Curso do Participante</label>
                        <select name="curso_id" class="form-select">
                            <option value="">-- Todos os Cursos --</option>
                            @foreach($cursos as $curso)
                                <option value="{{ $curso->id }}">
                                    {{ $curso->nome }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Apenas participantes vinculados a este curso serão listados.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary small">Filtrar por Turma</label>
                        <select name="turma_id" class="form-select">
                            <option value="">-- Todas as Turmas --</option>
                            @foreach($turmas as $turma)
                                <option value="{{ $turma->id }}">{{ $turma->nome_completo }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small">Atividades a partir de</label>
                            <input type="date" name="data_de" class="form-control" value="{{ old('data_de') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small">Atividades até</label>
                            <input type="date" name="data_ate" class="form-control @error('data_ate') is-invalid @enderror" value="{{ old('data_ate') }}">
                            @error('data_ate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary small">Participantes incluídos</label>
                        <select name="vinculo" class="form-select">
                            <option value="aluno">Somente alunos</option>
                            <option value="todos">Todos (alunos, servidores e externos)</option>
                        </select>
                    </div>

                    <div class="alert alert-light border small mb-0">
                        <strong>O que vem na planilha:</strong> abas <em>Resumo</em> (filtros, indicadores e quadro por turma), <em>Frequência</em> (uma linha por presença), <em>Por aluno</em> (atividades e horas de cada um), <em>Por atividade</em> e <em>Matriz</em> (alunos × atividades). Todas já vêm com filtro nas colunas e cabeçalho fixo.
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-success-custom px-5 py-3 fw-bold shadow-sm">
                            <i class="fa-solid fa-file-excel me-2"></i> Exportar Dados Completos (.xlsx)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
