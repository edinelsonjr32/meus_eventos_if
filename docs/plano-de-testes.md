# Plano de Testes — Plataforma Meus Eventos IF

Objetivo: validar todas as funcionalidades existentes, encontrar bugs, inconsistências, falhas de segurança e de usabilidade, e criar a base de regressão para o futuro módulo de trabalhos.

## 1. Escopo, ambiente e massa de dados

**Ambiente:** Docker Compose (Laravel Sail), MySQL 8.4, Mailpit (`http://localhost:8025`), app em `http://localhost:8080` (ou `https://laravel.test.meus-eventos-if.orb.local`).

**Massa de dados:** `php artisan migrate:fresh --seed` + `php artisan db:seed --class=EventoTesteSeeder`.

| Perfil | E-mail | Observação |
|---|---|---|
| Admin A (dono do evento de teste) | `admin@teste.com` | |
| Admin B | criar em `/admin/users` | **não** dono do evento — usado nos testes de autorização |
| Inscrito | `inscrito@teste.com` | já vinculado a um participante |
| Visitante | sem login | |

**Navegadores/dispositivos:** Chrome desktop, Safari desktop, Chrome Android, Safari iOS, WebView de leitor de QR Code (instagram/WhatsApp in-app browser).

**Convenções:** severidade **S1** (bloqueia uso/segurança), **S2** (funcionalidade errada), **S3** (cosmético/usabilidade). Cada caso: ID, pré-condição, passos, resultado esperado.

## 2. Estratégia

1. **Smoke** (30 min): login, abrir cada tela principal, nenhum 500.
2. **Funcional por módulo** (seção 3).
3. **Segurança e autorização** (seção 4) — maior risco do projeto.
4. **Integridade de dados e concorrência** (seção 5).
5. **Não funcional** (seção 6): responsividade, desempenho, acessibilidade.
6. **Automação**: converter os casos S1/S2 em testes Pest (`tests/Feature`), hoje só existem os de Auth/Profile.

## 3. Casos de teste funcionais

### 3.1 Autenticação e conta (AUT)
| ID | Caso | Esperado |
|---|---|---|
| AUT-01 | Registro com CPF válido, e-mail novo, senha forte | Conta `role=user`, participante criado e vinculado ao CPF |
| AUT-02 | Registro com CPF **já existente** em participante sem conta | Vincula ao participante existente (sem duplicar) |
| AUT-03 | Registro com CPF de participante **já vinculado a outro usuário** | Definir comportamento: hoje sobrescreve o `user_id` (risco de sequestro de histórico). Registrar como bug se sobrescrever |
| AUT-04 | CPF com formato inválido / dígitos verificadores errados | Rejeitar (hoje só valida `size:14` — confirmar que `000.000.000-00` é aceito = inconsistência) |
| AUT-05 | Login válido admin / user | Redireciona a `/admin/dashboard` ou `/minha-area` |
| AUT-06 | Login inválido, bloqueio por tentativas (throttle) | Mensagem em pt-BR, bloqueio após 5 tentativas |
| AUT-07 | Esqueci a senha → e-mail no Mailpit → redefinir | Link funciona, expira, não reutilizável |
| AUT-08 | Logout e botão "voltar" | Não exibe páginas autenticadas |
| AUT-09 | Aba de login aberta > 120 min e envio | 419 tratado com mensagem amigável (hoje página crua) |
| AUT-10 | Perfil: editar nome/e-mail/senha; excluir conta | Excluir conta com participante/inscrições/frequências: verificar FK (não pode gerar 500 nem apagar histórico de certificados) |
| AUT-11 | Idioma | Todas as mensagens em pt-BR (`APP_LOCALE`); procurar textos em inglês |

### 3.2 Administração de usuários (USR)
| ID | Caso | Esperado |
|---|---|---|
| USR-01 | Criar usuário com cada perfil | **Inconsistência confirmada:** `/admin/users` só aceita `admin` e `padrao`, mas o registro público, a migration e o `IsAdmin` usam `user`. Um usuário `padrao` não é tratado como inscrito (dashboard e middleware só conhecem `admin`/`user`) |
| USR-02 | Editar perfil de um usuário / trocar e-mail duplicado | Validação de unicidade |
| USR-03 | Excluir a si mesmo / último admin | Deve ser bloqueado |
| USR-04 | Usuário `user` acessa `/admin/*` | Redireciona p/ `/minha-area` |
| USR-05 | Usuário `padrao` (criado pelo admin) acessa `/admin/*` e `/minha-area` | Definir comportamento: hoje recebe 403 no admin e cai na área do inscrito pelo `/dashboard`. Ao editar um usuário `user`, o formulário só oferece `admin`/`padrao`, então pode converter o perfil sem querer |

### 3.3 Eventos (EVT)
| ID | Caso | Esperado |
|---|---|---|
| EVT-01 | Criar evento com todos os campos | Slug único gerado, `criado_por` = admin |
| EVT-02 | Título duplicado | Slug não colide (sufixo) |
| EVT-03 | `data_fim` anterior a `data_inicio` | Rejeitar |
| EVT-04 | Latitude/longitude/raio inválidos (texto, fora de -90/90) | Rejeitar; campos são `string` no banco |
| EVT-05 | Editar evento, trocar slug | URL pública antiga: 404 esperado; confirmar impacto em QR Codes já impressos |
| EVT-06 | Excluir evento (soft delete) | Some da lista e da home; inscrições/frequências preservadas; página pública 404; certificados já emitidos continuam válidos? (definir regra) |
| EVT-07 | Listagem: paginação, busca, ordenação | |
| EVT-08 | Home (`/`): só eventos com `data_fim >= hoje`, máx. 9 | Evento encerrado não aparece |

### 3.4 Atividades (ATV)
| ID | Caso | Esperado |
|---|---|---|
| ATV-01 | Criar atividade de cada tipo | Token de frequência único |
| ATV-02 | Atividade fora do intervalo do evento | Alertar/rejeitar |
| ATV-03 | `data_fim` < `data_inicio`; carga horária 0/negativa; vagas negativas | Rejeitar |
| ATV-04 | Editar atividade | **Token de frequência não pode mudar** (QR impresso) |
| ATV-05 | Excluir atividade com frequências / só com inscrições na agenda | Com frequências: bloqueado com mensagem (já existe). Só com inscrições (`atividade_inscricoes`): confirmar que exclui sem 500 e avisa os inscritos |
| ATV-06 | Lista de participantes, exportar CSV/Excel | Acentos corretos (UTF-8/BOM), CPF como texto |
| ATV-07 | Cadastro manual na atividade | Cria participante/inscrição/frequência; CPF já existente não duplica |
| ATV-08 | Alterar tipo de participação (ouvinte/palestrante/monitor) | Reflete no certificado |
| ATV-09 | Remover frequência | Certificado correspondente deixa de validar |
| ATV-10 | Check-in rápido de inscrito | Cria frequência uma única vez (idempotente) |

### 3.5 Inscrição pública (INS)
| ID | Caso | Esperado |
|---|---|---|
| INS-01 | `/e/{slug}` de evento futuro, em andamento, encerrado | Comportamento definido para cada estado (hoje: confirmar se inscreve em evento encerrado) |
| INS-02 | Inscrever visitante (sem login) | Participante + inscrição criados |
| INS-03 | Inscrever logado | `user_id` vinculado |
| INS-04 | Mesmo CPF duas vezes | Mensagem "já inscrito", sem duplicata |
| INS-05 | **CPF existente com e-mail diferente** / e-mail de outro CPF | Mensagem clara; não alterar dados de terceiros |
| INS-06 | Vínculo `aluno` sem turma/matrícula; `externo` com matrícula | Regras condicionais |
| INS-07 | Dupla submissão (duplo clique / F5 no POST) | Uma única inscrição |
| INS-08 | Slug inexistente | 404 |
| INS-09 | XSS em nome/descrição (`<script>`) | Escapado em todas as telas e no PDF |
| INS-10 | Atividade com vagas esgotadas (área do inscrito) | Bloqueia; **corrida**: dois usuários na última vaga |

### 3.6 Área do inscrito (AGD)
| ID | Caso | Esperado |
|---|---|---|
| AGD-01 | Ver eventos inscritos e cronograma | Só os seus |
| AGD-02 | Marcar/desmarcar atividade | Respeita vagas |
| AGD-03 | **Conflito de horário** entre duas atividades | Bloqueia com mensagem (lógica existe em `toggleAtividade`) |
| AGD-04 | Usuário sem participante vinculado | Mensagem amigável, não 500 |
| AGD-05 | Acessar atividade de evento em que não está inscrito | Negar |
| AGD-06 | Meus certificados disponíveis | Hoje não há listagem para o inscrito — registrar como lacuna funcional |

### 3.7 Frequência por QR Code e GPS (FRQ)
| ID | Caso | Esperado |
|---|---|---|
| FRQ-01 | Abrir `/frequencia/{token}` | Dados da atividade e do local |
| FRQ-02 | GPS permitido, dentro do raio | Registra presença, redireciona |
| FRQ-03 | GPS permitido, fora do raio | 403 com distância; nenhum dado salvo |
| FRQ-04 | GPS negado / indisponível / timeout | Mensagem e "Tentar novamente" |
| FRQ-05 | Navegador sem geolocalização / HTTP sem HTTPS | Mensagem clara |
| FRQ-06 | Evento **sem** coordenadas | Registra sem GPS (confirmar decisão de negócio) |
| FRQ-07 | **Fraude**: POST direto com `user_lat/user_lng` forjados (curl) | Limitação conhecida (GPS é cliente); documentar/mitigar (janela de horário, rate limit) |
| FRQ-08 | **Janela de tempo**: registrar antes do início ou depois do fim da atividade | Hoje não há verificação → provável bug S2 |
| FRQ-09 | Escanear duas vezes | Uma frequência (`firstOrCreate`) |
| FRQ-10 | **Sobrescrita de dados**: informar CPF de terceiros com outro nome/e-mail | Hoje `updateOrCreate` **altera** os dados do participante existente → bug S1 de integridade (qualquer um edita cadastro alheio) |
| FRQ-11 | 419 (sessão expirada, cookie bloqueado) | Recupera token e reenvia |
| FRQ-12 | CPF sem máscara / com letras | Validação consistente com o resto (hoje `size:14`) |
| FRQ-13 | Token inexistente | 404 |
| FRQ-14 | Rate limit (100 POSTs/min do mesmo IP) | Deve haver limitação |

### 3.8 Credenciamento / Recepção (CRD)
| ID | Caso | Esperado |
|---|---|---|
| CRD-01 | Busca por nome, e-mail, matrícula, CPF com e sem máscara, trecho do CPF | Encontra (corrigido; manter em regressão) |
| CRD-02 | Busca com `%`, `_`, `&`, acentos, < 2 caracteres | Sem erro, sem curinga |
| CRD-03 | Check-in e contador "credenciados" | Atualiza sem recarregar; idempotente |
| CRD-04 | Etiqueta/crachá: impressão, nome longo, QR | Layout não quebra |
| CRD-05 | Cadastro manual (novo) | Cria participante + inscrição + check-in |
| CRD-06 | Cadastro manual de CPF já inscrito | Mensagem orientando buscar |
| CRD-07 | Cadastro manual de CPF de participante existente **não inscrito** | Inscreve e credencia; cria inscrição sem violar unique |
| CRD-08 | Pessoa só cadastrada (sem inscrição no evento) na busca | Definir: oferecer "inscrever e credenciar" |
| CRD-09 | Mais de 10 resultados | Limite de 10 sem paginação — registrar usabilidade |
| CRD-10 | Dois recepcionistas ao mesmo tempo | Sem duplicar check-in |
| CRD-11 | Usuário admin B abre credenciamento | 403 (dono) — validar mensagem |

### 3.9 Equipe (EQP)
| ID | Caso | Esperado |
|---|---|---|
| EQP-01 | Adicionar membro por CPF/nome/e-mail | Vincula participante existente |
| EQP-02 | Adicionar o mesmo membro duas vezes / duas funções | Regra definida |
| EQP-03 | Carga horária 0/negativa/enorme | Validar |
| EQP-04 | Remover membro | Certificado de comissão deixa de existir |
| EQP-05 | Admin que não é dono usa `equipe.index` (listar) e `equipe.destroy` (remover) | **Sem checagem de dono** (só o `store` verifica) → S1 |

### 3.10 Certificados (CRT)
| ID | Caso | Esperado |
|---|---|---|
| CRT-01 | Configurar cabeçalho/corpo/rodapé com variáveis `{PARTICIPANTE}`, `{CPF}`, `{ATIVIDADE}`, `{EVENTO}`, `{CARGA_HORARIA}`, `{DATA_EVENTO}`, `{DATA_EMISSAO}`, `{TIPO_PARTICIPACAO}` | Substituídas no PDF |
| CRT-02 | Variável digitada errada (`{participante}`) | Aviso no editor / não aparecer literal |
| CRT-03 | Upload de fundo, brasão, assinaturas (png/jpg/pdf, 10 MB, arquivo corrompido) | Validação de tipo/tamanho; troca remove arquivo antigo |
| CRT-04 | Download PDF: paisagem, 1 página, nome longo, caracteres especiais | Layout íntegro |
| CRT-05 | QR do certificado aponta para a URL correta (APP_URL) | Abre `/certificado/validar/{hash}` |
| CRT-06 | Validação com hash válido / inválido / vazio | Página adequada (corrigido); dados mascarados (CPF) |
| CRT-07 | Hash é UUID/aleatório e não previsível | Sem enumeração; rate limit |
| CRT-08 | `certificado_emitido` é atualizado ao baixar? | Confirmar uso do campo |
| CRT-09 | Certificado de **equipe** e de **palestrante** | Texto/tipo corretos |
| CRT-10 | Alterar texto do certificado depois de emitido | Decidir: reflete retroativamente (provável) — documentar |
| CRT-11 | Download exige liberação (evento encerrado)? | Hoje qualquer um com o hash baixa a qualquer momento |

### 3.11 Relatórios e exportações (REL)
| ID | Caso | Esperado |
|---|---|---|
| REL-01 | Relatório geral de frequência (filtros por evento/período) | Totais batem com o banco |
| REL-02 | Exportar Excel/CSV | Abre no Excel, acentos, CPF não vira notação científica |
| REL-03 | Volume: 5.000 inscritos | Sem timeout/estouro de memória |
| REL-04 | Injeção de fórmula (`=cmd()` em nome) no CSV/Excel | Neutralizada (`'` prefixo) |
| REL-05 | Admin B exporta dados do evento do Admin A | Negado |

### 3.12 Dashboard (DSH)
| ID | Caso | Esperado |
|---|---|---|
| DSH-01 | Totais (eventos, inscritos, presenças) vs. consultas manuais | Idênticos |
| DSH-02 | Só dados do admin logado | Sim, `criado_por` |
| DSH-03 | Sem dados / com muitos dados | Sem erro |

## 4. Segurança e autorização (SEC) — prioridade alta

Matriz: para **cada rota `/admin/*`**, testar com (a) visitante, (b) `user`, (c) admin dono, (d) **admin não dono**, (e) ID inexistente. Esperado: 302/login, redireciona, OK, **403**, 404.

Achados prováveis a confirmar (leitura do código):
| ID | Suspeita | Severidade |
|---|---|---|
| SEC-01 | Só `Evento` (show/edit/update/destroy), credenciamento e `equipe.store` checam `criado_por`; `equipe.index` e `equipe.destroy` não. **Atividades, participantes, frequências, check-in, exportações, `equipe.destroy`, `frequencias.destroy/role`, relatórios** não checam dono → IDOR entre admins | S1 |
| SEC-02 | Rotas públicas `/certificado/*` e `/frequencia/*` sem rate limit | S2 |
| SEC-03 | Mass assignment: `User::$fillable` não inclui `role`, mas `/admin/users` define role por `input` — validar lista de valores aceitos | S2 |
| SEC-04 | CSRF em todos os POST/PUT/PATCH/DELETE; chamadas `fetch` enviam token? (credenciamento usa `fetch` POST) | S1 |
| SEC-05 | Upload: extensão/MIME real, tamanho, nome do arquivo, SVG com script, PDF malicioso; armazenamento em `public` | S1 |
| SEC-06 | XSS refletido/armazenado (nome, descrição, certificado — corpo aceita HTML `<b>`) | S1 |
| SEC-07 | SQL: buscas com aspas, `%`, `\` | S2 |
| SEC-08 | Headers: `APP_DEBUG=false` em produção, cookies `Secure`/`HttpOnly`/`SameSite`, HTTPS forçado | S1 |
| SEC-09 | Enumeração de CPF/e-mail via mensagens de erro (inscrição, frequência) | S2 |
| SEC-10 | Dados pessoais (LGPD): CPF mascarado em telas públicas, exportações restritas, política de exclusão | S2 |
| SEC-11 | `.env`, `storage/logs`, `/up` e rotas de debug não expostos | S1 |
| SEC-12 | Sessão: fixação após login, expiração, logout invalida | S2 |

## 5. Integridade de dados e concorrência (DAT)
| ID | Caso |
|---|---|
| DAT-01 | Constraints únicas: `participantes.cpf`, `inscricoes(evento,participante)`, `frequencias(participante,atividade)`, `atividade_inscricoes` — tentar violar por duas abas simultâneas |
| DAT-02 | Formato do CPF: mesmo CPF salvo com e sem máscara por rotas diferentes cria duplicata? (cadastro manual aceita até 14 chars sem validar formato) → padronizar |
| DAT-03 | Exclusões em cascata/restrição (evento, atividade, participante, usuário) sem 500 e sem perder histórico |
| DAT-04 | Fuso horário: datas exibidas × `APP_TIMEZONE` (config padrão UTC) — evento às 08:00 aparece correto? |
| DAT-05 | Colunas `latitude/longitude` como string, `vagas` nulo |
| DAT-06 | `migrate:fresh --seed` e `migrate:rollback` completos |
| DAT-07 | Backup/restauração do volume MySQL |

## 6. Não funcional (NFR)
- **Responsividade:** todas as telas em 360×640, 768, 1366; menus, tabelas e modais (credenciamento é usado em tablet).
- **Desempenho:** páginas com 1.000+ linhas (inscritos, participantes), N+1 queries (usar Debugbar/`DB::listen`), tempo de geração de PDF, exportações.
- **Acessibilidade:** contraste, foco por teclado, labels de formulário, mensagens de erro lidas por leitor de tela.
- **Dependências externas:** a tela de frequência carrega Bootstrap, Alpine, jQuery e Font Awesome de CDN — testar offline/CDN lento (evento sem internet) → risco S2; considerar empacotar via Vite.
- **Mensagens e i18n:** padronizar pt-BR, erros 403/404/419/500 com páginas amigáveis.
- **Logs:** erros relevantes em `storage/logs`, sem dados sensíveis.

## 7. Bugs e inconsistências já conhecidos (verificar regressão)
1. 419 na frequência (tratado com retry de token e `trustProxies`).
2. Validação de GPS só no cliente (agora também no servidor).
3. View `validacao_sucesso`/`validacao_erro` inexistente (corrigido).
4. Busca de CPF no credenciamento sem normalização (corrigido).
5. `EstruturaAcademicaSeeder` cria cursos duplicados se rodado duas vezes.
6. Perfis `user` × `padrao` inconsistentes (ver USR-01/05).
7. Mensagem de erro do cadastro manual cita "remova ele da equipe" fora de contexto.

## 8. Critérios de aceite e saída
- 0 defeitos S1 abertos; S2 com plano de correção; casos S1/S2 automatizados em Pest.
- Matriz de autorização (seção 4) 100% executada.
- Relatório final: casos executados/aprovados/falhos, bugs por severidade, riscos residuais.

## 9. Cronograma sugerido
| Dia | Atividade |
|---|---|
| 1 | Preparação de ambiente/massa + smoke + AUT/USR |
| 2 | EVT/ATV/INS/AGD |
| 3 | FRQ/CRD/EQP (mobile real) |
| 4 | CRT/REL/DSH |
| 5 | SEC + DAT |
| 6 | NFR + reteste + automação dos S1/S2 |
