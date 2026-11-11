<x-email.layout titulo="Trabalho recebido com sucesso" :preheader="'Protocolo ' . $trabalho->codigo . ' · ' . $trabalho->evento->titulo">
    <p style="margin:0 0 4px;">O trabalho abaixo foi submetido ao evento <strong>{{ $trabalho->evento->titulo }}</strong>. Guarde o número de protocolo para acompanhar o andamento.</p>

    <x-email.dados :linhas="[
        'Protocolo' => $trabalho->codigo,
        'Título' => $trabalho->titulo,
        'Categoria' => \App\Models\TrabalhoConfig::TIPOS[$trabalho->tipo_trabalho] ?? $trabalho->tipo_trabalho,
        'Enviado em' => $trabalho->submetido_em?->format('d/m/Y \à\s H:i'),
    ]" />

    <p style="margin:0 0 6px; font-weight:700; color:#064e3b;">Autores</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        @foreach($trabalho->autores as $autor)
            <tr>
                <td style="padding:6px 0; border-bottom:1px solid #f1f5f9; font-size:15px;">
                    <strong>{{ $autor->nome_completo }}</strong>
                    <span style="color:#64748b;"> &middot; {{ $autor->tipoRotulo() }}{{ $autor->apresentador ? ' (apresentador)' : '' }}</span>
                </td>
            </tr>
        @endforeach
    </table>

    <x-email.botao :href="route('trabalhos.show', $trabalho)">Acompanhar meu trabalho</x-email.botao>
</x-email.layout>
