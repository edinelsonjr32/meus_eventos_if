{{--
  Selo de status com cor e ícone próprios, para distinguir de relance.
  Uso: <x-badge tipo="trabalho" :valor="$t->status" /> · tipos: trabalho, avaliacao (e apresentação), papel, certificado, sim-nao
  Opcionais: rotulo="texto" (sobrescreve o padrão), :icone="false", extra="texto complementar".
--}}
@props(['tipo' => 'trabalho', 'valor' => null, 'rotulo' => null, 'icone' => true, 'extra' => null])

@php
    // cor => [fundo, texto, borda]
    $cores = [
        'cinza' => ['#f1f3f5', '#495057', '#ced4da'], 'azul' => ['#dbeafe', '#1d4ed8', '#93c5fd'], 'ciano' => ['#cffafe', '#0e7490', '#67e8f9'],
        'indigo' => ['#e0e7ff', '#4338ca', '#a5b4fc'], 'roxo' => ['#f3e8ff', '#7e22ce', '#d8b4fe'], 'ambar' => ['#fef3c7', '#b45309', '#fcd34d'],
        'laranja' => ['#ffedd5', '#c2410c', '#fdba74'], 'vermelho' => ['#fee2e2', '#b91c1c', '#fca5a5'], 'verde' => ['#dcfce7', '#15803d', '#86efac'],
        'lima' => ['#ecfccb', '#4d7c0f', '#bef264'], 'rosa' => ['#fce7f3', '#be185d', '#f9a8d4'], 'ardosia' => ['#e2e8f0', '#334155', '#94a3b8'],
        'verde-forte' => ['#16a34a', '#ffffff', '#16a34a'], 'dourado' => ['#fef9c3', '#a16207', '#facc15'], 'esmeralda' => ['#065f46', '#ffffff', '#065f46'], 'vermelho-forte' => ['#dc2626', '#ffffff', '#dc2626'],
    ];

    // tipo => valor => [cor, ícone, rótulo]
    $mapa = [
        'trabalho' => [
            'rascunho' => ['cinza', 'fa-pencil', 'Rascunho'], 'submetido' => ['azul', 'fa-paper-plane', 'Submetido'],
            'em_triagem' => ['ciano', 'fa-filter', 'Em triagem'], 'devolvido_triagem' => ['laranja', 'fa-rotate-left', 'Devolvido na triagem'],
            'rejeitado_triagem' => ['vermelho', 'fa-ban', 'Rejeitado na triagem'], 'em_avaliacao' => ['indigo', 'fa-magnifying-glass', 'Em avaliação'],
            'avaliado' => ['roxo', 'fa-clipboard-check', 'Avaliado'], 'aguardando_correcao' => ['ambar', 'fa-pen-to-square', 'Aguardando correção'],
            'resubmetido' => ['rosa', 'fa-arrows-rotate', 'Resubmetido'], 'aprovado' => ['verde-forte', 'fa-circle-check', 'Aprovado'],
            'aprovado_com_ressalvas' => ['lima', 'fa-circle-exclamation', 'Aprovado com ressalvas'], 'reprovado' => ['vermelho-forte', 'fa-circle-xmark', 'Reprovado'],
            'retirado' => ['ardosia', 'fa-box-archive', 'Retirado'],
            'aprovado_escrita' => ['lima', 'fa-file-circle-check', 'Aprovado na parte escrita'], 'aguardando_certificado' => ['dourado', 'fa-award', 'Aguardando emissão de certificado'], 'concluido' => ['esmeralda', 'fa-flag-checkered', 'Concluído (certificados emitidos)'], 'aprovado_apresentacao' => ['azul', 'fa-person-chalkboard', 'Aprovado na apresentação'],
        ],
        'avaliacao' => [
            'pendente' => ['ambar', 'fa-hourglass-half', 'Pendente'], 'em_andamento' => ['azul', 'fa-pen', 'Em andamento'],
            'concluida' => ['verde', 'fa-check', 'Concluída'], 'recusada' => ['vermelho', 'fa-hand', 'Recusada'],
        ],
        'papel' => [
            'gestor_trabalhos' => ['roxo', 'fa-inbox', 'Gestor de Trabalhos'], 'avaliador' => ['indigo', 'fa-clipboard-check', 'Avaliador'],
            'editor' => ['ciano', 'fa-user-gear', 'Co-administrador'],
        ],
        'autor' => [
            'autor_principal' => ['indigo', 'fa-user-pen', 'Autor principal'], 'coautor' => ['cinza', 'fa-user', 'Coautor'],
            'orientador' => ['ciano', 'fa-user-graduate', 'Orientador'], 'coorientador' => ['ciano', 'fa-user-graduate', 'Coorientador'],
            'apresentador' => ['verde', 'fa-person-chalkboard', 'Apresentador'], 'correspondente' => ['rosa', 'fa-envelope', 'Correspondente'],
            'responsavel' => ['azul', 'fa-user-check', 'Responsável pela submissão'], 'consta' => ['ciano', 'fa-user-tag', 'Você consta como autor'],
        ],
        'chamada' => [
            'aberta' => ['verde-forte', 'fa-door-open', 'Chamada aberta'], 'em_breve' => ['ambar', 'fa-clock', 'Em breve'],
            'encerrada' => ['ardosia', 'fa-lock', 'Encerrada'], 'rascunho' => ['cinza', 'fa-pencil', 'Rascunho'], 'nao_criada' => ['cinza', 'fa-circle-plus', 'Não criada'],
        ],
        'pendencia' => [
            'vincular_apresentador' => ['ambar', 'fa-user-tag', 'Aguardando vincular apresentador'],
            'vincular_sessao' => ['ciano', 'fa-calendar-plus', 'Aguardando definir sessão'],
            'atribuir_avaliadores' => ['indigo', 'fa-user-plus', 'Aguardando avaliadores'],
            'aguardando_decisao' => ['roxo', 'fa-gavel', 'Aguardando decisão da gestão'],
            'aguardando_reenvio_aprovacao' => ['rosa', 'fa-rotate', 'Aguardando aprovação do reenvio'],
            'aguardando_autor' => ['laranja', 'fa-user-pen', 'Aguardando ajustes do autor'],
            'atribuir_avaliadores_apresentacao' => ['indigo', 'fa-person-chalkboard', 'Aguardando avaliadores da apresentação'],
            'resultado_apresentacao' => ['dourado', 'fa-hourglass-half', 'Aguardando resultado da apresentação'],
        ],
        'anais' => [
            'pendente' => ['laranja', 'fa-book-open', 'Pendente de aprovação · Anais do evento'],
            'autorizado' => ['verde', 'fa-book-bookmark', 'Publicação nos Anais autorizada'],
            'nao_autorizado' => ['cinza', 'fa-book', 'Publicação nos Anais não autorizada'],
        ],
        'certificado' => [
            'valido' => ['verde', 'fa-award', 'Válido'], 'revogado' => ['vermelho-forte', 'fa-ban', 'Revogado'],
        ],
        'sim-nao' => [
            'sim' => ['verde', 'fa-check', 'Sim'], 'nao' => ['cinza', 'fa-minus', 'Não'],
            'apresentado' => ['verde-forte', 'fa-person-chalkboard', 'Apresentado'], 'nao_apresentado' => ['cinza', 'fa-hourglass-start', 'Não apresentado'],
        ],
    ];

    $def = $mapa[$tipo][$valor] ?? ['cinza', 'fa-circle', $rotulo ?? ucfirst(str_replace('_', ' ', (string) $valor))];
    [$fundo, $texto, $borda] = $cores[$def[0]];
    $texto_badge = $rotulo ?? $def[2];
@endphp

@once
    <style>
        .sb { display: inline-flex; align-items: center; gap: .35rem; padding: .22rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; line-height: 1.2; border: 1px solid transparent; white-space: nowrap; vertical-align: middle; }
        .sb i { font-size: .72rem; }
        .sb-extra { font-weight: 600; opacity: .85; }
    </style>
@endonce
<span {{ $attributes->merge(['class' => 'sb']) }} style="background: {{ $fundo }}; color: {{ $texto }}; border-color: {{ $borda }};" title="{{ $texto_badge }}">
    @if($icone)<i class="fa-solid {{ $def[1] }}"></i>@endif{{ $texto_badge }}@if($extra)<span class="sb-extra">· {{ $extra }}</span>@endif
</span>
