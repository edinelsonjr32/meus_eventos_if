{{-- Escolha das atividades que entram no PDF dos QR Codes. Precisa de $evento. --}}
@php $lista = $evento->atividades->sortBy('data_inicio')->values(); @endphp
<div class="modal fade" id="modalQrcodes" tabindex="-1" aria-labelledby="modalQrcodesTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="GET" action="{{ route('admin.eventos.atividades.qrcodes', $evento->id) }}" class="modal-content"
              x-data="{ marcadas: @js($lista->pluck('id')->map(fn ($id) => (string) $id)), todas: @js($lista->pluck('id')->map(fn ($id) => (string) $id)) }">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalQrcodesTitulo"><i class="fa-solid fa-qrcode text-success me-1"></i> Gerar PDF dos QR Codes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="marcadas = [...todas]">Marcar todas</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="marcadas = []">Desmarcar todas</button>
                    <span class="small text-muted ms-auto"><strong x-text="marcadas.length">{{ $lista->count() }}</strong> de {{ $lista->count() }} selecionada(s)</span>
                </div>
                @foreach($lista->groupBy(fn ($a) => $a->data_inicio->format('Y-m-d')) as $dia => $doDia)
                    <div class="d-flex justify-content-between align-items-center mt-3 mb-1">
                        <div class="small fw-bold text-secondary text-uppercase">{{ $doDia->first()->data_inicio->format('d/m/Y') }}</div>
                        <button type="button" class="btn btn-sm btn-link p-0" @click="marcadas = @js($doDia->pluck('id')->map(fn ($id) => (string) $id)->values())">Só este dia</button>
                    </div>
                    @foreach($doDia as $a)
                        <label class="d-flex gap-2 align-items-start border rounded-3 p-2 mb-1" style="cursor:pointer">
                            <input type="checkbox" class="form-check-input mt-1 flex-shrink-0" name="atividades[]" value="{{ $a->id }}" x-model="marcadas" checked>
                            <span style="min-width:0">
                                <span class="d-block fw-bold texto-quebra">{{ $a->titulo }}</span>
                                <span class="d-block small text-muted">{{ $a->data_inicio->format('H:i') }} · {{ $a->tipoRotulo() }}@if($a->local) · {{ $a->local }}@endif</span>
                            </span>
                        </label>
                    @endforeach
                @endforeach
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success fw-bold" :disabled="marcadas.length === 0"><i class="fa-solid fa-file-pdf me-1"></i> Gerar PDF</button>
            </div>
        </form>
    </div>
</div>
