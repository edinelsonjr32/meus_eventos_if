# Testes de navegador (Playwright)

Complementam a suíte Pest (`php artisan test`), que cobre a jornada completa via HTTP. Aqui valida-se o que só
o navegador mostra: formulário em 4 etapas (Alpine), confirmações, permissões e páginas públicas.

```bash
./e2e/rodar.sh
```

- Requer os containers no ar (`docker compose up -d`) e Docker.
- Semeia usuários/evento de teste (**nunca rode contra produção**; os seeders são bloqueados lá).
- Usuários: `autor@teste.com`, `gestor@teste.com`, `avaliador@teste.com` (senhas em `database/seeders/TrabalhosTesteSeeder.php`).
- Outra URL: `E2E_URL=https://staging.exemplo.com ./e2e/rodar.sh` (use só em homologação).
- A pasta `e2e/` não vai para o pacote de produção.
