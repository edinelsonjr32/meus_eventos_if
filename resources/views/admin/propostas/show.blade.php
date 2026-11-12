@php
    $a = $proposta->atividade;
    $v = fn ($campo, $padrao = null) => old($campo, $padrao);
@endphp
<x-app-layout>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.propostas.index', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="min-w-0">
                <h3 class="fw-bold m-0 texto-quebra">{{ $proposta->titulo }}</h3>
                <small class="text-secondary">{{ $proposta->tipoRotulo() }} · {{ $evento->titulo }}</small>
            </div>
        </div>
        <x-badge tipo="proposta" :valor="$proposta->status" />
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div> @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="glass-card mb-4">
                <h6 class="fw-bold text-success mb-3">Resumo da proposta</h6>
                <div class="conteudo-rico mb-3">{!! \App\Support\HtmlSeguro::paraExibir($proposta->resumo) !!}</div>
                <dl class="row small mb-0">
                    <dt class="col-sm-4 text-secondary">Proponente</dt><dd class="col-sm-8">{{ $proposta->proponente->name }} · {{ $proposta->proponente->email }}@if($proposta->participante) · CPF {{ $proposta->participante->cpf }}@endif</dd>
                    <dt class="col-sm-4 text-secondary">Carga horária prevista</dt><dd class="col-sm-8">{{ $proposta->carga_horaria_prevista }} h</dd>
                    <dt class="col-sm-4 text-secondary">Vagas sugeridas</dt><dd class="col-sm-8">{{ $proposta->vagas_sugeridas }}</dd>
                    <dt class="col-sm-4 text-secondary">Público-alvo</dt><dd class="col-sm-8">{{ $proposta->publico_alvo ?: '—' }}</dd>
                    <dt class="col-sm-4 text-secondary">Laboratório</dt><dd class="col-sm-8">{{ $proposta->requer_laboratorio ? 'Sim' : 'Não' }}@if($proposta->espaco_solicitado) · {{ $proposta->espaco_solicitado }}@endif</dd>
                    <dt class="col-sm-4 text-secondary">Equipamentos</dt><dd class="col-sm-8 conteudo-rico">{!! \App\Support\HtmlSeguro::paraExibir($proposta->equipamentos_solicitados) ?: '—' !!}</dd>
                </dl>
                @foreach(['objetivos' => 'Objetivos', 'conteudo_programatico' => 'Conteúdo programático', 'metodologia' => 'Metodologia', 'observacoes' => 'Observações do proponente'] as $campo => $rotulo)
                    @if($proposta->$campo)<div class="mt-3"><div class="small fw-bold text-secondary">{{ $rotulo }}</div><div class="small conteudo-rico">{!! \App\Support\HtmlSeguro::paraExibir($proposta->$campo) !!}</div></div>@endif
                @endforeach
            </div>

            <div class="glass-card">
                <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-people-carry-box me-1"></i> Pessoas de apoio (ajudantes)</h6>
                @forelse($proposta->apoios as $ap)
                    <div class="border rounded-3 p-3 mb-2">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div><div class="fw-bold">{{ $ap->nome }}</div><div class="small text-muted">{{ $ap->email ?: 'sem e-mail' }} · {{ $ap->cpf ?: 'sem CPF' }}</div></div>
                            @if($ap->noEvento())
                                <span class="sb sb-ok"><i class="fa-solid fa-circle-check"></i> No evento desde {{ $ap->adicionado_ao_evento_em->format('d/m/Y') }}</span>
                            @else
                                <form action="{{ route('admin.propostas.apoio-evento', [$evento->id, $ap->id]) }}" method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                                    @csrf
                                    @unless($ap->cpf)<input type="text" name="cpf" class="form-control form-control-sm" style="width:150px" placeholder="CPF" maxlength="14">@endunless
                                    @unless($ap->email)<input type="email" name="email" class="form-control form-control-sm" style="width:190px" placeholder="E-mail">@endunless
                                    <button class="btn btn-sm btn-outline-success fw-bold"><i class="fa-solid fa-user-plus"></i> Adicionar ao evento</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="small text-muted mb-0">O proponente não indicou pessoas de apoio.</p>
                @endforelse
                <div class="form-text">"Adicionar ao evento" cadastra a pessoa como participante e a inscreve no evento (para receber presença e certificado).</div>
            </div>
        </div>

        <div class="col-lg-5">
            {{-- Programação vinculada --}}
            @if($a)
                <div class="glass-card mb-4 border border-success">
                    <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-calendar-check me-1"></i> Na programação</h6>
                    <div class="small">
                        <div><strong>{{ $a->data_inicio->format('d/m/Y H:i') }}</strong> a {{ $a->data_fim->format('H:i') }}</div>
                        <div><i class="fa-solid fa-location-dot me-1"></i> {{ $a->local }}@if($a->espaco) · <strong>{{ $a->espaco }}</strong>@endif</div>
                        <div>Capacidade: {{ $a->vagas }} · {{ $a->inscritos()->count() }} inscrito(s) · {{ $a->carga_horaria }} h</div>
                        @if($a->equipamentos)<div class="mt-1">Equipamentos: {{ $a->equipamentos }}</div>@endif
                    </div>
                    @if($proposta->atividadeConcluida())
                        <form action="{{ route('admin.propostas.certificado', [$evento->id, $proposta->id]) }}" method="POST" class="mt-3">@csrf
                            <button class="btn btn-sm btn-primary-custom w-100"><i class="fa-solid fa-award"></i> Emitir certificado de ministrante</button>
                        </form>
                    @else
                        <div class="small text-muted mt-2"><i class="fa-regular fa-clock me-1"></i> O certificado de ministrante fica disponível depois que a atividade terminar.</div>
                    @endif
                </div>
            @endif

            {{-- Decisão --}}
            @php $decidida = in_array($proposta->status, ['aprovada', 'reprovada', 'ajustes'], true); @endphp
            <div class="glass-card" x-data="{ decisao: '{{ old('decisao', 'aprovada') }}', aberto: {{ ($errors->any() || old('decisao') || !$decidida) && $proposta->status !== 'retirada' ? 'true' : 'false' }} }">
                <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-gavel me-1"></i> Decisão da organização</h6>

                @if($proposta->status === 'retirada')
                    <div class="alert alert-secondary small mb-0"><i class="fa-solid fa-ban me-1"></i> Proposta retirada pelo proponente: não há decisão a tomar.</div>
                @else
                    @if($decidida)
                        <div class="small mb-3">Situação atual: <x-badge tipo="proposta" :valor="$proposta->status" />@if($proposta->decidido_em) <span class="text-muted ms-1">em {{ $proposta->decidido_em->format('d/m/Y H:i') }}</span>@endif</div>
                    @endif
                    @if($proposta->parecer && $proposta->status !== 'enviada')<div class="alert alert-light border small texto-quebra"><strong>Última mensagem enviada:</strong><br>{{ $proposta->parecer }}</div>@endif

                    @if($decidida)
                        <div x-show="!aberto"><button type="button" class="btn btn-outline-primary w-100 fw-bold" @click="aberto = true"><i class="fa-solid fa-arrows-rotate me-1"></i> Mudar status</button></div>
                    @endif
                    <div x-show="aberto" x-cloak>
                <form action="{{ route('admin.propostas.decisao', [$evento->id, $proposta->id]) }}" method="POST">
                    @csrf
                    <select name="decisao" class="form-select mb-3" x-model="decisao">
                        <option value="aprovada">Aprovar e incluir na programação</option>
                        <option value="ajustes">Pedir ajustes ao proponente</option>
                        <option value="reprovada">Não aprovar</option>
                        <option value="em_analise">Marcar como em análise</option>
                    </select>

                    <div x-show="decisao === 'aprovada'" x-cloak>
                        <div class="small fw-bold text-secondary mb-2">Programação da atividade</div>
                        <div class="row g-2">
                            <div class="col-6"><label class="small">Início *</label><input type="datetime-local" name="data_inicio" class="form-control form-control-sm" value="{{ $v('data_inicio', $a?->data_inicio?->format('Y-m-d\TH:i')) }}"></div>
                            <div class="col-6"><label class="small">Término *</label><input type="datetime-local" name="data_fim" class="form-control form-control-sm" value="{{ $v('data_fim', $a?->data_fim?->format('Y-m-d\TH:i')) }}"></div>
                            <div class="col-12"><label class="small">Local *</label>
                                <select name="local_id" class="form-select form-select-sm">
                                    <option value="">Escolha o local…</option>
                                    @foreach($locais as $lc)<option value="{{ $lc->id }}" @selected((int) $v('local_id', $a?->local_id) === $lc->id)>{{ $lc->rotulo() }}{{ $lc->capacidade ? ' · ' . $lc->capacidade . ' pessoas' : '' }}</option>@endforeach
                                </select>
                                <div class="form-text">@if($proposta->requer_laboratorio && $proposta->espaco_solicitado)Pedido do proponente: <strong>{{ $proposta->espaco_solicitado }}</strong>. @endif Dois eventos no mesmo local e horário são barrados. <a href="{{ route('admin.locais.index', $evento->id) }}" target="_blank">Gerenciar locais</a></div></div>
                            <div class="col-6"><label class="small">Capacidade máxima *</label><input type="number" name="vagas" min="1" class="form-control form-control-sm" value="{{ $v('vagas', $a?->vagas ?? $proposta->vagas_sugeridas) }}"></div>
                            <div class="col-6"><label class="small">Carga horária (h) *</label><input type="number" name="carga_horaria" min="1" class="form-control form-control-sm" value="{{ $v('carga_horaria', $a?->carga_horaria ?? $proposta->carga_horaria_prevista) }}"></div>
                            <div class="col-12"><label class="small">Equipamentos que serão disponibilizados</label><textarea name="equipamentos" rows="2" class="form-control form-control-sm">{{ $v('equipamentos', $a?->equipamentos ?? $proposta->equipamentos_solicitados) }}</textarea></div>
                            <div class="col-12"><label class="small">Observações da organização (internas)</label><textarea name="observacoes_organizacao" rows="2" class="form-control form-control-sm">{{ $v('observacoes_organizacao', $a?->observacoes_organizacao) }}</textarea></div>
                        </div>
                        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="ignorar_conflito" value="1" id="ic"><label class="form-check-label small" for="ic">Aprovar mesmo com conflito de local/horário ou capacidade</label></div>
                    </div>

                    <label class="small fw-bold text-secondary mt-3" x-text="decisao === 'aprovada' ? 'Mensagem ao proponente (opcional)' : 'Mensagem ao proponente (obrigatória)'"></label>
                    <textarea name="parecer" rows="3" class="form-control form-control-sm mb-3" placeholder="Orientações, ajustes pedidos ou motivo da decisão">{{ old('parecer') }}</textarea>
                    <button class="btn btn-primary-custom w-100 fw-bold" onclick="return confirm('Registrar a decisão? O proponente será avisado por e-mail.')">Registrar decisão</button>
                </form>
                    @if($decidida)<button type="button" class="btn btn-link btn-sm text-secondary w-100 mt-1" @click="aberto = false">Cancelar</button>@endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
