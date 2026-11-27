<article class="ev-noticia {{ $noticia->fixada ? 'fixada' : '' }}">
    <h3>@if($noticia->fixada)<i class="fa-solid fa-thumbtack me-1" style="color: var(--brand)" title="Em destaque"></i>@endif{{ $noticia->titulo }}</h3>
    <time datetime="{{ $noticia->publicado_em->toIso8601String() }}">{{ $noticia->publicado_em->format('d/m/Y \à\s H:i') }}</time>
    <div class="ev-desc">{!! $noticia->conteudoHtml() !!}</div>
</article>
