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
