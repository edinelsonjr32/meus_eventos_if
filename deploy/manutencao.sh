#!/usr/bin/env bash
#
# Rotinas de instalação e atualização no SERVIDOR (SSH ou agendada pelo cron do cPanel).
# Fica dentro da pasta do aplicativo (ex.: /home/CONTA/meuseventos/manutencao.sh).
#
#   bash manutencao.sh instalar     # primeira vez: chave, banco, cursos, link de imagens, caches, diagnóstico
#   bash manutencao.sh atualizar    # nova versão: modo manutenção, migrations, caches, volta ao ar
#   bash manutencao.sh diagnostico  # só roda o diagnóstico (php artisan sistema:verificar)
#
# Informe o PHP da conta, se o "php" padrão não for o 8.3 (veja no cPanel > MultiPHP Manager):
#   PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php bash manutencao.sh instalar
#
set -uo pipefail
cd "$(dirname "$0")"

PHP_BIN="${PHP_BIN:-php}"
art() { "$PHP_BIN" artisan "$@"; }

[ -f .env ] || { echo "ERRO: crie o arquivo .env primeiro (copie de .env.exemplo)."; exit 1; }
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' || { echo "ERRO: PHP $("$PHP_BIN" -r 'echo PHP_VERSION;') é antigo. Use PHP 8.3 (PHP_BIN=...)."; exit 1; }

case "${1:-}" in
  instalar)
    echo "==> Chave da aplicação"
    if grep -qE '^APP_KEY=[[:space:]]*$' .env; then art key:generate --force; else echo "APP_KEY já definida (mantida)."; fi
    echo "==> Banco de dados (migrations)";   art migrate --force || exit 1
    echo "==> Cursos e turmas";                art db:seed --force || exit 1
    echo "==> Link das imagens (storage)";     art storage:link || echo "(link já existe ou não foi possível criar: veja o guia)"
    echo "==> Caches de produção";             art optimize
    echo "==> Diagnóstico";                    art sistema:verificar
    echo
    echo "Falta criar o administrador:  $PHP_BIN artisan usuario:criar-admin seu@email.com"
    ;;
  atualizar)
    echo "==> Modo manutenção";                art down --retry=60 || true
    echo "==> Migrations";                     art migrate --force || { art up; exit 1; }
    echo "==> Limpando e refazendo caches";    art optimize:clear; art optimize
    art queue:restart >/dev/null 2>&1 || true
    echo "==> Voltando ao ar";                 art up
    echo "==> Diagnóstico";                    art sistema:verificar
    ;;
  diagnostico)
    art sistema:verificar "${@:2}"
    ;;
  *)
    echo "Uso: bash manutencao.sh {instalar|atualizar|diagnostico}"; exit 1 ;;
esac
