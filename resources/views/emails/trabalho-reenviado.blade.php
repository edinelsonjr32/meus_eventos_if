<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #047857;">Trabalho reenviado pelo autor</h2>
    <p>Evento: <strong>{{ $trabalho->evento->titulo }}</strong><br>
       Trabalho: <strong>{{ $trabalho->titulo }}</strong> ({{ $trabalho->codigo }})</p>
    <p>{{ match ($statusAnterior) {
        \App\Models\Trabalho::DEVOLVIDO_TRIAGEM => 'O autor fez os ajustes pedidos na triagem e reenviou o trabalho.',
        'aprovado' => 'O autor enviou a versão final corrigida (trabalho aprovado com ressalvas). Não é preciso aprovar de novo: o PDF já substituiu o anterior.',
        default => 'O autor enviou a versão corrigida solicitada na decisão da avaliação.',
    } }}</p>
    <p><a href="{{ route('gestao.trabalhos.show', $trabalho) }}">Abrir o trabalho na gestão</a> para continuar a triagem ou a avaliação.</p>
</body>
</html>
