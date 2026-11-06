<?php

return [
    // Binário do Ghostscript usado para juntar os PDFs dos Anais (precisa estar instalado no servidor)
    'ghostscript' => env('GS_BINARY', 'gs'),

    // Tempo máximo (segundos) para compilar o PDF dos Anais
    'anais_timeout' => (int) env('ANAIS_TIMEOUT', 600),
];
