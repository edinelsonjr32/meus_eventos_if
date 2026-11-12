<x-app-layout>
    <div class="row justify-content-center"><div class="col-xl-10">
        <h3 class="fw-bold mb-1">Voluntariado</h3>
        <p class="text-secondary mb-4">Inscreva-se como voluntário(a), veja em quais atividades você atua e quem procurar, e baixe o certificado.</p>

        @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
        @unless($participante)
            <div class="alert alert-warning"><i class="fa-solid fa-id-card me-1"></i> Para se inscrever, complete seu cadastro com o CPF em <a href="{{ route('profile.edit') }}" class="fw-bold">Minha Conta</a>.</div>
        @endunless

        <h5 class="fw-bold mb-3"><i class="fa-solid fa-hand-holding-heart text-success me-2"></i> Chamadas abertas</h5>
        <div class="row g-3 mb-4">
            @forelse($chamadas as $c)
                <div class="col-md-6"><div class="glass-card h-100 d-flex flex-column">
                    <div class="small text-secondary">{{ $c->evento->titulo }}</div>
                    <div class="fw-bold texto-quebra">{{ $c->titulo }}</div>
                    <div class="small text-muted mb-2">{{ $c->vagas ? $c->vagas . ' vaga(s)' : 'sem limite de vagas' }}{{ $c->fim ? ' · até ' . $c->fim->format('d/m/Y H:i') : '' }}</div>
                    @if($c->descricao)<p class="small texto-quebra mb-3">{{ \Illuminate\Support\Str::limit(\App\Support\HtmlSeguro::paraTexto($c->descricao), 200) }}</p>@endif
                    @if($inscricoes->where('chamada_id', $c->id)->where('status', '!=', 'desistente')->isNotEmpty())
                        <span class="small text-success fw-bold mt-auto"><i class="fa-solid fa-circle-check me-1"></i> Você já está inscrito(a)</span>
                    @else
                        <a href="{{ route('voluntariado.create', $c) }}" class="btn btn-primary-custom btn-sm mt-auto"><i class="fa-solid fa-hand-holding-heart"></i> Quero ser voluntário(a)</a>
                    @endif
                </div></div>
            @empty
                <div class="col-12"><div class="glass-card small text-muted">Nenhuma chamada de voluntários aberta no momento.</div></div>
            @endforelse
        </div>

        <h5 class="fw-bold mb-3"><i class="fa-solid fa-list-check text-success me-2"></i> Minhas inscrições</h5>
        @forelse($inscricoes as $i)
            @php $equipe = $certificados->get($i->id); @endphp
            <div class="glass-card mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div class="min-w-0"><div class="fw-bold texto-quebra">{{ $i->evento->titulo }}</div><div class="small text-secondary">{{ $i->chamada->titulo }} · inscrita em {{ $i->created_at->format('d/m/Y') }}</div></div>
                    <x-badge tipo="voluntario" :valor="$i->status" />
                </div>
                @if($i->disponibilidadeResumo())<div class="small text-secondary mt-2"><i class="fa-regular fa-calendar-check me-1"></i> <strong>Sua disponibilidade:</strong> {{ $i->disponibilidadeResumo() }}</div>@endif
                @if($i->parecer)<div class="alert alert-light border small mt-3 mb-0 texto-quebra"><strong>Mensagem da organização:</strong> {{ $i->parecer }}</div>@endif

                @if($i->status === 'aprovado')
                    <div class="mt-3">
                        <div class="small fw-bold text-secondary mb-2">Onde você atua</div>
                        @forelse($i->demandas as $d)
                            <div class="border rounded-3 p-3 mb-2">
                                <div class="fw-bold texto-quebra">{{ $d->titulo }}</div>
                                <div class="small text-secondary">
                                    @if($d->data_inicio)<i class="fa-regular fa-calendar me-1"></i> {{ $d->data_inicio->format('d/m/Y H:i') }}@if($d->data_fim) a {{ $d->data_fim->format('H:i') }}@endif @endif
                                    @if($d->local) · <i class="fa-solid fa-location-dot me-1"></i> {{ $d->local }}@endif · {{ rtrim(rtrim(number_format($d->carga_horaria, 1, ',', ''), '0'), ',') }} h
                                </div>
                                @if($d->descricao)<div class="small mt-1 texto-quebra">{{ $d->descricao }}</div>@endif
                                @if($d->responsavel_nome)
                                    <div class="small mt-2 p-2 rounded-3" style="background:#ecfdf5;"><i class="fa-solid fa-user-tie me-1 text-success"></i> <strong>Quem procurar:</strong> {{ $d->responsavel_nome }}@if($d->responsavel_contato) · {{ $d->responsavel_contato }}@endif</div>
                                @endif
                            </div>
                        @empty
                            <div class="small text-muted">A organização ainda não vinculou você a atividades. Você será avisado(a).</div>
                        @endforelse
                    </div>
                @endif

                <div class="d-flex flex-wrap gap-2 mt-3">
                    @if($equipe && $equipe->certificadoEmitido())
                        <a href="{{ route('voluntariado.certificado', $i) }}" class="btn btn-sm btn-primary-custom"><i class="fa-solid fa-award"></i> Baixar certificado de voluntário</a>
                    @elseif($i->status === 'aprovado')
                        <span class="small text-muted"><i class="fa-regular fa-clock me-1"></i> O certificado é emitido pela organização depois do evento.</span>
                    @endif
                    @if(in_array($i->status, ['inscrito', 'aprovado', 'lista_espera']))
                        <form action="{{ route('voluntariado.desistir', $i) }}" method="POST" onsubmit="return confirm('Desistir do voluntariado neste evento?')">@csrf<button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-ban"></i> Desistir</button></form>
                    @endif
                </div>
            </div>
        @empty
            <div class="glass-card text-center py-5 text-muted">Você ainda não se inscreveu como voluntário(a).</div>
        @endforelse
    </div></div>
</x-app-layout>
