<x-email.layout :titulo="$noticia->titulo" :preheader="$noticia->evento->titulo">
    <p style="margin:0 0 12px;font-size:13px;color:#64748b;">Nova informação do evento <strong>{{ $noticia->evento->titulo }}</strong>, em que você está inscrito(a).</p>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">{!! nl2br(e($resumo)) !!}</p>
    <x-email.botao :href="$link">Ler no site</x-email.botao>
    <p style="margin:0;font-size:12px;color:#64748b;">Se o botão não abrir, copie o endereço: {{ $link }}</p>
</x-email.layout>
