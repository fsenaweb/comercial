#!/usr/bin/env bash
# Reconstrói e republica a SPA do frontend no volume servido pelo nginx.
#
# Necessário sempre que o código do front mudar e alguém for testar via
# nginx (loja.local / localhost, sem porta), não via `npm run dev`.
# O `--no-cache` é obrigatório: `docker compose build` sem essa flag pode
# reaproveitar uma camada antiga e pular o `COPY frontend/ ./` mais recente
# (ver docs/07-dev-environment.md, "Armadilhas conhecidas").
set -euo pipefail
cd "$(dirname "$0")"

# `UID` é uma variável somente-leitura do próprio bash — não dá pra "export"
# nela. `env` passa UID/GID pro processo filho sem tocar na tabela de
# variáveis do shell atual.
env UID="$(id -u)" GID="$(id -g)" docker compose build --no-cache nuxt-build
env UID="$(id -u)" GID="$(id -g)" docker compose --profile build run --rm nuxt-build
docker compose restart nginx

echo "Frontend republicado."
