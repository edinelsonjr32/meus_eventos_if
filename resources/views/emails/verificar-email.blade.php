<x-email.layout titulo="Confirme seu e-mail" :saudacao="'Olá, ' . $nome . '!'" preheader="Falta só confirmar o seu endereço de e-mail.">
    <p style="margin:0 0 4px;">Para concluir o seu cadastro, confirme que este endereço de e-mail é seu.</p>

    <x-email.botao :href="$link">Confirmar meu e-mail</x-email.botao>

    <p style="margin:0 0 12px;">Se você não criou uma conta, nenhuma ação é necessária.</p>

    <p style="margin:0; font-size:12px; color:#64748b; word-break:break-all;">Se o botão não funcionar, copie e cole este endereço no navegador:<br>{{ $link }}</p>
</x-email.layout>
