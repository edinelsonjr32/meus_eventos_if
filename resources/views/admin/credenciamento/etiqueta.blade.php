<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $participante->nome_completo }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            /* COR PRINCIPAL DO EVENTO - Pega do banco ou usa um padrão elegante */
            --primary-color: {{ $evento->configuracoes['cor_fundo'] ?? '#064e3b' }};
            --secondary-color: #334155; /* Cinza azulado escuro para textos secundários */
        }

        body {
            font-family: 'Montserrat', sans-serif;
            margin: 0;
            padding: 0;
            background: #f1f5f9; /* Fundo cinza claro na tela */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* O CONTAINER DO CRACHÁ (Visualização em Tela) */
        .badge-container {
            width: 10cm;
            height: 6.5cm; /* Um pouco mais alto para respirar */
            background: #fff;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); /* Sombra elegante */
            border-radius: 12px; /* Bordas arredondadas na tela */
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        /* === ESTRUTURA INTERNA === */

        /* TOPO: Logo e Nome do Evento */
        .badge-header {
            padding: 15px 20px 5px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .event-logo {
            height: 40px;
            width: auto;
            object-fit: contain;
        }
        .event-name {
            font-size: 9pt;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--secondary-color);
            letter-spacing: 0.5px;
            text-align: right;
            flex-grow: 1;
            margin-left: 15px;
        }

        /* CORPO: Nome e Cargo */
        .badge-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 20px;
        }
        .participant-name {
            font-size: 22pt; /* Tamanho grande */
            font-weight: 900; /* Negrito extra pesado */
            text-transform: uppercase;
            line-height: 1;
            color: #000;
            margin-bottom: 8px;
            /* Garante que nomes longos quebrem sem estourar */
            word-wrap: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 2; /* Limita a 2 linhas */
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .participant-role-badge {
            display: inline-block;
            background: var(--primary-color);
            color: #fff;
            font-size: 10pt;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 4px;
            letter-spacing: 1px;
        }

        /* RODAPÉ: Metadados, Barra Colorida e QR */
        .badge-footer {
            position: relative;
            padding: 10px 20px 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            /* Barra colorida grossa na parte inferior */
            border-bottom: 8px solid var(--primary-color);
        }

        .meta-info {
            font-size: 8pt;
            color: var(--secondary-color);
            font-weight: 500;
        }
        .meta-info strong {
            display: block;
            font-size: 9pt;
            color: #000;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .qr-code-container {
            background: #fff;
            padding: 5px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            /* Posiciona o QR code para "sair" um pouco para a barra colorida */
            margin-bottom: -10px;
            z-index: 10;
        }
        .qr-code-img {
            width: 60px;
            height: 60px;
            display: block;
        }

        /* === CONFIGURAÇÃO DE IMPRESSÃO (CRÍTICO) === */
        @media print {
            @page {
                size: 10cm 6.5cm; /* Define o tamanho exato do papel da etiqueta */
                margin: 0mm; /* Sem margens na impressora */
            }
            body {
                background: none;
                display: block;
                margin: 0;
                padding: 0;
            }
            .badge-container {
                box-shadow: none;
                border-radius: 0;
                width: 100%;
                height: 100%;
                page-break-after: always;
                border: none; /* Remove qualquer borda na impressão */
                -webkit-print-color-adjust: exact; /* Força impressão de cores de fundo */
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="badge-container">

        <div class="badge-header">
            @if($evento->caminho_brasao)
                <img src="{{ asset('storage/' . $evento->caminho_brasao) }}" alt="Logo" class="event-logo">
            @else
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>
            @endif
            <div class="event-name">{{ Str::limit($evento->titulo, 40) }}</div>
        </div>

        <div class="badge-body">
            <div class="participant-name">
                {{ $participante->nome_completo }}
            </div>
            <div>
                 @php
                    $roleMap = [
                        'servidor' => 'SERVIDOR IFPA',
                        'externo' => 'VISITANTE',
                        'aluno' => 'PARTICIPANTE'
                    ];
                    $roleText = $roleMap[$participante->tipo_vinculo] ?? 'PARTICIPANTE';
                    // Verifica se é staff
                    $isStaff = $evento->equipe()->where('participante_id', $participante->id)->first();
                    if($isStaff) {
                        $roleText = $isStaff->funcao; // Ex: ORGANIZADOR
                    }
                @endphp
                <span class="participant-role-badge">{{ mb_strtoupper($roleText) }}</span>
            </div>
        </div>

        <div class="badge-footer">
            <div class="meta-info">
                @if($participante->turma)
                    <strong>{{ $participante->turma->nome_completo }}</strong>
                    Matrícula: {{ $participante->matricula }}
                @else
                    <strong>{{ Str::limit($participante->email, 30) }}</strong>
                    CPF: {{ $participante->cpf }}
                @endif
            </div>

            <div class="qr-code-container">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ $participante->cpf }}&bgcolor=ffffff&color=000000&margin=0" class="qr-code-img" alt="QR Code">
            </div>
        </div>
    </div>

</body>
</html>
