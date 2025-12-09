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

                    <div class="mt-5 pt-3 border-top">
                        <button type="submit" class="btn btn-success-custom px-5 py-3 fw-bold shadow-sm">
                            <i class="fa-solid fa-file-excel me-2"></i> Exportar Dados Completos (.xlsx)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
