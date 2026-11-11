<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Política de Privacidade — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0fdf4; font-family: system-ui, -apple-system, sans-serif; color: #1f2937; line-height: 1.6; }
        .folha { max-width: 860px; margin: 2rem auto; background: #fff; border-radius: 16px; padding: 2rem 2.25rem; box-shadow: 0 6px 24px -10px rgba(6,78,59,.25); }
        h1 { color: #064e3b; font-size: 1.8rem; } h2 { color: #047857; font-size: 1.15rem; margin-top: 1.75rem; }
        table { font-size: .9rem; }
    </style>
    @include('partials.favicon')
</head>
<body>
    <main class="folha">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}" class="small text-decoration-none">&larr; Voltar</a>
        <h1 class="mt-2">Política de Privacidade</h1>
        <p class="text-secondary">{{ config('app.name') }} · Atualizada em {{ now()->format('d/m/Y') }}</p>

        <h2>1. Quem é o responsável</h2>
        <p><strong>{{ config('privacidade.controlador') }}</strong> é o controlador dos dados pessoais tratados neste sistema.
            @if(config('privacidade.encarregado')) O encarregado pelo tratamento de dados pessoais é <strong>{{ config('privacidade.encarregado') }}</strong>. @endif
            Para exercer seus direitos ou tirar dúvidas, escreva para <a href="mailto:{{ config('privacidade.contato') }}">{{ config('privacidade.contato') }}</a>.</p>

        <h2>2. Quais dados coletamos e para quê</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Dados</th><th>Quando</th><th>Finalidade</th></tr></thead>
                <tbody>
                    <tr><td>Nome, e-mail e senha (guardada de forma criptografada)</td><td>Criar conta</td><td>Acesso ao sistema</td></tr>
                    <tr><td>CPF, vínculo (aluno, servidor, externo), matrícula e turma</td><td>Cadastro, inscrição, presença, submissão</td><td>Identificar o participante, evitar duplicidade e emitir certificados</td></tr>
                    <tr><td>Inscrições e presenças em eventos</td><td>Inscrever-se e registrar presença</td><td>Controle de frequência e certificados</td></tr>
                    <tr><td>Localização do aparelho (GPS)</td><td>Registrar presença por QR Code</td><td>Conferir que você está no local do evento. <strong>Não é armazenada</strong>: só a distância é verificada no momento.</td></tr>
                    <tr><td>Nome, CPF, e-mail, instituição e categoria dos autores; título, resumo, arquivo PDF</td><td>Submeter trabalho</td><td>Receber, avaliar e, se aprovado, apresentar e publicar o trabalho</td></tr>
                    <tr><td>Notas e pareceres de avaliação</td><td>Avaliação</td><td>Decidir sobre os trabalhos. Os pareceres chegam ao autor sem identificar o avaliador.</td></tr>
                    <tr><td>Registros técnicos (endereço IP, navegador, erros)</td><td>Ao usar o sistema</td><td>Segurança e funcionamento. Ficam em logs do servidor por prazo limitado.</td></tr>
                </tbody>
            </table>
        </div>

        <h2>3. Base legal</h2>
        <p>O tratamento ocorre para a execução das atividades acadêmicas e de extensão da instituição (eventos, certificação e publicação de trabalhos)
            e para o cumprimento de suas finalidades públicas. A <strong>publicação do trabalho nos Anais</strong> depende da sua autorização expressa no formulário de submissão, que pode ser retirada
            (veja o item 6).</p>

        <h2>4. O que é público</h2>
        <p>Se o trabalho for aprovado, apresentado e você tiver autorizado a publicação, os Anais exibem: título, resumo, palavras-chave, nome e instituição dos autores e o PDF.
            <strong>CPF e e-mail nunca são publicados.</strong> A página de validação de certificados mostra apenas o nome, o CPF parcialmente oculto, o evento e o trabalho.</p>

        <h2>5. Com quem os dados passam</h2>
        <ul>
            <li><strong>Hospedagem e e-mail:</strong> o sistema e seus backups ficam em servidor contratado pela instituição, que também envia as mensagens do sistema.</li>
            <li><strong>Recursos de terceiros nas telas:</strong> o navegador carrega estilos, fontes e ícones de serviços de entrega de conteúdo (CDN); eles recebem seu endereço IP e dados do navegador, como em qualquer site.</li>
            <li>Os QR Codes são gerados no próprio servidor: nenhum dado pessoal é enviado a serviços externos para isso.</li>
            <li>Não vendemos nem cedemos dados a terceiros para fins comerciais.</li>
        </ul>

        <h2>6. Seus direitos</h2>
        <p>Você pode solicitar: confirmação de que tratamos seus dados; acesso e cópia (em formato estruturado); correção de dados incorretos; anonimização ou eliminação;
            informação sobre compartilhamento; e a retirada da autorização de publicação nos Anais. Peça pelo contato do item 1. Responderemos em prazo razoável.</p>
        <p><strong>Excluir a conta:</strong> em <em>Minha Conta</em> você pode excluí-la. Seus dados pessoais são anonimizados; trabalhos, notas e presenças permanecem apenas como registro acadêmico, sem identificar você,
            e certificados em seu nome deixam de ser válidos.</p>

        <h2>7. Por quanto tempo guardamos</h2>
        <ul>
            <li><strong>Conta e cadastro de participante:</strong> enquanto houver vínculo com eventos ou até o pedido de eliminação.</li>
            <li><strong>Certificados e Anais:</strong> permanecem como documento acadêmico de interesse público, salvo anonimização a pedido.</li>
            <li><strong>Rascunhos de trabalho não enviados:</strong> apagados após {{ config('privacidade.dias_rascunho') }} dias sem alteração.</li>
            <li><strong>Backups:</strong> mantidos por cerca de {{ config('privacidade.dias_backup') }} dias; depois de uma anonimização, os dados antigos somem dos backups nesse prazo.</li>
        </ul>

        <h2>8. Segurança</h2>
        <p>Senhas são guardadas de forma criptografada, o acesso é por HTTPS, arquivos dos trabalhos ficam em área privada (sem endereço público) e as telas de gestão exigem permissão específica.
            Em caso de incidente que possa causar risco relevante, os titulares e a autoridade competente serão comunicados.</p>

        <h2>9. Mudanças nesta política</h2>
        <p>Podemos atualizar este texto; a data no topo indica a versão vigente.</p>
    </main>
</body>
</html>
