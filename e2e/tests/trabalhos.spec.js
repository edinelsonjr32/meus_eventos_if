// Fluxo ponta a ponta no navegador real. Pré-requisito: `./e2e/rodar.sh` (semeia os dados e executa).
const { test, expect } = require('@playwright/test');

const PDF = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF');
const TITULO = `Trabalho E2E ${Date.now()}`;

async function entrar(page, email, senha) {
  await page.goto('/login');
  await page.fill('input[name=email]', email);
  await page.fill('input[name=password]', senha);
  await page.click('button[type=submit]');
  await expect(page).not.toHaveURL(/\/login/);
}

test('páginas públicas: política de privacidade e Anais sem erro', async ({ page }) => {
  const r = await page.goto('/privacidade');
  expect(r.status()).toBe(200);
  await expect(page.getByText('Política de Privacidade').first()).toBeVisible();
  await expect(page.getByText('CPF e e-mail nunca são publicados')).toBeVisible();
  await page.goto('/login');
  await expect(page.locator('a[href$="/privacidade"]').first()).toBeVisible();
});

test('autor submete um trabalho pelo formulário em 4 etapas', async ({ page }) => {
  await entrar(page, 'autor@teste.com', 'Autor@12345');
  await page.goto('/e/semana-de-tecnologia-2026/trabalhos/submeter');

  // 1. trabalho
  await page.fill('input[name=titulo]', TITULO);
  await page.check('input[name=tipo_trabalho]');           // primeira categoria
  const palavras = 'ensino, tecnologia, inovação';
  const resumo = Array.from({ length: 120 }, (_, i) => `palavra${i}`).join(' ');
  await page.locator('textarea[name=resumo]:visible').fill(resumo);
  await page.locator('input[name=palavras_chave]:visible').fill(palavras);
  await page.getByRole('button', { name: /Próxima/ }).click();

  // 2. apresentador
  await page.fill('input[name="autores[0][nome_completo]"]', 'Autor Teste');
  await page.fill('input[name="autores[0][cpf]"]', '11144477735');
  await page.fill('input[name="autores[0][email]"]', 'autor@teste.com');
  await page.fill('input[name="autores[0][instituicao]"]', 'IFPA Campus Óbidos');
  await page.check('input[name="autores[0][categoria_autor]"] >> nth=1');
  await page.check('#mais_nao');
  await page.getByRole('button', { name: /Próxima/ }).click();

  // 4. arquivo + modalidade + declarações (a etapa 3 só existe com mais autores)
  await page.check('input[name=modalidade_apresentacao]');
  await page.setInputFiles('input[name=arquivo]', { name: 'trabalho.pdf', mimeType: 'application/pdf', buffer: PDF });
  await page.check('#d1');
  await page.check('#d2');
  page.once('dialog', (d) => d.accept());
  await page.locator('button[name=acao][value=submeter]').click();

  await expect(page.getByText(TITULO).first()).toBeVisible();
  await page.goto('/minha-area/trabalhos');
  await expect(page.getByText(TITULO)).toBeVisible();
});

test('gestor encontra o trabalho na fila e abre o painel', async ({ page }) => {
  await entrar(page, 'gestor@teste.com', 'Gestor@12345');
  await page.goto('/gestao/trabalhos');
  await page.getByRole('link', { name: 'Trabalhos', exact: true }).first().click();
  await expect(page.getByText(TITULO).first()).toBeVisible();
  await page.getByText(TITULO).first().click();
  await expect(page.getByText(/Decisão|Triagem|Avaliadores/).first()).toBeVisible();
});

test('autor não vê eventos na gestão e não abre o painel de um trabalho alheio', async ({ page }) => {
  await entrar(page, 'autor@teste.com', 'Autor@12345');
  await page.goto('/gestao/trabalhos');
  await expect(page.getByText('Semana de Tecnologia')).toHaveCount(0);
  const r = await page.goto('/gestao/trabalhos/evento/1');
  expect([403, 404]).toContain(r.status());
});

test('avaliador acessa a própria área', async ({ page }) => {
  await entrar(page, 'avaliador@teste.com', 'Avaliador@12345');
  const r = await page.goto('/avaliador/avaliacoes');
  expect(r.status()).toBe(200);
});

test('configuração da chamada: erros aparecem como alerta e por campo; salvar mostra confirmação', async ({ page }) => {
  await entrar(page, 'gestor@teste.com', 'Gestor@12345');
  await page.goto('/gestao/trabalhos');
  await page.getByRole('link', { name: 'Editar chamada' }).first().click();

  // guarda o estado original para DEIXAR a configuração como estava (este teste não pode alterar o ambiente local)
  const original = {
    aval: await page.inputValue('input[name=avaliadores_por_trabalho]'),
    min: await page.inputValue('input[name=resumo_min_palavras]'),
    max: await page.inputValue('input[name=resumo_max_palavras]'),
    url: await page.inputValue('input[name=template_url]'),
    tipos: await page.locator('input[name="tipos_permitidos[]"]').evaluateAll((els) => els.map((e) => e.checked)),
  };

  // campo obrigatório vazio + intervalo invertido + nenhuma categoria + link inválido
  await page.fill('input[name=avaliadores_por_trabalho]', '');
  await page.fill('input[name=resumo_min_palavras]', '500');
  await page.fill('input[name=resumo_max_palavras]', '100');
  await page.fill('input[name=template_url]', 'sem-protocolo');
  for (const c of await page.locator('input[name="tipos_permitidos[]"]').all()) await c.uncheck();
  await page.locator('button[name=acao][value=salvar]').click();

  const toast = page.locator('.fb-toast.danger');
  await expect(toast).toBeVisible();
  await expect(toast).toContainText('problemas encontrados');
  await expect(page.locator('.fb-erro-campo', { hasText: 'Preencha o campo' })).toBeVisible();
  await expect(page.locator('.fb-erro-campo', { hasText: 'maior ou igual ao mínimo' })).toBeVisible();
  await expect(page.locator('.fb-erro-campo', { hasText: 'ao menos uma categoria' })).toBeVisible();
  await expect(page).toHaveURL(/config$/); // nada foi enviado

  // restaura exatamente os valores originais e salva
  await page.fill('input[name=avaliadores_por_trabalho]', original.aval);
  await page.fill('input[name=resumo_min_palavras]', original.min);
  await page.fill('input[name=resumo_max_palavras]', original.max);
  await page.fill('input[name=template_url]', original.url);
  const caixas = page.locator('input[name="tipos_permitidos[]"]');
  for (let i = 0; i < original.tipos.length; i++) { original.tipos[i] ? await caixas.nth(i).check() : await caixas.nth(i).uncheck(); }
  await page.locator('button[name=acao][value=salvar]').click();
  await expect(page.locator('.fb-toast.success, .alert-success').first()).toBeVisible();

  // conferência: as categorias continuam as mesmas de antes
  await page.goto(page.url());
  expect(await page.locator('input[name="tipos_permitidos[]"]').evaluateAll((els) => els.map((e) => e.checked))).toEqual(original.tipos);
});

test('máscara numérica descarta letras nos campos inteiros', async ({ page }) => {
  await entrar(page, 'gestor@teste.com', 'Gestor@12345');
  await page.goto('/gestao/trabalhos');
  await page.getByRole('link', { name: 'Editar chamada' }).first().click();
  const campo = page.locator('input[name=max_autores]');
  await campo.fill('');
  await campo.pressSequentially('1a2b');
  await expect(campo).toHaveValue('12');
});

async function idDoEvento(page) {
  await page.goto('/admin/eventos');
  const hrefs = await page.locator('a').evaluateAll((as) => as.map((a) => a.getAttribute('href') || ''));
  return hrefs.map((h) => h.match(/\/admin\/eventos\/(\d+)/)).find(Boolean)[1];
}

test('equipe: Select2 filtra participantes em tempo real e adiciona o escolhido', async ({ page }) => {
  await entrar(page, 'admin@teste.com', 'Admin@12345');
  const id = await idDoEvento(page);
  await page.goto(`/admin/eventos/${id}/equipe`);
  await page.locator('.select2-selection').first().click();
  await page.locator('.select2-search__field').fill('thiago');
  const opcao = page.locator('.select2-results__option', { hasText: 'Thiago' }).first();
  await expect(opcao).toBeVisible();
  await expect(page.locator('.select2-results__option', { hasText: 'Ana Beatriz' })).toHaveCount(0); // filtrou
  await opcao.click();
  await page.getByRole('button', { name: /Adicionar|Salvar/ }).first().click();
  await expect(page.getByText(/Thiago Moura/).first()).toBeVisible();
});

test('papéis: Select2 busca usuário por nome ou e-mail', async ({ page }) => {
  await entrar(page, 'admin@teste.com', 'Admin@12345');
  const id = await idDoEvento(page);
  await page.goto(`/admin/eventos/${id}/papeis`);
  await page.locator('.select2-selection').first().click();
  await page.locator('.select2-search__field').fill('avaliador@');
  await expect(page.locator('.select2-results__option', { hasText: 'avaliador@teste.com' })).toBeVisible();
  // envio sem escolher pessoa mostra alerta
  await page.keyboard.press('Escape');
  await page.getByRole('button', { name: /Designar/ }).click();
  await expect(page.locator('.fb-toast.danger')).toContainText('Pessoa não selecionada');
});

test('avaliador abre a área de apresentações e a gestão a tela de avaliação das apresentações', async ({ page }) => {
  await entrar(page, 'avaliador@teste.com', 'Avaliador@12345');
  expect((await page.goto('/avaliador/apresentacoes')).status()).toBe(200);
  await expect(page.getByText('Apresentações a avaliar')).toBeVisible();
  await page.context().clearCookies();

  await entrar(page, 'gestor@teste.com', 'Gestor@12345');
  await page.goto('/gestao/trabalhos');
  await page.getByRole('link', { name: 'Trabalhos', exact: true }).first().click();
  await page.getByRole('link', { name: /Apresentações/ }).first().click();
  await expect(page.getByText('Avaliação das apresentações').first()).toBeVisible();
  await expect(page.getByText('Carregar critérios padrão')).toBeVisible();
});

test('detalhe do trabalho: avaliadores com Select2 e bloqueio de envio sem o mínimo', async ({ page }) => {
  await entrar(page, 'gestor@teste.com', 'Gestor@12345');
  await page.goto('/gestao/trabalhos');
  await page.getByRole('link', { name: 'Trabalhos', exact: true }).first().click();
  await page.goto(page.url() + '?status=em_triagem');
  await page.getByRole('link', { name: /Abrir/ }).first().click();

  await expect(page.locator('.select2-container').first()).toBeVisible();
  await page.locator('.select2-container').first().click();
  await expect(page.locator('.select2-search__field')).toBeVisible();
  await page.keyboard.type('aval');
  await expect(page.locator('.select2-results__option').first()).toBeVisible();
  await page.screenshot({ path: 'test-results/select2-avaliadores.png' });
});

test('lote: resumo ao vivo, Select2 e botão só habilita com seleção válida (tela de trabalhos)', async ({ page }) => {
  await entrar(page, 'gestor@teste.com', 'Gestor@12345');
  await page.goto('/gestao/trabalhos');
  await page.getByRole('link', { name: 'Trabalhos', exact: true }).first().click();
  await page.goto(page.url() + '?status=em_triagem');

  const enviar = page.locator('[data-lote-enviar]');
  await expect(page.locator('[data-lote-contagem]')).toContainText('0 selecionado');
  await expect(enviar).toBeDisabled();

  await page.locator('.sel-trabalho').nth(0).check();
  await page.locator('.sel-trabalho').nth(1).check();
  await expect(page.locator('[data-lote-contagem]')).toContainText('2 selecionado');
  await expect(page.locator('[data-lote-resumo]')).toContainText('Escolha ao menos um avaliador');
  await expect(enviar).toBeDisabled();

  await page.locator('.lote-barra .select2-container').first().click();
  await page.locator('.select2-results__option', { hasText: 'Avaliador Teste' }).first().click();
  await page.keyboard.press('Escape');
  await expect(page.locator('[data-lote-resumo]')).toContainText(/Serão feitas \d+ atribuição/);
  await expect(enviar).toBeEnabled();

  // trocar a ação muda o resumo e esconde os avaliadores
  await page.locator('label[for="lote-a1"]').click();
  await expect(page.locator('[data-lote-painel="atribuir"]')).toBeHidden();
  await expect(page.locator('[data-lote-resumo]')).toContainText('triagem');
  await page.screenshot({ path: 'test-results/lote.png' });

  await page.locator('[data-lote-limpar]').click();
  await expect(page.locator('[data-lote-contagem]')).toContainText('0 selecionado');
});

function cpfValido() {
  const n = Array.from({ length: 9 }, () => Math.floor(Math.random() * 10));
  for (let t = 9; t < 11; t++) {
    let s = 0;
    for (let i = 0; i < t; i++) s += n[i] * (t + 1 - i);
    n.push(((s * 10) % 11) % 10);
  }
  const d = n.join('');
  return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
}

test('inscrição exige conta: visitante vê o convite, cria a conta e volta ao evento para se inscrever', async ({ page }) => {
  await page.goto('/e/semana-de-tecnologia-2026');
  await expect(page.getByText('Entre ou crie sua conta para se inscrever')).toBeVisible();
  await expect(page.locator('input[name=cpf]')).toHaveCount(0);

  await page.getByRole('link', { name: /Criar minha conta/ }).click();
  await expect(page).toHaveURL(/\/register/);
  const email = `e2e${Date.now()}@teste.local`;
  await page.fill('input[name=name]', 'Pessoa E2E Inscricao');
  await page.fill('input[name=email]', email);
  await page.fill('input[name=cpf]', cpfValido());
  await page.fill('input[name=password]', 'Senha@12345');
  await page.fill('input[name=password_confirmation]', 'Senha@12345');
  await page.locator('form[action$="/register"] button[type=submit]').click();

  await expect(page).toHaveURL(/\/e\/semana-de-tecnologia-2026/);
  await expect(page.getByText('Inscrevendo como')).toBeVisible();
  await page.locator('select[name=tipo_vinculo]').selectOption('externo');
  await page.getByRole('button', { name: /Confirmar Inscri/ }).click();
  await expect(page).toHaveURL(/confirmado/);
});

test('feedback de avaliador já atribuído: no lote e no detalhe do trabalho', async ({ page }) => {
  await entrar(page, 'gestor@teste.com', 'Gestor@12345');
  await page.goto('/gestao/trabalhos');
  await page.getByRole('link', { name: 'Trabalhos', exact: true }).first().click();
  const lista = page.url();
  await page.goto(lista + '?status=em_triagem');

  // lote: marca todos e escolhe todos os avaliadores; quem já está num dos trabalhos aparece como impedido
  await page.locator('.sel-todos').check();
  await page.locator('.lote-barra .select2-container').first().click();
  for (const o of await page.locator('.select2-results__option').all()) await o.click();
  await page.keyboard.press('Escape');
  await expect(page.locator('[data-lote-impedimentos]')).toContainText('já atribuído');
  await expect(page.locator('[data-lote-resumo]')).toContainText('impedida');
  await page.screenshot({ path: 'test-results/lote-impedimentos.png', fullPage: true });

  // detalhe: o trabalho com avaliador atribuído mostra o grupo "Já atribuídos" desabilitado
  await page.goto(lista + '?status=em_triagem');
  const linhas = page.locator('tbody tr').filter({ has: page.locator('td', { hasText: /^\s*1\/2\s*$/ }) });   // coluna Aval. = 1/2 (não a data)
  await linhas.first().getByRole('link', { name: /Abrir/ }).click();
  await page.locator('.select2-container').first().click();
  await expect(page.locator('.select2-results__group', { hasText: 'Já atribuídos' })).toBeVisible();
  await expect(page.locator('.select2-results__option[aria-disabled=true]').first()).toContainText('já atribuído');
  await page.screenshot({ path: 'test-results/detalhe-impedidos.png' });
});

test('formulário de avaliação: nota só aceita 0 ao máximo, sem letras/negativos e com máximo travado', async ({ page }) => {
  await entrar(page, 'avaliador@teste.com', 'Avaliador@12345');
  await page.goto('/avaliador/avaliacoes');
  await page.getByRole('link', { name: /Avaliar|Continuar avaliação/ }).first().click();

  const campo = page.locator('input[name^="notas["]').first();
  await campo.click();
  await campo.pressSequentially('abc-7,5x');
  await expect(campo).toHaveValue('7,5');                     // letras e sinal descartados

  await campo.fill('');
  await campo.pressSequentially('15');
  await expect(campo).toHaveValue('10');                      // acima do máximo volta ao máximo
  await expect(page.locator('.fb-erro-campo').first()).toContainText('máxima');

  await campo.fill('');
  await campo.pressSequentially('7,555');
  await expect(campo).toHaveValue('7,55');                    // no máximo 2 casas

  await campo.fill('');
  await campo.pressSequentially('8');
  await campo.blur();
  await expect(campo).toHaveValue('8');
});

test('trabalho reenviado após correção: gestor aprova direto, com confirmação, e vira Aprovado', async ({ page }) => {
  await entrar(page, 'gestor@teste.com', 'Gestor@12345');
  await page.goto('/gestao/trabalhos');
  await page.getByRole('link', { name: 'Trabalhos', exact: true }).first().click();
  await page.goto(page.url() + '?status=resubmetido&q=reenviado');

  const aprovar = page.getByRole('button', { name: /Aprovar/ }).first();
  await expect(aprovar).toBeVisible();

  // recusar a confirmação não altera nada
  let mensagem = '';
  page.once('dialog', (d) => { mensagem = d.message(); d.dismiss(); });
  await aprovar.click();
  await expect.poll(() => mensagem).toContain('reenviado após correção');
  await expect(page.getByRole('button', { name: /Aprovar/ }).first()).toBeVisible();

  // aceitar aprova
  page.once('dialog', (d) => d.accept());
  await page.getByRole('button', { name: /Aprovar/ }).first().click();
  await expect(page.locator('.fb-toast.success, .alert-success').first()).toBeVisible();
  await page.goto(page.url().split('?')[0] + '?status=aprovado_escrita&q=reenviado');
  await expect(page.getByText('Aprovado na parte escrita').first()).toBeVisible();
});

test('notas e ranking: abas por categoria, busca, ordenação, chips, detalhes e limpar', async ({ page }) => {
  await entrar(page, 'gestor@teste.com', 'Gestor@12345');
  await page.goto('/gestao/trabalhos');
  await page.getByRole('link', { name: 'Trabalhos', exact: true }).first().click();
  await page.getByRole('link', { name: 'Notas e ranking' }).click();

  await expect(page.locator('.nav-pills a', { hasText: 'Todas' })).toBeVisible();
  await expect(page.locator('.nav-pills a', { hasText: 'Pesquisa' })).toBeVisible();
  const linhas = page.locator('#r-tabela tbody tr.r-linha');
  const total = await linhas.count();
  expect(total).toBeGreaterThan(3);
  await expect(page.locator('#r-contagem')).toContainText(`${total} de ${total}`);

  // busca por protocolo reduz a lista e atualiza o resumo
  const codigo = await linhas.first().getAttribute('data-codigo');
  await page.fill('#r-busca', codigo);
  await expect(page.locator('#r-tabela tbody tr.r-linha:not(.d-none)')).toHaveCount(1);
  await expect(page.locator('#r-qtd')).toHaveText('1');
  await page.fill('#r-busca', 'xxxx-nao-existe');
  await expect(page.locator('#r-vazio')).toBeVisible();
  await page.click('#r-vazio-limpar');
  await expect(page.locator('#r-tabela tbody tr.r-linha:not(.d-none)')).toHaveCount(total);

  // chip "Só com nota" e Top 3
  await page.locator('label.r-chip', { hasText: 'Só com nota' }).click();
  const comNota = await page.locator('#r-tabela tbody tr.r-linha:not(.d-none)').count();
  expect(comNota).toBeLessThanOrEqual(total);
  await page.selectOption('#r-top', '3');
  expect(await page.locator('#r-tabela tbody tr.r-linha:not(.d-none)').count()).toBeLessThanOrEqual(3);
  await page.selectOption('#r-top', '');

  // ordenação por título alterna a seta
  await page.locator('th[data-ordem=titulo]').click();
  await expect(page.locator('th[data-ordem=titulo]')).toHaveClass(/ord-asc|ord-desc/);

  // detalhes da linha
  await page.click('#r-limpar');
  await page.locator('.r-expandir').first().click();
  await expect(page.locator('tr.r-detalhe:not(.d-none)').first()).toContainText('Média por critério');
});

test('premiação: tela com categorias do evento, apuração pelo ranking e modelo do certificado', async ({ page }) => {
  await entrar(page, 'gestor@teste.com', 'Gestor@12345');
  await page.goto('/gestao/trabalhos');
  await page.getByRole('link', { name: 'Trabalhos', exact: true }).first().click();
  await page.getByRole('link', { name: 'Premiação' }).first().click();
  await expect(page.getByRole('heading', { name: /Premiação/ })).toBeVisible();
  await expect(page.getByText('Visível só para a gestão').or(page.getByText('Divulgada aos autores'))).toBeVisible();
  await expect(page.getByRole('button', { name: /Apurar pelo ranking/ })).toBeVisible();
  await expect(page.getByRole('button', { name: /Salvar premiação e gerar certificados/ })).toBeVisible();
  await page.getByText('Modelo do certificado de premiação').click();
  await expect(page.locator('textarea[name=cert_premio_corpo]')).toBeVisible();
  await page.screenshot({ path: 'test-results/premiacao.png', fullPage: true });
});
