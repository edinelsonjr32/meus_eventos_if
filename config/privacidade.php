<?php

return [
    // Quem decide sobre o tratamento dos dados (controlador) — ajuste ao órgão responsável pelo sistema
    'controlador' => env('PRIVACIDADE_CONTROLADOR', 'Instituto Federal do Pará - Campus Óbidos'),

    // Canal para o titular exercer seus direitos (acesso, correção, anonimização...)
    'contato' => env('PRIVACIDADE_CONTATO', 'contato@meuseventosif.com.br'),

    // Encarregado pelo tratamento de dados pessoais (DPO), se houver
    'encarregado' => env('PRIVACIDADE_ENCARREGADO'),

    // Data da versão vigente do texto da política (mude quando o texto ou o tratamento de dados mudar)
    'atualizada_em' => env('PRIVACIDADE_ATUALIZADA_EM', '2026-11-10'),

    // Retenção
    'dias_rascunho' => (int) env('PRIVACIDADE_DIAS_RASCUNHO', 180),   // rascunhos abandonados são apagados
    'dias_backup' => (int) env('PRIVACIDADE_DIAS_BACKUP', 14),         // informado na política
];
