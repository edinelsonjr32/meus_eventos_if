# LGPD — Tratamento de dados pessoais no Meus Eventos IF

Documento operacional para o responsável pelo sistema. A política exibida aos usuários está em `/privacidade`
(`resources/views/privacidade.blade.php`), alimentada por `config/privacidade.php`.

> Este material é um guia técnico, não parecer jurídico. Valide o texto da política e as bases legais com o
> encarregado (DPO) / assessoria jurídica da instituição.

## 1. Configuração (`.env`)

| Variável | Padrão | Uso |
|---|---|---|
| `PRIVACIDADE_CONTROLADOR` | IFPA - Campus Óbidos | Nome do controlador exibido na política |
| `PRIVACIDADE_CONTATO` | `MAIL_FROM_ADDRESS` | Canal do titular para exercer direitos |
| `PRIVACIDADE_ENCARREGADO` | (vazio) | Nome/contato do encarregado, se houver |
| `PRIVACIDADE_DIAS_RASCUNHO` | 180 | Dias até apagar rascunhos abandonados |
| `PRIVACIDADE_DIAS_BACKUP` | 14 | Prazo de rotação de backups (informado na política) |

## 2. Inventário de dados

| Dado | Onde | Finalidade | Base legal sugerida | Publicado? |
|---|---|---|---|---|
| Nome, e-mail, senha (hash) | `users` | Acesso ao sistema | Execução de serviço / consentimento | Não |
| Nome, CPF, e-mail, matrícula, vínculo | `participantes` | Inscrição, frequência, certificados | Execução de atividade institucional | Não |
| Inscrições, presenças (com data/hora) | `inscricoes`, `frequencias` | Controle de participação e certificação | Interesse institucional / obrigação acadêmica | Não |
| Localização (GPS) na frequência | Apenas validada no instante | Confirmar presença no local | Consentimento (permissão do navegador) | **Não é armazenada** |
| Autores: nome, instituição, CPF, e-mail, Lattes/ORCID | `trabalho_autores` | Autoria, certificado, contato | Execução de atividade institucional | Só nome e instituição nos Anais |
| PDF do trabalho | disco `trabalhos` (privado) | Avaliação e Anais | Execução / consentimento (`autoriza_publicacao`) | Só se aprovado, apresentado e autorizado |
| Pareceres e notas | `trabalho_avaliacoes` | Avaliação | Interesse institucional | Pareceres internos não aparecem ao público |
| Avaliações de apresentação | `apresentacao_avaliacoes` | Avaliação na feira | Interesse institucional | Pareceres internos não aparecem ao público |
| Certificados | `certificados_trabalho` | Comprovação | Obrigação / interesse do titular | Validação pública por código (sem CPF/e-mail) |
| Auditoria LGPD | `lgpd_registros` | Prestação de contas | Obrigação legal | Não (guarda só o hash do identificador) |

## 3. Direitos do titular — procedimentos

Todos os comandos rodam no servidor (SSH ou cron único do cPanel). O identificador é e-mail ou CPF.

```bash
# Acesso / portabilidade — gera JSON em storage/app/private/lgpd/
php artisan lgpd:exportar usuario@exemplo.com
php artisan lgpd:exportar 111.444.777-35

# Eliminação — anonimiza (pede confirmação; --confirmar para não interativo)
php artisan lgpd:anonimizar usuario@exemplo.com
php artisan lgpd:anonimizar 111.444.777-35 --confirmar
```

A exportação traz só dados **do próprio titular**: dados de coautores não vazam. Entregue o arquivo por canal
seguro (nunca por e-mail em texto aberto) e apague-o depois.

**O que a anonimização faz**
- Conta: nome "Usuário anonimizado", e-mail `anonimo-{id}@anonimizado.invalid`, senha invalidada, sessões e papéis em eventos removidos.
- Participante: nome "Titular anonimizado", CPF `ANON-…` (único), e-mail e matrícula limpos.
- Autoria: nome "Titular anonimizado", CPF/e-mail/telefone/Lattes/ORCID limpos.
- Certificados do titular são **revogados** (a página de validação mostra "revogado").
- Preservados, sem identificar a pessoa: inscrições, presenças, trabalhos, notas (registro acadêmico/estatístico).

**Exclusão pelo próprio titular** (Perfil → Excluir conta): sempre anonimiza; apaga o usuário de fato só
quando não há vínculos (trabalhos, avaliações, eventos criados) — antes isso gerava erro 500.

**Correção**: o titular edita o perfil; dados de autoria, pela gestão de trabalhos (editar autor).

**Prazo de resposta**: a LGPD prevê atendimento “imediato” ou em até 15 dias para declaração completa. Registre
o pedido (data, quem atendeu) além da trilha automática.

## 4. Retenção

| Dado | Prazo | Mecanismo |
|---|---|---|
| Rascunhos de trabalho abandonados (e PDFs) | `PRIVACIDADE_DIAS_RASCUNHO` (180 d) sem alteração | `lgpd:limpar-rascunhos`, agendado aos domingos 03:30 |
| Trabalhos enviados, inscrições, presenças, certificados | Enquanto o evento/Anais for referência institucional; anonimizar a pedido | Manual (§3) |
| Backups | `PRIVACIDADE_DIAS_BACKUP` (14 d) | Rotação manual (ver `deploy/manutencao.sh` e deploy §13) |
| Auditoria LGPD | Indefinido (sem dado pessoal em claro) | — |

`php artisan lgpd:limpar-rascunhos --dias=90` executa na hora com outro prazo. Garanta que o **cron do cPanel**
(agendador) está ativo, senão a limpeza semanal não roda (`php artisan sistema:verificar`).

> Atenção: pedidos de eliminação **não alcançam backups antigos**. Eles expiram pela rotação; ao restaurar um
> backup, reaplique as anonimizações registradas em `lgpd_registros` (hash do identificador → peça o dado ao titular).

## 5. Operadores e transferência

- **Hospedagem** (HostGator): armazena banco, arquivos e e-mails de envio — firmar/verificar contrato e local dos servidores.
- **SMTP** (e-mail transacional): recebe nome/e-mail do destinatário.
- **Bibliotecas externas via CDN no navegador**: confira em `resources/views/layouts/*` quais domínios o usuário contata; o ideal é servir tudo localmente (já é o caso do QR Code).
- Não há analytics nem cookies de publicidade; só cookie de sessão/CSRF (estritamente necessários).

## 6. Incidente de segurança

1. Conter: trocar `APP_KEY` não é suficiente isoladamente — revogar senhas de admin, rotacionar credenciais do banco/SMTP.
2. Avaliar escopo (logs do cPanel, `storage/logs`, tabelas afetadas).
3. Comunicar ANPD e titulares se houver risco relevante (art. 48 da LGPD), em prazo razoável.
4. Registrar o incidente e as medidas tomadas.

## 7. Checklist antes de publicar

- [ ] `PRIVACIDADE_CONTROLADOR` e `PRIVACIDADE_CONTATO` preenchidos com dados reais.
- [ ] Encarregado definido (ou texto da política ajustado).
- [ ] Texto de `/privacidade` revisado pelo jurídico.
- [ ] Cron do agendador ativo e `sistema:verificar` sem alertas.
- [ ] Rotina de backup com rotação ≤ `PRIVACIDADE_DIAS_BACKUP`.
