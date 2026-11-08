# Manual do gestor de trabalhos

Para quem coordena a chamada de trabalhos de um evento (dono do evento, editor com gestão ou **Gestor de Trabalhos**). Política de dados e procedimentos de titulares: `docs/lgpd.md`.

## 1. Preparar a chamada
1. **Evento → Equipe/Papéis**: designe *Gestor de Trabalhos* e *Avaliadores* (precisam ter conta; se não tiverem, peça o cadastro).
2. **Gestão de trabalhos → evento → Configuração da chamada** (*Criar chamada* / *Editar chamada*): período de submissão, categorias, modalidades, nº máx. de autores, tamanho do PDF, exigências (resumo, palavras-chave, CPF do apresentador), critérios de avaliação com pesos, prazos de correção, link do template.
3. **Salvar e publicar**. O evento passa a exibir **Submeter trabalho**.

## 2. Triagem
Em **Trabalhos do evento**, abra o trabalho:
- **Aprovar triagem** → segue para avaliação.
- **Devolver** (com motivo) → autor corrige e reenvia. **Rejeitar** → encerra.
Use filtros (status, categoria, busca) e **ações em lote**.

## 3. Avaliação
1. **Atribuir avaliadores** (mínimo conforme a chamada). Avaliadores são notificados.
2. Acompanhe pareceres no painel; **remova/reatribua** se alguém recusar ou atrasar.
3. **Encerrar avaliação** quando houver notas suficientes → status *Avaliado*.
4. **Decisão**: aprovado, aprovado com ressalvas (abre prazo de correção) ou reprovado, com justificativa.
5. Com ressalvas: o autor reenvia; use **Reavaliar** (nova rodada) ou aprove direto. **Prorrogar correção** se necessário.

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

## 6.1 Avaliação da apresentação (feira de trabalhos)
Depois que o trabalho está **aprovado** (inclusive os "com ressalvas" depois da correção), a apresentação pode ser avaliada por avaliadores — os mesmos do texto ou **novos** (designe em *Papéis*).
1. Crie a **atividade** do evento (ex.: "Feira de Trabalhos") e vincule os trabalhos a ela na página de cada trabalho (*Apresentação → Sessão*).
2. **Gestão de trabalhos → Apresentações**: ative a avaliação, defina o **peso da apresentação na nota geral** (padrão 30%), se o autor vê nota/pareceres e os **critérios** (há um modelo padrão: domínio, comunicação, material de apoio, arguição, tempo). Os critérios travam depois da primeira nota.
3. Na mesma tela, marque os trabalhos (ou filtre pela sessão) e **atribua avaliadores em lote**, com prazo opcional. Conflitos de interesse são ignorados; o avaliador recebe e-mail. Também é possível por trabalho, no painel.
4. Acompanhe status e notas por avaliador. **Nota geral = texto × (100 − peso) + apresentação × peso**, exibida no ranking e nas exportações.
5. Avaliador que recusar pode ser reatribuído; avaliações concluídas não são removidas.

## 7. Anais
**Anais**: título, ano, local, editora, ISBN/DOI, apresentação; **Gerar PDF** compilado (requer Ghostscript no servidor) e **Publicar**. A página pública lista só trabalhos **aprovados + apresentados + com autorização de publicação**, mostra nome/instituição dos autores (nunca CPF/e-mail) e citação ABNT. **Arquivo final** pode substituir o PDF original. **Despublicar** retira do ar.

## 8. LGPD no dia a dia
Pedidos de titulares (acesso, correção, eliminação): `php artisan lgpd:exportar|anonimizar` (ver `docs/lgpd.md`). Rascunhos abandonados são limpos automaticamente.

## Checklist de encerramento do evento
- [ ] Todos os trabalhos com decisão. - [ ] Apresentações registradas. - [ ] Certificados emitidos em lote. - [ ] Anais gerados e publicados. - [ ] Exportação das notas arquivada.
