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

# .env não vai para o Git (guarda segredo: senha do banco etc.) — numa
# instalação nova, esse arquivo ainda não existe. Copia o exemplo pra não
# travar os comandos abaixo com erro confuso do Laravel; os valores
# (APP_URL, SANCTUM_STATEFUL_DOMAINS, DB_PASSWORD) ainda precisam ser
# ajustados manualmente depois pro host real da loja (ver
# docs/10-instalacao-loja.md).
if [ ! -f backend/.env ]; then
  echo "backend/.env não encontrado — copiando de backend/.env.example."
  echo "Ajuste APP_URL, SANCTUM_STATEFUL_DOMAINS e DB_PASSWORD depois (ver docs/10-instalacao-loja.md)."
  cp backend/.env.example backend/.env
fi

# `UID` é uma variável somente-leitura do próprio bash — não dá pra "export"
# nela. `env` passa UID/GID pro processo filho sem tocar na tabela de
# variáveis do shell atual (mesmo padrão do deploy-frontend.sh).
env UID="$(id -u)" GID="$(id -g)" docker compose build
docker compose up -d

# vendor/ não vai para o Git (.gitignore) e o Dockerfile só instala o binário
# do Composer, não roda `install` — porque backend/ é bind mount, então
# qualquer `composer install` rodado durante o build da imagem seria
# sobrescrito pelo conteúdo do host ao subir o container. Por isso o install
# roda aqui, contra o volume já montado, senão o `migrate`/artisan abaixo
# falha por falta de vendor/autoload.php.
docker compose exec php-fpm composer install --no-dev --optimize-autoloader

docker compose exec php-fpm php artisan migrate --force
docker compose exec php-fpm php artisan storage:link || true
# Corrige permissão restritiva (0700) que storage/app/backup possa ter
# herdado de antes de `visibility => public` (config/filesystems.php) —
# achado real em Windows/Docker Desktop, ver docs/07-dev-environment.md.
docker compose exec php-fpm php artisan backups:ensure-directory-permissions

./deploy-frontend.sh

echo "Deploy concluído."
