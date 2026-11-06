<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $anais->titulo }}</title>
    <style>
        @page { margin: 2.5cm 2cm; }
        body { font-family: 'Times-Roman', serif; font-size: 12pt; color: #111; }
        .capa { text-align: center; padding-top: 5cm; page-break-after: always; }
        .capa h1 { font-size: 30pt; color: #064e3b; margin: 1.5cm 0 .5cm; }
        .capa .evento { font-size: 16pt; margin-bottom: 1cm; }
        .capa .rodape { margin-top: 6cm; font-size: 12pt; }
        h2 { font-size: 16pt; color: #064e3b; border-bottom: 1px solid #daa520; padding-bottom: 4px; margin-top: 0; }
        h3 { font-size: 13pt; margin: 14px 0 4px; }
        .secao { page-break-after: always; }
        table.sumario { width: 100%; border-collapse: collapse; }
        table.sumario td { padding: 3px 0; vertical-align: top; }
        .pg { text-align: right; width: 40px; }
        .autores { font-size: 10pt; color: #555; }
        .meta td { padding: 2px 10px 2px 0; }
    </style>
</head>
<body>
    <div class="capa">
        <div style="font-size: 12pt;">{{ $anais->editora }}</div>
        <h1>{{ $anais->titulo }}</h1>
        <div class="evento">{{ $anais->evento->titulo }}</div>
        @if($anais->edicao) <div>{{ $anais->edicao }}</div> @endif
        <div class="rodape">{{ $anais->local_publicacao }}<br>{{ $anais->ano }}</div>
    </div>

    <div class="secao">
        <h2>Expediente</h2>
        <table class="meta">
            @if($anais->issn) <tr><td><b>ISSN</b></td><td>{{ $anais->issn }}</td></tr> @endif
            @if($anais->isbn) <tr><td><b>ISBN</b></td><td>{{ $anais->isbn }}</td></tr> @endif
            @if($anais->doi_prefixo) <tr><td><b>DOI (prefixo)</b></td><td>{{ $anais->doi_prefixo }}</td></tr> @endif
            <tr><td><b>Editora</b></td><td>{{ $anais->editora }}</td></tr>
            <tr><td><b>Local</b></td><td>{{ $anais->local_publicacao }}, {{ $anais->ano }}</td></tr>
            <tr><td><b>Trabalhos</b></td><td>{{ $total }}</td></tr>
        </table>

        @if($anais->apresentacao)
            <h3>Apresentação</h3>
            <div>{!! nl2br(e($anais->apresentacao)) !!}</div>
        @endif
        @if($anais->comissao)
            <h3>Comissão organizadora</h3>
            <div>{!! nl2br(e($anais->comissao)) !!}</div>
        @endif
    </div>

    <h2>Sumário</h2>
    @foreach($grupos as $area => $itens)
        <h3>{{ $area }}</h3>
        <table class="sumario">
            @foreach($itens as $item)
                <tr>
                    <td>{{ $item['titulo'] }}<div class="autores">{{ $item['autores'] }}</div></td>
                    <td class="pg">{{ $item['pagina'] }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach
</body>
</html>
