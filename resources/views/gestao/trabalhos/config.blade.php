<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-9">

            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h3 class="fw-bold m-0">{{ $config->exists ? 'Editar chamada de trabalhos' : 'Criar chamada de trabalhos' }}</h3>
                    <small class="text-secondary">{{ $evento->titulo }}</small>
                </div>
            </div>

            @php $sit = \App\Models\TrabalhoConfig::situacaoDe($config->exists ? $config : null); @endphp
            <div class="glass-card bg-white mb-4">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <strong>Situação:</strong>
                    <x-badge tipo="chamada" :valor="$sit['chave']" :rotulo="$sit['rotulo']" />
                </div>
                <p class="small text-secondary mb-2">{{ $sit['descricao'] }}</p>
                <ol class="small text-secondary mb-0 ps-3">
                    <li><strong>Configure</strong> o período, as categorias, as regras e os textos abaixo.</li>
                    <li><strong>Publique</strong> a chamada: o botão <em>Submeter Trabalho</em> aparece na página do evento e em "Meus Trabalhos" dos participantes, dentro do período definido.</li>
                    <li>Acompanhe as submissões em <em>Trabalhos</em>, faça a triagem, distribua aos avaliadores e publique os Trabalhos Publicados.</li>
                </ol>
                @if($config->exists && $config->ativo)
                    <div class="small mt-2">Página pública do evento: <a href="{{ route('evento.publico.show', $evento->slug) }}" target="_blank">{{ route('evento.publico.show', $evento->slug) }}</a><br>
                        Link direto do formulário: <a href="{{ route('trabalhos.create', $evento->slug) }}" target="_blank">{{ route('trabalhos.create', $evento->slug) }}</a></div>
                @endif
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 small">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>
            @endif

            <form action="{{ route('gestao.trabalhos.config.update', $evento) }}" method="POST" data-validar data-regras="regrasChamada">
                @csrf @method('PUT')

                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3">Período</h6>
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="ativo" value="0">
                        <input class="form-check-input" type="checkbox" name="ativo" value="1" id="ativo" @checked(old('ativo', $config->ativo))>
                        <label class="form-check-label fw-bold" for="ativo">Chamada publicada (aceita submissões dentro do período abaixo)</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Início da submissão</label>
                            <input type="datetime-local" name="submissao_inicio" data-nome="Início da submissão" class="form-control" value="{{ old('submissao_inicio', $config->submissao_inicio?->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Fim da submissão</label>
                            <input type="datetime-local" name="submissao_fim" data-nome="Fim da submissão" class="form-control" value="{{ old('submissao_fim', $config->submissao_fim?->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Prazo padrão das avaliações</label>
                            <input type="datetime-local" name="avaliacao_fim" class="form-control" value="{{ old('avaliacao_fim', $config->avaliacao_fim?->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary">Avaliadores por trabalho</label>
                            <input type="number" min="1" max="10" name="avaliadores_por_trabalho" data-mascara="inteiro" class="form-control" value="{{ old('avaliadores_por_trabalho', $config->avaliadores_por_trabalho ?? 2) }}" required>
                        </div>
                    </div>
                </div>

                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3">Abertura do formulário de submissão</h6>
                    <div class="mb-3">
                        <label class="small fw-bold text-secondary">Tema do evento</label>
                        <input type="text" name="chamada_tema" class="form-control" maxlength="255" value="{{ old('chamada_tema', $config->chamada_tema) }}" placeholder="Ex.: Planeta Amazônia: cultura das águas para enfrentar as mudanças climáticas">
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-secondary">Texto de abertura</label>
                        <textarea name="chamada_intro" rows="5" class="form-control" placeholder="Vazio usa o texto padrão: 'Este formulário destina-se à submissão de trabalhos para o evento ...'">{{ old('chamada_intro', $config->chamada_intro) }}</textarea>
                    </div>
                    <div>
                        <label class="small fw-bold text-secondary">Link do template do trabalho</label>
                        <input type="url" name="template_url" class="form-control" maxlength="500" value="{{ old('template_url', $config->template_url) }}" placeholder="https://docs.google.com/document/d/...">
                        <div class="form-text">Aparece na abertura do formulário e junto ao envio do PDF.</div>
                    </div>
                </div>

                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3">Regras do formulário</h6>
                    @php $novo = !$config->exists; @endphp
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="exige_resumo" value="0">
                            <input class="form-check-input" type="checkbox" name="exige_resumo" value="1" id="exResumo" @checked(old('exige_resumo', $novo ? false : $config->exige_resumo))>
                            <label class="form-check-label" for="exResumo">Exigir resumo (o formulário original não pedia)</label>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="exige_palavras_chave" value="0">
                            <input class="form-check-input" type="checkbox" name="exige_palavras_chave" value="1" id="exPal" @checked(old('exige_palavras_chave', $novo ? false : $config->exige_palavras_chave))>
                            <label class="form-check-label" for="exPal">Exigir palavras-chave (3 a 5)</label>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="exige_cpf_apresentador" value="0">
                            <input class="form-check-input" type="checkbox" name="exige_cpf_apresentador" value="1" id="exCpf" @checked(old('exige_cpf_apresentador', $novo ? true : $config->exige_cpf_apresentador))>
                            <label class="form-check-label" for="exCpf">Exigir CPF do autor apresentador (usado no certificado)</label>
                        </div>
                        <div class="form-text">Os demais autores informam apenas nome completo e instituição de ensino.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="small fw-bold text-secondary">Máx. de autores</label>
                            <input type="number" min="1" max="30" name="max_autores" data-mascara="inteiro" class="form-control" value="{{ old('max_autores', $config->max_autores ?? 10) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold text-secondary">Tamanho máx. do PDF (MB)</label>
                            <input type="number" min="1" max="100" name="max_arquivo_mb" data-mascara="inteiro" class="form-control" value="{{ old('max_arquivo_mb', $config->max_arquivo_mb ?? 20) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold text-secondary">Resumo: mín. palavras</label>
                            <input type="number" min="0" name="resumo_min_palavras" data-mascara="inteiro" class="form-control" value="{{ old('resumo_min_palavras', $config->resumo_min_palavras ?? 100) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold text-secondary">Resumo: máx. palavras</label>
                            <input type="number" min="1" name="resumo_max_palavras" data-mascara="inteiro" class="form-control" value="{{ old('resumo_max_palavras', $config->resumo_max_palavras ?? 300) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold text-secondary">Trabalhos por usuário (vazio = sem limite)</label>
                            <input type="number" min="1" name="max_trabalhos_por_usuario" data-mascara="inteiro" class="form-control" value="{{ old('max_trabalhos_por_usuario', $config->max_trabalhos_por_usuario) }}">
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
                        <label class="small fw-bold text-secondary d-block">Categorias de trabalho aceitas</label>
                        @php($marcados = old('tipos_permitidos', $config->tipos_permitidos ?: array_keys(\App\Models\TrabalhoConfig::TIPOS)))
                        @foreach(\App\Models\TrabalhoConfig::TIPOS as $valor => $rotulo)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="tipos_permitidos[]" value="{{ $valor }}" id="t{{ $valor }}" @checked(in_array($valor, $marcados))>
                                <label class="form-check-label" for="t{{ $valor }}">{{ $rotulo }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="glass-card bg-white mb-4" x-data="criteriosForm({{ \Illuminate\Support\Js::from(old('criterios', $criterios->map(fn ($c) => ['id' => $c->id, 'nome' => $c->nome, 'descricao' => $c->descricao, 'peso' => (float) $c->peso, 'nota_max' => (float) $c->nota_max, 'usado' => in_array($c->id, $criteriosUsados, true)])->values())) }}, {{ \Illuminate\Support\Js::from(collect(\App\Models\CriterioAvaliacao::PADROES)->map(fn ($p) => ['id' => null, 'nome' => $p[0], 'descricao' => $p[1], 'peso' => 1, 'nota_max' => 10])->values()) }})">
                    <h6 class="fw-bold text-success mb-3">Avaliação</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="small fw-bold text-secondary">Nota mínima p/ aprovação (0–10)</label>
                            <input type="number" step="0.1" min="0" max="10" name="nota_minima_aprovacao" class="form-control" value="{{ old('nota_minima_aprovacao', $config->nota_minima_aprovacao ?? 7) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold text-secondary">Divergência máxima entre avaliadores</label>
                            <input type="number" step="0.1" min="0" max="10" name="divergencia_maxima" class="form-control" value="{{ old('divergencia_maxima', $config->divergencia_maxima ?? 3) }}" required>
                        </div>
                        <div class="col-md-6 d-flex flex-column justify-content-end">
                            <div class="form-check form-switch">
                                <input type="hidden" name="autor_ve_notas" value="0">
                                <input class="form-check-input" type="checkbox" name="autor_ve_notas" value="1" id="vnotas" @checked(old('autor_ve_notas', $config->autor_ve_notas ?? true))>
                                <label class="form-check-label" for="vnotas">Autor vê a nota final</label>
                            </div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="autor_ve_pareceres" value="0">
                                <input class="form-check-input" type="checkbox" name="autor_ve_pareceres" value="1" id="vpar" @checked(old('autor_ve_pareceres', $config->autor_ve_pareceres ?? true))>
                                <label class="form-check-label" for="vpar">Autor vê os pareceres (sempre anônimos)</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="small fw-bold text-secondary m-0">Critérios de avaliação (nota ponderada de 0 a 10)</label>
                        <div class="d-flex gap-2" >
                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="padroes()">Carregar critérios padrão</button>
                            <button type="button" class="btn btn-sm btn-outline-success" @click="adicionar()"><i class="fa-solid fa-plus"></i> Critério</button>
                        </div>
                    </div>
                    <div class="alert alert-info small py-2" x-show="linhas.length === 0" x-cloak>Defina ao menos um critério: sem eles não é possível encaminhar trabalhos para avaliação.</div>

                    <template x-for="(c, i) in linhas" :key="i">
                    <div class="row g-2 align-items-start mb-2">
                        <input type="hidden" :name="`criterios[${i}][id]`" :value="c.id ?? ''">
                        <div class="col-md-4"><input type="text" class="form-control form-control-sm" :class="c.usado ? 'bg-light' : ''" placeholder="Nome" data-nome="Nome do critério" :name="`criterios[${i}][nome]`" x-model="c.nome" :readonly="c.usado" required></div>
                        <div class="col-md-4"><input type="text" class="form-control form-control-sm" placeholder="Descrição (opcional)" :name="`criterios[${i}][descricao]`" x-model="c.descricao"></div>
                        <div class="col-md-2"><input type="number" step="0.1" min="0.1" class="form-control form-control-sm" :class="c.usado ? 'bg-light' : ''" placeholder="Peso" data-nome="Peso do critério" :name="`criterios[${i}][peso]`" x-model="c.peso" :readonly="c.usado" required></div>
                        <div class="col-md-1"><input type="number" step="0.1" min="0.1" class="form-control form-control-sm" :class="c.usado ? 'bg-light' : ''" placeholder="Máx" data-nome="Nota máxima" :name="`criterios[${i}][nota_max]`" x-model="c.nota_max" :readonly="c.usado" required></div>
                        <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-light text-danger" @click="remover(i)" title="Remover critério"><i class="fa-solid fa-trash"></i></button></div>
                        <div class="col-12 small text-muted" x-show="c.usado" x-cloak style="margin-top:-2px"><i class="fa-solid fa-lock me-1"></i>Já tem notas lançadas: nome, peso e máximo ficam fixos. Ao remover, ele deixa de ser usado nas próximas avaliações e as notas atuais são mantidas.</div>
                    </div>
                    </template>
                    <div class="form-text">Colunas: nome, descrição, peso e nota máxima. A nota do avaliador é convertida para a escala 0–10 considerando os pesos.</div>
                </div>

                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3">Certificados dos trabalhos</h6>
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="exige_apresentacao_para_certificado" value="0">
                        <input class="form-check-input" type="checkbox" name="exige_apresentacao_para_certificado" value="1" id="exigeApr" @checked(old('exige_apresentacao_para_certificado', $config->exige_apresentacao_para_certificado ?? true))>
                        <label class="form-check-label" for="exigeApr">Emitir certificado somente para trabalhos <strong>aprovados e apresentados</strong></label>
                    </div>
                    <label class="small fw-bold text-secondary">Cabeçalho</label>
                    <textarea name="cert_trab_cabecalho" rows="2" class="form-control mb-3" placeholder="{{ \App\Models\TrabalhoConfig::CERT_CABECALHO_PADRAO }}">{{ old('cert_trab_cabecalho', $config->cert_trab_cabecalho) }}</textarea>
                    <label class="small fw-bold text-secondary">Texto do certificado</label>
                    <textarea name="cert_trab_corpo" rows="5" class="form-control mb-1" placeholder="{{ \App\Models\TrabalhoConfig::CERT_CORPO_PADRAO }}">{{ old('cert_trab_corpo', $config->cert_trab_corpo) }}</textarea>
                    <div class="form-text mb-3">Variáveis: <code>{{ implode('  ', \App\Models\TrabalhoConfig::CERT_VARIAVEIS) }}</code>. Formatação permitida: &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;br&gt;. Vazio usa o texto padrão.</div>
                    <label class="small fw-bold text-secondary">Rodapé</label>
                    <textarea name="cert_trab_rodape" rows="2" class="form-control">{{ old('cert_trab_rodape', $config->cert_trab_rodape) }}</textarea>
                    <div class="form-text">Fundo, brasão e assinaturas são os mesmos configurados no certificado do evento.</div>
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

                <div class="d-flex flex-wrap gap-2 justify-content-end mb-5">
                    <button name="acao" value="salvar" class="btn btn-outline-secondary fw-bold px-4">
                        <i class="fa-regular fa-floppy-disk me-1"></i> {{ $config->exists ? 'Salvar' : 'Salvar como rascunho' }}
                    </button>
                    @unless($config->exists && $config->ativo)
                        <button name="acao" value="publicar" class="btn btn-primary-custom fw-bold px-4" data-confirmar="Publicar a chamada? Os participantes poderão submeter trabalhos dentro do período definido.">
                            <i class="fa-solid fa-bullhorn me-1"></i> Salvar e publicar chamada
                        </button>
                    @endunless
                </div>
            </form>
        </div>
    </div>

    <script>
        // Regras que cruzam campos (a validação básica vem do componente de feedback)
        function regrasChamada(form, erro) {
            const f = (n) => form.querySelector('[name="' + n + '"]');
            const ini = f('submissao_inicio'), fim = f('submissao_fim');
            if (ini.value && fim.value && fim.value < ini.value) erro(fim, 'O fim da submissão deve ser posterior ao início.');
            if (form.querySelector('[name="ativo"][type=checkbox]:checked') || form.dataset.acao === 'publicar') {
                if (!ini.value || !fim.value) erro(!ini.value ? ini : fim, 'Para publicar, defina o período de submissão (início e fim).');
            }
            const min = f('resumo_min_palavras'), max = f('resumo_max_palavras');
            if (min.value !== '' && max.value !== '' && Number(max.value) < Number(min.value)) erro(max, 'O máximo de palavras deve ser maior ou igual ao mínimo.');
            const tipos = form.querySelectorAll('[name="tipos_permitidos[]"]');
            if (tipos.length && !form.querySelector('[name="tipos_permitidos[]"]:checked')) erro(tipos[0], 'Selecione ao menos uma categoria de trabalho.');
            const link = f('template_url');
            if (link.value && !/^https?:\/\//i.test(link.value)) erro(link, 'O link do template deve começar com https://');
            const linhas = form.querySelectorAll('[name^="criterios["][name$="[nome]"]:not(:disabled)');
            const nomes = new Set();
            linhas.forEach((c) => { const n = c.value.trim().toLowerCase(); if (n && nomes.has(n)) erro(c, 'Critério repetido: "' + c.value.trim() + '".'); nomes.add(n); });
        }

        function criteriosForm(iniciais, padroes) {
            return {
                linhas: iniciais.map(c => ({ ...c })),
                adicionar() { this.linhas.push({ id: null, nome: '', descricao: '', peso: 1, nota_max: 10, usado: false }); },
                remover(i) {
                    const c = this.linhas[i];
                    const aviso = c.usado ? 'Este critério já tem notas lançadas. Remover o retira das próximas avaliações; as notas existentes continuam valendo. Remover?' : 'Remover este critério?';
                    if (!confirm(aviso)) return;
                    this.linhas.splice(i, 1);
                },
                padroes() {
                    if (this.linhas.length && !confirm('Substituir os critérios atuais pelos critérios padrão? Critérios com notas lançadas serão retirados das próximas avaliações.')) return;
                    this.linhas = padroes.map(c => ({ ...c, usado: false }));
                },
            };
        }
    </script>
</x-app-layout>
