#!/usr/bin/env bash
# Painel de manutenção da loja (menu interativo) — atualizar o sistema, ver
# status/logs dos containers e reiniciar. Ver docs/07-dev-environment.md,
# "Deploy / atualização na máquina da loja", pro runbook que a opção
# "Atualizar sistema" encapsula: git pull + rebuild dos serviços +
# migrations + publicação da SPA.
#
# Rodar direto na máquina da loja, na raiz do repo, com a stack já no ar
# (docker compose up -d rodado ao menos uma vez antes).
cd "$(dirname "$0")"

# Cores ANSI — suportadas nativamente por qualquer terminal Linux/macOS
# moderno, sem dependência extra.
GREEN=$'\033[0;32m'
RED=$'\033[0;31m'
YELLOW=$'\033[0;33m'
CYAN=$'\033[0;36m'
BOLD=$'\033[1m'
RESET=$'\033[0m'

TOTAL_STEPS=7
# Duração da última atualização bem-sucedida (segundos), usada só pra
# estimar a barra de progresso da próxima vez — não existe na primeira
# execução, nesse caso a barra cai pra fração de passos concluídos em vez
# de tempo. Arquivo local, fora do Git (.gitignore).
TIMING_FILE=".deploy-last-duration"
PREV_DURATION=0
[ -f "$TIMING_FILE" ] && PREV_DURATION=$(cat "$TIMING_FILE" 2>/dev/null || echo 0)

banner() {
  clear
  echo "${CYAN}${BOLD}"
  echo "  ========================================================"
  echo "    JP PARAFUSOS E ACESSORIOS - SISTEMA COMERCIAL"
  echo "  ========================================================"
  echo "${RESET}"
}

pausar() {
  echo
  read -rp "Pressione ENTER para voltar ao menu..." _
}

format_duration() {
  local secs=$1
  printf '%dmin %ds' $(( secs / 60 )) $(( secs % 60 ))
}

progress_bar() {
  local pct=$1 width=30
  [ "$pct" -gt 100 ] && pct=100
  [ "$pct" -lt 0 ] && pct=0
  local filled=$(( pct * width / 100 ))
  local empty=$(( width - filled ))
  local bar=""
  local i
  for ((i = 0; i < filled; i++)); do bar+="#"; done
  for ((i = 0; i < empty; i++)); do bar+="-"; done
  printf '[%s] %3d%%' "$bar" "$pct"
}

# Roda numa subshell com `set -e` só aqui dentro — se um passo falhar, a
# atualização para (evita continuar com o sistema em estado inconsistente),
# mas o painel em si continua no ar pro usuário tentar de novo ou escolher
# outra opção, em vez de fechar a janela inteira como o script antigo fazia.
atualizar() (
  set -euo pipefail
  banner
  echo "${BOLD}Atualizando o sistema...${RESET}"
  if [ "$PREV_DURATION" -gt 0 ]; then
    echo "Estimativa: ~$(format_duration "$PREV_DURATION") (baseado na última atualização)"
  else
    echo "Estimativa: primeira execução, sem histórico ainda."
  fi
  echo

  local deploy_start
  deploy_start=$(date +%s)

  # Barra de progresso baseada no tempo decorrido em relação à última
  # execução (mais precisa que "passo N de 7", já que passos como o rebuild
  # do Docker variam muito de duração entre si). Sem histórico ainda, cai
  # pra fração de passos concluídos. Trava em 99% até o final de verdade,
  # pra nunca mostrar "100%" com o deploy ainda rodando.
  step() {
    local n=$1 label=$2
    local elapsed=$(( $(date +%s) - deploy_start ))
    local pct
    if [ "$PREV_DURATION" -gt 0 ]; then
      pct=$(( elapsed * 100 / PREV_DURATION ))
    else
      pct=$(( (n - 1) * 100 / TOTAL_STEPS ))
    fi
    [ "$pct" -gt 99 ] && pct=99
    echo "${YELLOW}$(progress_bar "$pct")${RESET} Passo ${n}/${TOTAL_STEPS} - ${label}..."
  }

  step 1 "Atualizando código (git pull)"
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

  step 2 "Reconstruindo imagens"
  # `UID` é uma variável somente-leitura do próprio bash — não dá pra
  # "export" nela. `env` passa UID/GID pro processo filho sem tocar na
  # tabela de variáveis do shell atual (mesmo padrão do deploy-frontend.sh).
  env UID="$(id -u)" GID="$(id -g)" docker compose build
  docker compose up -d

  step 3 "Corrigindo permissões de storage"
  # storage/ é bind mount: se ficar com outro dono no host (root via
  # `exec -u root`, restauração de backup, rebuild com UID diferente), o
  # www-data do container perde escrita em logs/backup — ver
  # docs/07-dev-environment.md, "Armadilhas conhecidas".
  docker compose exec -u root php-fpm chown -R www-data:www-data storage bootstrap/cache
  docker compose exec -u root php-fpm chmod -R ug+rwX storage bootstrap/cache

  step 4 "Instalando dependências do Composer"
  # vendor/ não vai para o Git e o Dockerfile só instala o binário do
  # Composer — como backend/ é bind mount, o install precisa rodar contra
  # o volume já montado, senão o migrate/artisan abaixo falha.
  docker compose exec php-fpm composer install --no-dev --optimize-autoloader

  step 5 "Rodando migrations"
  docker compose exec php-fpm php artisan migrate --force
  docker compose exec php-fpm php artisan storage:link || true
  # Corrige permissão restritiva (0700) que storage/app/backup possa ter
  # herdado de antes de `visibility => public` (config/filesystems.php).
  docker compose exec php-fpm php artisan backups:ensure-directory-permissions

  step 6 "Publicando o frontend"
  ./deploy-frontend.sh

  step 7 "Finalizando"
  local total_elapsed=$(( $(date +%s) - deploy_start ))
  echo "$total_elapsed" > "$TIMING_FILE"

  echo
  echo "${GREEN}$(progress_bar 100)${RESET}"
  echo "${GREEN}${BOLD}Sistema atualizado com sucesso em $(format_duration "$total_elapsed").${RESET}"
)

status() {
  banner
  echo "${BOLD}Status dos containers${RESET}"
  echo
  docker compose ps
}

logs() {
  banner
  echo "${BOLD}Ver logs${RESET}"
  echo
  echo "  1) php-fpm"
  echo "  2) nginx"
  echo "  3) postgres"
  echo "  4) Todos"
  echo "  5) Voltar"
  echo
  read -rp "  Escolha uma opção: " opt
  case "$opt" in
    1) docker compose logs --tail=100 php-fpm ;;
    2) docker compose logs --tail=100 nginx ;;
    3) docker compose logs --tail=100 postgres ;;
    4) docker compose logs --tail=100 ;;
    *) return ;;
  esac
}

reiniciar() {
  banner
  echo "${BOLD}Reiniciando os containers...${RESET}"
  echo
  docker compose restart
  echo
  echo "${GREEN}Reiniciado.${RESET}"
}

while true; do
  banner
  echo "  Painel de manutenção - máquina da loja"
  echo
  echo "  1) Atualizar sistema"
  echo "  2) Ver status do sistema"
  echo "  3) Ver logs"
  echo "  4) Reiniciar sistema"
  echo "  5) Sair"
  echo
  # Sem `|| break`, um stdin fechado (EOF) faria `read` falhar e `opcao`
  # ficar vazio pra sempre, entrando num loop infinito de "opção inválida".
  read -rp "  Escolha uma opção: " opcao || break
  case "$opcao" in
    1)
      # Não usar `if atualizar; then` aqui: bash ignora o `set -e` de DENTRO
      # da subshell quando ela é chamada como condição de um `if`/`while`
      # (comportamento documentado, mas nada óbvio) - um passo do deploy
      # falhando não pararia os passos seguintes. Chamar como statement
      # solto e checar `$?` depois preserva o `set -e` da subshell.
      atualizar
      deploy_status=$?
      if [ "$deploy_status" -ne 0 ]; then
        echo
        echo "${RED}${BOLD}ERRO: atualização interrompida.${RESET}"
      fi
      pausar
      ;;
    2) status; pausar ;;
    3) logs; pausar ;;
    4) reiniciar; pausar ;;
    5) echo "Até mais!"; exit 0 ;;
    *) echo "${RED}Opção inválida.${RESET}"; sleep 1 ;;
  esac
done
