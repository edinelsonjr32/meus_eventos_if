# Publicação em produção — HostGator (hospedagem compartilhada / cPanel)

Guia passo a passo para publicar o **Meus Eventos IF**. Tudo aqui foi ensaiado a partir do pacote gerado
pelo projeto (instalação em banco vazio, modo produção, navegação nas telas principais).

> Nas instruções, `CONTA` é o usuário do cPanel (ex.: `ifpaobid`) e `eventos.seudominio.com.br` é o endereço
> do sistema. Troque pelos seus valores.

---

## 0. O que você precisa antes

| Item | Como conferir | Observação |
|---|---|---|
| Hospedagem com **cPanel** | Painel da HostGator | Planos compartilhados (Hatchling/Baby/Business) servem. |
| **PHP 8.3** (mínimo 8.2) | cPanel › **MultiPHP Manager** | As dependências foram verificadas em 8.2 e 8.3. |
| **MySQL** e **e-mail** | cPanel › MySQL® Databases / Email Accounts | Será criado um banco e uma caixa de e-mail. |
| **SSH** (recomendado) | cPanel › Terminal, ou peça à HostGator | **Não é obrigatório**: há um caminho só com cron (passo 8-B). |
| **Docker** no seu computador | `docker --version` | Só para **gerar o pacote** (usa a imagem `composer`). |
| Domínio ou subdomínio | cPanel › Domains | Recomendado: subdomínio dedicado, ex. `eventos.seudominio.com.br`. |

Limitações da hospedagem compartilhada que este projeto já contorna:

- **Sem processo permanente** (worker de fila): o agendador (cron) processa a fila a cada minuto.
- **Sem Node**: o sistema não precisa (as telas usam CDN para Bootstrap/Alpine).
- **Limites de PHP** (upload, memória): ajustados no passo 3.
- **Ghostscript** (só para o PDF compilado dos Anais) pode não existir: o diagnóstico avisa; o resto funciona.

## 1. Como os arquivos ficam no servidor

O aplicativo fica **fora** da pasta pública (assim `.env`, código e PDFs dos autores não ficam acessíveis pela web):

```
/home/CONTA/
├── meuseventos/        ← aplicativo: código, vendor, .env, storage (PDFs privados dos trabalhos)
└── public_html/        ← somente: index.php, .htaccess, favicon, robots.txt e o link "storage"
```

Se o seu domínio/subdomínio aponta para uma **subpasta** (ex.: `public_html/eventos`), veja o passo 6.

## 2. Domínio e certificado SSL

1. cPanel › **Domains** › crie o subdomínio (ex.: `eventos`). Anote a *Document Root* que o cPanel sugerir.
2. cPanel › **SSL/TLS Status** › **Run AutoSSL** para o domínio. Aguarde o cadeado ficar ativo.

## 3. PHP: versão, extensões e limites

1. cPanel › **MultiPHP Manager** › selecione o domínio › **PHP 8.3** › Apply.
2. cPanel › **Select PHP Version** › **Extensions**: confirme ativas
   `bcmath, curl, dom, fileinfo, gd, iconv, intl, mbstring, openssl, pdo_mysql, simplexml, xml, zip`.
3. cPanel › **MultiPHP INI Editor** › domínio › ajuste:

| Diretiva | Valor |
|---|---|
| `upload_max_filesize` | `25M` (ou mais, conforme o limite de PDF da chamada) |
| `post_max_size` | `30M` |
| `memory_limit` | `256M` |
| `max_execution_time` | `120` |

## 4. Banco de dados e e-mail

**Banco** — cPanel › **MySQL® Databases**
1. Crie o banco (fica `CONTA_eventos`).
2. Crie um usuário (`CONTA_eventos`) com **senha forte** e **adicione o usuário ao banco** com *ALL PRIVILEGES*.
3. Anote: nome do banco, usuário, senha (host é `localhost`).

**E-mail** — cPanel › **Email Accounts**: crie `nao-responda@seudominio.com.br`. Em *Connect Devices* confira
o servidor SMTP (normalmente `mail.seudominio.com.br`, porta **465** com SSL). Confira também, em
**Email Deliverability**, que SPF e DKIM estão válidos (evita cair no spam).

## 5. Gerar o pacote (no seu computador)

Na pasta do projeto:

```bash
./deploy/build-release.sh
```

Gera `deploy/dist/meuseventos-AAAAMMDD-HHMM.zip` (~22 MB) e mostra o SHA-256. O pacote traz apenas código de
produção, **sem** `.env`, testes, seeders de dados de teste nem dados de usuários.

## 6. Enviar e extrair

1. cPanel › **File Manager** › pasta `/home/CONTA` › **Upload** do `.zip` (ou use FTP/SFTP se o limite de upload não permitir).
2. Remova o conteúdo padrão de `public_html` (ex.: `index.html`, "coming soon"), se houver.
3. Selecione o `.zip` › **Extract** em `/home/CONTA`. Resultado: `meuseventos/` e `public_html/`.
4. Apague o `.zip` do servidor.

**Domínio em subpasta** (ex.: Document Root = `/home/CONTA/public_html/eventos`): mova o conteúdo de `public_html`
extraído para essa pasta e edite a linha `$base` do `index.php` para o caminho até o aplicativo:

```php
$base = __DIR__ . '/../../meuseventos';   // duas pastas acima, no exemplo
```

## 7. Criar o `.env`

1. Em `/home/CONTA/meuseventos`, copie `.env.exemplo` para `.env` (File Manager › Copy).
2. Edite (File Manager › Edit) e preencha **ao menos**:

```ini
APP_URL=https://eventos.seudominio.com.br
PUBLIC_PATH=/home/CONTA/public_html            # pasta onde está o index.php (caminho ABSOLUTO)

DB_DATABASE=CONTA_eventos
DB_USERNAME=CONTA_eventos
DB_PASSWORD=sua_senha_do_banco

MAIL_HOST=mail.seudominio.com.br
MAIL_USERNAME=nao-responda@seudominio.com.br
MAIL_PASSWORD=senha_da_caixa
MAIL_FROM_ADDRESS="nao-responda@seudominio.com.br"
```

Deixe `APP_KEY=` vazio (é gerada no próximo passo). Mantenha `APP_ENV=production` e `APP_DEBUG=false`.
Permissão do `.env`: **600** (File Manager › Permissions).

## 8. Instalar (escolha A ou B)

Descubra o caminho do PHP 8.3 da conta (costuma ser `/opt/cpanel/ea-php83/root/usr/bin/php`):
no Terminal, `ls /opt/cpanel | grep ea-php`.

### 8-A. Com SSH / Terminal do cPanel

```bash
cd ~/meuseventos
PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php bash manutencao.sh instalar
/opt/cpanel/ea-php83/root/usr/bin/php artisan usuario:criar-admin seu@email.com --nome="Seu Nome"
```

O primeiro comando gera a chave, cria as tabelas, cadastra cursos/turmas, cria o link das imagens, otimiza e roda o
diagnóstico. O segundo cria o administrador pedindo a **senha de forma oculta** (mínimo 12 caracteres).

### 8-B. Sem SSH (usando o Cron do cPanel uma única vez)

1. cPanel › **Cron Jobs** › adicione uma linha, definindo o horário para **2 minutos à frente** (ex.: agora 14:10 → `12 14 * * *`):

```
PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php bash /home/CONTA/meuseventos/manutencao.sh instalar > /home/CONTA/instalacao.log 2>&1
```
2. Depois do horário, abra `/home/CONTA/instalacao.log` e confira o diagnóstico (sem `✘`).
3. Crie o administrador do mesmo jeito (outra linha, 2 minutos à frente; **apague a linha e o log logo depois**, pois contém a senha):

```
/opt/cpanel/ea-php83/root/usr/bin/php /home/CONTA/meuseventos/artisan usuario:criar-admin seu@email.com --nome="Seu Nome" --senha='UmaSenha-Forte-2026' > /home/CONTA/admin.log 2>&1
```
4. **Remova** essas linhas do cron e apague `instalacao.log` e `admin.log`.
   (No cron, o caractere `%` tem significado especial: evite-o na senha ou escreva `\%`.)

## 9. Agendador (cron permanente)

Um único cron, **a cada minuto**, cuida dos lembretes aos avaliadores **e da fila** (e-mails, PDF dos Anais):

```
* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/CONTA/meuseventos/artisan schedule:run >> /dev/null 2>&1
```

> Se a hospedagem só permitir intervalos maiores (ex.: a cada 15 min), use esse intervalo: e-mails e PDFs
> passam a sair com esse atraso. Alternativa: `QUEUE_CONNECTION=sync` no `.env` (tudo na hora, porém mais
> lento e sujeito a limite de tempo na geração do PDF dos Anais).

## 10. Conferir

```bash
cd ~/meuseventos
/opt/cpanel/ea-php83/root/usr/bin/php artisan sistema:verificar --email=seu@email.com
```

Deve terminar em `Resultado: pronto`. O comando confere PHP, extensões, banco, migrations, permissões, link das
imagens, fila, **cron ativo** (espere 2 min após criar o cron), Ghostscript e envia um **e-mail de teste**.

Checklist no navegador:

- [ ] `https://eventos.seudominio.com.br` abre (cadeado ativo, sem aviso de conteúdo misto).
- [ ] Login com o administrador criado funciona.
- [ ] Criar um evento de teste, **Criar chamada de trabalhos**, publicar e submeter um trabalho com PDF.
- [ ] O e-mail de confirmação da submissão chegou.
- [ ] Imagens do certificado (brasão/fundo/assinatura) aparecem; o PDF do certificado abre.
- [ ] Registro de frequência por QR Code pelo celular (o GPS exige HTTPS).
- [ ] Apague o evento de teste e os trabalhos de teste.

## 11. Primeiros passos no sistema

1. **Usuários** › crie contas e defina perfis. 2. **Eventos** › crie o evento. 3. No evento, card **Chamada de
trabalhos** › **Criar chamada** (período, categorias, textos, template) › **Salvar e publicar**. 4. **Papéis** ›
designe *Gestor de Trabalhos* e *Avaliadores* (precisam ter conta). 5. Configure o **certificado** do evento
(fundo, brasão, assinaturas).

> **Nunca** execute os seeders de dados de teste em produção. Eles estão bloqueados e nem vão no pacote.

## 12. Atualizando para uma nova versão

1. **Backup** do banco e de `storage/app` (passo 13).
2. No seu computador: `./deploy/build-release.sh` (novo `.zip`).
3. Envie e extraia o `.zip` **em uma pasta temporária** (ex.: `/home/CONTA/novo`).
4. Copie por cima do aplicativo **sem apagar** o `.env` nem `storage/app`:
   - substitua `meuseventos/app`, `bootstrap`, `config`, `database`, `lang`, `resources`, `routes`, `vendor`, `artisan`, `composer.*`, `manutencao.sh`;
   - substitua `public_html/index.php` e `.htaccess` **somente se mudaram** (preserve o `$base` que você ajustou).
5. `PHP_BIN=... bash ~/meuseventos/manutencao.sh atualizar` (modo manutenção → migrations → caches → volta ao ar → diagnóstico).
6. Rode o checklist do passo 10.

**Reverter:** restaure os arquivos da versão anterior e o backup do banco (migrations novas não são desfeitas sozinhas).

## 13. Backup (faça antes de usar de verdade)

O que precisa de cópia:

| O quê | Onde | Por quê |
|---|---|---|
| Banco MySQL | cPanel › Backup, ou `mysqldump` | Usuários, eventos, trabalhos, notas. |
| `meuseventos/storage/app/` | pasta inteira | **PDFs dos trabalhos**, versões e imagens de certificados. Não estão no banco! |
| `meuseventos/.env` | arquivo | Guarda `APP_KEY`: sem ela, dados criptografados e sessões não recuperam. |

Cron diário de exemplo (03:00) — ajuste `USUARIO_DB`, `SENHA_DB` e `NOME_DB`:

```
0 3 * * * mkdir -p /home/CONTA/backups && mysqldump -uUSUARIO_DB -p'SENHA_DB' NOME_DB | gzip > /home/CONTA/backups/db-$(date +\%F).sql.gz && tar -czf /home/CONTA/backups/storage-$(date +\%F).tar.gz -C /home/CONTA/meuseventos/storage app && find /home/CONTA/backups -mtime +14 -delete
```

Baixe cópias para **fora** do servidor periodicamente.

## 14. Solução de problemas

| Sintoma | Causa provável e solução |
|---|---|
| **Erro 500** / página em branco | Veja `meuseventos/storage/logs/laravel-AAAA-MM-DD.log`. Confira PHP 8.3, `vendor/` presente e permissões de `storage` e `bootstrap/cache` (755/775). |
| "No application encryption key" | `APP_KEY` vazia: `artisan key:generate --force` e depois `artisan optimize`. |
| Mudei o `.env` e nada mudou | Os caches guardam a configuração: `artisan optimize:clear && artisan optimize`. |
| **419 Page Expired** ao logar | Cookie bloqueado: confira `APP_URL` com `https://`, `SESSION_SECURE_COOKIE=true` só com HTTPS ativo, e limpe cookies do site. |
| Imagens do certificado não aparecem | Falta o link: `artisan storage:link` (cria `public_html/storage`). Confirme `PUBLIC_PATH` no `.env`. |
| Upload de PDF falha | Aumente `upload_max_filesize` e `post_max_size` (passo 3). |
| E-mails não saem | `sistema:verificar --email=...`. Confira host/porta/senha SMTP, SPF/DKIM e limite horário de envio do plano. |
| E-mails/PDF dos Anais parados | Cron do passo 9 não está rodando: o diagnóstico mostra "Cron parado?" e jobs presos. |
| PDF compilado dos Anais falha | Sem Ghostscript no servidor. Os trabalhos e PDFs individuais continuam disponíveis; compile em outro computador ou peça à HostGator. |
| Horários 3 h errados | `APP_TIMEZONE=America/Belem` no `.env` e `artisan optimize:clear && artisan optimize`. |
| Página fica "em manutenção" | Rode `artisan up`. |
| Telas sem estilo | As telas usam CDN (Bootstrap, Alpine, ícones, fontes): confira a internet do usuário e bloqueadores. |

## 15. Segurança (checklist)

- [ ] `APP_DEBUG=false` e `APP_ENV=production` (o diagnóstico **reprova** se estiver ligado em produção).
- [ ] `.env` fora da pasta pública, permissão 600; HTTPS ativo (o `.htaccess` força o redirecionamento).
- [ ] Senha do administrador forte; ative **autenticação em dois fatores** no cPanel.
- [ ] Nenhum usuário de teste (`admin@teste.com` etc.) existe em produção.
- [ ] Senhas do banco e do e-mail só no `.env`; troque-as se forem expostas.
- [ ] Mantenha o sistema atualizado (`composer audit` na sua máquina antes de gerar cada pacote; a versão atual está sem avisos).
- [ ] **LGPD:** o sistema guarda CPF, e-mail e instituição. Informe a finalidade aos participantes, limite o acesso a quem precisa (papéis) e proteja os backups.
- [ ] O QR Code de etiquetas e certificados é gerado **no próprio servidor** (nenhum dado vai a serviços externos).
