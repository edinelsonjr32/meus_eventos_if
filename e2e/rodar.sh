#!/usr/bin/env bash
# Executa os testes de navegador contra o ambiente local (Sail/docker compose em http://localhost:8080).
# Uso: ./e2e/rodar.sh        (da raiz do projeto)
set -euo pipefail
cd "$(dirname "$0")/.."

echo "› Semeando dados de teste (apenas ambiente local)…"
docker compose exec -T laravel.test php artisan db:seed --class=EstruturaAcademicaSeeder --force >/dev/null
docker compose exec -T laravel.test php artisan db:seed --class=EventoTesteSeeder --force >/dev/null
docker compose exec -T laravel.test php artisan db:seed --class=TrabalhosTesteSeeder --force >/dev/null

docker compose exec -T laravel.test php artisan tinker --execute="require base_path('e2e/preparar.php');" >/dev/null
docker compose exec -T laravel.test php artisan tinker --execute="App\\Models\\Equipe::whereHas('participante', fn(\$q) => \$q->where('nome_completo', 'Thiago Moura'))->delete();" >/dev/null

echo "› Rodando Playwright…"
EVENTO_ID=$(docker compose exec -T laravel.test php artisan tinker --execute='echo App\Models\Evento::where("slug","semana-de-tecnologia-2026")->value("id");' | tail -1 | tr -dc 0-9)

docker run --rm --ipc=host \
  -e E2E_EVENTO_ID="$EVENTO_ID" \
  -e E2E_URL="${E2E_URL:-http://host.docker.internal:8080}" \
  -v "$PWD/e2e:/work" -w /work \
  mcr.microsoft.com/playwright:v1.49.1-noble \
  bash -c "npm install --no-audit --no-fund >/dev/null && npx playwright test"
