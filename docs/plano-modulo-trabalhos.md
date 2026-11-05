# Plano de Implementação — Submissão, Avaliação e Certificação de Trabalhos

Módulo novo da plataforma Meus Eventos IF (Laravel 12, Blade, Alpine, MySQL).

## 1. Objetivo e perfis

Permitir que cada evento receba trabalhos acadêmicos, avalie-os e publique os aprovados e apresentados nos **Anais**, com certificado por autor.

| Perfil | Quem é | Principais ações |
|---|---|---|
| **Autor / Submetente** | usuário logado (`user`) | submeter, editar até o prazo, reenviar versão corrigida, acompanhar status/notas/pareceres, baixar certificado |
| **Gestor de Trabalhos** | por evento | configurar a chamada, receber e triar, atribuir avaliadores, acompanhar prazos, decidir status final, marcar apresentação, publicar Anais, emitir certificados |
| **Avaliador** | por evento | ver apenas os trabalhos atribuídos, atribuir nota por critério, parecer, recomendação |
| **Admin** (existente) | dono do evento | tudo; designa gestores e avaliadores |
| **Público** | visitante | ver Anais publicados e validar certificado |

### Onde guardar os perfis
Os papéis são **por evento**, não globais. A tabela pivô `evento_user` já existe (coluna `role` com 20 caracteres, hoje `editor`/`visualizador`). Reaproveitar com os novos valores `gestor_trabalhos` e `avaliador`, e centralizar a regra em **Policies/Gates**. Não criar novos valores em `users.role`, que já tem inconsistência (`user` × `padrao`).

## 2. Pré-requisitos (fazer antes — dependem do plano de testes)

1. **Autorização por evento (SEC-01):** hoje só parte das rotas checa `criado_por`. Criar `EventoPolicy` (`gerenciar`, `gerirTrabalhos`, `avaliar`) e aplicar a todo o admin. O módulo novo já nasce com isso.
2. **Padronizar perfis:** unificar `user`/`padrao` (migration + `UserController` + `IsAdmin`).
3. **CPF:** criar uma regra de validação e normalização única (`App\Rules\Cpf`) e padronizar o formato salvo em `participantes.cpf`. Autores são identificados por CPF.
4. **Armazenamento privado:** PDFs em disco **privado** (`storage/app/private`), não no `public`. Download só por controller autorizado.
5. **Fila e e-mail:** `QUEUE_CONNECTION=database` já está no `.env`; incluir `php artisan queue:work` no Sail/supervisor. E-mails vão ao Mailpit em desenvolvimento.
6. **Teto de upload:** `upload_max_filesize`/`post_max_size` do PHP (Sail) ≥ limite do PDF (sugestão 20 MB) e validação no Laravel.

## 3. Fluxo e status

```
rascunho → submetido → em_triagem ─┬→ devolvido_triagem (formato/anonimização) → submetido
                                   ├→ rejeitado_triagem
                                   └→ em_avaliacao → avaliado ─┬→ aprovado ──┐
                                                               ├→ aprovado_com_ressalvas → aguardando_correcao → resubmetido → (volta a em_avaliacao ou aprovado pelo gestor)
                                                               └→ reprovado
aprovado → apresentado   (somente aprovado)
qualquer ativo → retirado (pelo autor, antes da decisão)
```

- **Estado final de publicação:** `aprovado` + `apresentado = true` ⇒ elegível aos Anais e ao certificado do trabalho.
- Todas as transições passam por um serviço (`TrabalhoWorkflow`) que valida a regra, grava o **histórico** (quem, quando, de/para, justificativa) e dispara notificações.
- Status calculado a partir das avaliações: média das notas ≥ `nota_minima_aprovacao` ⇒ sugestão de `aprovado`; divergência > X pontos entre avaliadores ⇒ pede **terceiro avaliador**; decisão final sempre confirmada pelo gestor.

## 4. Modelo de dados (migrations)

```
eventos ─┬─ trabalho_configs (1:1)
         ├─ areas_tematicas (1:N)
         ├─ criterios_avaliacao (1:N)
         ├─ trabalhos (1:N) ─┬─ trabalho_autores (1:N) ── participantes (N:1, opcional)
         │                   ├─ trabalho_arquivos (1:N, versões)
         │                   ├─ trabalho_avaliacoes (1:N) ── users (avaliador)
         │                   │        └─ trabalho_avaliacao_notas (1:N → criterios)
         │                   ├─ trabalho_historico (1:N)
         │                   └─ certificados_trabalho (1:N, um por autor)
         └─ anais (1:1 por edição)
```

| Tabela | Campos principais |
|---|---|
| `trabalho_configs` | `evento_id` (unique), `submissao_inicio`, `submissao_fim`, `prazo_correcao`, `avaliacao_fim`, `max_autores`, `max_arquivo_mb`, `avaliadores_por_trabalho`, `avaliacao_cega` (bool), `nota_minima_aprovacao`, `escala_nota_max` (ex. 10), `tipos_permitidos` (json), `exige_apresentacao_para_certificado` (bool), textos do certificado (`cert_trab_cabecalho/corpo/rodape`), `regras_texto` |
| `areas_tematicas` | `evento_id`, `nome`, `ordem` |
| `criterios_avaliacao` | `evento_id`, `nome`, `descricao`, `peso`, `nota_max`, `ordem`, `ativo` |
| `trabalhos` | `evento_id`, `codigo` (protocolo único, ex. `TRB-2026-0001`), `submetido_por` (user), `titulo`, `resumo`, `abstract` (opcional), `palavras_chave` (json), `tipo_trabalho` (resumo, resumo_expandido, artigo_completo, relato_experiencia, pôster), `area_tematica_id`, `modalidade_apresentacao` (oral/pôster), `linha_pesquisa`, `agencia_fomento`, `projeto_pesquisa`, `comite_etica` (texto/numero), `status`, `nota_final`, `decisao_em`, `decidido_por`, `autoriza_publicacao` (bool + data), `apresentado` (bool), `apresentado_em`, `atividade_id` (sessão em que apresenta, opcional), `softDeletes` |
| `trabalho_autores` | `trabalho_id`, `ordem`, `participante_id` (nullable), `nome_completo`, `cpf`, `email`, `telefone`, `instituicao`, `vinculo` (aluno/servidor/externo), `curso`/`titulacao`, `lattes`, `orcid`, **`tipo_autor`** (`autor_principal`, `coautor`, `orientador`, `coorientador`, `apresentador`), `apresentador` (bool), `correspondente` (bool). Unique `(trabalho_id, cpf)`. Regras: exatamente 1 autor principal; ao menos 1 apresentador; orientador não conta no limite de autores (configurável) |
| `trabalho_arquivos` | `trabalho_id`, `versao`, `tipo` (`original`, `corrigido`, `anonimizado`, `final_anais`), `path`, `nome_original`, `mime`, `tamanho`, `sha256`, `enviado_por`, `ativo` |
| `trabalho_avaliacoes` | `trabalho_id`, `avaliador_id` (user), `rodada`, `status` (`pendente`, `em_andamento`, `concluida`, `recusada`), `nota_final`, `parecer`, `parecer_confidencial` (só gestor), `recomendacao` (`aprovar`, `aprovar_com_ressalvas`, `reprovar`), `atribuido_por`, `prazo`, `concluida_em`. Unique `(trabalho_id, avaliador_id, rodada)` |
| `trabalho_avaliacao_notas` | `avaliacao_id`, `criterio_id`, `nota`, `comentario` |
| `trabalho_historico` | `trabalho_id`, `usuario_id`, `de`, `para`, `justificativa`, `created_at` |
| `certificados_trabalho` | `trabalho_id`, `trabalho_autor_id`, `hash_validacao` (unique, aleatório), `tipo` (`autor`, `orientador`, `apresentador`), `emitido_em`, `emitido_por`, `downloads` |
| `anais` | `evento_id`, `titulo`, `edicao`, `issn`/`isbn`, `doi_prefixo`, `apresentacao`, `comissao` (texto), `status` (`rascunho`/`publicado`), `publicado_em`, `pdf_path` (compilado) |

Índices: `trabalhos(evento_id,status)`, `trabalho_autores(cpf)`, `trabalho_avaliacoes(avaliador_id,status)`.

## 5. Matriz de permissões

| Ação | Autor | Avaliador | Gestor | Admin |
|---|:--:|:--:|:--:|:--:|
| Submeter / editar rascunho e dentro do prazo | ✔ (próprio) | | ✔ (por exceção) | ✔ |
| Ver trabalho | próprio | só atribuídos | todos do evento | todos |
| Ver identidade dos autores | ✔ | só se avaliação **não** cega | ✔ | ✔ |
| Baixar PDF | próprio | atribuídos (versão anonimizada se cega) | ✔ | ✔ |
| Atribuir avaliador | | | ✔ | ✔ |
| Avaliar (nota/parecer) | | ✔ (se não for autor/conflito) | | |
| Decidir status final | | | ✔ | ✔ |
| Marcar "apresentado" | | | ✔ | ✔ |
| Publicar Anais / emitir certificados | | | ✔ | ✔ |
| Configurar chamada, critérios, áreas | | | ✔ | ✔ |
| Designar gestor/avaliador | | | | ✔ |

Regras de **conflito de interesse**: avaliador não pode ser autor/orientador do trabalho nem (opcional) da mesma instituição; o sistema bloqueia a atribuição.

## 6. Formulário de submissão

Assistente em **etapas** (Alpine), com salvamento de **rascunho** a cada etapa:

1. **Dados do trabalho:** título, resumo (com contador, mín./máx. palavras configuráveis), abstract (opcional), palavras-chave (3–5), tipo do trabalho, área temática, modalidade de apresentação, agência de fomento, projeto de pesquisa, comitê de ética.
2. **Autores** (lista dinâmica, reordenável, até `max_autores`):
   - Campos por autor: nome completo, CPF, e-mail, telefone, instituição, vínculo, titulação/curso, Lattes, ORCID.
   - **Tipo de autor:** autor principal, coautor, orientador, coorientador.
   - Marcadores: **apresentador** (≥ 1) e **correspondente** (1).
   - Ao digitar o CPF: autopreenchimento se o participante já existe (sem sobrescrever dados de terceiros — ver FRQ-10 do plano de testes; só preenche campos vazios e pede confirmação).
   - O submetente é pré-preenchido como autor principal.
   - Validações: CPF válido e sem duplicata no trabalho, exatamente 1 principal, ≥ 1 apresentador, e-mails únicos.
3. **Arquivo PDF:** upload com barra de progresso, arraste-e-solte, limite configurável (padrão 20 MB), somente `application/pdf`, validação de magic bytes `%PDF-`, cálculo do SHA-256, pré-visualização do nome/tamanho/páginas. Se `avaliacao_cega`, instrução e checagem para enviar a versão **sem identificação** (opcionalmente um segundo upload "versão anonimizada").
4. **Declarações:** autoriza publicação nos Anais (obrigatório para aparecer), declara ineditismo e concordância de todos os autores, aceita o regulamento.
5. **Revisão e envio:** resumo de tudo, botão **Submeter** (gera código/protocolo, trava edição, envia e-mail de confirmação a todos os autores).

Pós-envio: autor acompanha em **Meus Trabalhos** (status, histórico, notas e pareceres liberados pelo gestor, botão "Enviar versão corrigida" quando `aguardando_correcao`).

## 7. Avaliação

- **Gestor** vê fila de trabalhos com filtros (status, área, tipo, avaliador, pendências), seleciona e **atribui N avaliadores** (sugestão automática por área e carga, respeitando conflitos).
- **Avaliador** tem a tela *Meus trabalhos para avaliar*: abre o PDF no navegador, preenche **nota por critério** (cada critério com peso e nota máxima), **parecer**, **recomendação** e, se desejar, comentário confidencial ao gestor. Salva rascunho, depois conclui (não edita mais; só o gestor pode reabrir).
- **Nota final do trabalho:** média ponderada por critério, depois média entre avaliadores; divergência acima do limite aciona terceiro avaliador.
- **Gestor decide** o status final e escolhe o que o autor vê (parecer completo/resumido, anônimo).
- **Prazos:** lembretes automáticos (agendador `schedule`) a avaliadores atrasados; relatório de pendências.

## 8. Apresentação e certificação

**Marcação de "apresentado":**
- Manual pelo gestor (lista com filtro por sessão), ou
- Automática: o trabalho é vinculado a uma `atividade` (sessão de apresentação) e a presença do **autor apresentador** nessa atividade (módulo de Frequência já existente, QR + GPS) marca `apresentado = true`. O gestor pode corrigir manualmente.

**Certificado do trabalho** (um por autor, tipo conforme `tipo_autor`: autor, orientador, apresentador):
- Condição: `status = aprovado` **e** (`apresentado = true` ou `exige_apresentacao_para_certificado = false`).
- Textos configuráveis por evento com variáveis: `{AUTOR}`, `{CPF}`, `{TIPO_AUTOR}`, `{TITULO_TRABALHO}`, `{TIPO_TRABALHO}`, `{AREA}`, `{COAUTORES}`, `{EVENTO}`, `{DATA_APRESENTACAO}`, `{CODIGO_TRABALHO}`.
- Reutilizar a infraestrutura atual: `barryvdh/laravel-dompdf`, QR Code, assinaturas e fundo do evento, e o mesmo padrão do `CertificadoController`. Novo `CertificadoTrabalhoController` com `download` e `validar` (rotas `/certificado-trabalho/...`) e `hash_validacao` aleatório.
- A página de validação mostra título, autores, evento e status; CPF mascarado.
- Emissão **em lote** (todos os autores de todos os trabalhos elegíveis) e **individual**; reemissão registra auditoria.
- O autor baixa o certificado em *Meus Trabalhos*.

## 9. Anais de Trabalhos

**Página pública** `/e/{slug}/anais` (somente com `anais.status = publicado`):
- Lista **apenas** trabalhos `aprovado` + `apresentado` + `autoriza_publicacao`.
- Agrupamento/filtros: área temática, tipo, modalidade, ordem alfabética, busca por título, autor ou palavra-chave; paginação.
- Cada item: título, autores (com tipos), resumo e palavras-chave, **citação ABNT** copiável, botão **Baixar PDF** (arquivo `final_anais` ou o aprovado), e **Certificado**: o autor informa o CPF e e-mail cadastrados para baixar o seu, ou usa o link do e-mail/Meus Trabalhos. Sem expor CPF nem listas de certificados publicamente.
- Metadados para indexação (`<meta name="citation_*">` estilo Google Scholar) e URL estável por trabalho: `/e/{slug}/anais/{codigo}`.

**Área do gestor — montagem dos Anais:**
- Cadastro da edição (título, ISSN/ISBN, comissão, apresentação) e ordem/seções.
- Pré-visualização e **geração do PDF compilado** (capa, ficha catalográfica/expediente, comissão, sumário, trabalhos por área com PDFs anexados — usar `setasign/fpdi` ou `pdfunite`/`qpdf` no container) via **job em fila**, com arquivo salvo e versionado.
- **Publicar/Despublicar** com registro de data e quem publicou.

## 10. Rotas e telas (resumo)

```
Autor      GET  /e/{slug}/trabalhos/submeter      wizard (rascunho)
           POST /trabalhos                        criar/atualizar rascunho
           POST /trabalhos/{t}/submeter
           POST /trabalhos/{t}/arquivo            upload (nova versão)
           GET  /minha-area/trabalhos             lista e acompanhamento
           GET  /trabalhos/{t}/arquivo            download autorizado
           GET  /trabalhos/{t}/certificados/{autor}
Avaliador  GET  /avaliador/trabalhos              meus para avaliar
           GET|POST /avaliador/trabalhos/{t}      formulário de avaliação
Gestor     /admin/eventos/{e}/trabalhos           fila, filtros, exportação
           /admin/eventos/{e}/trabalhos/config    chamada, prazos, critérios, áreas
           /admin/trabalhos/{t}                   detalhe, histórico, atribuir, decidir
           /admin/eventos/{e}/trabalhos/certificados   emissão em lote
           /admin/eventos/{e}/anais               montagem, publicar
Admin      /admin/eventos/{e}/papeis              designar gestores e avaliadores
Público    GET  /e/{slug}/anais, /e/{slug}/anais/{codigo}
           GET  /certificado-trabalho/validar/{hash} e /download/{hash}
```

Estrutura de código: `app/Models/Trabalho*.php`, `app/Services/TrabalhoWorkflow.php`, `app/Policies/TrabalhoPolicy.php`, `app/Http/Controllers/{Trabalho,Avaliador,Admin\Trabalho,Admin\Anais,Publico\Anais,Publico\CertificadoTrabalho}Controller.php`, `app/Http/Requests/SubmeterTrabalhoRequest.php`, `app/Notifications/*`, `app/Jobs/GerarAnaisPdf.php`, views em `resources/views/{trabalhos,avaliador,admin/trabalhos,admin/anais,publico/anais,certificados}`.

## 11. Notificações (e-mail, em fila)
Submissão recebida (todos os autores), devolvido na triagem, atribuição de avaliação, lembrete de prazo, resultado/parecer, solicitação de correção, correção recebida (gestor), trabalho aprovado, certificado disponível, Anais publicados.

## 12. Segurança, privacidade e qualidade

- **Autorização** exclusivamente por Policies; testes de IDOR para cada rota (autor A não vê trabalho de B; avaliador só vê atribuídos; gestor só do seu evento).
- **Upload:** disco privado, nome randômico, validação de MIME real, tamanho, SHA-256, (opcional) ClamAV em container; `Content-Disposition` seguro; PDF servido em `inline` com `X-Content-Type-Options: nosniff`.
- **Avaliação cega:** a view do avaliador não consulta tabelas de autores; versão anonimizada do arquivo; nome do arquivo não revela autores.
- **LGPD:** consentimento explícito para publicação, CPF mascarado em telas públicas, política de retenção e exclusão, auditoria de acesso a dados pessoais.
- **Integridade:** transações nas mudanças de status, locks para evitar dupla atribuição/decisão, constraints únicas, protocolo gerado de forma atômica.
- **Rate limit** nas rotas públicas (validação de certificado, consulta de certificado nos Anais).
- Escapar HTML em todos os campos livres (resumo, parecer); os textos do certificado continuam aceitando só tags permitidas.

## 13. Testes
- **Unitários:** workflow de status (transições válidas/ inválidas), cálculo de nota ponderada, regras de autores (1 principal, ≥ 1 apresentador), validação de CPF, geração de código/protocolo, variáveis do certificado.
- **Feature (Pest):** submissão completa com upload, bloqueio fora do prazo, edição pós-envio, atribuição e conflito de interesse, avaliação e conclusão, correção e nova versão, decisão do gestor, marcação de apresentado (manual e via frequência), emissão e validação de certificado, Anais só com `aprovado + apresentado + autorizado`, matriz de autorização completa.
- **Dusk/Playwright (E2E):** fluxo autor→gestor→avaliador→Anais→certificado.
- **Dados de teste:** `TrabalhosTesteSeeder` (1 gestor, 3 avaliadores, 15 trabalhos em todos os status, autores variados, PDFs de exemplo, Anais publicado).
- Os casos do módulo entram no plano de testes (novas siglas `TRB`, `AVL`, `ANL`, `CTT`).

## 14. Fases, entregas e estimativa

| Fase | Entregas | Esforço |
|---|---|---|
| **0 — Fundação** | Policies/autorização por evento, padronização de perfis, regra de CPF, disco privado, fila, papéis em `evento_user` + tela de designação | 3–4 d |
| **1 — Submissão** | Migrations, models, configuração da chamada (prazos, áreas, tipos), wizard, autores, upload PDF, rascunho, protocolo, e-mail, "Meus Trabalhos" | 6–8 d |
| **2 — Gestão** | Fila do gestor, triagem, atribuição de avaliadores, histórico, exportação CSV/Excel | 4–5 d |
| **3 — Avaliação** | Critérios, tela do avaliador, cálculo de nota, decisão, correção/resubmissão, lembretes | 5–6 d |
| **4 — Apresentação e certificados** | Marcação de apresentado (manual + frequência), certificados por autor, validação pública, emissão em lote | 4–5 d |
| **5 — Anais** | Página pública, busca, citação ABNT, geração do PDF compilado em fila, publicação | 4–5 d |
| **6 — Fechamento** | Testes E2E, seeders, segurança, desempenho, documentação, treinamento dos gestores | 3–4 d |

Total aproximado: **29–37 dias úteis** para 1 desenvolvedor (entrega incremental: ao fim da Fase 1 já recebe submissões; ao fim da 3, já avalia).

## 15. Riscos
| Risco | Mitigação |
|---|---|
| Vazamento de PDFs/dados entre usuários | disco privado + Policies + testes de IDOR |
| Quebra do anonimato na avaliação cega | versão anonimizada, view sem autores, checagem do gestor |
| PDFs grandes/PHP limites | ajustar `upload_max_filesize`, fila e timeouts, validação no cliente |
| Compilar Anais pesado | job em fila + ferramenta nativa (`qpdf`/`pdfunite`) no container |
| Autores duplicados/CPF divergente | normalização única e vínculo por CPF com confirmação |
| Mudança de regras no meio da chamada | configurações versionadas e congeladas após abertura |
| Dependência de CDN nas telas | empacotar assets com Vite no novo módulo |

## 16. Decisões em aberto (precisam do responsável pelo evento)
1. Avaliação **cega** (duplo-cega) é obrigatória ou opcional por evento?
2. Quantos avaliadores por trabalho e qual a regra de desempate/divergência?
3. Escala e critérios de nota padrão (sugestão: 0–10, 5 critérios).
4. Um autor pode submeter mais de um trabalho por evento? Há limite?
5. Certificado do **avaliador** e do **orientador** entram no escopo?
6. O certificado do trabalho exige apresentação confirmada, ou só aprovação?
7. Os Anais terão ISBN/ISSN e DOI? Gerar o PDF compilado é requisito?
8. Autor pode ver o parecer completo, só um resumo ou apenas a nota?
9. Formatos aceitos além de PDF (ex.: DOCX para editoração dos Anais)?
10. Prazos de reenvio após ressalvas e política de retirada do trabalho.
