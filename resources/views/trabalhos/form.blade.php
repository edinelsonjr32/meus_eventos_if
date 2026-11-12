@php
    $modoGestao = $modoGestao ?? false;   // gestão cadastrando/editando em nome dos autores
    $editando = $trabalho->exists;
    $action = $modoGestao
        ? ($editando ? route('gestao.trabalhos.atualizar', $trabalho) : route('gestao.trabalhos.criar', $evento))
        : ($editando ? route('trabalhos.update', $trabalho) : route('trabalhos.store', $evento->slug));
    $categorias = $config->tiposHabilitados();
    $arquivoAtual = $editando ? $trabalho->arquivoAtual : null;

    $exigeResumo = $config->exige_resumo ?? true;
    $exigePalavras = $config->exige_palavras_chave ?? true;
    $exigeCpf = $config->exige_cpf_apresentador ?? true;
    $tema = $config->chamada_tema;
    $templateUrl = $config->template_url;

    // Erros do servidor viram mensagens junto de cada campo (além do alerta no topo)
    $mapaErros = [
        'titulo' => 'titulo', 'tipo_trabalho' => 'tipo', 'resumo' => 'resumo', 'palavras_chave' => 'palavras', 'modalidade_apresentacao' => 'modalidade',
        'arquivo' => 'arquivo', 'autores' => 'autores', 'autores.0.nome_completo' => 'ap_nome', 'autores.0.cpf' => 'ap_cpf', 'autores.0.email' => 'ap_email',
        'autores.0.instituicao' => 'ap_ies', 'autores.0.categoria_autor' => 'ap_categoria', 'declaracao_ineditismo' => 'declaracoes', 'declaracao_regulamento' => 'declaracoes',
    ];
    $errosServidor = [];
    foreach ($mapaErros as $chave => $campo) {
        if ($errors->has($chave) && !isset($errosServidor[$campo])) {
            $errosServidor[$campo] = $errors->first($chave);
        }
    }
    $palavrasAtuais = old('palavras_chave', $trabalho->palavras_chave ?? []);
    $palavrasAtuais = is_array($palavrasAtuais) ? $palavrasAtuais : \App\Http\Requests\SalvarTrabalhoRequest::palavras($palavrasAtuais);

    $maisAutores = old('mais_autores', count($autores) > 1 ? 'sim' : 'nao');
    $dadosJs = [
        'autores' => $autores,
        'maxAutores' => (int) $config->max_autores,
        'resumoMin' => (int) $config->resumo_min_palavras,
        'resumoMax' => (int) $config->resumo_max_palavras,
        'resumo' => old('resumo', $trabalho->resumo),
        'exigeResumo' => (bool) $exigeResumo,
        'exigePalavras' => (bool) $exigePalavras,
        'exigeCpf' => (bool) $exigeCpf,
        'erros' => $errosServidor,
        'maisAutores' => $maisAutores,
        'tipo' => old('tipo_trabalho', $trabalho->tipo_trabalho),
        'modalidade' => old('modalidade_apresentacao', $trabalho->modalidade_apresentacao),
        'titulo' => old('titulo', $trabalho->titulo),
        'palavras' => array_values(array_filter($palavrasAtuais, 'is_string')),
        'temArquivo' => (bool) $arquivoAtual,
        'maxArquivoMb' => (int) $config->max_arquivo_mb,
        'decl1' => (bool) old('declaracao_ineditismo'),
        'decl2' => (bool) old('declaracao_regulamento'),
        'modoGestao' => $modoGestao,
    ];
@endphp
<x-layout-adaptavel titulo="Submissão de trabalhos">
    <div class="row justify-content-center">
        <div class="col-xl-9">

            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="{{ auth()->guest() ? route('evento.publico.show', $evento->slug) : ($modoGestao ? ($editando ? route('gestao.trabalhos.show', $trabalho) : route('gestao.trabalhos.index', $evento)) : ($editando ? route('trabalhos.show', $trabalho) : route('trabalhos.index'))) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h3 class="fw-bold m-0">{{ $modoGestao ? ($editando ? 'Editar dados do trabalho (gestão)' : 'Cadastrar trabalho em nome dos autores') : 'Formulário de Submissão de Trabalhos' }}</h3>
                    <small class="text-secondary">{{ $evento->titulo }}
                        @if($config->submissao_fim) · prazo até {{ $config->submissao_fim->format('d/m/Y H:i') }} @endif
                    </small>
                </div>
            </div>

            @if($modoGestao)
                <div class="alert alert-info">
                    <i class="fa-solid fa-user-gear me-1"></i>
                    @if($editando)
                        Você está editando <strong>{{ $trabalho->codigo }}</strong> como gestor. As mudanças ficam registradas no histórico, o status não muda e
                        <strong>certificados já emitidos passam a refletir as correções</strong> (o mesmo código continua válido). Para remover um autor que já tem certificado, revogue o certificado antes.
                    @else
                        Cadastre um trabalho recebido por outro meio (e-mail, papel...). Ele entra direto como <strong>Submetido</strong>, sem as declarações do autor e fora do período da chamada.
                    @endif
                </div>
            @endif

            {{-- Abertura (igual ao formulário original) --}}
            <div class="glass-card bg-white mb-4 @if($modoGestao) d-none @endif">
                @if($config->chamada_intro)
                    <div style="white-space: pre-line;">{{ $config->chamada_intro }}</div>
                @else
                    <p>Este formulário destina-se à submissão de trabalhos para o evento <strong>{{ $evento->titulo }}</strong>@if($tema), que terá como tema <em>"{{ $tema }}"</em>@endif.</p>
                    <p>Se você tem uma proposta que se alinha a esse tema, preencha o formulário com os detalhes de seu trabalho. As propostas serão analisadas pela comissão organizadora e os selecionados terão a oportunidade de compartilhar seu conhecimento e experiência durante o evento.</p>
                @endif

                <p class="mb-2">Leia com atenção as orientações de submissão{!! $templateUrl ? ' e utilize o <a href="' . e($templateUrl) . '" target="_blank" rel="noopener" class="fw-bold">template</a> para submeter o trabalho' : '' !!}.</p>

                @if($config->regras_texto)
                    <details class="mb-2">
                        <summary class="fw-bold">Orientações de submissão e regulamento</summary>
                        <div class="mt-2 small" style="white-space: pre-line;">{{ $config->regras_texto }}</div>
                    </details>
                @endif
                <div class="small text-danger">* Indica uma pergunta obrigatória</div>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
            <x-aviso-login acao="preencher e submeter seu trabalho" />
            @if($errors->any())
                <div class="alert alert-danger" id="alerta-erros" role="alert" tabindex="-1">
                    <strong><i class="fa-solid fa-circle-exclamation me-1"></i> Não foi possível salvar. Corrija os campos abaixo:</strong>
                    <div class="small mt-1">Os dados que você preencheu foram mantidos{{ $trabalho->arquivoAtual ? '' : ' (o navegador não guarda o arquivo PDF: selecione-o novamente)' }}.</div>
                    <ul class="mb-0 mt-1 small">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
                </div>
            @endif
            @if(in_array($trabalho->status, ['aguardando_correcao', 'devolvido_triagem'], true))
                <div class="alert alert-warning"><i class="fa-solid fa-pen me-1"></i> A gestão solicitou ajustes. Atualize o trabalho (e o PDF, se necessário) e reenvie.</div>
            @endif

            <form action="{{ $action }}" method="POST" enctype="multipart/form-data" novalidate x-data="formTrabalho({{ \Illuminate\Support\Js::from($dadosJs) }})">
                @csrf
                <fieldset @disabled(auth()->guest()) style="border:0;padding:0;margin:0;min-width:0">
                @if($editando) @method('PUT') @endif

                {{-- Erros encontrados no navegador (antes de enviar) --}}
                <div class="alert alert-danger d-none" id="alerta-erros-cliente" role="alert" tabindex="-1" :class="{ 'd-none': !listaErros.length }">
                    <strong><i class="fa-solid fa-circle-exclamation me-1"></i> Revise o formulário antes de enviar:</strong>
                    <ul class="mb-0 mt-1 small"><template x-for="e in listaErros" :key="e"><li x-text="e"></li></template></ul>
                </div>

                {{-- ============ Seção 1: dados do trabalho ============ --}}
                <section class="glass-card bg-white mb-4" id="sec-dados">
                    <h6 class="fw-bold text-success mb-3"><span class="badge bg-success me-2">1</span> Dados do trabalho</h6>

                    @if($modoGestao && !$editando)
                        <div class="mb-3">
                            <label class="small fw-bold text-secondary">E-mail da conta do autor responsável <span class="fw-normal">(opcional)</span></label>
                            <input type="email" name="responsavel_email" class="form-control" value="{{ old('responsavel_email') }}" placeholder="autor@exemplo.com">
                            <div class="form-text">Se a pessoa já tem conta, o trabalho aparece em "Meus Trabalhos" dela. Em branco: fica em seu nome e o apresentador recebe os e-mails.</div>
                        </div>
                    @elseif(!$modoGestao)
                        <div class="mb-3">
                            <label class="small fw-bold text-secondary">E-mail *</label>
                            <input type="email" class="form-control bg-light" value="{{ auth()->user()?->email }}" readonly tabindex="-1">
                            <div class="form-text">O e-mail da sua conta é registrado automaticamente com a submissão.</div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="small fw-bold text-secondary">Título do trabalho *</label>
                        <input type="text" name="titulo" class="form-control" maxlength="255" value="{{ old('titulo', $trabalho->titulo) }}" x-model="titulo">
                        <div class="text-danger small" x-show="erros.titulo" x-text="erros.titulo"></div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-secondary d-block">Categoria do Trabalho *</label>
                        @php
                            // A categoria já gravada continua disponível mesmo se a chamada deixou de aceitá-la (editar não força trocar)
                            $categoriasForm = $categorias;
                            $salva = $trabalho->tipo_trabalho;
                            if ($salva && !isset($categoriasForm[$salva]) && isset(\App\Models\TrabalhoConfig::TIPOS[$salva])) {
                                $categoriasForm[$salva] = \App\Models\TrabalhoConfig::TIPOS[$salva] . ' (categoria que não é mais aceita nesta chamada)';
                            }
                        @endphp
                        @foreach($categoriasForm as $valor => $rotulo)
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_trabalho" id="cat_{{ $valor }}" value="{{ $valor }}" @checked(old('tipo_trabalho', $trabalho->tipo_trabalho) === $valor) x-model="tipo">
                                <label class="form-check-label" for="cat_{{ $valor }}">{{ $rotulo }}</label>
                            </div>
                        @endforeach
                        <div class="text-danger small" x-show="erros.tipo" x-text="erros.tipo"></div>
                    </div>

                    @if($exigeResumo)
                        <div class="mb-3">
                            <label class="small fw-bold text-secondary d-flex justify-content-between">
                                <span>Resumo * <span class="fw-normal">({{ $config->resumo_min_palavras }} a {{ $config->resumo_max_palavras }} palavras)</span></span>
                                <span :class="palavrasResumo < resumoMin || palavrasResumo > resumoMax ? 'text-danger' : 'text-success'" x-text="palavrasResumo + ' palavras'"></span>
                            </label>
                            <textarea name="resumo" rows="7" class="form-control" x-model="resumo">{{ old('resumo', $trabalho->resumo) }}</textarea>
                            <div class="text-danger small" x-show="erros.resumo" x-text="erros.resumo"></div>
                        </div>
                    @endif
                    @if($exigePalavras)
                        <div class="mb-3">
                            <label class="small fw-bold text-secondary">Palavras-chave * (3 a 5)</label>
                            <div>
                            <div class="d-flex flex-wrap gap-2 mb-2" x-show="palavras.length" x-cloak>
                                <template x-for="(p, i) in palavras" :key="p">
                                    <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle d-inline-flex align-items-center gap-2 px-3 py-2 fs-6 fw-semibold">
                                        <span class="texto-quebra" x-text="p"></span>
                                        <button type="button" class="btn-close btn-close-sm" style="font-size:.6rem" @click="removerPalavra(i)" :aria-label="'Remover ' + p"></button>
                                        <input type="hidden" name="palavras_chave[]" :value="p">
                                    </span>
                                </template>
                            </div>
                            <div class="input-group">
                                <input type="text" class="form-control" x-model="novaPalavra" maxlength="60" placeholder="Digite uma palavra-chave" @keydown.enter.prevent="adicionarPalavra()" @blur="adicionarPalavra()" :disabled="palavras.length >= 5">
                                <button type="button" class="btn btn-outline-success" @click="adicionarPalavra()" :disabled="palavras.length >= 5 || !novaPalavra.trim()"><i class="fa-solid fa-plus"></i> Adicionar</button>
                            </div>
                            <div class="form-text"><span x-text="palavras.length"></span>/5 · mínimo 3. Digite uma palavra ou expressão e pressione Enter (ou toque em Adicionar).</div>
                            <div class="text-danger small" x-show="erros.palavras" x-text="erros.palavras"></div>
                        </div>
                        </div>
                    @endif

                    <details @if(!$exigeResumo || !$exigePalavras || $trabalho->abstract || $trabalho->area_tematica_id) open @endif>
                        <summary class="small fw-bold text-secondary mb-2">Informações complementares (opcional)</summary>
                        <div class="row g-3 mt-1">
                            @if($areas->isNotEmpty())
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary">Área temática</label>
                                    <select name="area_tematica_id" class="form-select">
                                        <option value="">Selecione...</option>
                                        @foreach($areas as $area)
                                            <option value="{{ $area->id }}" @selected((int) old('area_tematica_id', $trabalho->area_tematica_id) === $area->id)>{{ $area->nome }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @unless($exigeResumo)
                                <div class="col-12">
                                    <label class="small fw-bold text-secondary d-flex justify-content-between">
                                        <span>Resumo <span class="fw-normal">(até {{ $config->resumo_max_palavras }} palavras)</span></span>
                                        <span :class="palavrasResumo > resumoMax ? 'text-danger' : 'text-muted'" x-text="palavrasResumo + ' palavras'"></span>
                                    </label>
                                    <textarea name="resumo" rows="6" class="form-control" x-model="resumo">{{ old('resumo', $trabalho->resumo) }}</textarea>
                                    <div class="text-danger small" x-show="erros.resumo" x-text="erros.resumo"></div>
                                </div>
                            @endunless
                            @unless($exigePalavras)
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary">Palavras-chave (3 a 5)</label>
                                    <div>
                            <div class="d-flex flex-wrap gap-2 mb-2" x-show="palavras.length" x-cloak>
                                <template x-for="(p, i) in palavras" :key="p">
                                    <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle d-inline-flex align-items-center gap-2 px-3 py-2 fs-6 fw-semibold">
                                        <span class="texto-quebra" x-text="p"></span>
                                        <button type="button" class="btn-close btn-close-sm" style="font-size:.6rem" @click="removerPalavra(i)" :aria-label="'Remover ' + p"></button>
                                        <input type="hidden" name="palavras_chave[]" :value="p">
                                    </span>
                                </template>
                            </div>
                            <div class="input-group">
                                <input type="text" class="form-control" x-model="novaPalavra" maxlength="60" placeholder="Digite uma palavra-chave" @keydown.enter.prevent="adicionarPalavra()" @blur="adicionarPalavra()" :disabled="palavras.length >= 5">
                                <button type="button" class="btn btn-outline-success" @click="adicionarPalavra()" :disabled="palavras.length >= 5 || !novaPalavra.trim()"><i class="fa-solid fa-plus"></i> Adicionar</button>
                            </div>
                            <div class="form-text"><span x-text="palavras.length"></span>/5 · mínimo 3. Digite uma palavra ou expressão e pressione Enter (ou toque em Adicionar).</div>
                            <div class="text-danger small" x-show="erros.palavras" x-text="erros.palavras"></div>
                        </div>
                                </div>
                            @endunless
                            <div class="col-12">
                                <label class="small fw-bold text-secondary">Abstract</label>
                                <textarea name="abstract" rows="4" class="form-control">{{ old('abstract', $trabalho->abstract) }}</textarea>
                            </div>
                        </div>
                    </details>
                </section>

                {{-- ============ Seção 2: autor apresentador ============ --}}
                <section class="glass-card bg-white mb-4" id="sec-apresentador">
                    <h6 class="fw-bold text-success mb-1"><span class="badge bg-success me-2">2</span> Cadastro de autores</h6>
                    <p class="small text-muted">Insira o nome completo e a Instituição de Ensino ao qual o apresentador(a) esteja vinculado(a).</p>

                    <input type="hidden" name="autores[0][id]" value="{{ $autores[0]['id'] ?? '' }}">
                    <input type="hidden" name="autores[0][tipo_autor]" value="autor_principal">
                    {{-- Trabalho novo: o primeiro autor é apresentador e correspondente. Trabalho já salvo: mantém o que está gravado
                         (a gestão pode ter definido outro apresentador/correspondente; editar não pode desfazer isso em silêncio). --}}
                    <input type="hidden" name="autores[0][apresentador]" value="{{ $trabalho->exists ? (int) ($autores[0]['apresentador'] ?? 0) : 1 }}">
                    <input type="hidden" name="autores[0][correspondente]" value="{{ $trabalho->exists ? (int) ($autores[0]['correspondente'] ?? 0) : 1 }}">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="small fw-bold text-secondary">Nome completo do autor apresentador *</label>
                            <input type="text" name="autores[0][nome_completo]" class="form-control" value="{{ $autores[0]['nome_completo'] ?? '' }}" x-model="apresentador.nome_completo">
                            <div class="text-danger small" x-show="erros.ap_nome" x-text="erros.ap_nome"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold text-secondary">CPF @if($exigeCpf) * @endif</label>
                            <input type="text" name="autores[0][cpf]" class="form-control" placeholder="000.000.000-00" maxlength="14" value="{{ $autores[0]['cpf'] ?? '' }}" x-model="apresentador.cpf" @input="apresentador.cpf = mascara(apresentador.cpf)">
                            <div class="form-text">Usado para emitir o seu certificado.</div>
                            <div class="text-danger small" x-show="erros.ap_cpf" x-text="erros.ap_cpf"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">E-mail *</label>
                            <input type="email" name="autores[0][email]" class="form-control" value="{{ $autores[0]['email'] ?? '' }}" x-model="apresentador.email">
                            <div class="text-danger small" x-show="erros.ap_email" x-text="erros.ap_email"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Instituição de Ensino - IES de vínculo *</label>
                            <input type="text" name="autores[0][instituicao]" class="form-control" value="{{ $autores[0]['instituicao'] ?? '' }}" x-model="apresentador.instituicao">
                            <div class="text-danger small" x-show="erros.ap_ies" x-text="erros.ap_ies"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary d-block">Categoria *</label>
                            @foreach(\App\Models\Trabalho::CATEGORIAS_AUTOR as $valor => $rotulo)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="autores[0][categoria_autor]" id="apcat_{{ $valor }}" value="{{ $valor }}" @checked(($autores[0]['categoria_autor'] ?? null) === $valor) x-model="apresentador.categoria_autor">
                                    <label class="form-check-label" for="apcat_{{ $valor }}">{{ $rotulo }}</label>
                                </div>
                            @endforeach
                            <div class="text-danger small" x-show="erros.ap_categoria" x-text="erros.ap_categoria"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary d-block">Cadastrar mais autores? *</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mais_autores" id="mais_sim" value="sim" @checked($maisAutores === 'sim') x-model="maisAutores" @change="aoEscolherMaisAutores()">
                                <label class="form-check-label" for="mais_sim">Sim</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mais_autores" id="mais_nao" value="nao" @checked($maisAutores === 'nao') x-model="maisAutores">
                                <label class="form-check-label" for="mais_nao">Não</label>
                            </div>
                        </div>
                    </div>
                    <div class="text-danger small mt-2" x-show="erros.autores" x-text="erros.autores"></div>
                </section>

                {{-- ============ Seção 3: demais autores ============ --}}
                <section x-show="maisAutores === 'sim'" x-cloak class="glass-card bg-white mb-4" id="sec-autores">
                    <h6 class="fw-bold text-success mb-1"><span class="badge bg-success me-2">3</span> Cadastro de autores (demais)</h6>
                    <p class="small text-muted">Insira o nome completo e a Instituição de Ensino ao qual cada autor(a) esteja vinculado(a) (João da Silva - Instituto Federal do Pará - Campus Óbidos).</p>

                    <template x-if="maisAutores === 'sim'">
                        <div>
                            <template x-for="(a, i) in extras" :key="a.uid">
                                <div class="border rounded-3 p-3 mb-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong x-text="'Autor ' + (i + 2)"></strong>
                                        <button type="button" class="btn btn-sm btn-light text-danger" @click="removerExtra(i)" title="Remover"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                    <input type="hidden" :name="`autores[${i + 1}][id]`" :value="a.id || ''">
                                    <input type="hidden" :name="`autores[${i + 1}][apresentador]`" :value="a.apresentador ? 1 : 0">
                                    <input type="hidden" :name="`autores[${i + 1}][correspondente]`" :value="a.correspondente ? 1 : 0">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="small fw-bold text-secondary" x-text="'Nome completo do autor ' + (i + 2) + ' *'"></label>
                                            <input type="text" class="form-control form-control-sm" :name="`autores[${i + 1}][nome_completo]`" x-model="a.nome_completo">
                                            <div class="text-danger small" x-show="a.erroNome" x-text="a.erroNome"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-bold text-secondary">IES (Instituição de Ensino) *</label>
                                            <input type="text" class="form-control form-control-sm" :name="`autores[${i + 1}][instituicao]`" x-model="a.instituicao">
                                            <div class="text-danger small" x-show="a.erroIes" x-text="a.erroIes"></div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-secondary">Função</label>
                                            <select class="form-select form-select-sm" :name="`autores[${i + 1}][tipo_autor]`" x-model="a.tipo_autor">
                                                <option value="coautor">Coautor</option>
                                                <option value="orientador">Orientador</option>
                                                <option value="coorientador">Coorientador</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-secondary">Categoria</label>
                                            <select class="form-select form-select-sm" :name="`autores[${i + 1}][categoria_autor]`" x-model="a.categoria_autor">
                                                <option value="">—</option>
                                                @foreach(\App\Models\Trabalho::CATEGORIAS_AUTOR as $valor => $rotulo) <option value="{{ $valor }}">{{ $rotulo }}</option> @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-secondary">E-mail <span class="fw-normal">(opcional)</span></label>
                                            <input type="email" class="form-control form-control-sm" :name="`autores[${i + 1}][email]`" x-model="a.email">
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <button type="button" class="btn btn-sm btn-outline-success fw-bold" @click="adicionarExtra()" x-show="extras.length + 1 < maxAutores + 2">
                                <i class="fa-solid fa-plus me-1"></i> Adicionar autor
                            </button>
                            <div class="form-text">Até {{ $config->max_autores }} autores (orientadores e coorientadores não contam no limite).</div>
                        </div>
                    </template>
                </section>

                {{-- ============ Seção 4: envio de arquivo ============ --}}
                <section class="glass-card bg-white mb-4" id="sec-arquivo">
                    <h6 class="fw-bold text-success mb-3"><span class="badge bg-success me-2" x-text="maisAutores === 'sim' ? 4 : 3"></span> Envio de arquivo</h6>

                    <div class="mb-3">
                        <label class="small fw-bold text-secondary d-block">Modalidade de Apresentação *</label>
                        @foreach(\App\Models\Trabalho::MODALIDADES_DESCRICAO as $valor => $rotulo)
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modalidade_apresentacao" id="mod_{{ $valor }}" value="{{ $valor }}" @checked(old('modalidade_apresentacao', $trabalho->modalidade_apresentacao) === $valor) x-model="modalidade">
                                <label class="form-check-label" for="mod_{{ $valor }}">{{ $rotulo }}</label>
                            </div>
                        @endforeach
                        <div class="text-danger small" x-show="erros.modalidade" x-text="erros.modalidade"></div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-secondary">Envio de arquivo (Formato PDF) {{ $modoGestao ? '(opcional: envie para cadastrar ou substituir o PDF)' : '*' }}</label>
                        @if($arquivoAtual)
                            <div class="alert alert-light border small py-2">
                                Arquivo atual: <strong>{{ $arquivoAtual->nome_original }}</strong>
                                ({{ number_format($arquivoAtual->tamanho / 1048576, 2, ',', '.') }} MB, versão {{ $arquivoAtual->versao }}) —
                                <a href="{{ route('trabalhos.arquivo', $trabalho) }}" target="_blank">abrir</a>.
                                Envie um novo arquivo somente se quiser substituí-lo.
                            </div>
                        @endif
                        <input type="file" name="arquivo" accept="application/pdf,.pdf" class="form-control" @change="validarArquivo($event)">
                        <div class="form-text">
                            Somente PDF, até {{ $config->max_arquivo_mb }} MB.
                            @if($templateUrl) Use o <a href="{{ $templateUrl }}" target="_blank" rel="noopener">template</a> do evento. @endif
                            @if($config->avaliacao_cega) <strong>Avaliação cega:</strong> envie a versão <u>sem identificação dos autores</u>. @endif
                        </div>
                        <div class="text-danger small" x-show="erros.arquivo" x-text="erros.arquivo"></div>
                    </div>

                    <hr>
                    @unless($modoGestao)
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="declaracao_ineditismo" value="1" id="d1" x-model="decl1" @checked(old('declaracao_ineditismo'))>
                        <label class="form-check-label small" for="d1">Declaro que o trabalho é inédito e que todos os autores concordam com a submissão. *</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="declaracao_regulamento" value="1" id="d2" x-model="decl2" @checked(old('declaracao_regulamento'))>
                        <label class="form-check-label small" for="d2">Li e aceito o regulamento da chamada de trabalhos. *</label>
                    </div>
                    @endunless
                    <div class="form-check">
                        <input type="hidden" name="autoriza_publicacao" value="0">
                        <input class="form-check-input" type="checkbox" name="autoriza_publicacao" value="1" id="d3" @checked(old('autoriza_publicacao', $trabalho->exists ? $trabalho->autoriza_publicacao : !$modoGestao))>
                        <label class="form-check-label small" for="d3">Autorizo a publicação do trabalho nos Trabalhos Publicados, <strong>caso seja aprovado</strong> (e apresentado). Já vem marcado: desmarque se não quiser publicar. Você pode mudar depois na página do trabalho.</label>
                    </div>
                    <div class="text-danger small mt-2" x-show="erros.declaracoes" x-text="erros.declaracoes"></div>
                    <div class="small text-muted mt-2"><i class="fa-solid fa-shield-halved me-1"></i> Seus dados pessoais são tratados conforme a <a href="{{ route('privacidade') }}" target="_blank">Política de Privacidade</a>. CPF e e-mail dos autores nunca são publicados.</div>
                </section>

                </fieldset>

                {{-- Envio --}}
                @guest
                    <div class="d-flex justify-content-end mb-5"><a href="{{ route('login') }}" class="btn btn-primary-custom fw-bold"><i class="fa-solid fa-right-to-bracket me-1"></i> Entrar para submeter</a></div>
                @else
                <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between mb-5">
                    <a href="{{ $modoGestao ? ($editando ? route('gestao.trabalhos.show', $trabalho) : route('gestao.trabalhos.index', $evento)) : ($editando ? route('trabalhos.show', $trabalho) : route('trabalhos.index')) }}" class="btn btn-light fw-bold"><i class="fa-solid fa-arrow-left me-1"></i> Cancelar</a>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        @unless($modoGestao)
                        <button type="submit" name="acao" value="rascunho" class="btn btn-outline-secondary fw-bold" @click="rascunho($event)">
                            <i class="fa-regular fa-floppy-disk me-1"></i> Salvar rascunho
                        </button>
                        @endunless
                        <button type="submit" name="acao" value="submeter" class="btn btn-primary-custom fw-bold" @click="enviar($event)">
                            <i class="fa-solid fa-paper-plane me-1"></i> {{ $modoGestao ? ($editando ? 'Salvar alterações' : 'Cadastrar trabalho') : ($trabalho->status === 'aguardando_correcao' ? 'Reenviar versão corrigida' : 'Enviar') }}
                        </button>
                    </div>
                </div>
                @endguest
            </form>
        </div>
    </div>

    <script>
        function formTrabalho(cfg) {
            let seq = 0;
            const vazio = () => ({ uid: ++seq, id: null, nome_completo: '', cpf: '', email: '', instituicao: '', categoria_autor: '', tipo_autor: 'coautor', apresentador: false, correspondente: false, erroNome: '', erroIes: '' });
            const normaliza = (d = {}) => {
                const o = vazio();
                ['id', 'nome_completo', 'cpf', 'email', 'instituicao', 'categoria_autor', 'tipo_autor'].forEach(k => { if (d[k]) o[k] = d[k]; });
                o.apresentador = !!d.apresentador; o.correspondente = !!d.correspondente;
                return o;
            };

            const lista = (cfg.autores && cfg.autores.length) ? cfg.autores : [{}];
            const emailOk = v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
            // Onde rolar para cada campo com problema
            const ancora = { titulo: 'input[name=titulo]', tipo: '[name=tipo_trabalho]', resumo: 'textarea[name=resumo]', palavras: '[x-model=novaPalavra]', ap_nome: '[name="autores[0][nome_completo]"]', ap_email: '[name="autores[0][email]"]', ap_ies: '[name="autores[0][instituicao]"]', ap_categoria: '[name="autores[0][categoria_autor]"]', ap_cpf: '[name="autores[0][cpf]"]', autores: '#sec-apresentador', extras: '#sec-autores', modalidade: '[name=modalidade_apresentacao]', arquivo: 'input[name=arquivo]', declaracoes: '#d1' };

            return {
                maisAutores: cfg.maisAutores, titulo: cfg.titulo || '', tipo: cfg.tipo || '', modalidade: cfg.modalidade || '',
                resumo: cfg.resumo || '', palavras: cfg.palavras || [], novaPalavra: '', resumoMin: cfg.resumoMin, resumoMax: cfg.resumoMax,
                listaErros: [], maxAutores: cfg.maxAutores, decl1: !!cfg.decl1, decl2: !!cfg.decl2, erros: cfg.erros || {},
                apresentador: normaliza(lista[0]),
                extras: lista.slice(1).map(normaliza),

                init() {
                    // Erro vindo do servidor: leva a pessoa direto ao alerta (os dados continuam preenchidos)
                    const alerta = document.getElementById('alerta-erros');
                    if (alerta) this.$nextTick(() => { alerta.scrollIntoView({ behavior: 'smooth', block: 'center' }); alerta.focus({ preventScroll: true }); });
                },

                get palavrasResumo() { const t = (this.resumo || '').trim(); return t ? t.split(/\s+/).length : 0; },

                mascara(v) {
                    v = (v || '').replace(/\D/g, '').slice(0, 11);
                    return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                },

                aoEscolherMaisAutores() { if (this.extras.length === 0) this.adicionarExtra(); },
                adicionarExtra() { if (this.extras.length + 1 < this.maxAutores + 2) this.extras.push(vazio()); },
                removerExtra(i) { this.extras.splice(i, 1); },

                // ---- palavras-chave: uma de cada vez (a vírgula também separa, mas não é obrigatória) ----
                adicionarPalavra() {
                    const novas = this.novaPalavra.split(/[,;]+/).map(p => p.trim()).filter(Boolean);
                    novas.forEach(p => {
                        if (this.palavras.length >= 5) return;
                        if (!this.palavras.some(x => x.toLowerCase() === p.toLowerCase())) this.palavras.push(p.slice(0, 60));
                    });
                    this.novaPalavra = '';
                },
                removerPalavra(i) { this.palavras.splice(i, 1); },

                // ---- validação no navegador (o servidor continua sendo a validação definitiva) ----
                validarTudo() {
                    this.adicionarPalavra();
                    const e = {};
                    if (!this.titulo.trim()) e.titulo = 'Informe o título do trabalho.';
                    if (!this.tipo) e.tipo = 'Selecione a categoria do trabalho.';
                    if (cfg.exigeResumo && (this.palavrasResumo < this.resumoMin || this.palavrasResumo > this.resumoMax)) e.resumo = `O resumo deve ter entre ${this.resumoMin} e ${this.resumoMax} palavras.`;
                    if (!cfg.exigeResumo && this.palavrasResumo > this.resumoMax) e.resumo = `O resumo deve ter no máximo ${this.resumoMax} palavras.`;
                    const qtd = this.palavras.length;
                    if ((cfg.exigePalavras || qtd > 0) && (qtd < 3 || qtd > 5)) e.palavras = 'Informe de 3 a 5 palavras-chave.';

                    const a = this.apresentador;
                    if (!a.nome_completo.trim()) e.ap_nome = 'Informe o nome completo do apresentador.';
                    if (!emailOk(a.email)) e.ap_email = 'Informe um e-mail válido para o apresentador.';
                    if (!a.instituicao.trim()) e.ap_ies = 'Informe a instituição de ensino do apresentador.';
                    if (!a.categoria_autor) e.ap_categoria = 'Informe se o apresentador é docente ou discente.';
                    if (cfg.exigeCpf && a.cpf.length !== 14) e.ap_cpf = 'Informe o CPF completo do apresentador.';
                    if (!this.maisAutores) e.autores = 'Informe se deseja cadastrar mais autores.';

                    if (this.maisAutores === 'sim') {
                        this.extras.forEach((x, i) => {
                            x.erroNome = x.nome_completo.trim() ? '' : 'Informe o nome completo.';
                            x.erroIes = x.instituicao.trim() ? '' : 'Informe a instituição.';
                            if ((x.erroNome || x.erroIes) && !e.extras) e.extras = `Complete os dados do autor ${i + 2} (nome e instituição).`;
                        });
                    }

                    if (!this.modalidade) e.modalidade = 'Selecione a modalidade de apresentação.';
                    const arq = this.$root.querySelector('input[name=arquivo]');
                    if (!cfg.modoGestao) {
                        if (!cfg.temArquivo && !(arq && arq.files.length)) e.arquivo = 'Envie o arquivo PDF do trabalho.';
                        if (!this.decl1 || !this.decl2) e.declaracoes = 'É necessário aceitar as duas declarações obrigatórias.';
                    }
                    this.erros = e;
                    this.listaErros = Object.values(e);
                    return e;
                },
                irParaPrimeiroErro(e) {
                    const chave = Object.keys(ancora).find(k => e[k]);
                    this.$nextTick(() => {
                        const alvo = document.getElementById('alerta-erros-cliente');
                        if (alvo) alvo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        const campo = chave ? this.$root.querySelector(ancora[chave]) : null;
                        if (campo && campo.focus) setTimeout(() => campo.focus({ preventScroll: true }), 350);
                    });
                },

                // Rascunho exige apenas o título
                rascunho(ev) {
                    this.listaErros = [];
                    if (!this.titulo.trim()) {
                        ev.preventDefault();
                        this.erros = { titulo: 'Informe ao menos o título para salvar o rascunho.' };
                        this.listaErros = [this.erros.titulo];
                        this.irParaPrimeiroErro(this.erros);
                    } else { this.adicionarPalavra(); }
                },
                // Envio: valida tudo de uma vez, mostra a lista de problemas e leva ao primeiro
                enviar(ev) {
                    const e = this.validarTudo();
                    if (Object.keys(e).length) { ev.preventDefault(); this.irParaPrimeiroErro(e); return; }
                    const aviso = cfg.modoGestao ? 'Salvar as alterações deste trabalho?' : 'Após enviar, o trabalho será encaminhado à gestão do evento. Deseja continuar?';
                    if (!confirm(aviso)) ev.preventDefault();
                },
                validarArquivo(ev) {
                    this.erros = { ...this.erros, arquivo: '' };
                    const f = ev.target.files[0];
                    if (!f) return;
                    let msg = '';
                    if (!/\.pdf$/i.test(f.name) && f.type !== 'application/pdf') msg = 'O arquivo deve ser um PDF.';
                    else if (f.size > cfg.maxArquivoMb * 1048576) msg = `O arquivo excede ${cfg.maxArquivoMb} MB.`;
                    if (msg) { ev.target.value = ''; this.erros = { ...this.erros, arquivo: msg }; }
                },
            };
        }
    </script>
</x-layout-adaptavel>
