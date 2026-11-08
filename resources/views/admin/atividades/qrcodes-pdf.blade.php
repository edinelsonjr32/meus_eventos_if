<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>QR Codes de frequência — {{ $evento->titulo }}</title>
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; color: #111827; }
        .pagina { width: 100%; height: 297mm; padding: 14mm 16mm; box-sizing: border-box; text-align: center; page-break-after: always; }
        .pagina.ultima { page-break-after: auto; }
        .topo { border-bottom: 3px solid #059669; padding-bottom: 8mm; margin-bottom: 14mm; }
        .instituicao { font-size: 11pt; color: #047857; letter-spacing: 1px; text-transform: uppercase; font-weight: bold; }
        .evento { font-size: 22pt; font-weight: bold; margin: 3mm 0; }
        .meta { font-size: 11pt; color: #4b5563; }
        .tipo { display: inline-block; background: #d1fae5; color: #065f46; font-size: 10pt; font-weight: bold; padding: 1.5mm 5mm; border-radius: 10mm; text-transform: uppercase; margin-bottom: 6mm; }
        .atividade { font-size: 24pt; font-weight: bold; margin: 0 0 4mm; line-height: 1.25; }
        .responsavel { font-size: 14pt; color: #374151; margin-bottom: 4mm; }
        .horario { font-size: 11pt; color: #6b7280; margin-bottom: 12mm; }
        .qr { width: 95mm; height: 95mm; border: 2mm solid #064e3b; padding: 4mm; border-radius: 4mm; }
        .instrucao { margin-top: 10mm; font-size: 13pt; font-weight: bold; color: #064e3b; }
        .sub { font-size: 10pt; color: #6b7280; margin-top: 2mm; }
    </style>
</head>
<body>
@foreach($paginas as $p)
    @php $a = $p['atividade']; @endphp
    <div class="pagina {{ $loop->last ? 'ultima' : '' }}">
        <div class="topo">
            <div class="instituicao">Instituto Federal do Pará — Campus Óbidos</div>
            <div class="evento">{{ $evento->titulo }}</div>
            <div class="meta">
                @if($evento->data_inicio) {{ $evento->data_inicio->format('d/m/Y') }} @endif
                @if($evento->local) · {{ $evento->local }}@endif
            </div>
        </div>

        <div class="tipo">{{ str_replace('_', ' ', $a->tipo) }}</div>
        <div class="atividade">{{ $a->titulo }}</div>
        @if($a->responsavel)<div class="responsavel">Responsável: <strong>{{ $a->responsavel }}</strong></div>@endif
        <div class="horario">{{ $a->data_inicio?->format('d/m/Y \à\s H:i') }} · {{ $a->carga_horaria }}h @if($a->local) · Local: {{ $a->local }} @endif</div>

        <img class="qr" src="{{ $p['qr'] }}" alt="QR Code de frequência">

        <div class="instrucao">Aponte a câmera do celular para registrar sua presença</div>
        <div class="sub">É necessário entrar com sua conta. Ative a localização do aparelho, se solicitado.</div>
    </div>
@endforeach
</body>
</html>
