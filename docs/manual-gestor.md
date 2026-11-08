# Manual do gestor de trabalhos

Para quem coordena a chamada de trabalhos de um evento (dono do evento, editor com gestão ou **Gestor de Trabalhos**). Política de dados e procedimentos de titulares: `docs/lgpd.md`.

## 1. Preparar a chamada
1. **Evento → Equipe/Papéis**: designe *Gestor de Trabalhos* e *Avaliadores* (precisam ter conta; se não tiverem, peça o cadastro).
2. **Gestão de trabalhos → evento → Configuração da chamada** (*Criar chamada* / *Editar chamada*): período de submissão, categorias, modalidades, nº máx. de autores, tamanho do PDF, exigências (resumo, palavras-chave, CPF do apresentador), critérios de avaliação com pesos, prazos de correção, link do template.
3. **Salvar e publicar**. O evento passa a exibir **Submeter trabalho**.

## 1.1 Tags de pendência
Na fila e no painel do trabalho aparecem **tags** que dizem o que falta para ele avançar: *Aguardando avaliadores*, *Aguardando decisão da gestão*, *Aguardando aprovação do reenvio*, *Aguardando ajustes do autor*, *Aguardando vincular apresentador* (nenhum autor apresentador com conta vinculada: a presença na sessão não confirma a apresentação sozinha), *Aguardando definir sessão*, *Aguardando avaliadores da apresentação* e *Aguardando resultado da apresentação*. Use o filtro **Qualquer pendência** para listar os trabalhos com uma delas. Para vincular o apresentador, use o cartão *Apresentação* do trabalho (**Vincular**).

## 2. Triagem
Em **Trabalhos do evento**, abra o trabalho:
- **Aprovar triagem** → segue para avaliação.
- **Devolver** (com motivo) → autor corrige e reenvia. **Rejeitar** → encerra.
Use filtros (status, categoria, busca) e **ações em lote**: *Atribuir avaliadores*, *Iniciar triagem* e **Encaminhar para avaliação** em massa (só entram os trabalhos em triagem que já têm o mínimo de avaliadores da chamada; os demais são listados com o motivo, por exemplo "faltam 1 avaliador(es)").

## 3. Avaliação
1. **Atribuir avaliadores** pela caixa de seleção com busca (digite para filtrar; quem tem conflito aparece desabilitado). O botão **Encaminhar para avaliação** só libera quando o **mínimo de avaliadores da chamada** foi atribuído (recusas não contam). No detalhe do trabalho a lista separa **Disponíveis**, **Já atribuídos a este trabalho** (inclui quem recusou) e **Conflito de interesse** (por exemplo, o avaliador é autor ou responsável pelo trabalho e não pode se avaliar): os impedidos aparecem desabilitados, com o motivo. No lote, o resumo lista, por avaliador, em quais trabalhos há impedimento antes de você confirmar. Colocou o avaliador errado? **Cancelar envio para avaliação** (volta à triagem, desde que nenhuma avaliação tenha sido concluída), remova o avaliador, atribua o certo e encaminhe de novo. Avaliadores são notificados.
2. Acompanhe pareceres no painel; **remova/reatribua** se alguém recusar ou atrasar.
3. **Encerrar avaliação** quando houver notas suficientes → status *Avaliado*.
4. **Decisão**: aprovado, aprovado com ressalvas (abre prazo de correção) ou reprovado, com justificativa.
5. Com ressalvas: o autor reenvia e o trabalho fica **Resubmetido**: use o botão verde **Aprovar trabalho** (no painel do trabalho ou direto na fila) para aprovar de uma vez, ou **Reavaliar** (nova rodada) / **Reprovar** na decisão final; use **Reavaliar** (nova rodada) ou aprove direto. **Prorrogar correção** se necessário.

## 4. Notas e relatórios
- **Ranking**: média por trabalho, por categoria. **Exportar notas** (xlsx/csv) por relatório: ranking, notas por critério, pareceres.
- **Exportar** lista completa (CSV recomendado em eventos muito grandes).
- Pareceres/identidade dos avaliadores nunca vão ao público.

## 5. Gestão em nome do autor
**Novo trabalho** cadastra um trabalho recebido por outro canal (já entra como submetido; PDF opcional). **Editar** corrige dados/autores sem perder certificados já emitidos.

## 6. Apresentação e certificados
1. Marque **apresentado** manualmente na página do trabalho **ou** deixe a **frequência na sessão** marcar (CPF ou e-mail do autor).
2. **Certificados**: emissão individual ou **em lote**; cada autor recebe o seu; é possível **revogar**. Por padrão exige apresentação (configurável).
3. Validação pública por código/QR em `/certificado-trabalho/validar/CÓDIGO`.

> **Onde marco "apresentado"?** Em dois lugares: na página do trabalho, cartão **Apresentação → Marcar como apresentado** (há um atalho no cartão *Etapas*, no topo), ou em **Gestão de trabalhos → Apresentações**, botão **Marcar** na linha do trabalho. Se o trabalho tem sessão, a presença do autor apresentador na atividade também marca sozinha. "Apresentado" serve ao certificado; a **nota** da apresentação é separada.

## 6.0 Regra de aprovação (texto + apresentação)
Com a avaliação de apresentações **ativada**, o trabalho só é **Aprovado (100%)** se for aprovado na **parte escrita** *e* na **apresentação** (média dos avaliadores ≥ *nota mínima da apresentação*, configurável). Aprovado só no texto aparece como **Aprovado na parte escrita** (aguardando ou não aprovado na apresentação). Só vai à apresentação quem foi aprovado no texto, e **apenas os aprovados nas duas partes entram nos Anais**. O resultado da apresentação só sai quando todos os avaliadores atribuídos concluíram. Com a avaliação de apresentações desativada, vale a aprovação do texto, como antes.

## 6.1 Avaliação da apresentação (feira de trabalhos)
Depois que o trabalho está **aprovado** (inclusive os "com ressalvas" depois da correção), a apresentação pode ser avaliada por avaliadores — os mesmos do texto ou **novos** (designe em *Papéis*).
1. Crie a **atividade** do evento (ex.: "Feira de Trabalhos") e vincule os trabalhos a ela na página de cada trabalho (*Apresentação → Sessão*).
2. **Gestão de trabalhos → Apresentações**: ative a avaliação, defina o **peso da apresentação na nota geral** (padrão 30%), se o autor vê nota/pareceres e os **critérios** (há um modelo padrão: domínio, comunicação, material de apoio, arguição, tempo). Os critérios travam depois da primeira nota.
3. Na mesma tela, marque os trabalhos (ou filtre pela sessão) e **atribua avaliadores em lote**, com prazo opcional. Conflitos de interesse são ignorados; o avaliador recebe e-mail. Também é possível por trabalho, no painel.
4. Acompanhe status e notas por avaliador. **Nota geral = texto × (100 − peso) + apresentação × peso**, exibida no ranking e nas exportações.
5. Avaliador que recusar pode ser reatribuído; avaliações concluídas não são removidas.

## 6.5 Fim do fluxo: certificados e conclusão
Depois de aprovado nas duas partes (e apresentado), o trabalho fica **Aguardando emissão de certificado**. Na fila de trabalhos marque os trabalhos e use a ação em lote **Emitir certificados**: cada um passa a **Concluído (certificados emitidos)**, último passo do fluxo. Há filtro e contador para essas duas situações.

**Desempate:** se a divergência entre avaliadores passar do limite da chamada, o painel do trabalho oferece **Adicionar avaliador de desempate** (trabalho "avaliado" volta a "em avaliação" até ele concluir; a nota passa a ser a média de todos).

## 7. Anais
**Só concluídos autorizados entram:** o trabalho precisa estar aprovado, apresentado, com PDF e com a **publicação autorizada pelos autores**. Se um concluído não aparece, veja o painel *Concluídos que não entram nos Anais* (motivo por trabalho) e use **Solicitar autorização aos autores** (e-mail; o autor autoriza na página do trabalho, mesmo depois da aprovação).
**Visibilidade:** a página dos Anais pode ser tornada **visível a qualquer momento** (mesmo antes do fim do evento) e os **dados de publicação** (ISSN, ISBN, DOI, editora, local, ano, citação ABNT e PDF compilado) ficam **ocultos** até você clicar em *Liberar dados de publicação* — normalmente depois do evento. A página avisa o público de que esses dados serão divulgados depois.


**Anais**: título, ano, local, editora, ISBN/DOI, apresentação; **Gerar PDF** compilado (requer Ghostscript no servidor) e **Publicar**. A página pública lista só trabalhos **aprovados + apresentados + com autorização de publicação**, mostra nome/instituição dos autores (nunca CPF/e-mail) e citação ABNT. **Arquivo final** pode substituir o PDF original. **Despublicar** retira do ar.

## 8. LGPD no dia a dia
Pedidos de titulares (acesso, correção, eliminação): `php artisan lgpd:exportar|anonimizar` (ver `docs/lgpd.md`). Rascunhos abandonados são limpos automaticamente.

## Checklist de encerramento do evento
- [ ] Todos os trabalhos com decisão. - [ ] Apresentações registradas. - [ ] Certificados emitidos em lote. - [ ] Anais gerados e publicados. - [ ] Exportação das notas arquivada.
