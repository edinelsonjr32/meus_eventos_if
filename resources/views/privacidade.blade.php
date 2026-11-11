@php
    $controlador = config('privacidade.controlador');
    $contato = config('privacidade.contato');
    $encarregado = config('privacidade.encarregado');
    $atualizada = \Carbon\Carbon::parse(config('privacidade.atualizada_em'));
    $sessaoHoras = max(1, round(config('session.lifetime') / 60));
    $secoes = [
        'responsavel' => 'Quem é o responsável',
        'dados' => 'Quais dados coletamos',
        'base-legal' => 'Por que podemos tratar',
        'publico' => 'O que é público',
        'compartilhamento' => 'Com quem os dados passam',
        'cookies' => 'Cookies e navegador',
        'direitos' => 'Seus direitos',
        'exclusao' => 'Excluir a conta',
        'retencao' => 'Por quanto tempo guardamos',
        'seguranca' => 'Segurança',
        'menores' => 'Estudantes menores de idade',
        'mudancas' => 'Mudanças nesta política',
    ];
@endphp
<x-publico-layout titulo="Política de Privacidade" descricao="Como o Meus Eventos IF coleta, usa, protege e elimina dados pessoais, conforme a LGPD, e como exercer os seus direitos.">
    <x-slot:estilos>
        <style>
            .pv-hero { position: relative; color: #fff; padding: 3rem 0 3.4rem; background: radial-gradient(800px 360px at 90% 0%, rgba(52,211,153,.25), transparent 60%), linear-gradient(135deg, #053b2d, #065f46 60%, #047857); overflow: hidden; }
            .pv-hero::before { content: ""; position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.1) 1px, transparent 1px); background-size: 28px 28px; opacity: .5; }
            .pv-hero > .container { position: relative; }
            .pv-hero h1 { color: #fff; font-weight: 800; font-size: clamp(1.8rem, 4vw, 2.7rem); line-height: 1.12; }
            .pv-trilha { font-size: .83rem; color: rgba(255,255,255,.75); } .pv-trilha a { color: rgba(255,255,255,.88); text-decoration: none; } .pv-trilha a:hover { color: #fff; }
            .pv-chip { display: inline-flex; align-items: center; gap: .45rem; font-size: .8rem; font-weight: 600; padding: .38rem .85rem; border-radius: 999px; background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.24); color: #e8fff5; }

            .pv-card { height: 100%; padding: 1.2rem 1.25rem; border-radius: 18px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); backdrop-filter: blur(4px); }
            .pv-card i.ic { width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,.16); color: #6ee7b7; display: grid; place-items: center; font-size: 1rem; margin-bottom: .85rem; }
            .pv-card h2 { font-size: 1rem; font-weight: 800; margin-bottom: .35rem; color: #fff; letter-spacing: -.01em; }
            .pv-card p { font-size: .85rem; line-height: 1.5; color: #d1fae5; margin: 0; }
            @media (max-width: 575.98px) { .pv-card { display: flex; gap: .85rem; align-items: flex-start; padding: .95rem 1rem; } .pv-card i.ic { flex: 0 0 38px; width: 38px; height: 38px; margin: 0; } .pv-card h2 { font-size: .95rem; } .pv-card p { font-size: .82rem; } .pv-resumo { --bs-gutter-y: .6rem; } }

            .pv-indice { position: sticky; top: 84px; padding: 1.1rem; }
            .pv-indice .titulo { font-size: .72rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #059669; margin-bottom: .6rem; }
            .pv-indice a { display: block; padding: .42rem .7rem; border-radius: 9px; color: var(--ink-2); text-decoration: none; font-size: .86rem; font-weight: 600; border-left: 3px solid transparent; }
            .pv-indice a:hover { background: #f3faf6; color: #047857; }
            .pv-indice a.ativo { background: #ecfdf5; color: #047857; border-left-color: #10b981; }

            .pv-secao { padding: 1.75rem; margin-bottom: 1.25rem; scroll-margin-top: 90px; }
            .pv-secao h2 { font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem; display: flex; align-items: center; gap: .65rem; }
            .pv-secao h2 .n { width: 32px; height: 32px; border-radius: 10px; background: #ecfdf5; color: #047857; display: grid; place-items: center; font-size: .85rem; flex: 0 0 32px; }
            .pv-secao p, .pv-secao li { line-height: 1.75; color: var(--ink-2); } .pv-secao ul { padding-left: 1.2rem; }
            .pv-secao table { font-size: .88rem; } .pv-secao th { background: #f6f8fa; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #5b6b7d; }
            .pv-destaque { background: #ecfdf5; border: 1px solid #a7f3d0; border-left: 4px solid #10b981; border-radius: 12px; padding: .9rem 1.1rem; color: #065f46; font-size: .92rem; }
            .pv-aviso { background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 12px; padding: .9rem 1.1rem; color: #78350f; font-size: .92rem; }
            .pv-passos { counter-reset: p; list-style: none; padding: 0 !important; }
            .pv-passos li { counter-increment: p; display: flex; gap: .85rem; margin-bottom: .8rem; }
            .pv-passos li::before { content: counter(p); width: 28px; height: 28px; border-radius: 50%; background: #059669; color: #fff; display: grid; place-items: center; font-weight: 800; font-size: .8rem; flex: 0 0 28px; margin-top: .1rem; }
            .pv-contato { background: linear-gradient(135deg, #053b2d, #065f46); color: #d1fae5; border-radius: 22px; padding: 1.75rem; }
            .pv-contato h2 { color: #fff; font-size: 1.25rem; font-weight: 800; }
            .pv-contato a.mail { color: #fff; font-weight: 700; overflow-wrap: anywhere; }
            @media (max-width: 991.98px) { .pv-indice { position: static; } }
        </style>
    </x-slot:estilos>

    <header class="pv-hero">
        <div class="container">
            <div class="pv-trilha mb-3"><a href="{{ route('home') }}">Início</a> <i class="fa-solid fa-chevron-right mx-1" style="font-size:.6rem"></i> <span>Política de Privacidade</span></div>
            <div class="mb-3"><span class="pv-chip"><i class="fa-solid fa-shield-halved"></i> Lei Geral de Proteção de Dados (LGPD)</span></div>
            <h1 class="mb-3">Política de Privacidade</h1>
            <p class="mb-3" style="color:#d1fae5; max-width: 700px; font-size: 1.05rem;">Explicamos, em linguagem simples, quais dados pessoais o {{ config('app.name') }} usa, para quê, com quem passam, por quanto tempo ficam guardados e como você pode controlá-los.</p>
            <div class="small" style="color:#a7f3d0;"><i class="fa-regular fa-calendar me-1"></i> Versão vigente desde {{ $atualizada->format('d/m/Y') }}</div>

            {{-- Resumo em 4 cartões, dentro do próprio cabeçalho --}}
            <div class="pv-resumo row g-3 mt-4">
                @foreach([
                    ['fa-database', 'Só o necessário', 'Pedimos apenas os dados que cada função exige: conta, inscrição, presença, trabalhos e certificados.'],
                    ['fa-eye-slash', 'CPF e e-mail ficam privados', 'Nunca aparecem nos Trabalhos Publicados nem em páginas públicas. A validação de certificado oculta parte do CPF.'],
                    ['fa-sliders', 'Você tem controle', 'Pode pedir acesso, correção, anonimização e retirar a autorização de publicação.'],
                    ['fa-lock', 'Protegido', 'Senhas criptografadas, conexão segura (HTTPS) e arquivos de trabalhos em área privada.'],
                ] as [$ico, $titulo, $texto])
                    <div class="col-sm-6 col-lg-3"><div class="pv-card"><i class="fa-solid {{ $ico }} ic"></i><div><h2>{{ $titulo }}</h2><p>{{ $texto }}</p></div></div></div>
                @endforeach
            </div>
        </div>
    </header>

    <div class="container pt-5">
        <div class="row g-4 g-xl-5">
            {{-- Índice --}}
            <div class="col-lg-3 d-none d-lg-block">
                <nav class="cartao pv-indice" aria-label="Índice da política" id="indice">
                    <div class="titulo">Nesta página</div>
                    @foreach($secoes as $id => $rotulo)<a href="#{{ $id }}" data-alvo="{{ $id }}">{{ $rotulo }}</a>@endforeach
                </nav>
            </div>

            <div class="col-lg-9">
                <section class="cartao pv-secao" id="responsavel">
                    <h2><span class="n">1</span> Quem é o responsável</h2>
                    <p><strong>{{ $controlador }}</strong> é o controlador dos dados pessoais tratados neste sistema, isto é, quem decide como e para que eles são usados.
                        @if($encarregado) O encarregado pelo tratamento de dados pessoais (canal entre você, a instituição e a Autoridade Nacional de Proteção de Dados) é <strong>{{ $encarregado }}</strong>. @endif</p>
                    <p class="mb-0">Para exercer seus direitos ou tirar dúvidas sobre esta política, escreva para <a href="mailto:{{ $contato }}">{{ $contato }}</a>.</p>
                </section>

                <section class="cartao pv-secao" id="dados">
                    <h2><span class="n">2</span> Quais dados coletamos e para quê</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Dados</th><th>Quando</th><th>Finalidade</th></tr></thead>
                            <tbody>
                                <tr><td>Nome, e-mail e senha (guardada de forma criptografada)</td><td>Criar conta</td><td>Acesso ao sistema</td></tr>
                                <tr><td>CPF, vínculo (aluno, servidor, externo), matrícula e turma</td><td>Cadastro, inscrição, presença, submissão</td><td>Identificar o participante, evitar duplicidade e emitir certificados</td></tr>
                                <tr><td>Inscrições e presenças em eventos</td><td>Inscrever-se e registrar presença</td><td>Controle de frequência e certificados</td></tr>
                                <tr><td>Localização do aparelho (GPS)</td><td>Registrar presença por QR Code</td><td>Conferir que você está no local do evento. <strong>Não é armazenada</strong>: só a distância é verificada no momento.</td></tr>
                                <tr><td>Nome, CPF, e-mail, instituição e categoria dos autores; título, resumo, palavras-chave e arquivo PDF</td><td>Submeter trabalho</td><td>Receber, avaliar e, se aprovado, apresentar e publicar o trabalho</td></tr>
                                <tr><td>Notas e pareceres de avaliação</td><td>Avaliação</td><td>Decidir sobre os trabalhos. Os pareceres chegam ao autor sem identificar o avaliador.</td></tr>
                                <tr><td>Registros técnicos (endereço IP, navegador, erros)</td><td>Ao usar o sistema</td><td>Segurança e funcionamento. Ficam em registros do servidor por prazo limitado.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pv-destaque mt-3"><i class="fa-solid fa-ban me-1"></i> <strong>O que não fazemos:</strong> não coletamos dados sensíveis (saúde, religião, origem racial etc.), não gravamos sua localização, não usamos seus dados para publicidade e não tomamos decisões apenas por programas automáticos: toda aprovação de trabalho é feita por pessoas.</div>
                </section>

                <section class="cartao pv-secao" id="base-legal">
                    <h2><span class="n">3</span> Por que podemos tratar seus dados (base legal)</h2>
                    <p>A LGPD exige uma justificativa legal para cada uso de dados. Aqui elas são:</p>
                    <ul>
                        <li><strong>Atividades acadêmicas e de extensão da instituição pública:</strong> inscrição, frequência, avaliação de trabalhos e emissão de certificados (execução de políticas e finalidades públicas da instituição de ensino).</li>
                        <li><strong>Execução do serviço que você pediu:</strong> manter sua conta e permitir que você participe de eventos.</li>
                        <li><strong>Cumprimento de obrigação legal e regulatória:</strong> guarda de registros de acesso e documentos acadêmicos pelos prazos exigidos.</li>
                        <li><strong>Seu consentimento:</strong> a <strong>publicação do trabalho nos Trabalhos Publicados</strong> depende da sua autorização expressa, dada no formulário de submissão ou na página do trabalho. Você pode retirá-la quando quiser.</li>
                    </ul>
                </section>

                <section class="cartao pv-secao" id="publico">
                    <h2><span class="n">4</span> O que é público</h2>
                    <p>Se o trabalho for aprovado, apresentado e você tiver autorizado a publicação, os Trabalhos Publicados exibem: título, resumo, palavras-chave, nome e instituição dos autores e o PDF.</p>
                    <div class="pv-destaque mb-3"><i class="fa-solid fa-circle-check me-1"></i> <strong>CPF e e-mail nunca são publicados.</strong></div>
                    <p class="mb-0">A página de <em>Validar certificado</em> mostra apenas o nome, o CPF parcialmente oculto, o evento e o trabalho a que o certificado se refere, para que qualquer pessoa confirme a autenticidade do documento.</p>
                </section>

                <section class="cartao pv-secao" id="compartilhamento">
                    <h2><span class="n">5</span> Com quem os dados passam</h2>
                    <ul>
                        <li><strong>Hospedagem e e-mail:</strong> o sistema e seus backups ficam em servidor contratado pela instituição, que também envia as mensagens do sistema (confirmações, resultados e avisos).</li>
                        <li><strong>Equipe do evento:</strong> organizadores, gestores de trabalhos, avaliadores e recepção veem só o que a sua função exige. Avaliadores recebem o trabalho sem acesso a CPF ou e-mail, e podem receber a versão sem identificação dos autores quando a avaliação é cega.</li>
                        <li><strong>Recursos de terceiros nas telas:</strong> o navegador carrega estilos, fontes e ícones de serviços de entrega de conteúdo (CDN) de fora do Brasil; eles recebem seu endereço IP e dados do navegador, como em qualquer site. Nenhum dado do seu cadastro é enviado a eles.</li>
                        <li>Os QR Codes são gerados no próprio servidor: nenhum dado pessoal é enviado a serviços externos para isso.</li>
                        <li><strong>Não vendemos nem cedemos dados a terceiros para fins comerciais.</strong></li>
                    </ul>
                </section>

                <section class="cartao pv-secao" id="cookies">
                    <h2><span class="n">6</span> Cookies e armazenamento no navegador</h2>
                    <p>Usamos apenas cookies <strong>essenciais</strong>, necessários para o sistema funcionar. Por isso não há banner de consentimento de cookies de publicidade ou rastreamento: não os utilizamos.</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Cookie</th><th>Para que serve</th><th>Duração</th></tr></thead>
                            <tbody>
                                <tr><td>Sessão do sistema</td><td>Manter você conectado enquanto navega</td><td>Até {{ $sessaoHoras }} {{ $sessaoHoras === 1 ? 'hora' : 'horas' }} sem uso, ou até você sair</td></tr>
                                <tr><td>Proteção de formulários (XSRF)</td><td>Impedir que outro site envie formulários em seu nome</td><td>Mesma da sessão</td></tr>
                                <tr><td>"Lembrar-me" (opcional)</td><td>Manter o acesso neste aparelho, se você marcar a opção no login</td><td>Prazo longo, até você sair</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="cartao pv-secao" id="direitos">
                    <h2><span class="n">7</span> Seus direitos</h2>
                    <p>Pela LGPD você pode solicitar, a qualquer momento: confirmação de que tratamos seus dados; acesso e cópia (em formato estruturado); correção de dados incorretos ou desatualizados; anonimização, bloqueio ou eliminação de dados desnecessários; informação sobre com quem os dados são compartilhados; revisão de decisões; e a retirada da autorização de publicação nos Trabalhos Publicados.</p>
                    <h3 class="h6 fw-bold mt-4 mb-3">Como exercer, passo a passo</h3>
                    <ol class="pv-passos">
                        <li><span>Escreva para <a href="mailto:{{ $contato }}">{{ $contato }}</a> a partir do e-mail cadastrado na sua conta e diga o que deseja (acesso, correção, eliminação...).</span></li>
                        <li><span>Informe seu nome completo e CPF, para podermos localizar seus dados com segurança. Podemos pedir uma confirmação adicional de identidade.</span></li>
                        <li><span>Responderemos em até <strong>15 dias</strong> (prazo da LGPD para confirmação e acesso). Correções simples você pode fazer sozinho em <em>Minha Conta</em>.</span></li>
                    </ol>
                    <div class="pv-aviso mt-3"><i class="fa-solid fa-circle-info me-1"></i> Se achar que seus direitos não foram respeitados, você também pode reclamar à <a href="https://www.gov.br/anpd/" target="_blank" rel="noopener" style="color:#78350f;font-weight:700;">Autoridade Nacional de Proteção de Dados (ANPD)</a>.</div>
                </section>

                <section class="cartao pv-secao" id="exclusao">
                    <h2><span class="n">8</span> Excluir a conta</h2>
                    <p>Em <em>Minha Conta</em> você pode excluir a sua conta. Seus dados pessoais são <strong>anonimizados</strong>. Trabalhos, notas e presenças permanecem apenas como registro acadêmico, sem identificar você, e certificados em seu nome deixam de ser válidos.</p>
                    <p class="mb-0">Alguns registros são mantidos por exigência legal ou por interesse acadêmico público (como os trabalhos já publicados); nesses casos eliminamos ou anonimizamos o que for possível.</p>
                </section>

                <section class="cartao pv-secao" id="retencao">
                    <h2><span class="n">9</span> Por quanto tempo guardamos</h2>
                    <ul class="mb-0">
                        <li><strong>Conta e cadastro de participante:</strong> enquanto houver vínculo com eventos ou até o pedido de eliminação.</li>
                        <li><strong>Certificados e Trabalhos Publicados:</strong> permanecem como documento acadêmico de interesse público, salvo anonimização a pedido.</li>
                        <li><strong>Rascunhos de trabalho não enviados:</strong> apagados após {{ config('privacidade.dias_rascunho') }} dias sem alteração.</li>
                        <li><strong>Backups:</strong> mantidos por cerca de {{ config('privacidade.dias_backup') }} dias; depois de uma anonimização, os dados antigos somem dos backups nesse prazo.</li>
                        <li><strong>Registros técnicos (logs):</strong> mantidos por prazo curto, apenas para segurança e correção de falhas.</li>
                    </ul>
                </section>

                <section class="cartao pv-secao" id="seguranca">
                    <h2><span class="n">10</span> Segurança</h2>
                    <p>Adotamos medidas técnicas e administrativas para proteger seus dados: senhas guardadas de forma criptografada, acesso por HTTPS, arquivos dos trabalhos em área privada (sem endereço público), telas de gestão com permissão específica por função e registro das ações sensíveis.</p>
                    <p class="mb-0">Em caso de incidente que possa causar risco ou dano relevante, os titulares e a autoridade competente serão comunicados, como determina a LGPD. Use uma senha própria e não a compartilhe: ela é a sua principal proteção.</p>
                </section>

                <section class="cartao pv-secao" id="menores">
                    <h2><span class="n">11</span> Estudantes menores de idade</h2>
                    <p class="mb-0">O sistema atende a comunidade acadêmica, que inclui estudantes menores de 18 anos. Os dados deles são tratados no <strong>seu melhor interesse</strong> e apenas para as finalidades educacionais descritas aqui (inscrição, presença e certificação). Responsáveis podem exercer os direitos acima em nome do estudante, pelo mesmo contato.</p>
                </section>

                <section class="cartao pv-secao" id="mudancas">
                    <h2><span class="n">12</span> Mudanças nesta política</h2>
                    <p class="mb-0">Podemos atualizar este texto para refletir mudanças no sistema ou na lei. A data no topo da página indica a versão vigente ({{ $atualizada->format('d/m/Y') }}). Mudanças relevantes serão informadas na plataforma.</p>
                </section>

                <section class="pv-contato mb-2">
                    <div class="row align-items-center g-3">
                        <div class="col-md-8">
                            <h2 class="mb-1">Ficou com dúvida ou quer exercer um direito?</h2>
                            <p class="mb-0">Fale com a gente pelo canal oficial de privacidade: <a class="mail" href="mailto:{{ $contato }}">{{ $contato }}</a></p>
                        </div>
                        <div class="col-md-4 text-md-end"><a href="mailto:{{ $contato }}" class="btn btn-light px-4"><i class="fa-regular fa-envelope me-1"></i> Enviar e-mail</a></div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <x-slot:scripts>
        <script>
            // Destaca no índice a seção que está na tela
            (function () {
                var links = {};
                document.querySelectorAll('#indice a[data-alvo]').forEach(function (a) { links[a.dataset.alvo] = a; });
                if (!('IntersectionObserver' in window)) return;
                var obs = new IntersectionObserver(function (itens) {
                    itens.forEach(function (i) {
                        if (i.isIntersecting && links[i.target.id]) {
                            Object.values(links).forEach(function (l) { l.classList.remove('ativo'); });
                            links[i.target.id].classList.add('ativo');
                        }
                    });
                }, { rootMargin: '-20% 0px -70% 0px' });
                document.querySelectorAll('.pv-secao').forEach(function (s) { obs.observe(s); });
            })();
        </script>
    </x-slot:scripts>
</x-publico-layout>
