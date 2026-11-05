@php
    $editando = $trabalho->exists;
    $action = $editando ? route('trabalhos.update', $trabalho) : route('trabalhos.store', $evento->slug);
    $tipos = $config->tiposHabilitados();
    $arquivoAtual = $editando ? $trabalho->arquivoAtual : null;
@endphp
<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-10">

            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="{{ $editando ? route('trabalhos.show', $trabalho) : route('evento.publico.show', $evento->slug) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h3 class="fw-bold m-0">{{ $editando ? 'Editar trabalho' : 'Submeter trabalho' }}</h3>
                    <small class="text-secondary">{{ $evento->titulo }}
                        @if($config->submissao_fim) · prazo até {{ $config->submissao_fim->format('d/m/Y H:i') }} @endif
                    </small>
                </div>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <strong>Corrija os campos abaixo:</strong>
                    <ul class="mb-0 mt-1 small">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
                </div>
            @endif

            @if($trabalho->status === 'aguardando_correcao' || $trabalho->status === 'devolvido_triagem')
                <div class="alert alert-warning"><i class="fa-solid fa-pen me-1"></i> A gestão solicitou ajustes. Atualize o trabalho (e o PDF, se necessário) e reenvie.</div>
            @endif

            @if($config->regras_texto)
                <details class="glass-card bg-white mb-4">
                    <summary class="fw-bold">Regulamento e normas da chamada</summary>
                    <div class="mt-3 small" style="white-space: pre-line;">{{ $config->regras_texto }}</div>
                </details>
            @endif

            <form action="{{ $action }}" method="POST" enctype="multipart/form-data"
                  x-data="formTrabalho({{ \Illuminate\Support\Js::from($autores) }}, {{ (int) $config->max_autores }}, {{ (int) $config->resumo_min_palavras }}, {{ (int) $config->resumo_max_palavras }}, {{ \Illuminate\Support\Js::from(old('resumo', $trabalho->resumo)) }})">
                @csrf
                @if($editando) @method('PUT') @endif

                {{-- 1. Dados do trabalho --}}
                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-file-lines me-2"></i> 1. Dados do trabalho</h6>

                    <div class="mb-3">
                        <label class="small fw-bold text-secondary">Título *</label>
                        <input type="text" name="titulo" class="form-control" maxlength="255" value="{{ old('titulo', $trabalho->titulo) }}" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="small fw-bold text-secondary">Tipo de trabalho *</label>
                            <select name="tipo_trabalho" class="form-select">
                                <option value="">Selecione...</option>
                                @foreach($tipos as $valor => $rotulo)
                                    <option value="{{ $valor }}" @selected(old('tipo_trabalho', $trabalho->tipo_trabalho) === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold text-secondary">Área temática</label>
                            <select name="area_tematica_id" class="form-select">
                                <option value="">Selecione...</option>
                                @foreach($areas as $area)
                                    <option value="{{ $area->id }}" @selected((int) old('area_tematica_id', $trabalho->area_tematica_id) === $area->id)>{{ $area->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold text-secondary">Modalidade de apresentação *</label>
                            <select name="modalidade_apresentacao" class="form-select">
                                <option value="">Selecione...</option>
                                @foreach(\App\Models\Trabalho::MODALIDADES as $valor => $rotulo)
                                    <option value="{{ $valor }}" @selected(old('modalidade_apresentacao', $trabalho->modalidade_apresentacao) === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-secondary d-flex justify-content-between">
                            <span>Resumo * <span class="fw-normal">({{ $config->resumo_min_palavras }} a {{ $config->resumo_max_palavras }} palavras)</span></span>
                            <span :class="palavras < min || palavras > max ? 'text-danger' : 'text-success'" x-text="palavras + ' palavras'"></span>
                        </label>
                        <textarea name="resumo" rows="8" class="form-control" x-model="resumo">{{ old('resumo', $trabalho->resumo) }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-secondary">Abstract (opcional)</label>
                        <textarea name="abstract" rows="4" class="form-control">{{ old('abstract', $trabalho->abstract) }}</textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Palavras-chave * (3 a 5, separadas por vírgula)</label>
                            <input type="text" name="palavras_chave" class="form-control" value="{{ old('palavras_chave', implode(', ', $trabalho->palavras_chave ?? [])) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Agência de fomento</label>
                            <input type="text" name="agencia_fomento" class="form-control" value="{{ old('agencia_fomento', $trabalho->agencia_fomento) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Projeto de pesquisa</label>
                            <input type="text" name="projeto_pesquisa" class="form-control" value="{{ old('projeto_pesquisa', $trabalho->projeto_pesquisa) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Comitê de ética (nº do parecer)</label>
                            <input type="text" name="comite_etica" class="form-control" value="{{ old('comite_etica', $trabalho->comite_etica) }}">
                        </div>
                    </div>
                </div>

                {{-- 2. Autores --}}
                <div class="glass-card bg-white mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-success m-0"><i class="fa-solid fa-users me-2"></i> 2. Autores</h6>
                        <button type="button" class="btn btn-sm btn-outline-success fw-bold" @click="adicionar()">
                            <i class="fa-solid fa-plus me-1"></i> Adicionar autor
                        </button>
                    </div>
                    <p class="small text-muted">Informe exatamente <strong>um autor principal</strong> e ao menos <strong>um apresentador</strong>.
                        Limite de {{ $config->max_autores }} autores (orientadores e coorientadores não contam).</p>

                    <template x-for="(a, i) in autores" :key="a.uid">
                        <div class="border rounded-3 p-3 mb-3 bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong x-text="'Autor ' + (i + 1)"></strong>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-light" @click="mover(i, -1)" :disabled="i === 0" title="Subir"><i class="fa-solid fa-arrow-up"></i></button>
                                    <button type="button" class="btn btn-sm btn-light" @click="mover(i, 1)" :disabled="i === autores.length - 1" title="Descer"><i class="fa-solid fa-arrow-down"></i></button>
                                    <button type="button" class="btn btn-sm btn-light text-danger" @click="remover(i)" :disabled="autores.length === 1" title="Remover"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary">Nome completo *</label>
                                    <input type="text" class="form-control form-control-sm" :name="`autores[${i}][nome_completo]`" x-model="a.nome_completo">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary">CPF *</label>
                                    <input type="text" class="form-control form-control-sm" placeholder="000.000.000-00" maxlength="14" :name="`autores[${i}][cpf]`" x-model="a.cpf" @input="a.cpf = mascara(a.cpf)">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary">Tipo de autor *</label>
                                    <select class="form-select form-select-sm" :name="`autores[${i}][tipo_autor]`" x-model="a.tipo_autor">
                                        @foreach(\App\Models\Trabalho::TIPOS_AUTOR as $valor => $rotulo)
                                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="small fw-bold text-secondary">E-mail *</label>
                                    <input type="email" class="form-control form-control-sm" :name="`autores[${i}][email]`" x-model="a.email">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary">Telefone</label>
                                    <input type="text" class="form-control form-control-sm" :name="`autores[${i}][telefone]`" x-model="a.telefone">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary">Vínculo</label>
                                    <select class="form-select form-select-sm" :name="`autores[${i}][vinculo]`" x-model="a.vinculo">
                                        <option value="">Selecione...</option>
                                        <option value="aluno">Aluno</option>
                                        <option value="servidor">Servidor</option>
                                        <option value="externo">Externo</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary">Instituição</label>
                                    <input type="text" class="form-control form-control-sm" :name="`autores[${i}][instituicao]`" x-model="a.instituicao">
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary">Titulação / Curso</label>
                                    <input type="text" class="form-control form-control-sm" :name="`autores[${i}][titulacao]`" x-model="a.titulacao">
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary">Currículo Lattes (URL)</label>
                                    <input type="url" class="form-control form-control-sm" placeholder="https://lattes.cnpq.br/..." :name="`autores[${i}][lattes]`" x-model="a.lattes">
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary">ORCID</label>
                                    <input type="text" class="form-control form-control-sm" placeholder="0000-0000-0000-0000" :name="`autores[${i}][orcid]`" x-model="a.orcid">
                                </div>
                            </div>

                            <div class="d-flex gap-4 mt-2">
                                <input type="hidden" :name="`autores[${i}][apresentador]`" :value="a.apresentador ? 1 : 0">
                                <input type="hidden" :name="`autores[${i}][correspondente]`" :value="a.correspondente ? 1 : 0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" :id="`apr${a.uid}`" x-model="a.apresentador">
                                    <label class="form-check-label small" :for="`apr${a.uid}`">Apresentador</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="correspondente_ui" :id="`cor${a.uid}`" :checked="a.correspondente" @change="definirCorrespondente(i)">
                                    <label class="form-check-label small" :for="`cor${a.uid}`">Autor correspondente</label>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- 3. Arquivo --}}
                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-file-pdf me-2"></i> 3. Arquivo do trabalho (PDF)</h6>

                    @if($arquivoAtual)
                        <div class="alert alert-light border small">
                            Arquivo atual: <strong>{{ $arquivoAtual->nome_original }}</strong>
                            ({{ number_format($arquivoAtual->tamanho / 1048576, 2, ',', '.') }} MB, versão {{ $arquivoAtual->versao }}) —
                            <a href="{{ route('trabalhos.arquivo', $trabalho) }}" target="_blank">abrir</a>.
                            Envie um novo arquivo abaixo somente se quiser substituí-lo.
                        </div>
                    @endif

                    <input type="file" name="arquivo" accept="application/pdf,.pdf" class="form-control"
                           @change="validarArquivo($event, {{ (int) $config->max_arquivo_mb }})">
                    <div class="form-text">Somente PDF, até {{ $config->max_arquivo_mb }} MB.
                        @if($config->avaliacao_cega) <strong>Avaliação cega:</strong> envie a versão <u>sem identificação dos autores</u>. @endif
                    </div>
                    <div class="text-danger small mt-1" x-show="erroArquivo" x-text="erroArquivo" x-cloak></div>
                </div>

                {{-- 4. Declarações --}}
                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-signature me-2"></i> 4. Declarações</h6>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="declaracao_ineditismo" value="1" id="d1" @checked(old('declaracao_ineditismo'))>
                        <label class="form-check-label small" for="d1">Declaro que o trabalho é inédito e que todos os autores concordam com a submissão. *</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="declaracao_regulamento" value="1" id="d2" @checked(old('declaracao_regulamento'))>
                        <label class="form-check-label small" for="d2">Li e aceito o regulamento da chamada de trabalhos. *</label>
                    </div>
                    <div class="form-check">
                        <input type="hidden" name="autoriza_publicacao" value="0">
                        <input class="form-check-input" type="checkbox" name="autoriza_publicacao" value="1" id="d3" @checked(old('autoriza_publicacao', $trabalho->autoriza_publicacao))>
                        <label class="form-check-label small" for="d3">Autorizo a publicação do trabalho nos Anais do evento (necessário para constar nos Anais, caso seja aprovado e apresentado).</label>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-end mb-5">
                    <button type="submit" name="acao" value="rascunho" class="btn btn-outline-secondary fw-bold px-4">
                        <i class="fa-regular fa-floppy-disk me-1"></i> Salvar rascunho
                    </button>
                    <button type="submit" name="acao" value="submeter" class="btn btn-primary-custom fw-bold px-4"
                            onclick="return confirm('Após submeter, o trabalho será enviado à gestão. Deseja continuar?')">
                        <i class="fa-solid fa-paper-plane me-1"></i> {{ $trabalho->status === 'aguardando_correcao' ? 'Reenviar versão corrigida' : 'Submeter trabalho' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function formTrabalho(iniciais, maxAutores, min, max, resumoInicial) {
            let seq = 0;
            const novo = (d = {}) => ({
                uid: ++seq, nome_completo: '', cpf: '', email: '', telefone: '', instituicao: '', vinculo: '', titulacao: '',
                lattes: '', orcid: '', tipo_autor: 'coautor', apresentador: false, correspondente: false, ...d,
                apresentador: !!(d.apresentador), correspondente: !!(d.correspondente),
            });

            return {
                min, max, resumo: resumoInicial || '', erroArquivo: '',
                autores: (iniciais.length ? iniciais : [{}]).map(a => novo(Object.fromEntries(Object.entries(a).map(([k, v]) => [k, v ?? ''])))),
                get palavras() {
                    const t = (this.resumo || '').trim();
                    return t ? t.split(/\s+/).length : 0;
                },
                adicionar() {
                    if (this.autores.length >= maxAutores + 4) { alert('Limite de autores atingido.'); return; }
                    this.autores.push(novo());
                },
                remover(i) { if (this.autores.length > 1) this.autores.splice(i, 1); },
                mover(i, d) {
                    const j = i + d;
                    if (j < 0 || j >= this.autores.length) return;
                    [this.autores[i], this.autores[j]] = [this.autores[j], this.autores[i]];
                },
                definirCorrespondente(i) { this.autores.forEach((a, k) => a.correspondente = k === i); },
                mascara(v) {
                    v = (v || '').replace(/\D/g, '').slice(0, 11);
                    return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                },
                validarArquivo(e, mb) {
                    this.erroArquivo = '';
                    const f = e.target.files[0];
                    if (!f) return;
                    if (!/\.pdf$/i.test(f.name) && f.type !== 'application/pdf') this.erroArquivo = 'O arquivo deve ser um PDF.';
                    else if (f.size > mb * 1048576) this.erroArquivo = `O arquivo excede ${mb} MB.`;
                    if (this.erroArquivo) e.target.value = '';
                },
            };
        }
    </script>
</x-app-layout>
