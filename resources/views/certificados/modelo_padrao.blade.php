<!DOCTYPE html>
<html>
<head>
    <title>Certificado Oficial</title>
    <style>
        @page { margin: 0px; }
        body {
            font-family: 'Helvetica', sans-serif;
            margin: 0; padding: 0;
            width: 100%; height: 100%;
        }
        /* Borda Ornamental */
        .border-frame {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border: 15px solid #064e3b; /* Verde IFPA */
            z-index: -1;
        }
        .inner-frame {
            position: absolute;
            top: 15px; left: 15px; right: 15px; bottom: 15px;
            border: 2px solid #daa520; /* Dourado */
            background: #fff;
            z-index: -1;
        }
        .watermark {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.05;
            width: 500px;
            z-index: 0;
        }

        .content {
            padding: 60px 80px;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .header h1 {
            font-size: 56px;
            text-transform: uppercase;
            letter-spacing: 5px;
            color: #064e3b;
            margin: 0 0 10px 0;
            font-family: 'Times New Roman', serif;
        }
        .header p {
            font-size: 18px;
            color: #666;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .body-text {
            font-size: 22px;
            line-height: 1.8;
            margin-top: 60px;
            color: #333;
            text-align: justify;
        }
        .highlight {
            font-weight: bold;
            color: #000;
            border-bottom: 1px solid #ccc;
        }

        .footer-signatures {
            margin-top: 80px;
            width: 100%;
        }
        .signature-box {
            width: 45%;
            display: inline-block;
            vertical-align: top;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 80%;
            margin: 0 auto 10px auto;
        }

        .validation-footer {
            position: absolute;
            bottom: 40px;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #999;
        }
    </style>
</head>
<body>

    <div class="border-frame"></div>
    <div class="inner-frame"></div>

    <div class="content">
        <div class="header">
            <h1>Certificado</h1>
            <p>Instituto Federal do Pará - Campus Óbidos</p>
        </div>

        <div class="body-text">
            Certificamos que <span class="highlight">&nbsp; {{ strtoupper($participante->nome_completo) }} &nbsp;</span>,
            inscrito(a) no CPF sob o nº {{ $participante->cpf }}, participou

            @if($frequencia->tipo_participacao == 'palestrante')
                na qualidade de <strong>PALESTRANTE</strong>
            @elseif($frequencia->tipo_participacao == 'voluntario')
                 na qualidade de <strong>VOLUNTÁRIO(A)</strong>
            @else
                 na qualidade de <strong>OUVINTE</strong>
            @endif

            da atividade <span class="highlight">&nbsp; {{ $atividade->titulo }} &nbsp;</span>,
            parte integrante do evento <strong>{{ $evento->titulo }}</strong>,
            realizado em {{ $atividade->data_inicio->format('d/m/Y') }},
            contabilizando a carga horária total de <strong>{{ $atividade->carga_horaria }} horas</strong>.
        </div>

        <div class="footer-signatures">
            <div class="signature-box">
                <div style="height: 50px;"></div> <div class="signature-line"></div>
                <strong>Coordenação de Extensão</strong><br>
                IFPA Campus Óbidos
            </div>

            <div class="signature-box">
                <div style="height: 50px;"></div>
                <div class="signature-line"></div>
                <strong>Coordenação do Evento</strong><br>
                {{ $evento->titulo }}
            </div>
        </div>
    </div>

    <div class="validation-footer">
        Documento emitido eletronicamente.<br>
        Verifique a autenticidade deste documento em: <strong>{{ route('certificado.validar', $hash) }}</strong><br>
        Código de Validação: <strong>{{ $hash }}</strong>
    </div>

</body>
</html>
