<x-app-layout>
    <div class="row justify-content-center"><div class="col-xl-11">
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="{{ route('gestao.trabalhos.certificados.index', $evento) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <h3 class="fw-bold m-0">Certificados de avaliadores</h3>
                <small class="text-secondary">{{ $evento->titulo }}</small>
            </div>
        </div>

        @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
        @if($errors->any())     <div class="alert alert-danger small">{{ $errors->first() }}</div> @endif

        <form method="POST" action="{{ route('gestao.trabalhos.avaliadores-certificados.emitir', $evento) }}" class="glass-card bg-white mb-3">
            @csrf <input type="hidden" name="todos" value="1">
            <div class="row g-2 align-items-end">
                <div class="col-sm-4 col-md-3">
                    <label class="small fw-bold text-secondary" for="ch-todos">Carga horária (opcional)</label>
                    <input id="ch-todos" type="number" name="carga_horaria" min="1" max="1000" class="form-control form-control-sm" placeholder="Sem carga horária">
                </div>
                <div class="col-sm-8 col-md-9"><button class="btn btn-sm btn-primary-custom fw-bold"><i class="fa-solid fa-award"></i> Emitir para todos que concluíram</button></div>
            </div>
            <div class="form-text small">Só contam avaliações concluídas. O certificado informa a quantidade, sem títulos nem autores. A pessoa o encontra em Minha Agenda.</div>
        </form>

        <div class="glass-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="ps-4">Avaliador(a)</th><th>Fase</th><th>Concluídas</th><th>Pendentes</th><th class="text-end pe-4">Certificado</th></tr></thead>
                    <tbody>
                        @forelse($linhas as $l)
                            @php $c = $l->certificado; $valido = $c && !$c->revogado(); @endphp
                            <tr>
                                <td class="ps-4"><div class="fw-bold">{{ $l->usuario->name }}</div><small class="text-muted">{{ $l->usuario->email }}</small></td>
                                <td>{{ \App\Models\CertificadoAvaliador::FASES[$l->fase] }}</td>
                                <td>{{ $l->concluidas }}</td>
                                <td>{{ $l->pendentes }}</td>
                                <td class="text-end pe-4">
                                    @if($valido)
                                        <a href="{{ route('certificado.avaliador.download', $c->hash_validacao) }}" target="_blank" class="btn btn-sm btn-outline-success fw-bold"><i class="fa-solid fa-download"></i> PDF</a>
                                    @endif
                                    @if($l->concluidas > 0 && (!$valido || $c->quantidade !== $l->concluidas))
                                        <form method="POST" action="{{ route('gestao.trabalhos.avaliadores-certificados.emitir', $evento) }}">@csrf
                                            <input type="hidden" name="user_id" value="{{ $l->usuario->id }}"><input type="hidden" name="fase" value="{{ $l->fase }}">
                                            <button class="btn btn-sm btn-primary-custom fw-bold">{{ $valido ? 'Atualizar' : 'Emitir' }}</button>
                                        </form>
                                    @endif
                                    @if($valido)
                                        <form method="POST" action="{{ route('gestao.trabalhos.avaliadores-certificados.revogar', [$evento, $c]) }}" onsubmit="return confirm('Revogar este certificado? Ele deixa de valer.')">@csrf<button class="btn btn-sm btn-outline-danger">Revogar</button></form>
                                    @elseif($l->concluidas === 0)
                                        <span class="small text-muted">Aguardando concluir</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-5">Nenhum avaliador designado neste evento ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div></div>
</x-app-layout>
