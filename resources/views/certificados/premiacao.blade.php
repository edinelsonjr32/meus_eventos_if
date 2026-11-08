<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Certificado de Premiação</title>
    <style>
        @page { margin: 0cm 0cm; }
        body { font-family: 'Times New Roman', serif; margin: 0; padding: 0; }
        .background-full { position: fixed; top: 0cm; left: 0cm; width: 297mm; height: 210mm; z-index: -1000; }
        .pagina { page-break-after: always; }
        .pagina.ultima { page-break-after: auto; }
        .container { padding: 40px 60px; text-align: center; position: relative; z-index: 10; }
        .header { margin-bottom: 20px; margin-top: 10px; }
        .brasao { height: 80px; margin-bottom: 8px; }
        .content-text { font-size: 20px; line-height: 1.6; text-align: justify; margin: 25px 0 35px 0; }
        .signatures-table { width: 100%; margin-top: 30px; border-collapse: collapse; page-break-inside: avoid; }
        .signatures-table td { text-align: center; vertical-align: bottom; padding: 0 10px; }
        .sig-img { height: 55px; width: auto; display: block; margin: 0 auto 5px auto; max-width: 180px; }
        .sig-name { font-weight: bold; font-size: 14px; margin-top: 5px; }
        .sig-role { font-size: 12px; color: #555; }
        .footer-validation { position: absolute; bottom: 25px; left: 60px; right: 60px; font-size: 10px; text-align: left; color: #666; }
        .qr-code { float: right; width: 70px; height: 70px; margin-top: -20px; }
    </style>
</head>
<body>
    @if($imgFundo)
        <img src="{{ $imgFundo }}" class="background-full">
    @else
        <div style="position:fixed; top:0; left:0; width:297mm; height:210mm; border: 15px solid #a16207; z-index:-1000; background: #fff; box-sizing: border-box;"></div>
    @endif

    @foreach($itens as $item)
        @php $cert = $item['certificado']; $textos = $item['textos']; @endphp
        <div class="pagina {{ $loop->last ? 'ultima' : '' }}">
            <div class="container">
                <div class="header">
                    @if($imgBrasao) <img src="{{ $imgBrasao }}" class="brasao"> @endif
                    <div style="margin-top: 8px; font-size: 14px; line-height: 1.3;">{!! nl2br($textos['cabecalho']) !!}</div>
                    <h1 style="text-transform: uppercase; color: #a16207; margin-top: 12px; font-size: 38px; border-bottom: 2px solid #daa520; display: inline-block; padding-bottom: 5px;">Certificado de Premiação</h1>
                    <div style="font-size: 22px; font-weight: bold; color: #a16207; margin-top: 8px;">{{ $cert->premiacao->colocacaoRotulo() }} · {{ $cert->premiacao->categoriaRotulo() }}</div>
                </div>

                <div class="content-text">{!! nl2br($textos['corpo']) !!}</div>

                @if($assinaturas->count() > 0)
                    <table class="signatures-table">
                        <tr>
                            @foreach($assinaturas as $ass)
                                <td width="{{ 100 / $assinaturas->count() }}%">
                                    @if(!empty($ass->img_base64)) <img src="{{ $ass->img_base64 }}" class="sig-img"> @else <div style="height: 55px;"></div> @endif
                                    <div class="sig-name">{{ $ass->nome }}</div>
                                    <div class="sig-role">{{ $ass->cargo }}</div>
                                </td>
                            @endforeach
                        </tr>
                    </table>
                @endif

                <div class="footer-validation">
                    <img src="{{ $item['qrCode'] }}" class="qr-code">
                    <strong>Registro de Autenticidade:</strong> {{ $cert->hash_validacao }}<br>
                    Verifique em: {{ $item['urlValidacao'] }}<br>
                    @if($textos['rodape']) <br><span style="color: #888;">{!! nl2br($textos['rodape']) !!}</span> @endif
                    <br>Emitido eletronicamente em: {{ $cert->emitido_em->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>
    @endforeach
</body>
</html>
