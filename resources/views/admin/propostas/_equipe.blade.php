{{-- Outros ministrantes e ajudantes da proposta, com a atribuição validada pela organização. Precisa de $proposta e $evento. --}}
<div class="glass-card {{ $classe ?? '' }}" id="equipe-proposta">
    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-people-carry-box me-1"></i> Outros ministrantes e ajudantes</h6>
    @forelse($proposta->apoios as $ap)
        <div class="border rounded-3 p-3 mb-2">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div><div class="fw-bold">{{ $ap->nome }}</div><div class="small text-muted">{{ $ap->email ?: 'sem e-mail' }} · {{ $ap->cpf ?: 'sem CPF' }}</div>
                    <form action="{{ route('admin.propostas.apoio-funcao', [$evento->id, $ap->id]) }}" method="POST" class="d-flex align-items-center gap-2 mt-2">
                        @csrf
                        <label class="small fw-bold text-secondary" for="funcao{{ $ap->id }}">Atribuição</label>
                        <select name="funcao" id="funcao{{ $ap->id }}" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
                            @foreach(\App\Models\PropostaApoio::FUNCOES as $valor => $rotulo)<option value="{{ $valor }}" @selected($ap->funcao === $valor)>{{ $rotulo }}</option>@endforeach
                        </select>
                    </form></div>
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
        <p class="small text-muted mb-0">O proponente não indicou outras pessoas.</p>
    @endforelse
    <div class="form-text">"Adicionar ao evento" cadastra a pessoa como participante e a inscreve no evento (para receber presença e certificado). Ao emitir o certificado de ministrante, os demais ministrantes também recebem o deles.</div>
</div>
