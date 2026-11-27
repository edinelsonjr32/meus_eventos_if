{{-- Quem ministra as atividades, com foto (ou iniciais). A partir de 13 pessoas, o restante abre com um botão. --}}
@if($ministrantes->isNotEmpty())
    <section class="cartao ev-bloco" id="ministrantes" x-data="{ todos: false }">
        <h2><i class="fa-solid fa-chalkboard-user"></i> Ministrantes</h2>
        <div class="ev-pessoas">
            @foreach($ministrantes as $i => $pessoa)
                <div class="ev-pessoa" @if($i >= 12) x-show="todos" x-cloak @endif>
                    @if($pessoa->foto)
                        <img src="{{ $pessoa->foto }}" alt="Foto de {{ $pessoa->nome }}" class="ev-avatar" loading="lazy" width="104" height="104">
                    @else
                        <div class="ev-avatar" aria-hidden="true">{{ $pessoa->iniciais }}</div>
                    @endif
                    <b>{{ $pessoa->nome }}</b>
                    @if($pessoa->descricao)<span>{{ $pessoa->descricao }}</span>@endif
                    @if($pessoa->atividades)
                        <span title="{{ implode(' · ', $pessoa->atividades) }}">{{ \Illuminate\Support\Str::limit($pessoa->atividades[0], 60) }}@if(count($pessoa->atividades) > 1) (+{{ count($pessoa->atividades) - 1 }})@endif</span>
                    @endif
                </div>
            @endforeach
        </div>
        @if($ministrantes->count() > 12)
            <div class="text-center mt-3">
                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" @click="todos = !todos" x-text="todos ? 'Mostrar menos' : 'Ver todos ({{ $ministrantes->count() }})'">Ver todos</button>
            </div>
        @endif
    </section>
@endif
