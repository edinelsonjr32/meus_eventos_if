@php
    $metaAvaliadores = $trabalho->evento->trabalhoConfig?->avaliadores_por_trabalho ?? 2;
    $ativas = $trabalho->avaliacoes->where('status', '!=', 'recusada');
@endphp

@if(session('erros_lote'))
    <div class="alert alert-warning small">
        <ul class="mb-0 ps-3">@foreach(session('erros_lote') as $erro) <li>{{ $erro }}</li> @endforeach</ul>
    </div>
@endif

{{-- Triagem --}}
<div class="glass-card bg-white mb-4">
    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-filter me-1"></i> Triagem</h6>

    @if($acoesTriagem->isEmpty())
        <p class="small text-muted m-0">Nenhuma ação de triagem disponível para o status atual ({{ $trabalho->statusRotulo() }}).</p>
    @else
        <form action="{{ route('gestao.trabalhos.triagem', $trabalho) }}" method="POST">
            @csrf
            @if($acoesTriagem->contains(true))
                <label class="small fw-bold text-secondary">Justificativa (obrigatória para devolver ou rejeitar)</label>
                <textarea name="justificativa" rows="3" class="form-control form-control-sm mb-2" maxlength="3000" placeholder="Explique ao autor o que precisa ser ajustado ou o motivo da rejeição">{{ old('justificativa') }}</textarea>
            @endif

            <div class="d-grid gap-2">
                @foreach($acoesTriagem as $para => $exigeJustificativa)
                    @php
                        $rotulos = [
                            'em_triagem' => ['Iniciar triagem', 'btn-primary-custom', 'fa-play'],
                            'em_avaliacao' => ['Encaminhar para avaliação', 'btn-success', 'fa-share'],
                            'devolvido_triagem' => ['Devolver ao autor para ajustes', 'btn-warning', 'fa-rotate-left'],
                            'rejeitado_triagem' => ['Rejeitar na triagem', 'btn-outline-danger', 'fa-ban'],
                        ][$para];
                    @endphp
                    <button type="submit" name="para" value="{{ $para }}" class="btn btn-sm {{ $rotulos[1] }} fw-bold"
                            @if($para === 'rejeitado_triagem') onclick="return confirm('Rejeitar este trabalho? O autor será avisado por e-mail.')" @endif>
                        <i class="fa-solid {{ $rotulos[2] }} me-1"></i> {{ $rotulos[0] }}
                    </button>
                @endforeach
            </div>
        </form>
    @endif
</div>

{{-- Avaliadores --}}
<div class="glass-card bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-success m-0"><i class="fa-solid fa-user-check me-1"></i> Avaliadores</h6>
        <span class="badge {{ $ativas->count() >= $metaAvaliadores ? 'bg-success' : 'bg-warning text-dark' }}">{{ $ativas->count() }}/{{ $metaAvaliadores }}</span>
    </div>

    @forelse($trabalho->avaliacoes as $av)
        <div class="d-flex justify-content-between align-items-start small mb-2 pb-2 border-bottom">
            <div>
                <div class="fw-bold">{{ $av->avaliador->name }}</div>
                <div class="text-muted">{{ $av->statusRotulo() }}@if($av->prazo) · prazo {{ $av->prazo->format('d/m/Y') }} @endif</div>
            </div>
            @if($av->status === 'pendente')
                <form action="{{ route('gestao.trabalhos.avaliadores.destroy', [$trabalho, $av]) }}" method="POST" onsubmit="return confirm('Remover este avaliador?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-light text-danger" title="Remover"><i class="fa-solid fa-xmark"></i></button>
                </form>
            @endif
        </div>
    @empty
        <p class="small text-muted">Nenhum avaliador atribuído.</p>
    @endforelse

    @if($podeAtribuir)
        <form action="{{ route('gestao.trabalhos.avaliadores.store', $trabalho) }}" method="POST" class="mt-3">
            @csrf
            <label class="small fw-bold text-secondary">Atribuir avaliadores</label>
            @forelse($avaliadores as $u)
                <div class="form-check small">
                    <input class="form-check-input" type="checkbox" name="avaliadores[]" value="{{ $u->id }}" id="av{{ $u->id }}" @disabled($u->conflito)>
                    <label class="form-check-label {{ $u->conflito ? 'text-muted' : '' }}" for="av{{ $u->id }}">
                        {{ $u->name }} <span class="text-muted">({{ $u->carga }} em aberto)</span>
                        @if($u->conflito) <span class="text-danger">— conflito: {{ $u->conflito }}</span> @endif
                    </label>
                </div>
            @empty
                <p class="small text-muted">Não há avaliadores disponíveis. Designe avaliadores em "Papéis" do evento.</p>
            @endforelse

            @if($avaliadores->isNotEmpty())
                <label class="small fw-bold text-secondary mt-2">Prazo (opcional)</label>
                <input type="date" name="prazo" class="form-control form-control-sm mb-2" min="{{ now()->addDay()->format('Y-m-d') }}">
                <button class="btn btn-sm btn-outline-success w-100 fw-bold"><i class="fa-solid fa-user-plus me-1"></i> Atribuir selecionados</button>
            @endif
        </form>
    @else
        <p class="small text-muted mt-2 mb-0">Atribuição disponível apenas em triagem ou avaliação.</p>
    @endif
</div>


{{-- Resultado das avaliações --}}
@if($trabalho->avaliacoes->isNotEmpty())
<div class="glass-card bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-success m-0"><i class="fa-solid fa-chart-simple me-1"></i> Resultado das avaliações</h6>
        @if($resultado['rodada'] > 1) <span class="badge bg-info text-dark">Rodada {{ $resultado['rodada'] }}</span> @endif
    </div>

    <div class="d-flex gap-3 mb-3">
        <div class="text-center flex-fill border rounded-3 p-2">
            <div class="small text-muted">Nota média</div>
            <div class="fs-4 fw-bold {{ $resultado['media'] !== null && $resultado['media'] >= $resultado['notaMinima'] ? 'text-success' : 'text-dark' }}">
                {{ $resultado['media'] !== null ? number_format($resultado['media'], 2, ',', '.') : '—' }}
            </div>
            <div class="small text-muted">mínimo {{ rtrim(rtrim(number_format($resultado['notaMinima'], 2, ',', ''), '0'), ',') }}</div>
        </div>
        <div class="text-center flex-fill border rounded-3 p-2">
            <div class="small text-muted">Concluídas</div>
            <div class="fs-4 fw-bold">{{ $resultado['concluidas'] }}/{{ $trabalho->avaliacoes->where('rodada', $resultado['rodada'])->where('status', '!=', 'recusada')->count() }}</div>
        </div>
    </div>

    @if($resultado['divergenciaAlta'])
        <div class="alert alert-warning small py-2">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            Divergência alta entre avaliadores ({{ number_format($resultado['divergencia'], 2, ',', '.') }} pontos).
            Considere atribuir um terceiro avaliador.
        </div>
    @endif

    @foreach($trabalho->avaliacoes->where('rodada', $resultado['rodada']) as $av)
        <details class="small mb-2 border rounded-3 p-2">
            <summary class="fw-bold">
                {{ $av->avaliador->name }}
                <span class="fw-normal text-muted">
                    — {{ $av->statusRotulo() }}@if($av->status === 'concluida'), nota {{ number_format($av->nota_final, 2, ',', '.') }}, {{ $av->recomendacaoRotulo() }}@endif
                </span>
            </summary>
            @if($av->status === 'concluida')
                <table class="table table-sm mt-2 mb-2">
                    @foreach($av->notas as $n)
                        <tr><td>{{ $n->criterio->nome }}</td><td class="text-end">{{ rtrim(rtrim(number_format($n->nota, 2, ',', ''), '0'), ',') }}/{{ rtrim(rtrim(number_format($n->criterio->nota_max, 2, ',', ''), '0'), ',') }}</td></tr>
                        @if($n->comentario) <tr><td colspan="2" class="text-muted">{{ $n->comentario }}</td></tr> @endif
                    @endforeach
                </table>
                <div class="fw-bold">Parecer</div>
                <p style="white-space: pre-line;">{{ $av->parecer }}</p>
                @if($av->parecer_confidencial)
                    <div class="fw-bold text-danger">Confidencial (só gestão)</div>
                    <p style="white-space: pre-line;">{{ $av->parecer_confidencial }}</p>
                @endif
            @elseif($av->status === 'recusada')
                <p class="mt-2 mb-0"><strong>Motivo da recusa:</strong> {{ $av->parecer_confidencial }}</p>
            @else
                <p class="mt-2 mb-0 text-muted">Ainda sem parecer.</p>
            @endif
        </details>
    @endforeach

    <div class="d-grid gap-2 mt-3">
        @if($resultado['podeEncerrar'])
            <form action="{{ route('gestao.trabalhos.encerrar', $trabalho) }}" method="POST"
                  onsubmit="return confirm('Encerrar a avaliação sem aguardar os demais avaliadores?')">
                @csrf <button class="btn btn-sm btn-outline-secondary w-100 fw-bold">Encerrar avaliação agora</button>
            </form>
        @endif
        @if($resultado['podeReavaliar'])
            <form action="{{ route('gestao.trabalhos.reavaliar', $trabalho) }}" method="POST">
                @csrf <button class="btn btn-sm btn-outline-primary w-100 fw-bold">Enviar versão corrigida aos mesmos avaliadores</button>
            </form>
        @endif
    </div>
</div>
@endif

{{-- Decisão --}}
@if($resultado['podeDecidir'])
<div class="glass-card bg-white mb-4" x-data="{ decisao: '{{ old('decisao', $resultado['media'] !== null && $resultado['media'] >= $resultado['notaMinima'] ? 'aprovado' : 'reprovado') }}' }">
    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-gavel me-1"></i> Decisão final</h6>
    @if($resultado['media'] !== null)
        <p class="small text-muted">Sugestão pela nota: {{ $resultado['media'] >= $resultado['notaMinima'] ? 'aprovar' : 'reprovar' }} (média {{ number_format($resultado['media'], 2, ',', '.') }}). A decisão é sempre sua.</p>
    @endif
    <form action="{{ route('gestao.trabalhos.decisao', $trabalho) }}" method="POST">
        @csrf
        <select name="decisao" class="form-select form-select-sm mb-2" x-model="decisao">
            <option value="aprovado">Aprovar</option>
            <option value="aprovado_com_ressalvas">Aprovar com ressalvas (solicita correção)</option>
            <option value="reprovado">Reprovar</option>
        </select>
        <div class="mb-2">
            <label class="small fw-bold text-secondary">Prazo para correção <span class="fw-normal">(obrigatório ao aprovar com ressalvas)</span></label>
            <input type="date" name="prazo_correcao" class="form-control form-control-sm" min="{{ now()->addDay()->format('Y-m-d') }}" value="{{ old('prazo_correcao') }}">
        </div>
        <label class="small fw-bold text-secondary">Mensagem ao autor <span class="fw-normal">(obrigatória ao reprovar ou aprovar com ressalvas)</span></label>
        <textarea name="justificativa" rows="4" class="form-control form-control-sm mb-2" placeholder="Orientações, ressalvas ou motivo da decisão">{{ old('justificativa') }}</textarea>
        <button class="btn btn-sm btn-primary-custom w-100 fw-bold" onclick="return confirm('Registrar a decisão? Os autores serão avisados por e-mail.')">Registrar decisão</button>
    </form>
</div>
@elseif($trabalho->decisao)
<div class="glass-card bg-white mb-4">
    <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-gavel me-1"></i> Decisão registrada</h6>
    <p class="small mb-1"><strong>{{ ['aprovado' => 'Aprovado', 'aprovado_com_ressalvas' => 'Aprovado com ressalvas', 'reprovado' => 'Reprovado'][$trabalho->decisao] }}</strong>
        em {{ $trabalho->decisao_em?->format('d/m/Y H:i') }}</p>
    @if($trabalho->decisao_justificativa) <p class="small text-muted" style="white-space: pre-line;">{{ $trabalho->decisao_justificativa }}</p> @endif

    @if($trabalho->status === 'aguardando_correcao')
        <p class="small mb-2">Prazo de correção: <strong>{{ $trabalho->prazo_correcao?->format('d/m/Y') }}</strong></p>
        <form action="{{ route('gestao.trabalhos.prorrogar', $trabalho) }}" method="POST" class="d-flex gap-2">
            @csrf
            <input type="date" name="prazo_correcao" class="form-control form-control-sm" min="{{ now()->addDay()->format('Y-m-d') }}" required>
            <button class="btn btn-sm btn-outline-secondary fw-bold text-nowrap">Prorrogar</button>
        </form>
    @endif
</div>
@endif


{{-- Apresentação e certificados --}}
@if($trabalho->status === 'aprovado' || $trabalho->certificados->isNotEmpty())
<div class="glass-card bg-white mb-4">
    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-person-chalkboard me-1"></i> Apresentação</h6>

    <div class="small mb-2">
        @if($trabalho->apresentado)
            <span class="badge bg-success">Apresentado</span>
            <span class="text-muted">{{ $trabalho->apresentado_em?->format('d/m/Y H:i') }} · {{ $trabalho->apresentacao_origem === 'frequencia' ? 'confirmado pela frequência' : 'registrado pela gestão' }}</span>
        @else
            <span class="badge bg-secondary">Não apresentado</span>
        @endif
    </div>

    @if($trabalho->status === 'aprovado')
        <form action="{{ route('gestao.trabalhos.apresentacao', $trabalho) }}" method="POST" class="mb-2">
            @csrf
            <label class="small fw-bold text-secondary">Sessão de apresentação</label>
            <div class="d-flex gap-2">
                <select name="atividade_id" class="form-select form-select-sm">
                    <option value="">— sem sessão —</option>
                    @foreach($certificacao['atividades'] as $at)
                        <option value="{{ $at->id }}" @selected($trabalho->atividade_id === $at->id)>{{ $at->titulo }} ({{ $at->data_inicio->format('d/m H:i') }})</option>
                    @endforeach
                </select>
                <button name="acao" value="sessao" class="btn btn-sm btn-outline-secondary fw-bold">Salvar</button>
            </div>
            <div class="form-text">Com sessão definida, a presença do autor apresentador na atividade confirma a apresentação automaticamente.</div>
        </form>

        <form action="{{ route('gestao.trabalhos.apresentacao', $trabalho) }}" method="POST">
            @csrf
            @if($trabalho->apresentado)
                <button name="acao" value="desmarcar" class="btn btn-sm btn-light text-danger w-100" onclick="return confirm('Desmarcar a apresentação?')">Desmarcar apresentação</button>
            @else
                <button name="acao" value="marcar" class="btn btn-sm btn-success w-100 fw-bold"><i class="fa-solid fa-check me-1"></i> Marcar como apresentado</button>
            @endif
        </form>
    @endif
</div>

<div class="glass-card bg-white mb-4">
    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-award me-1"></i> Certificados</h6>

    @forelse($trabalho->certificados as $c)
        <div class="small mb-2 pb-2 border-bottom">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div class="fw-bold">{{ $c->autor->nome_completo }}</div>
                    <div class="text-muted">{{ $c->funcaoRotulo() }} · {{ $c->hash_validacao }} · {{ $c->downloads }} download(s)</div>
                    @if($c->revogado()) <span class="badge bg-danger">Revogado: {{ $c->revogado_motivo }}</span> @endif
                </div>
                @unless($c->revogado())
                    <a href="{{ route('certificado.trabalho.download', $c->hash_validacao) }}" target="_blank" class="btn btn-sm btn-light" title="Baixar"><i class="fa-solid fa-download"></i></a>
                @endunless
            </div>
            @unless($c->revogado())
                <form action="{{ route('gestao.trabalhos.certificados.revogar', $c) }}" method="POST" class="d-flex gap-1 mt-1">
                    @csrf
                    <input type="text" name="motivo" class="form-control form-control-sm" placeholder="Motivo da revogação" maxlength="250" required>
                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Revogar este certificado?')">Revogar</button>
                </form>
            @endunless
        </div>
    @empty
        <p class="small text-muted">Nenhum certificado emitido.</p>
    @endforelse

    @if($certificacao['motivo'])
        <div class="alert alert-light border small py-2 mb-0">Ainda não elegível: {{ $certificacao['motivo'] }}.</div>
    @elseif($trabalho->certificados->count() < $trabalho->autores->count())
        <form action="{{ route('gestao.trabalhos.certificados.emitir', $trabalho) }}" method="POST">
            @csrf
            <button class="btn btn-sm btn-primary-custom w-100 fw-bold"><i class="fa-solid fa-paper-plane me-1"></i> Emitir certificados e avisar autores</button>
        </form>
    @endif
</div>
@endif
