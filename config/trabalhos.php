<?php

return [
    // Binário do Ghostscript usado para juntar os PDFs dos Trabalhos Publicados (precisa estar instalado no servidor)
    'ghostscript' => env('GS_BINARY', 'gs'),

    // Tempo máximo (segundos) para compilar o PDF dos Trabalhos Publicados
    'anais_timeout' => (int) env('ANAIS_TIMEOUT', 600),
];
