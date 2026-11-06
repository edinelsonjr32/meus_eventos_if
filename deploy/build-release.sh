#!/usr/bin/env bash
#
# Gera o pacote de publicação (ZIP) para hospedagem compartilhada (HostGator / cPanel).
#
#   ./deploy/build-release.sh
#
# Requisitos na SUA máquina: Docker (usa a imagem composer:2) e rsync/zip (já vêm no macOS/Linux).
# O pacote NÃO contém: .env, testes, documentação, seeders de dados de teste, dependências de
# desenvolvimento, nem dados de storage. Resultado: deploy/dist/meuseventos-AAAAMMDD-HHMM.zip
#
set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
cd "$RAIZ"

command -v docker >/dev/null || { echo "Docker não encontrado (necessário para o Composer)."; exit 1; }
command -v rsync  >/dev/null || { echo "rsync não encontrado."; exit 1; }
command -v zip    >/dev/null || { echo "zip não encontrado."; exit 1; }

STAMP="$(date +%Y%m%d-%H%M)"
SAIDA="$RAIZ/deploy/dist"
TRAB="$(mktemp -d)"
APP="$TRAB/meuseventos"
PUB="$TRAB/public_html"
trap 'rm -rf "$TRAB"' EXIT

mkdir -p "$SAIDA" "$APP" "$PUB"

echo "==> 1/5 Copiando o código (sem testes, docs, segredos e dados de teste)"
rsync -a \
  --exclude='.git' --exclude='.gitignore' --exclude='.gitattributes' --exclude='.editorconfig' --exclude='.idea' --exclude='.vscode' \
  --exclude='node_modules' --exclude='vendor' --exclude='tests' --exclude='docs' --exclude='deploy' \
  --exclude='.env' --exclude='.env.*' --exclude='phpunit.xml' --exclude='compose.yaml' --exclude='README.md' \
  --exclude='package.json' --exclude='package-lock.json' --exclude='vite.config.js' --exclude='postcss.config.js' --exclude='tailwind.config.js' \
  --exclude='public' --exclude='role' --exclude='.DS_Store' \
  --exclude='storage/app/*' --exclude='storage/logs/*' --exclude='storage/framework/cache/*' --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' --exclude='storage/framework/testing' --exclude='bootstrap/cache/*' \
  --exclude='database/seeders/EventoTesteSeeder.php' --exclude='database/seeders/TrabalhosTesteSeeder.php' \
  ./ "$APP/"

# Estrutura de storage vazia (o servidor precisa que as pastas existam e sejam graváveis)
mkdir -p "$APP/storage/app/public" "$APP/storage/app/private/trabalhos" "$APP/storage/framework/cache/data" \
         "$APP/storage/framework/sessions" "$APP/storage/framework/views" "$APP/storage/logs" "$APP/bootstrap/cache"
for d in storage/app/public storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
  touch "$APP/$d/.gitkeep"
done
cp "$RAIZ/.env.production.example" "$APP/.env.exemplo"
cp "$RAIZ/deploy/manutencao.sh" "$APP/manutencao.sh"

echo "==> 2/5 Instalando dependências de PRODUÇÃO (composer install --no-dev)"
docker run --rm -v "$APP":/app -w /app composer:2 \
  install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-progress --ignore-platform-reqs

echo "==> 3/5 Montando a pasta pública"
cp "$RAIZ/deploy/public_html/index.php" "$PUB/index.php"
cp "$RAIZ/deploy/public_html/.htaccess" "$PUB/.htaccess"
cp "$RAIZ/public/robots.txt" "$PUB/robots.txt" 2>/dev/null || true
cp "$RAIZ/public/favicon.ico" "$PUB/favicon.ico" 2>/dev/null || true

echo "==> 4/5 Verificando o pacote"
for f in "$APP/vendor/autoload.php" "$APP/artisan" "$APP/manutencao.sh" "$APP/bootstrap/app.php" "$PUB/index.php" "$PUB/.htaccess"; do
  [ -f "$f" ] || { echo "ERRO: arquivo ausente no pacote: ${f#$TRAB/}"; exit 1; }
done
if [ -e "$APP/.env" ] || find "$APP" -name '.env' -maxdepth 1 | grep -q .; then echo "ERRO: .env no pacote"; exit 1; fi
if ls "$APP/database/seeders" | grep -qi 'Teste'; then echo "ERRO: seeder de teste no pacote"; exit 1; fi
[ -d "$APP/vendor/phpunit" ] && { echo "ERRO: dependências de desenvolvimento no pacote"; exit 1; }
{
  echo "release: $STAMP"
  echo "commit:  $(git -C "$RAIZ" rev-parse --short HEAD 2>/dev/null || echo desconhecido)"
} > "$APP/RELEASE"

echo "==> 5/5 Compactando"
ARQ="$SAIDA/meuseventos-$STAMP.zip"
( cd "$TRAB" && zip -rqX "$ARQ" meuseventos public_html -x '*.DS_Store' )

TAM="$(du -h "$ARQ" | cut -f1)"
SHA="$(shasum -a 256 "$ARQ" | cut -d' ' -f1)"
echo
echo "Pacote gerado: ${ARQ#$RAIZ/}"
echo "Tamanho:       $TAM"
echo "SHA-256:       $SHA"
echo
echo "Próximo passo: siga docs/deploy-hostgator.md"
