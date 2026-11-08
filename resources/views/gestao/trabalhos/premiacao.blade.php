@php $fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.'); $medalha = [1 => ['#facc15', '1º'], 2 => ['#cbd5e1', '2º'], 3 => ['#d6a06b', '3º']]; @endphp
<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-11">

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('gestao.trabalhos.ranking', $evento) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                    <div>
                        <h3 class="fw-bold m-0"><i class="fa-solid fa-trophy me-1" style="color:#ca8a04;"></i> Premiação</h3>
                        <small class="text-secondary">{{ $evento->titulo }} · 1º, 2º e 3º por categoria, pela nota geral ({{ $comApresentacao ? 'texto + apresentação' : 'texto' }})</small>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="sb" style="{{ $divulgada ? 'background:#dcfce7;color:#15803d;border-color:#86efac;' : 'background:#fef3c7;color:#b45309;border-color:#fcd34d;' }}">
                        <i class="fa-solid {{ $divulgada ? 'fa-eye' : 'fa-eye-slash' }}"></i> {{ $divulgada ? 'Divulgada aos autores' : 'Visível só para a gestão' }}
                    </span>
                    <form action="{{ route('gestao.trabalhos.premiacao.sugerir', $evento) }}" method="POST">@csrf
                        <button class="btn btn-sm btn-outline-primary" data-confirmar="Apurar a premiação pelo ranking? As escolhas atuais serão substituídas pela sugestão (top 3 de cada categoria)."><i class="fa-solid fa-wand-magic-sparkles"></i> Apurar pelo ranking</button>
                    </form>
                    <a href="{{ route('gestao.trabalhos.premiacao.imprimir', $evento) }}" target="_blank" class="btn btn-sm btn-tone-amber @if($atuais->isEmpty()) disabled @endif"><i class="fa-solid fa-print"></i> Imprimir todos os certificados</a>
                    @if($divulgada)
                        <form action="{{ route('gestao.trabalhos.premiacao.ocultar', $evento) }}" method="POST">@csrf <button class="btn btn-sm btn-outline-warning" data-confirmar="Ocultar a premiação dos autores?"><i class="fa-solid fa-eye-slash"></i> Ocultar dos autores</button></form>
                    @else
                        <form action="{{ route('gestao.trabalhos.premiacao.divulgar', $evento) }}" method="POST">@csrf <button class="btn btn-sm btn-success" @disabled($atuais->isEmpty()) data-confirmar="Divulgar a premiação? Os autores premiados passarão a ver o certificado em seu perfil."><i class="fa-solid fa-bullhorn"></i> Divulgar aos autores</button></form>
                    @endif
                </div>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

            <div class="alert alert-light border small">
                <i class="fa-solid fa-circle-info me-1"></i> Concorrem trabalhos <strong>aprovados nas duas partes</strong> e com nota geral, na categoria do próprio trabalho. O certificado já pode ser <strong>impresso pela gestão</strong>;
                o autor só o vê no perfil depois de <strong>Divulgar aos autores</strong>. Em empate de nota geral, vale a nota do texto, depois a da apresentação e, por fim, o protocolo; ajuste manualmente se a comissão decidir diferente.
            </div>

            <form action="{{ route('gestao.trabalhos.premiacao.salvar', $evento) }}" method="POST" data-validar>
                @csrf @method('PUT')
                <div class="row g-4">
                    @foreach($categorias as $tipo => $rotulo)
                        @php $ranking = $rankings[$tipo]; $atual = $atuais[$tipo] ?? collect(); @endphp
                        <div class="col-lg-6">
                            <div class="glass-card bg-white h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold m-0" style="color:#a16207;"><i class="fa-solid fa-medal me-1"></i> {{ $rotulo }}</h6>
                                    <span class="small text-muted">{{ $ranking->count() }} concorrente(s)</span>
                                </div>

                                @foreach([1, 2, 3] as $col)
                                    @php $premio = $atual->get($col); $sug = $sugestao[$tipo][$col] ?? null; @endphp
                                    <div class="mb-3">
                                        <label class="small fw-bold d-flex align-items-center gap-2" for="p-{{ $tipo }}-{{ $col }}">
                                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle fw-bold" style="width:26px;height:26px;background:{{ $medalha[$col][0] }};color:#422006;font-size:.75rem;">{{ $medalha[$col][1] }}</span>
                                            {{ \App\Models\Premiacao::COLOCACOES[$col] }}
                                            @if($premio) <span class="sb" style="background:#dcfce7;color:#15803d;border-color:#86efac;"><i class="fa-solid fa-check"></i> definido</span> @endif
                                        </label>
                                        <select name="escolhas[{{ $tipo }}][{{ $col }}]" id="p-{{ $tipo }}-{{ $col }}" class="form-select form-select-sm" @disabled($ranking->isEmpty())>
                                            <option value="">— sem premiado —</option>
                                            @foreach($ranking as $i => $t)
                                                <option value="{{ $t->id }}" @selected(($premio?->trabalho_id ?? null) === $t->id)>
                                                    {{ $i + 1 }}º no ranking · {{ $t->codigo }} · {{ \Illuminate\Support\Str::limit($t->titulo, 45) }} · nota {{ $fmt($t->nota_geral_calculada) }}{{ $t->empate ? ' (empate)' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if($premio)
                                            <div class="small mt-1 d-flex flex-wrap gap-2 align-items-center">
                                                <span class="text-muted">{{ $premio->trabalho->autores->pluck('nome_completo')->take(3)->implode(', ') }}</span>
                                                <a href="{{ route('gestao.trabalhos.premiacao.imprimir', [$evento, 'premiacao' => $premio->id]) }}" target="_blank" class="btn btn-sm btn-tone-amber py-0"><i class="fa-solid fa-print"></i> Imprimir ({{ $premio->certificados->count() }})</a>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach

                                @if($ranking->isEmpty())
                                    <div class="small text-muted"><i class="fa-solid fa-hourglass-half me-1"></i> Nenhum trabalho elegível nesta categoria ainda.</div>
                                @elseif($ranking->contains(fn ($t) => $t->empate))
                                    <div class="small text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i> Há empate de nota geral nesta categoria: confira a ordem.</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="text-end my-4">
                    <button class="btn btn-primary-custom fw-bold px-4"><i class="fa-regular fa-floppy-disk me-1"></i> Salvar premiação e gerar certificados</button>
                </div>
            </form>

            {{-- Modelo do certificado --}}
            <details class="glass-card bg-white mb-5" @if($errors->any()) open @endif>
                <summary class="fw-bold text-success" style="cursor:pointer;"><i class="fa-solid fa-certificate me-1"></i> Modelo do certificado de premiação</summary>
                <form action="{{ route('gestao.trabalhos.premiacao.modelo', $evento) }}" method="POST" class="mt-3">
                    @csrf @method('PUT')
                    <label class="small fw-bold text-secondary">Cabeçalho (vazio usa o do certificado de trabalhos)</label>
                    <textarea name="cert_premio_cabecalho" rows="2" class="form-control mb-3">{{ old('cert_premio_cabecalho', $config->cert_premio_cabecalho) }}</textarea>
                    <label class="small fw-bold text-secondary">Texto</label>
                    <textarea name="cert_premio_corpo" rows="5" class="form-control mb-1" placeholder="{{ \App\Models\TrabalhoConfig::CERT_PREMIO_CORPO_PADRAO }}">{{ old('cert_premio_corpo', $config->cert_premio_corpo) }}</textarea>
                    <div class="form-text mb-3">Variáveis: <code>{{ implode('  ', \App\Models\TrabalhoConfig::CERT_PREMIO_VARIAVEIS) }}</code>. Formatação: &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;br&gt;. Vazio usa o texto padrão.</div>
                    <label class="small fw-bold text-secondary">Rodapé</label>
                    <textarea name="cert_premio_rodape" rows="2" class="form-control mb-3">{{ old('cert_premio_rodape', $config->cert_premio_rodape) }}</textarea>
                    <button class="btn btn-sm btn-outline-success fw-bold"><i class="fa-regular fa-floppy-disk"></i> Salvar modelo</button>
                </form>
            </details>
        </div>
    </div>
</x-app-layout>
