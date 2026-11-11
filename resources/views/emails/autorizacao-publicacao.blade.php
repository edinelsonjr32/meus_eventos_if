<x-email.layout titulo="Autorize a publicação do seu trabalho nos Anais" :preheader="$trabalho->titulo . ' · ' . $trabalho->codigo">
    <x-email.dados :linhas="['Evento' => $trabalho->evento->titulo, 'Trabalho' => $trabalho->titulo, 'Protocolo' => $trabalho->codigo]" />

    <p style="margin:0 0 4px;">Seu trabalho foi aprovado e apresentado, mas ainda <strong>não consta nos Anais</strong> porque a publicação não foi autorizada.</p>

    <x-email.aviso tipo="info">
        Se você concorda, autorize na página do trabalho. Apenas <strong>título, resumo, nome e instituição dos autores e o PDF</strong> são publicados; CPF e e-mail nunca são divulgados.
    </x-email.aviso>

    <x-email.botao :href="route('trabalhos.show', $trabalho)">Abrir meu trabalho e autorizar</x-email.botao>
</x-email.layout>
