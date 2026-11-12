@php
    $editando = $proposta->exists;
    $acao = $editando ? route('propostas.update', $proposta) : route('propostas.store', $chamada);
    $v = fn ($campo) => old($campo, $proposta->$campo);
    $lista = array_values(array_map(fn ($a) => ['nome' => $a['nome'] ?? '', 'email' => $a['email'] ?? '', 'cpf' => $a['cpf'] ?? ''], $apoios ?? []));
@endphp
<x-layout-adaptavel :titulo="$chamada->titulo" :descricao="\App\Support\HtmlSeguro::paraTexto($chamada->descricao) ?: ('Envie sua proposta de minicurso ou oficina para ' . $chamada->evento->titulo . '.')" :imagem="$chamada->evento->bannerPublicoUrl() ?: $chamada->evento->logoPublicaUrl()" :url="route('propostas.create', $chamada)">
    <div class="row justify-content-center"><div class="col-xl-9">
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="{{ auth()->check() ? route('propostas.minhas') : route('evento.publico.show', $chamada->evento->slug) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="min-w-0"><h3 class="fw-bold m-0 texto-quebra">{{ $chamada->titulo }}</h3><small class="text-secondary">{{ $editando ? 'Editar proposta' : 'Submissão de proposta de minicurso ou oficina' }} · {{ $chamada->evento->titulo }}</small></div>
        </div>

        <x-aviso-login acao="preencher e enviar sua proposta" />
        @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
        @if($errors->any())<div class="alert alert-danger" id="alerta-erros"><strong>Corrija os campos abaixo:</strong><ul class="mb-0 mt-1 small">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>@endif
        @if($editando && $proposta->status === 'ajustes')
            <div class="alert alert-warning"><i class="fa-solid fa-pen me-1"></i> A organização pediu ajustes: <span class="texto-quebra">{{ $proposta->parecer }}</span></div>
        @endif
        @if($chamada->descricao)<div class="glass-card mb-4 small conteudo-rico">{!! \App\Support\HtmlSeguro::paraExibir($chamada->descricao) !!}</div>@endif

        <form action="{{ $acao }}" method="POST" x-data="{ lab: {{ $v('requer_laboratorio') ? 'true' : 'false' }}, apoios: {{ \Illuminate\Support\Js::from($lista) }} }">
            @csrf
            @if($editando) @method('PUT') @endif
            <fieldset @disabled(!auth()->check()) style="border:0;padding:0;margin:0;min-width:0">

            <div class="glass-card mb-4">
                <h6 class="fw-bold text-success mb-3"><span class="badge bg-success me-2">1</span> A atividade</h6>
                <div class="row g-3">
                    <div class="col-md-4"><label class="small fw-bold">Tipo *</label>
                        <select name="tipo" class="form-select">@foreach($chamada->tiposAceitos() as $valor => $rotulo)<option value="{{ $valor }}" @selected($v('tipo') === $valor)>{{ $rotulo }}</option>@endforeach</select></div>
                    <div class="col-md-8"><label class="small fw-bold">Título *</label><input type="text" name="titulo" class="form-control" minlength="5" maxlength="200" value="{{ $v('titulo') }}" required></div>
                    <div class="col-12"><label class="small fw-bold">Resumo da proposta * <span class="fw-normal text-muted">(o que será ensinado e o que o participante vai levar; mínimo de 80 caracteres)</span></label><x-editor-rico name="resumo" :value="$v('resumo')" modo="simples" :altura="170" placeholder="Descreva o que será ensinado e o que o participante vai levar (mínimo de 80 caracteres)." /></div>
                    <div class="col-md-6"><label class="small fw-bold">Objetivos</label><x-editor-rico name="objetivos" :value="$v('objetivos')" modo="simples" :altura="110" placeholder="Objetivos de aprendizagem" /></div>
                    <div class="col-md-6"><label class="small fw-bold">Metodologia</label><x-editor-rico name="metodologia" :value="$v('metodologia')" modo="simples" :altura="110" placeholder="Como a atividade será conduzida" /></div>
                    <div class="col-12"><label class="small fw-bold">Conteúdo programático</label><x-editor-rico name="conteudo_programatico" :value="$v('conteudo_programatico')" modo="simples" :altura="150" placeholder="Tópicos que serão abordados, em ordem" /></div>
                    <div class="col-md-6"><label class="small fw-bold">Público-alvo</label><input type="text" name="publico_alvo" class="form-control" maxlength="255" placeholder="Ex.: alunos do ensino médio técnico" value="{{ $v('publico_alvo') }}"></div>
                    <div class="col-md-3"><label class="small fw-bold">Carga horária (h) *</label><input type="number" name="carga_horaria_prevista" min="1" max="40" step="1" class="form-control" value="{{ $v('carga_horaria_prevista') ?: 4 }}" required></div>
                    <div class="col-md-3"><label class="small fw-bold">Vagas sugeridas *</label><input type="number" name="vagas_sugeridas" min="1" max="500" step="1" class="form-control" value="{{ $v('vagas_sugeridas') ?: 30 }}" required></div>
                </div>
            </div>

            <div class="glass-card mb-4">
                <h6 class="fw-bold text-success mb-3"><span class="badge bg-success me-2">2</span> Espaço e equipamentos</h6>
                <div class="form-check form-switch mb-3"><input type="hidden" name="requer_laboratorio" value="0"><input class="form-check-input" type="checkbox" role="switch" name="requer_laboratorio" value="1" id="lab" x-model="lab" @checked($v('requer_laboratorio'))><label class="form-check-label fw-bold small" for="lab">Precisa de laboratório ou espaço específico</label></div>
                <div class="row g-3">
                    <div class="col-12" x-show="lab" x-cloak><label class="small fw-bold">Qual espaço? *</label><input type="text" name="espaco_solicitado" :required="lab" class="form-control" maxlength="255" placeholder="Ex.: Laboratório de Informática, com 30 computadores" value="{{ $v('espaco_solicitado') }}"></div>
                    <div class="col-12"><label class="small fw-bold">Equipamentos e materiais necessários</label><x-editor-rico name="equipamentos_solicitados" :value="$v('equipamentos_solicitados')" modo="simples" :altura="90" placeholder="Ex.: projetor, caixa de som, kits Arduino (a organização informa o que consegue disponibilizar)" /></div>
                    <div class="col-12"><label class="small fw-bold">Observações</label><x-editor-rico name="observacoes" :value="$v('observacoes')" modo="simples" :altura="90" placeholder="Restrições de data/horário, necessidades de acessibilidade..." /></div>
                </div>
            </div>

            <div class="glass-card mb-4">
                <h6 class="fw-bold text-success mb-1"><span class="badge bg-success me-2">3</span> Pessoas de apoio (ajudantes)</h6>
                <p class="small text-muted">Opcional. Informe quem vai ajudar na atividade (até 5). Nome, e-mail e CPF são obrigatórios para cada pessoa: a organização poderá adicioná-la como participante do evento.</p>
                <template x-for="(a, i) in apoios" :key="i">
                    <div class="row g-2 mb-2 align-items-end">
                        <div class="col-md-4"><label class="small">Nome *</label><input type="text" class="form-control form-control-sm" :name="`apoios[${i}][nome]`" x-model="a.nome" minlength="3" maxlength="255" required></div>
                        <div class="col-md-4"><label class="small">E-mail *</label><input type="email" class="form-control form-control-sm" :name="`apoios[${i}][email]`" x-model="a.email" required></div>
                        <div class="col-md-3"><label class="small">CPF *</label><input type="text" class="form-control form-control-sm" :name="`apoios[${i}][cpf]`" x-model="a.cpf" maxlength="14" placeholder="000.000.000-00" inputmode="numeric" required></div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-light border w-100" @click="apoios.splice(i, 1)" title="Remover"><i class="fa-solid fa-trash"></i></button></div>
                    </div>
                </template>
                <button type="button" class="btn btn-sm btn-outline-success" x-show="apoios.length < 5" @click="apoios.push({ nome: '', email: '', cpf: '' })"><i class="fa-solid fa-plus"></i> Adicionar pessoa de apoio</button>
            </div>

            </fieldset>
            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between mb-5">
                <a href="{{ auth()->check() ? route('propostas.minhas') : route('evento.publico.show', $chamada->evento->slug) }}" class="btn btn-light fw-bold"><i class="fa-solid fa-arrow-left me-1"></i> Cancelar</a>
                @auth
                    <button class="btn btn-primary-custom fw-bold"><i class="fa-solid fa-paper-plane me-1"></i> {{ $editando ? 'Salvar e reenviar' : 'Enviar proposta' }}</button>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary-custom fw-bold"><i class="fa-solid fa-right-to-bracket me-1"></i> Entrar para enviar</a>
                @endauth
            </div>
        </form>
    </div></div>
</x-layout-adaptavel>
