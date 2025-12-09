<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Certificado</title>
    <style>
        /* Zera margens da página para o fundo cobrir tudo */
        @page { margin: 0cm 0cm; }
        body { font-family: 'Times New Roman', serif; margin: 0; padding: 0; }

        /* --- CORREÇÃO DO TAMANHO DA IMAGEM DE FUNDO --- */
        .background-full {
            position: fixed; /* Fixa em relação à página */
            top: 0cm;
            left: 0cm;
            /* Força as dimensões exatas de uma folha A4 Paisagem */
            width: 297mm;
            height: 210mm;
            z-index: -1000; /* Garante que fique atrás de tudo */
        }

        /* Layout Principal */
        .container {
            /* Padding ajustado para não ficar muito na borda do papel */
            padding: 40px 60px;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .header { margin-bottom: 30px; margin-top: 20px; }
        .brasao { height: 90px; margin-bottom: 10px; }

        .content-text {
            font-size: 20px;
            line-height: 1.6; /* Espaçamento ligeiramente reduzido para caber melhor */
            text-align: justify;
            margin-bottom: 40px;
            margin-top: 30px;
        }
        .highlight { font-weight: bold; }

        /* Assinaturas */
        .signatures-table { width: 100%; margin-top: 40px; border-collapse: collapse; page-break-inside: avoid; }
        .signatures-table td { text-align: center; vertical-align: bottom; padding: 0 10px; }
        /* Limita o tamanho das imagens de assinatura também */
        .sig-img { height: 60px; width: auto; display: block; margin: 0 auto 5px auto; max-width: 180px; }
        .sig-line { border-top: 1px solid #000; width: 90%; margin: 5px auto; }
        .sig-name { font-weight: bold; font-size: 14px; margin-top: 5px; }
        .sig-role { font-size: 12px; color: #555; }

        /* Rodapé */
        .footer-validation {
            position: absolute; bottom: 25px; left: 60px; right: 60px;
            font-size: 10px; text-align: left; color: #666;
        }
        /* Limita o tamanho do QR Code */
        .qr-code { float: right; width: 70px; height: 70px; margin-top: -20px; }
    </style>
</head>
<body>

    @if($imgFundo)
        <img src="{{ $imgFundo }}" class="background-full">
    @else
        <div style="position:fixed; top:0; left:0; width:297mm; height:210mm; border: 15px solid #064e3b; z-index:-1000; background: #fff; box-sizing: border-box;"></div>
    @endif

    <div class="container">

        <div class="header">
            @if($imgBrasao)
                <img src="{{ $imgBrasao }}" class="brasao">
            @else
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/bc/Coat_of_arms_of_Brazil.svg/1200px-Coat_of_arms_of_Brazil.svg.png" class="brasao">
            @endif

            <div class="institute-name" style="margin-top: 10px; font-size: 14px; line-height: 1.3;">
                {!! nl2br(e($textoCabecalho)) !!}
            </div>

            <h1 style="text-transform: uppercase; color: #064e3b; margin-top: 15px; font-size: 42px; border-bottom: 2px solid #daa520; display: inline-block; padding-bottom: 5px;">CERTIFICADO</h1>
        </div>

        <div class="content-text">
            {!! nl2br($textoCorpo) !!}
        </div>

        @if($assinaturas->count() > 0)
            <table class="signatures-table">
                <tr>
                    @foreach($assinaturas as $ass)
                        <td width="{{ 100 / $assinaturas->count() }}%">
                            @if(isset($ass->img_base64) && $ass->img_base64)
                                <img src="{{ $ass->img_base64 }}" class="sig-img">
                            @else
                                <div style="height: 60px;"></div>
                            @endif

                            <div class="sig-line"></div>
                            <div class="sig-name">{{ $ass->nome }}</div>
                            <div class="sig-role">{{ $ass->cargo }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif

        <div class="footer-validation">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ route('certificado.validar', $frequencia->hash_validacao) }}" class="qr-code">

            <strong>Registro de Autenticidade:</strong> {{ $frequencia->hash_validacao }} <br>
            Verifique em: {{ route('certificado.validar', $frequencia->hash_validacao) }}<br>

            @if($textoRodape)
                <br><span style="color: #888;">{!! nl2br(e($textoRodape)) !!}</span>
            @endif

            <br>Emitido eletronicamente em: {{ $frequencia->created_at->format('d/m/Y H:i') }}
        </div>

    </div>
</body>
</html>
