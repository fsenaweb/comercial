#!/usr/bin/env bash
# Encapsula o runbook completo de atualização da loja (ver
# docs/07-dev-environment.md, "Deploy / atualização na máquina da loja"):
# git pull + rebuild dos serviços + migrations + publicação da SPA.
#
# Rodar direto na máquina da loja, na raiz do repo, com a stack já no ar
# (docker compose up -d rodado ao menos uma vez antes).
set -euo pipefail
cd "$(dirname "$0")"

git pull

# `UID` é uma variável somente-leitura do próprio bash — não dá pra "export"
# nela. `env` passa UID/GID pro processo filho sem tocar na tabela de
# variáveis do shell atual (mesmo padrão do deploy-frontend.sh).
env UID="$(id -u)" GID="$(id -g)" docker compose build
docker compose up -d

docker compose exec php-fpm php artisan migrate --force
docker compose exec php-fpm php artisan storage:link || true
# Corrige permissão restritiva (0700) que storage/app/backup possa ter
# herdado de antes de `visibility => public` (config/filesystems.php) —
# achado real em Windows/Docker Desktop, ver docs/07-dev-environment.md.
docker compose exec php-fpm php artisan backups:ensure-directory-permissions

./deploy-frontend.sh

echo "Deploy concluído."
