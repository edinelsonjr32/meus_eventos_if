<x-email.layout titulo="Seu certificado está disponível" :saudacao="'Olá, ' . $certificado->autor->nome_completo . '!'" :preheader="'Certificado do trabalho ' . $certificado->trabalho->titulo">
    <p style="margin:0 0 4px;">O certificado de <strong>{{ mb_strtolower($certificado->funcaoRotulo()) }}</strong> já pode ser baixado.</p>

    <x-email.dados :linhas="[
        'Evento' => $certificado->trabalho->evento->titulo,
        'Trabalho' => $certificado->trabalho->titulo,
        'Função' => $certificado->funcaoRotulo(),
    ]" />

    <x-email.botao :href="route('certificado.trabalho.download', $certificado->hash_validacao)">Baixar certificado (PDF)</x-email.botao>

    <p style="margin:0; font-size:13px; color:#64748b;">
        <strong>Código de autenticidade:</strong> {{ $certificado->hash_validacao }}<br>
        Verificação pública: <a href="{{ route('certificado.trabalho.validar', $certificado->hash_validacao) }}" style="color:#047857;">{{ route('certificado.trabalho.validar', $certificado->hash_validacao) }}</a>
    </p>
</x-email.layout>
