<x-layout-adaptavel :titulo="$chamada->titulo" :descricao="\App\Support\HtmlSeguro::paraTexto($chamada->descricao) ?: ('Inscreva-se como voluntário(a) em ' . $chamada->evento->titulo . '.')" :imagem="$chamada->evento->bannerPublicoUrl() ?: $chamada->evento->logoPublicaUrl()" :url="route('voluntariado.create', $chamada)">
    <div class="row justify-content-center"><div class="col-xl-8">
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="{{ auth()->check() ? route('voluntariado.meu') : route('evento.publico.show', $chamada->evento->slug) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="min-w-0"><h3 class="fw-bold m-0 texto-quebra">{{ $chamada->titulo }}</h3><small class="text-secondary">Inscrição de voluntário(a) · {{ $chamada->evento->titulo }}</small></div>
        </div>

        <x-aviso-login acao="se inscrever como voluntário(a)" />
        @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>@endif

        @if($chamada->descricao || $chamada->requisitos)
            <div class="glass-card mb-4 small">
                @if($chamada->descricao)<div class="conteudo-rico mb-2">{!! \App\Support\HtmlSeguro::paraExibir($chamada->descricao) !!}</div>@endif
                @if($chamada->requisitos)<div class="mt-2"><strong>Requisitos:</strong><div class="conteudo-rico">{!! \App\Support\HtmlSeguro::paraExibir($chamada->requisitos) !!}</div></div>@endif
                <div class="mt-2 text-muted">{{ $chamada->vagas ? $chamada->vagas . ' vaga(s)' : 'Sem limite de vagas' }}{{ $chamada->fim ? ' · inscrições até ' . $chamada->fim->format('d/m/Y H:i') : '' }}</div>
            </div>
        @endif

        <form action="{{ route('voluntariado.store', $chamada) }}" method="POST" class="glass-card">
            @csrf
            @auth<div class="alert alert-light border small"><i class="fa-solid fa-user-check me-1 text-success"></i> Inscrevendo como <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).</div>@endauth
            <fieldset @disabled(!auth()->check()) style="border:0;padding:0;margin:0;min-width:0">
            <div class="mb-3"><label class="small fw-bold">Por que você quer ser voluntário(a)? *</label><x-editor-rico name="motivacao" :value="old('motivacao')" modo="simples" :altura="130" placeholder="Conte por que você quer ser voluntário(a) (mínimo de 20 caracteres)" /></div>
            @php $marcados = old('disponibilidade', []); $periodos = \App\Models\Voluntario::PERIODOS; @endphp
            <div class="mb-3" x-data="{ marcar(dia, tudo) { document.querySelectorAll('[data-dia=\'' + dia + '\']').forEach(c => c.checked = tudo) } }">
                <label class="small fw-bold">Dias e períodos em que pode ajudar *</label>
                <div class="form-text mb-2">Marque os períodos livres. A organização só vincula você a atividades que caibam na sua disponibilidade.</div>
                <style>
                    .disp-grid { display: grid; grid-template-columns: minmax(96px, 1.2fr) repeat(3, 1fr); align-items: stretch; }
                    .disp-grid > .disp-cel { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: .55rem .35rem; border-top: 1px solid #e2e8f0; text-align: center; }
                    .disp-grid > .disp-cab { border-top: 0; background: #f8fafc; font-weight: 600; font-size: .78rem; }
                    .disp-grid > .disp-dia { align-items: flex-start; text-align: left; padding-left: .75rem; }
                </style>
                <div class="border rounded-3 overflow-hidden">
                    <div class="disp-grid">
                        <div class="disp-cel disp-cab disp-dia">Dia</div>
                        @foreach($periodos as $p)<div class="disp-cel disp-cab">{{ $p['rotulo'] }}<div class="fw-normal text-muted" style="font-size:.68rem">{{ $p['faixa'] }}</div></div>@endforeach
                        @foreach($dias as $dia)
                            @php $c = \Illuminate\Support\Carbon::parse($dia); @endphp
                            <div class="disp-cel disp-dia"><div><strong>{{ $c->format('d/m') }}</strong> <span class="text-muted small">{{ $c->translatedFormat('D') }}</span></div>
                                <div style="font-size:.72rem"><a href="#" class="text-decoration-none" @click.prevent="marcar('{{ $dia }}', true)">dia todo</a> · <a href="#" class="text-secondary text-decoration-none" @click.prevent="marcar('{{ $dia }}', false)">limpar</a></div></div>
                            @foreach($periodos as $chave => $p)
                                <div class="disp-cel"><input type="checkbox" class="form-check-input" style="width:1.3rem;height:1.3rem" name="disponibilidade[{{ $dia }}][]" value="{{ $chave }}" data-dia="{{ $dia }}" aria-label="{{ $c->format('d/m') }} {{ $p['rotulo'] }}" @checked(in_array($chave, $marcados[$dia] ?? [], true))></div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="mb-3"><label class="small fw-bold">Habilidades ou experiências úteis</label><x-editor-rico name="habilidades" :value="old('habilidades')" modo="simples" :altura="100" placeholder="Ex.: fotografia, informática, atendimento ao público" /></div>
            <div class="mb-4"><label class="small fw-bold">Telefone / WhatsApp</label><input type="text" name="telefone" class="form-control" maxlength="15" value="{{ old('telefone') }}"></div>
            </fieldset>
            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between">
                <a href="{{ auth()->check() ? route('voluntariado.meu') : route('evento.publico.show', $chamada->evento->slug) }}" class="btn btn-light fw-bold">Cancelar</a>
                @auth
                    <button class="btn btn-primary-custom fw-bold"><i class="fa-solid fa-hand-holding-heart me-1"></i> Enviar inscrição</button>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary-custom fw-bold"><i class="fa-solid fa-right-to-bracket me-1"></i> Entrar para se inscrever</a>
                @endauth
            </div>
        </form>
    </div></div>
</x-layout-adaptavel>
