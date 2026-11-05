<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-9">

            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h3 class="fw-bold m-0">Configurar chamada de trabalhos</h3>
                    <small class="text-secondary">{{ $evento->titulo }}</small>
                </div>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 small">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>
            @endif

            <form action="{{ route('gestao.trabalhos.config.update', $evento) }}" method="POST">
                @csrf @method('PUT')

                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3">Período</h6>
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="ativo" value="0">
                        <input class="form-check-input" type="checkbox" name="ativo" value="1" id="ativo" @checked(old('ativo', $config->ativo))>
                        <label class="form-check-label fw-bold" for="ativo">Chamada ativa (aceitando submissões dentro do período)</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Início da submissão</label>
                            <input type="datetime-local" name="submissao_inicio" class="form-control" value="{{ old('submissao_inicio', $config->submissao_inicio?->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Fim da submissão</label>
                            <input type="datetime-local" name="submissao_fim" class="form-control" value="{{ old('submissao_fim', $config->submissao_fim?->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Prazo padrão das avaliações</label>
                            <input type="datetime-local" name="avaliacao_fim" class="form-control" value="{{ old('avaliacao_fim', $config->avaliacao_fim?->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Avaliadores por trabalho</label>
                            <input type="number" min="1" max="10" name="avaliadores_por_trabalho" class="form-control" value="{{ old('avaliadores_por_trabalho', $config->avaliadores_por_trabalho ?? 2) }}" required>
                        </div>
                    </div>
                </div>

                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3">Regras do formulário</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="small fw-bold text-secondary">Máx. de autores</label>
                            <input type="number" min="1" max="30" name="max_autores" class="form-control" value="{{ old('max_autores', $config->max_autores ?? 8) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold text-secondary">Tamanho máx. do PDF (MB)</label>
                            <input type="number" min="1" max="100" name="max_arquivo_mb" class="form-control" value="{{ old('max_arquivo_mb', $config->max_arquivo_mb ?? 20) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold text-secondary">Resumo: mín. palavras</label>
                            <input type="number" min="0" name="resumo_min_palavras" class="form-control" value="{{ old('resumo_min_palavras', $config->resumo_min_palavras ?? 100) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold text-secondary">Resumo: máx. palavras</label>
                            <input type="number" min="1" name="resumo_max_palavras" class="form-control" value="{{ old('resumo_max_palavras', $config->resumo_max_palavras ?? 300) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold text-secondary">Trabalhos por usuário (vazio = sem limite)</label>
                            <input type="number" min="1" name="max_trabalhos_por_usuario" class="form-control" value="{{ old('max_trabalhos_por_usuario', $config->max_trabalhos_por_usuario) }}">
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input type="hidden" name="avaliacao_cega" value="0">
                                <input class="form-check-input" type="checkbox" name="avaliacao_cega" value="1" id="cega" @checked(old('avaliacao_cega', $config->avaliacao_cega))>
                                <label class="form-check-label" for="cega">Avaliação cega (o PDF deve vir sem identificação dos autores)</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="small fw-bold text-secondary d-block">Tipos de trabalho aceitos</label>
                        @php($marcados = old('tipos_permitidos', $config->tipos_permitidos ?: array_keys(\App\Models\TrabalhoConfig::TIPOS)))
                        @foreach(\App\Models\TrabalhoConfig::TIPOS as $valor => $rotulo)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="tipos_permitidos[]" value="{{ $valor }}" id="t{{ $valor }}" @checked(in_array($valor, $marcados))>
                                <label class="form-check-label" for="t{{ $valor }}">{{ $rotulo }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3">Áreas temáticas</h6>
                    <textarea name="areas" rows="6" class="form-control" placeholder="Uma área por linha">{{ old('areas', $areas) }}</textarea>
                    <div class="form-text">Uma por linha. Remover uma área desvincula os trabalhos que a usam.</div>
                </div>

                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3">Regulamento (exibido no formulário)</h6>
                    <textarea name="regras_texto" rows="8" class="form-control">{{ old('regras_texto', $config->regras_texto) }}</textarea>
                </div>

                <div class="text-end mb-5">
                    <button class="btn btn-primary-custom fw-bold px-4"><i class="fa-regular fa-floppy-disk me-1"></i> Salvar configuração</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
