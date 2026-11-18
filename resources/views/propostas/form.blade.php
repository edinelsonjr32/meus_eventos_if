@php
    $editando = $proposta->exists;
    $acao = $editando ? route('propostas.update', $proposta) : route('propostas.store', $chamada);
    $v = fn ($campo) => old($campo, $proposta->$campo);
    $lista = array_values(array_map(fn ($a) => ['nome' => $a['nome'] ?? '', 'email' => $a['email'] ?? '', 'cpf' => $a['cpf'] ?? ''], $apoios ?? []));
    $dias = ($dias ?? collect())->values();
    $periodos = \App\Models\Voluntario::PERIODOS;
    $dispAtual = old('disponibilidade', $proposta->disponibilidade_dias ?? []);
    $disp = [];
    foreach ($dias as $d) { $disp[$d] = array_values((array) ($dispAtual[$d] ?? [])); }

    // Erros do servidor: viram mensagem junto de cada campo (apoios.0.cpf -> apoio_0_cpf)
    $errosServidor = [];
    foreach ($errors->getMessages() as $chave => $msgs) {
        $k = preg_match('/^apoios\.(\d+)\.(\w+)$/', $chave, $m) ? "apoio_{$m[1]}_{$m[2]}" : (str_starts_with($chave, 'disponibilidade.') ? 'disponibilidade' : $chave);
        $errosServidor[$k] = $errosServidor[$k] ?? $msgs[0];
    }

    $cfg = [
        'v' => [
            'tipo' => $v('tipo'), 'titulo' => $v('titulo'), 'resumo' => $v('resumo'), 'objetivos' => $v('objetivos'), 'metodologia' => $v('metodologia'),
            'conteudo_programatico' => $v('conteudo_programatico'), 'pre_requisitos' => $v('pre_requisitos'), 'materiais_participante' => $v('materiais_participante'),
            'publico_alvo' => $v('publico_alvo'), 'carga_horaria_prevista' => $v('carga_horaria_prevista') ?: 4, 'vagas_sugeridas' => $v('vagas_sugeridas') ?: 30,
            'minicurriculo' => $v('minicurriculo'), 'contato_whatsapp' => $v('contato_whatsapp'), 'lab' => (bool) $v('requer_laboratorio'),
            'espaco_solicitado' => $v('espaco_solicitado'), 'equipamentos_solicitados' => $v('equipamentos_solicitados'), 'observacoes' => $v('observacoes'),
        ],
        'disp' => (object) $disp, 'apoios' => $lista, 'erros' => (object) $errosServidor, 'temErroServidor' => $errors->any(), 'logado' => auth()->check(),
        'dias' => $dias->all(), 'tiposAceitos' => array_keys($chamada->tiposAceitos()),
        'emailProprio' => mb_strtolower((string) auth()->user()?->email),
        'pingUrl' => auth()->check() ? route('sessao.ping') : null,
        'chaveRascunho' => auth()->check() ? 'proposta-rascunho:' . ($editando ? 'e' . $proposta->id : 'c' . $chamada->id) . ':' . auth()->id() : null,
        'editando' => $editando,
    ];
@endphp
<x-layout-adaptavel :titulo="$chamada->titulo" :descricao="\App\Support\HtmlSeguro::paraTexto($chamada->descricao) ?: ('Envie sua proposta de minicurso ou oficina para ' . $chamada->evento->titulo . '.')" :imagem="$chamada->evento->bannerPublicoUrl() ?: $chamada->evento->logoPublicaUrl()" :url="route('propostas.create', $chamada)">
    <x-form-seguro />
    <style>
        .pf-sec h6 { display: flex; align-items: center; }
        .pf-disp { display: grid; grid-template-columns: minmax(96px, 1.2fr) repeat(3, 1fr); align-items: stretch; }
        .pf-disp > .c { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: .55rem .35rem; border-top: 1px solid #e2e8f0; text-align: center; }
        .pf-disp > .cab { border-top: 0; background: #f8fafc; font-weight: 600; font-size: .78rem; }
        .pf-disp > .dia { align-items: flex-start; text-align: left; padding-left: .75rem; }
    </style>
    <div class="row justify-content-center"><div class="col-xl-9">
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="{{ auth()->check() ? route('propostas.minhas') : route('evento.publico.show', $chamada->evento->slug) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="min-w-0"><h3 class="fw-bold m-0 texto-quebra">{{ $chamada->titulo }}</h3><small class="text-secondary">{{ $editando ? 'Editar proposta' : 'Submissão de proposta de minicurso ou oficina' }} · {{ $chamada->evento->titulo }}</small></div>
        </div>

        <x-aviso-login acao="preencher e enviar sua proposta" />
        @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
        @if(session('erro_sessao')) <div class="alert alert-warning"><i class="fa-solid fa-clock me-1"></i> {{ session('erro_sessao') }}</div> @endif
        @if($errors->any())<div class="alert alert-danger" id="alerta-erros" role="alert" tabindex="-1"><strong><i class="fa-solid fa-circle-exclamation me-1"></i> Não foi possível enviar. Corrija os campos abaixo:</strong><div class="small mt-1">Os dados que você preencheu foram mantidos.</div><ul class="mb-0 mt-1 small">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>@endif
        @if($editando && $proposta->status === 'ajustes')
            <div class="alert alert-warning"><i class="fa-solid fa-pen me-1"></i> A organização pediu ajustes: <span class="texto-quebra">{{ $proposta->parecer }}</span></div>
        @endif
        @if($chamada->descricao)<div class="glass-card mb-4 small conteudo-rico">{!! \App\Support\HtmlSeguro::paraExibir($chamada->descricao) !!}</div>@endif

        <form action="{{ $acao }}" method="POST" novalidate x-data="formProposta({{ \Illuminate\Support\Js::from($cfg) }})" @submit="enviando = true">
            @csrf
            @if($editando) @method('PUT') @endif
            <fieldset @disabled(!auth()->check()) style="border:0;padding:0;margin:0;min-width:0">

            {{-- Dados digitados e não enviados: oferece restaurar --}}
            <div x-show="rascunhoSalvo" x-cloak><div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span><i class="fa-solid fa-clock-rotate-left me-1"></i> Encontramos dados que você digitou e <strong>não chegou a enviar</strong>. Quer continuar de onde parou?</span>
                <span class="d-flex gap-2"><button type="button" class="btn btn-sm btn-primary-custom fw-bold" @click="restaurar()">Restaurar</button><button type="button" class="btn btn-sm btn-light border" @click="descartar()">Descartar</button></span>
            </div></div>

            {{-- Problemas encontrados no navegador antes de enviar --}}
            <div class="alert alert-danger d-none" id="alerta-erros-cliente" role="alert" tabindex="-1" :class="{ 'd-none': !lista.length }">
                <strong><i class="fa-solid fa-circle-exclamation me-1"></i> Revise o formulário antes de enviar:</strong>
                <ul class="mb-0 mt-1 small"><template x-for="e in lista" :key="e"><li x-text="e"></li></template></ul>
            </div>

            {{-- 1. A atividade --}}
            <div class="glass-card mb-4 pf-sec">
                <h6 class="fw-bold text-success mb-3"><span class="badge bg-success me-2">1</span> A atividade</h6>
                <div class="row g-3">
                    <div class="col-md-4"><label class="small fw-bold">Tipo *</label>
                        <select name="tipo" class="form-select" :class="{ 'is-invalid': erros.tipo }" x-model="f.tipo">@foreach($chamada->tiposAceitos() as $valor => $rotulo)<option value="{{ $valor }}">{{ $rotulo }}</option>@endforeach</select>
                        <div class="text-danger small mt-1" x-show="erros.tipo" x-text="erros.tipo"></div></div>
                    <div class="col-md-8"><label class="small fw-bold">Título *</label>
                        <input type="text" name="titulo" class="form-control" :class="{ 'is-invalid': erros.titulo }" maxlength="200" x-model="f.titulo" value="{{ $v('titulo') }}">
                        <div class="text-danger small mt-1" x-show="erros.titulo" x-text="erros.titulo"></div></div>
                    <div class="col-md-6"><label class="small fw-bold">Público-alvo</label>
                        <input type="text" name="publico_alvo" class="form-control" :class="{ 'is-invalid': erros.publico_alvo }" maxlength="255" placeholder="Ex.: alunos do ensino médio técnico" x-model="f.publico_alvo" value="{{ $v('publico_alvo') }}">
                        <div class="text-danger small mt-1" x-show="erros.publico_alvo" x-text="erros.publico_alvo"></div></div>
                    <div class="col-md-3"><label class="small fw-bold">Carga horária (h) *</label>
                        <input type="number" name="carga_horaria_prevista" min="1" max="40" step="1" class="form-control" :class="{ 'is-invalid': erros.carga_horaria_prevista }" x-model="f.carga_horaria_prevista">
                        <div class="text-danger small mt-1" x-show="erros.carga_horaria_prevista" x-text="erros.carga_horaria_prevista"></div></div>
                    <div class="col-md-3"><label class="small fw-bold">Vagas sugeridas *</label>
                        <input type="number" name="vagas_sugeridas" min="1" max="500" step="1" class="form-control" :class="{ 'is-invalid': erros.vagas_sugeridas }" x-model="f.vagas_sugeridas">
                        <div class="text-danger small mt-1" x-show="erros.vagas_sugeridas" x-text="erros.vagas_sugeridas"></div></div>
                </div>
            </div>

            {{-- 2. Conteúdo --}}
            <div class="glass-card mb-4 pf-sec">
                <h6 class="fw-bold text-success mb-3"><span class="badge bg-success me-2">2</span> Conteúdo da proposta</h6>
                <div class="row g-3">
                    <div class="col-12"><label class="small fw-bold">Resumo da proposta * <span class="fw-normal text-muted">(o que será ensinado e o que o participante vai levar)</span></label>
                        <textarea name="resumo" rows="6" class="form-control" :class="{ 'is-invalid': erros.resumo }" x-model="f.resumo">{{ $v('resumo') }}</textarea>
                        <div class="d-flex justify-content-between"><div class="text-danger small mt-1" x-show="erros.resumo" x-text="erros.resumo"></div><div class="form-text ms-auto" :class="{ 'text-danger': f.resumo.length > 3000 }" x-text="f.resumo.length + '/3000 (mínimo 80)'"></div></div></div>
                    <div class="col-md-6"><label class="small fw-bold">Objetivos</label>
                        <textarea name="objetivos" rows="3" class="form-control" :class="{ 'is-invalid': erros.objetivos }" placeholder="Objetivos de aprendizagem" x-model="f.objetivos">{{ $v('objetivos') }}</textarea>
                        <div class="d-flex justify-content-between"><div class="text-danger small mt-1" x-show="erros.objetivos" x-text="erros.objetivos"></div><div class="form-text ms-auto" x-text="f.objetivos.length + '/3000'"></div></div></div>
                    <div class="col-md-6"><label class="small fw-bold">Metodologia</label>
                        <textarea name="metodologia" rows="3" class="form-control" :class="{ 'is-invalid': erros.metodologia }" placeholder="Como a atividade será conduzida" x-model="f.metodologia">{{ $v('metodologia') }}</textarea>
                        <div class="d-flex justify-content-between"><div class="text-danger small mt-1" x-show="erros.metodologia" x-text="erros.metodologia"></div><div class="form-text ms-auto" x-text="f.metodologia.length + '/3000'"></div></div></div>
                    <div class="col-12"><label class="small fw-bold">Conteúdo programático</label>
                        <textarea name="conteudo_programatico" rows="4" class="form-control" :class="{ 'is-invalid': erros.conteudo_programatico }" placeholder="Tópicos que serão abordados, em ordem" x-model="f.conteudo_programatico">{{ $v('conteudo_programatico') }}</textarea>
                        <div class="d-flex justify-content-between"><div class="text-danger small mt-1" x-show="erros.conteudo_programatico" x-text="erros.conteudo_programatico"></div><div class="form-text ms-auto" x-text="f.conteudo_programatico.length + '/5000'"></div></div></div>
                    <div class="col-md-6"><label class="small fw-bold">Pré-requisitos <span class="fw-normal text-muted">(opcional)</span></label>
                        <textarea name="pre_requisitos" rows="3" class="form-control" :class="{ 'is-invalid': erros.pre_requisitos }" placeholder="Conhecimentos que o participante precisa ter" x-model="f.pre_requisitos">{{ $v('pre_requisitos') }}</textarea>
                        <div class="d-flex justify-content-between"><div class="text-danger small mt-1" x-show="erros.pre_requisitos" x-text="erros.pre_requisitos"></div><div class="form-text ms-auto" x-text="f.pre_requisitos.length + '/2000'"></div></div></div>
                    <div class="col-md-6"><label class="small fw-bold">O que o participante deve levar <span class="fw-normal text-muted">(opcional)</span></label>
                        <textarea name="materiais_participante" rows="3" class="form-control" :class="{ 'is-invalid': erros.materiais_participante }" placeholder="Ex.: notebook, caderno, jaleco" x-model="f.materiais_participante">{{ $v('materiais_participante') }}</textarea>
                        <div class="d-flex justify-content-between"><div class="text-danger small mt-1" x-show="erros.materiais_participante" x-text="erros.materiais_participante"></div><div class="form-text ms-auto" x-text="f.materiais_participante.length + '/2000'"></div></div></div>
                </div>
            </div>

            {{-- 3. Ministrante e disponibilidade --}}
            <div class="glass-card mb-4 pf-sec" id="bloco-ministrante">
                <h6 class="fw-bold text-success mb-3"><span class="badge bg-success me-2">3</span> Ministrante e disponibilidade</h6>
                <div class="row g-3">
                    <div class="col-12"><label class="small fw-bold">Minicurrículo * <span class="fw-normal text-muted">(quem é você e sua experiência com o tema; aparece na divulgação)</span></label>
                        <textarea name="minicurriculo" rows="3" class="form-control" :class="{ 'is-invalid': erros.minicurriculo }" x-model="f.minicurriculo">{{ $v('minicurriculo') }}</textarea>
                        <div class="d-flex justify-content-between"><div class="text-danger small mt-1" x-show="erros.minicurriculo" x-text="erros.minicurriculo"></div><div class="form-text ms-auto" x-text="f.minicurriculo.length + '/1000 (mínimo 30)'"></div></div></div>
                    <div class="col-md-5"><label class="small fw-bold">WhatsApp para contato *</label>
                        <input type="text" name="contato_whatsapp" data-mascara-auto="telefone" class="form-control" :class="{ 'is-invalid': erros.contato_whatsapp }" maxlength="15" placeholder="(00) 00000-0000" x-model="f.contato_whatsapp" value="{{ $v('contato_whatsapp') }}">
                        <div class="text-danger small mt-1" x-show="erros.contato_whatsapp" x-text="erros.contato_whatsapp"></div>
                        <div class="form-text">Só a organização vê.</div></div>
                    <div class="col-12" id="bloco-disp"><label class="small fw-bold">Dias e períodos em que você pode ministrar *</label>
                        <div class="form-text mb-2">A organização monta a programação dentro da sua disponibilidade. Marque todos os períodos possíveis.</div>
                        <div class="border rounded-3 overflow-hidden" :class="{ 'border-danger': erros.disponibilidade }">
                            <div class="pf-disp">
                                <div class="c cab dia">Dia</div>
                                @foreach($periodos as $p)<div class="c cab">{{ $p['rotulo'] }}<div class="fw-normal text-muted" style="font-size:.68rem">{{ $p['faixa'] }}</div></div>@endforeach
                                @foreach($dias as $dia)
                                    @php $c = \Illuminate\Support\Carbon::parse($dia); @endphp
                                    <div class="c dia"><div><strong>{{ $c->format('d/m') }}</strong> <span class="text-muted small">{{ $c->translatedFormat('D') }}</span></div>
                                        <div style="font-size:.72rem"><a href="#" class="text-decoration-none" @click.prevent="disp['{{ $dia }}'] = ['manha', 'tarde', 'noite']">dia todo</a> · <a href="#" class="text-secondary text-decoration-none" @click.prevent="disp['{{ $dia }}'] = []">limpar</a></div></div>
                                    @foreach($periodos as $chave => $p)
                                        <div class="c"><input type="checkbox" class="form-check-input" style="width:1.3rem;height:1.3rem" name="disponibilidade[{{ $dia }}][]" value="{{ $chave }}" x-model="disp['{{ $dia }}']" aria-label="{{ $c->format('d/m') }} {{ $p['rotulo'] }}"></div>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                        <div class="text-danger small mt-1" x-show="erros.disponibilidade" x-text="erros.disponibilidade"></div></div>
                </div>
            </div>

            {{-- 4. Espaço e equipamentos --}}
            <div class="glass-card mb-4 pf-sec">
                <h6 class="fw-bold text-success mb-3"><span class="badge bg-success me-2">4</span> Espaço e equipamentos</h6>
                <div class="form-check form-switch mb-3"><input type="hidden" name="requer_laboratorio" value="0"><input class="form-check-input" type="checkbox" role="switch" name="requer_laboratorio" value="1" id="lab" x-model="f.lab"><label class="form-check-label fw-bold small" for="lab">Precisa de laboratório ou espaço específico</label></div>
                <div class="row g-3">
                    <div class="col-12" x-show="f.lab" x-cloak><label class="small fw-bold">Qual espaço? *</label>
                        <input type="text" name="espaco_solicitado" class="form-control" :class="{ 'is-invalid': erros.espaco_solicitado }" maxlength="255" placeholder="Ex.: Laboratório de Informática, com 30 computadores" x-model="f.espaco_solicitado" value="{{ $v('espaco_solicitado') }}">
                        <div class="text-danger small mt-1" x-show="erros.espaco_solicitado" x-text="erros.espaco_solicitado"></div></div>
                    <div class="col-12"><label class="small fw-bold">Equipamentos e materiais necessários</label>
                        <textarea name="equipamentos_solicitados" rows="2" class="form-control" :class="{ 'is-invalid': erros.equipamentos_solicitados }" placeholder="Ex.: projetor, caixa de som, kits Arduino (a organização informa o que consegue disponibilizar)" x-model="f.equipamentos_solicitados">{{ $v('equipamentos_solicitados') }}</textarea>
                        <div class="text-danger small mt-1" x-show="erros.equipamentos_solicitados" x-text="erros.equipamentos_solicitados"></div></div>
                    <div class="col-12"><label class="small fw-bold">Observações</label>
                        <textarea name="observacoes" rows="2" class="form-control" :class="{ 'is-invalid': erros.observacoes }" placeholder="Restrições de data/horário, necessidades de acessibilidade..." x-model="f.observacoes">{{ $v('observacoes') }}</textarea>
                        <div class="text-danger small mt-1" x-show="erros.observacoes" x-text="erros.observacoes"></div></div>
                </div>
            </div>

            {{-- 5. Pessoas de apoio --}}
            <div class="glass-card mb-4 pf-sec">
                <h6 class="fw-bold text-success mb-1"><span class="badge bg-success me-2">5</span> Pessoas de apoio (ajudantes)</h6>
                <p class="small text-muted">Opcional. Informe quem vai ajudar na atividade (quantas precisar). Nome, e-mail e CPF são obrigatórios para cada pessoa: a organização poderá adicioná-la como participante do evento.</p>
                <template x-for="(a, i) in apoios" :key="a.uid">
                    <div class="border rounded-3 p-2 mb-2 bg-light">
                        <div class="row g-2 align-items-start">
                            <div class="col-md-4"><label class="small">Nome *</label><input type="text" class="form-control form-control-sm" :class="{ 'is-invalid': erros['apoio_' + i + '_nome'] }" :name="`apoios[${i}][nome]`" x-model="a.nome" maxlength="255">
                                <div class="text-danger small" x-show="erros['apoio_' + i + '_nome']" x-text="erros['apoio_' + i + '_nome']"></div></div>
                            <div class="col-md-4"><label class="small">E-mail *</label><input type="email" class="form-control form-control-sm" :class="{ 'is-invalid': erros['apoio_' + i + '_email'] }" :name="`apoios[${i}][email]`" x-model="a.email">
                                <div class="text-danger small" x-show="erros['apoio_' + i + '_email']" x-text="erros['apoio_' + i + '_email']"></div></div>
                            <div class="col-md-3"><label class="small">CPF *</label><input type="text" class="form-control form-control-sm" :class="{ 'is-invalid': erros['apoio_' + i + '_cpf'] }" :name="`apoios[${i}][cpf]`" x-model="a.cpf" maxlength="14" placeholder="000.000.000-00" inputmode="numeric">
                                <div class="text-danger small" x-show="erros['apoio_' + i + '_cpf']" x-text="erros['apoio_' + i + '_cpf']"></div></div>
                            <div class="col-md-1"><label class="small d-block">&nbsp;</label><button type="button" class="btn btn-sm btn-light border w-100" @click="apoios.splice(i, 1)" title="Remover"><i class="fa-solid fa-trash"></i></button></div>
                        </div>
                    </div>
                </template>
                <div class="text-danger small mb-2" x-show="erros.apoios" x-text="erros.apoios"></div>
                <button type="button" class="btn btn-sm btn-outline-success" x-show="apoios.length < 30" @click="adicionarApoio()"><i class="fa-solid fa-plus"></i> Adicionar pessoa de apoio</button>
            </div>

            </fieldset>
            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between mb-5">
                <a href="{{ auth()->check() ? route('propostas.minhas') : route('evento.publico.show', $chamada->evento->slug) }}" class="btn btn-light fw-bold"><i class="fa-solid fa-arrow-left me-1"></i> Cancelar</a>
                @auth
                    <button type="submit" class="btn btn-primary-custom fw-bold" :disabled="enviando" @click="enviar($event)"><i class="fa-solid fa-paper-plane me-1"></i> <span x-text="enviando ? 'Enviando…' : '{{ $editando ? 'Salvar e reenviar' : 'Enviar proposta' }}'"></span></button>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary-custom fw-bold"><i class="fa-solid fa-right-to-bracket me-1"></i> Entrar para enviar</a>
                @endauth
            </div>
        </form>
    </div></div>

    <script>
        function formProposta(cfg) {
            let seq = 0;
            const novoApoio = (d = {}) => ({ uid: ++seq, nome: d.nome || '', email: d.email || '', cpf: d.cpf || '' });
            const emailOk = v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v || '');
            const digitos = v => (v || '').replace(/\D/g, '');
            const cpfOk = v => {
                const c = digitos(v); if (c.length !== 11 || /^(\d)\1+$/.test(c)) return false;
                const dv = n => { let s = 0; for (let i = 0; i < n; i++) s += parseInt(c[i]) * (n + 1 - i); const r = (s * 10) % 11; return r === 10 ? 0 : r; };
                return dv(9) === parseInt(c[9]) && dv(10) === parseInt(c[10]);
            };
            const rascunho = FormSeguro.rascunho(cfg.chaveRascunho);
            const campos = ['tipo', 'titulo', 'resumo', 'objetivos', 'metodologia', 'conteudo_programatico', 'pre_requisitos', 'materiais_participante', 'publico_alvo', 'carga_horaria_prevista', 'vagas_sugeridas', 'minicurriculo', 'contato_whatsapp', 'lab', 'espaco_solicitado', 'equipamentos_solicitados', 'observacoes'];
            const ancora = { tipo: '[name=tipo]', titulo: '[name=titulo]', resumo: '[name=resumo]', objetivos: '[name=objetivos]', metodologia: '[name=metodologia]', conteudo_programatico: '[name=conteudo_programatico]', pre_requisitos: '[name=pre_requisitos]', materiais_participante: '[name=materiais_participante]', publico_alvo: '[name=publico_alvo]', carga_horaria_prevista: '[name=carga_horaria_prevista]', vagas_sugeridas: '[name=vagas_sugeridas]', minicurriculo: '[name=minicurriculo]', contato_whatsapp: '[name=contato_whatsapp]', disponibilidade: '#bloco-disp', espaco_solicitado: '[name=espaco_solicitado]', equipamentos_solicitados: '[name=equipamentos_solicitados]', observacoes: '[name=observacoes]' };

            return {
                f: Object.fromEntries(campos.map(k => [k, cfg.v[k] ?? (k === 'lab' ? false : '')])),
                disp: JSON.parse(JSON.stringify(cfg.disp || {})),
                apoios: (cfg.apoios || []).map(novoApoio),
                erros: { ...(cfg.erros || {}) }, lista: [], enviando: false, rascunhoSalvo: false, _t: null,

                init() {
                    const alerta = document.getElementById('alerta-erros');
                    if (alerta) this.$nextTick(() => { alerta.scrollIntoView({ behavior: 'smooth', block: 'center' }); alerta.focus({ preventScroll: true }); });
                    ['f', 'disp', 'apoios'].forEach(p => this.$watch(p, () => { this.revalidar(); this.agendarSalvar(); }));
                    window.addEventListener('pageshow', () => { this.enviando = false; });   // voltar com o botão do navegador não deixa o envio travado
                    FormSeguro.sessaoViva(cfg.pingUrl);
                    const salvo = rascunho.ler();
                    if (cfg.chaveRascunho && salvo && !cfg.temErroServidor) {
                        this.rascunhoSalvo = cfg.editando ? true : (!this.f.titulo.trim() && !this.f.resumo.trim() && !!(salvo.f && (salvo.f.titulo || salvo.f.resumo)));
                    }
                },

                adicionarApoio() { if (this.apoios.length < 30) this.apoios.push(novoApoio()); },

                // ---- rascunho no navegador ----
                agendarSalvar() {
                    if (!cfg.chaveRascunho || this.rascunhoSalvo) return;
                    clearTimeout(this._t);
                    this._t = setTimeout(() => {
                        if (this.f.titulo || this.f.resumo) rascunho.salvar({ f: this.f, disp: this.disp, apoios: this.apoios.map(a => ({ nome: a.nome, email: a.email, cpf: a.cpf })) });
                    }, 800);
                },
                restaurar() {
                    const d = rascunho.ler(); if (!d) { this.rascunhoSalvo = false; return; }
                    Object.assign(this.f, d.f || {});
                    Object.keys(this.disp).forEach(k => { this.disp[k] = (d.disp && d.disp[k]) || []; });
                    this.apoios = (d.apoios || []).map(novoApoio);
                    this.rascunhoSalvo = false;
                },
                descartar() { rascunho.apagar(); this.rascunhoSalvo = false; },

                // ---- validação no navegador (o servidor continua sendo a validação definitiva) ----
                calcular() {
                    const e = {}, f = this.f, n = v => (v || '').toString().trim().length;
                    if (!cfg.tiposAceitos.includes(f.tipo)) e.tipo = 'Selecione o tipo da atividade.';
                    if (n(f.titulo) < 5) e.titulo = n(f.titulo) ? 'O título deve ter pelo menos 5 caracteres.' : 'Informe o título da proposta.';
                    if (n(f.titulo) > 200) e.titulo = 'O título pode ter no máximo 200 caracteres.';
                    if (n(f.resumo) < 80) e.resumo = n(f.resumo) ? `O resumo deve ter pelo menos 80 caracteres (faltam ${80 - n(f.resumo)}).` : 'Escreva o resumo da proposta (mínimo de 80 caracteres).';
                    if (f.resumo.length > 3000) e.resumo = 'O resumo pode ter no máximo 3000 caracteres.';
                    [['objetivos', 3000, 'Os objetivos'], ['metodologia', 3000, 'A metodologia'], ['conteudo_programatico', 5000, 'O conteúdo programático'], ['pre_requisitos', 2000, 'Os pré-requisitos'], ['materiais_participante', 2000, 'Os materiais do participante'], ['equipamentos_solicitados', 2000, 'Os equipamentos'], ['observacoes', 2000, 'As observações']]
                        .forEach(([k, max, r]) => { if ((f[k] || '').length > max) e[k] = `${r} pode ter no máximo ${max} caracteres.`; });
                    if ((f.publico_alvo || '').length > 255) e.publico_alvo = 'O público-alvo pode ter no máximo 255 caracteres.';
                    const ch = Number(f.carga_horaria_prevista); if (!Number.isInteger(ch) || ch < 1 || ch > 40) e.carga_horaria_prevista = 'Informe uma carga horária inteira de 1 a 40 horas.';
                    const vg = Number(f.vagas_sugeridas); if (!Number.isInteger(vg) || vg < 1 || vg > 500) e.vagas_sugeridas = 'Informe de 1 a 500 vagas.';
                    if (n(f.minicurriculo) < 30) e.minicurriculo = n(f.minicurriculo) ? `O minicurrículo deve ter pelo menos 30 caracteres (faltam ${30 - n(f.minicurriculo)}).` : 'Escreva um minicurrículo curto: quem é você e sua experiência com o tema.';
                    if (f.minicurriculo.length > 1000) e.minicurriculo = 'O minicurrículo pode ter no máximo 1000 caracteres.';
                    if (![10, 11].includes(digitos(f.contato_whatsapp).length)) e.contato_whatsapp = 'Informe o WhatsApp com DDD, por exemplo (93) 91234-5678.';
                    if (!Object.values(this.disp).some(p => p && p.length)) e.disponibilidade = 'Marque ao menos um dia e período em que você pode ministrar a atividade.';
                    if (f.lab && !n(f.espaco_solicitado)) e.espaco_solicitado = 'Informe qual laboratório ou espaço específico você precisa.';

                    const cpfs = {}, emails = {};
                    this.apoios.forEach((a, i) => {
                        if (n(a.nome) < 3) e['apoio_' + i + '_nome'] = 'Informe o nome (mínimo de 3 letras).';
                        if (!emailOk(a.email)) e['apoio_' + i + '_email'] = 'Informe um e-mail válido.';
                        else if (a.email.toLowerCase() === cfg.emailProprio) e['apoio_' + i + '_email'] = 'Você não pode se indicar como pessoa de apoio.';
                        else if (emails[a.email.toLowerCase()] !== undefined) e['apoio_' + i + '_email'] = 'E-mail repetido na lista de apoio.';
                        emails[(a.email || '').toLowerCase()] = i;
                        if (!cpfOk(a.cpf)) e['apoio_' + i + '_cpf'] = digitos(a.cpf).length === 11 ? 'CPF inválido.' : 'Informe o CPF completo.';
                        else if (cpfs[digitos(a.cpf)] !== undefined) e['apoio_' + i + '_cpf'] = 'CPF repetido na lista de apoio.';
                        cpfs[digitos(a.cpf)] = i;
                    });
                    return e;
                },
                // Só mantém o que ainda está errado: o que foi corrigido some na hora, sem acusar campos ainda não tocados
                revalidar() {
                    const novos = this.calcular(), atuais = {};
                    Object.keys(this.erros).forEach(k => { if (this.erros[k] && novos[k]) atuais[k] = novos[k]; });
                    this.erros = atuais;
                    if (this.lista.length) this.lista = Object.values(atuais);
                    if (!Object.keys(atuais).length) this.avisoCorrigido();
                },
                avisoCorrigido() {
                    const a = document.getElementById('alerta-erros');
                    if (!a || a.dataset.corrigido) return;
                    a.dataset.corrigido = '1'; a.className = 'alert alert-success';
                    a.innerHTML = '<strong><i class="fa-solid fa-circle-check me-1"></i> Tudo certo!</strong> <span class="small">Os campos com problema foram corrigidos. Revise e clique em enviar novamente.</span>';
                },
                enviar(ev) {
                    const e = this.calcular();
                    if (!Object.keys(e).length) return;           // tudo certo: o envio segue
                    ev.preventDefault(); this.enviando = false;
                    this.erros = e; this.lista = Object.values(e);
                    const chave = Object.keys(ancora).find(k => e[k]) || Object.keys(e)[0];
                    this.$nextTick(() => {
                        const alvo = document.getElementById('alerta-erros-cliente'); if (alvo) alvo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        const sel = ancora[chave] || (chave && chave.startsWith('apoio_') ? `[name="apoios[${chave.split('_')[1]}][${chave.split('_')[2]}]"]` : null);
                        const campo = sel ? this.$root.querySelector(sel) : null;
                        if (campo && campo.focus) setTimeout(() => campo.focus({ preventScroll: true }), 350);
                    });
                },
            };
        }
    </script>
</x-layout-adaptavel>
