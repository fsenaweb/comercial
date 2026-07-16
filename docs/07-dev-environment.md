# Ambiente de Desenvolvimento & Runbook

Guia operacional do projeto: como subir a stack, rodar validações, e as armadilhas conhecidas (todas encontradas e resolvidas na Sprint 0 — não redescobrir do zero).

## Pré-requisitos no host
- Docker + Docker Compose (PHP e Composer **não** precisam estar instalados no host — tudo roda em container).
- Node 22+ e npm (para o dev server e validações do frontend).

## Subindo a stack pela primeira vez

```bash
# 1. Sobe banco, API, scheduler e nginx
# (UID/GID do host: default já é 1000; se o seu usuário for outro, prefixe com
# `env UID=$(id -u) GID=$(id -g)` — `UID` é somente-leitura no bash, não dá pra "export")
docker compose up -d

# 2. Configura o backend (primeira vez)
cp backend/.env.example backend/.env
docker compose exec php-fpm php artisan key:generate
docker compose exec php-fpm php artisan migrate --seed   # cria admin@loja.local / password
docker compose exec php-fpm php artisan storage:link     # symlink pra servir uploads (ex.: logo da loja) via nginx

# 3. Gera a SPA e publica no volume servido pelo nginx
docker compose --profile build run --rm nuxt-build

# 4. Acessa http://localhost — login com o admin do seed (trocar a senha!)
```

O banco `comercial_testing` (usado pela suíte de testes) é criado automaticamente pelo init script do postgres — **apenas na primeira criação do volume**. Se o volume for antigo e o banco não existir: `docker compose exec postgres psql -U comercial -c "CREATE DATABASE comercial_testing OWNER comercial"`.

## Comandos do dia a dia

```bash
# Artisan / Composer (rodam como www-data com UID do host — nunca root)
docker compose exec php-fpm php artisan <comando>
docker compose exec php-fpm composer <comando>

# Testes do backend (obrigatório antes de qualquer commit)
docker compose exec php-fpm php artisan test

# Validação do frontend (obrigatório antes de qualquer commit)
cd frontend && npx nuxi typecheck && npm run generate

# Dev server do frontend com hot-reload (opcional; a stack via nginx já serve o build)
cd frontend && cp .env.example .env && npm install && npm run dev
# (o .env aponta NUXT_PUBLIC_API_BASE para http://localhost/api — o nginx do compose)

# Atualizar a SPA servida pelo nginx após mudanças no front
./deploy-frontend.sh
# (equivale a: docker compose build --no-cache nuxt-build && docker compose --profile build run --rm nuxt-build && docker compose restart nginx —
#  o --no-cache é obrigatório, ver "Armadilhas conhecidas"; nunca rodar sem --no-cache)
```

## Hostname local (opcional, recomendado)
Testar via `localhost` esconde um problema real: em produção, os terminais acessam por IP/hostname da LAN, não por `localhost`, e o Sanctum só mantém sessão para origens listadas em `SANCTUM_STATEFUL_DOMAINS`. Simular isso localmente evita surpresa no deploy:

```bash
# Rodar uma vez (precisa de sudo — cada dev roda na própria máquina)
echo "127.0.0.1 loja.local" | sudo tee -a /etc/hosts
```

O `.env` do backend já reconhece qualquer host graças a `SESSION_DOMAIN=null`; só é preciso adicionar o hostname escolhido em `SANCTUM_STATEFUL_DOMAINS` (ver `.env.example`). Depois, acessar `http://loja.local` normalmente — nginx responde por qualquer `Host` (`server_name _;`), não precisa mudar nada no docker-compose.

## Armadilhas conhecidas (e suas correções)

| Sintoma | Causa | Correção |
|---|---|---|
| `export UID=$(id -u)` (ou `./deploy-frontend.sh`) falha com `UID: a variável permite somente leitura` | `UID` é uma variável especial somente-leitura do bash (e do zsh, dependendo da versão) — não pode ser reatribuída via `export`/`=` | Usar `env UID=$(id -u) GID=$(id -g) docker compose ...` (passa a variável só pro processo filho, sem tocar na tabela de variáveis do shell). `deploy-frontend.sh` já faz isso |
| `502 Bad Gateway` em tudo | nginx cacheia o IP do container `php-fpm`; ao recriar o container, o IP muda | `docker compose restart nginx` |
| `404 File not found` vindo do PHP em `/api/*` | Caminho do `SCRIPT_FILENAME` divergente entre nginx e php-fpm | `backend/public` deve estar montado no nginx no **mesmo** caminho absoluto do container PHP (`/var/www/html/public`) — já configurado; não alterar um lado só |
| Arquivos do repo com dono `root` | Comando rodado em container sem `-u`, ou volume nomeado aninhado dentro do bind mount | Prevenção: `docker compose exec` (usuário já correto) ou `-u $(id -u):$(id -g)` em `docker run`; **nunca** declarar volume nomeado dentro de `./backend`. Limpeza: `docker run --rm -v "$(pwd)":/app alpine rm -rf /app/<caminho>` |
| Login não persiste (autentica mas `/api/me` dá 401) | Host de acesso fora de `SANCTUM_STATEFUL_DOMAINS`, ou requisição sem `Referer`/`Origin` da mesma origem | Incluir o host/IP usado no navegador em `SANCTUM_STATEFUL_DOMAINS`; em testes de API manuais (curl), enviar `-H "Referer: http://localhost"` |
| `CSRF token mismatch` (419) após login | O login **regenera** a sessão e o token CSRF | Reler o cookie `XSRF-TOKEN` após o login (o `useApi` do front já faz isso automaticamente por ler o cookie a cada requisição) |
| `nuxi typecheck` quebra com `ERR_PACKAGE_PATH_NOT_EXPORTED` | TypeScript 7 instalado (incompatível com `vue-tsc`) | Manter `typescript@^5` no `package.json` (já pinado) |
| Teste com processo externo não vê os dados | `RefreshDatabase` mantém os dados numa transação não commitada, invisível para conexões externas (ex.: `pg_dump`) | Usar `DatabaseMigrations` nesse teste (ver `BackupRestoreTest`) |
| Componente (`<BaseButton>`, `<BaseInput>`...) não aparece na tela — sem erro no build/typecheck | Nuxt prefixa componentes auto-importados pelo nome da subpasta (`components/ui/BaseButton.vue` vira `<UiBaseButton>`, não `<BaseButton>`); Vue falha em resolver a tag em runtime e renderiza só o texto do slot (ou nada) — **build e `nuxi typecheck` passam normalmente**, isso não é erro de compilação | `components: [{ path: '~/components', pathPrefix: false }]` no `nuxt.config.ts` (já configurado). Para conferir o nome real registrado sem abrir navegador: `grep BaseButton frontend/.nuxt/components.d.ts` |
| `curl`/`typecheck`/build "verdes" mas a tela não funciona | O front é 100% client-rendered (`ssr:false`) — `curl` só vê o HTML estático vazio (a Nitro não prerenderiza conteúdo), nunca executa o JS que monta a tela de verdade. **Bug de runtime do Vue não aparece em nenhuma validação que não execute JS no navegador.** | Não existe substituto para abrir no navegador (ou usar uma ferramenta de automação de browser, se disponível) antes de dar uma tela por concluída; validação de HTTP status não é validação de UI |
| Editou código do front, rodou `docker compose --profile build run --rm nuxt-build`, mas a build servida pelo nginx continua com a versão antiga (chunks `_nuxt/*.js` sem o código novo) | O serviço `nuxt-build` faz `COPY frontend/ ./` **na imagem** Docker (ver `docker/nuxt/Dockerfile`) — não é bind mount do código-fonte. `docker compose run` reusa a imagem já construída, que não sabe dos arquivos novos; às vezes até `docker compose build nuxt-build` (sem `--no-cache`) reaproveita uma camada velha e não pega o `COPY` mais recente. Achado na Sprint 1 depurando por que as telas de cadastro não apareciam mesmo com typecheck/build limpos. | `docker compose build --no-cache nuxt-build` antes de `docker compose --profile build run --rm nuxt-build` quando o código do front mudou desde a última imagem — o `--no-cache` é o que garante que o `COPY` não é pulado |
| Corrigiu um bug de front, rebuildou, reiniciou o nginx, mas o navegador do usuário final continua reproduzindo o bug antigo | `default.conf` não define `Cache-Control`; sem esse header o navegador aplica cache heurístico e pode continuar servindo o `index.html`/bundle antigos indefinidamente, mesmo com o servidor já atualizado | Corrigido: `/_nuxt/*` (nome com hash de conteúdo) ganhou `Cache-Control: public, max-age=31536000, immutable`; `index.html`/demais rotas ganharam `Cache-Control: no-cache` (sempre revalida). Se o sintoma persistir em uma máquina específica, pedir um hard refresh (`Ctrl+Shift+R`) uma única vez para descartar o cache antigo já guardado antes dessa correção |
| Login falha com CORS/CSRF ao acessar via nginx (`http://loja.local` ou o host da LAN), mas funcionava antes | `frontend/.env` (criado para o `npm run dev` local, que precisa apontar `NUXT_PUBLIC_API_BASE` para `http://localhost/api`) foi parar dentro da imagem do `nuxt-build` — o `COPY frontend/ ./` do Dockerfile copia esse `.env`, e o `npm run generate` o lê, baking o valor de dev (`localhost`) na build de produção em vez do padrão relativo (`/api`, mesma origem). Causa raiz: `frontend/.env` não estava no `.dockerignore`. | Corrigido: `frontend/.env` adicionado ao `.dockerignore` — a build via Docker nunca mais herda esse arquivo, independente do que estiver configurado localmente para o dev server |
| Upload (ex.: logo da loja) salva com sucesso no backend, mas a imagem dá 404 ao carregar pelo nginx | `public/storage` é um symlink (`php artisan storage:link`) pra um caminho **absoluto** (`/var/www/html/storage/app/public`). O container do nginx só montava `backend/public`, não `backend/storage` — o symlink existe mas aponta pra um caminho que não existe dentro do próprio container do nginx | Corrigido: `docker-compose.yml` do serviço `nginx` ganhou um segundo bind mount, `./backend/storage/app/public:/var/www/html/storage/app/public:ro`, além do `location /storage/` em `docker/nginx/default.conf`. Rodar `docker compose up -d nginx` (não só `restart`) depois de puxar essa mudança, pra recriar o container com o volume novo |

## Deploy / atualização na máquina da loja

```bash
git pull
# UID é somente-leitura no bash — não dá pra "export"; `env` passa pro processo filho
env UID=$(id -u) GID=$(id -g) docker compose build
docker compose up -d
docker compose exec php-fpm php artisan migrate --force
docker compose exec php-fpm php artisan storage:link   # idempotente; só precisa na primeira vez
./deploy-frontend.sh   # build --no-cache + publica a SPA + restart nginx
```

> `./deploy.sh` (raiz do repo) encapsula o ciclo completo acima (`git pull` + rebuild + `up -d` + `migrate --force` + `storage:link` + `./deploy-frontend.sh`) — não precisa rodar os passos manualmente.
>
> **Servidor Windows:** o SO definitivo da máquina da loja ainda não está fechado (`01-architecture.md`) — pode ser Linux (plano principal) ou um Windows 10 já existente na loja. Para esse segundo caso, `deploy.bat`/`deploy-frontend.bat` (raiz do repo) fazem exatamente o mesmo ciclo via **Docker Desktop com backend WSL2** — mesmo `docker-compose.yml`, sem UID/GID (esse truque é só para bind mount Linux; o Docker Desktop mapeia permissão por fora disso, os defaults `1000` do compose bastam). Rodar direto num `cmd.exe`/Prompt de Comando, mesma pasta do repo.

### Configuração de produção (LAN) — checklist do `.env`
- `APP_ENV=production`, `APP_DEBUG=false`.
- `APP_URL=http://<ip-do-servidor>` (ex.: `http://192.168.0.10`).
- **`SANCTUM_STATEFUL_DOMAINS=<ip-do-servidor>`** — o host que os terminais digitam no navegador. Sem isso o login não persiste nos terminais (o Sanctum só trata como sessão stateful requisições vindas dessas origens).
- **`SESSION_DOMAIN=null`** — cookie restrito ao host exato; funciona para acesso por IP. (`localhost` só serve para dev.)
- Trocar `DB_PASSWORD` e a senha do usuário seed `admin@loja.local`.

## Backup
- Onde ficam: `backend/storage/app/backup/` (disco `backups`), no host da loja — apontar essa pasta para um HD externo/segundo disco é recomendado.
- Agendamento: `backup:clean` 09:45 e `backup:run` 10:00 (diários, horário definido pelo PM), via container `scheduler`.
- Restore manual (para teste periódico ou desastre):
  ```bash
  unzip <arquivo>.zip -d /tmp/restore
  docker compose exec -T postgres psql -U comercial -d comercial < /tmp/restore/db-dumps/postgresql-comercial.sql
  ```
- O teste automatizado `BackupRestoreTest` valida o ciclo completo (backup → restore → conferência de dados) em toda execução da suíte.
- **Restauração pela tela** (`/settings/backup`, Sub-sprint E): alternativa ao restore manual acima — exige código de confirmação (gerado na hora, expira em 5 min) e bloqueia com caixa aberto. Ver `01-architecture.md` para os detalhes técnicos.
- **Achado real:** o cliente `psql`/`pg_dump` da imagem PHP é 17.x, mas o servidor do projeto é `postgres:16-alpine` — um dump gerado por esse cliente inclui `SET transaction_timeout = 0;` (parâmetro só do PG17), que o PG16 rejeita ao restaurar (`ERROR: unrecognized configuration parameter`). A restauração pela tela já remove essa linha automaticamente; ao restaurar manualmente via `psql -f` (comando acima), se aparecer esse erro, edite o `.sql` removendo a linha `SET transaction_timeout...` antes de rodar de novo.

### Backup remoto — Google Drive (camada 2, Sub-sprint E)
- `.env`: `GOOGLE_OAUTH_CLIENT_ID`/`GOOGLE_OAUTH_CLIENT_SECRET` — Client ID OAuth do tipo "TVs e dispositivos de entrada limitada" (setup detalhado em `01-architecture.md`). Em dev local, deixar em branco é normal: o botão "Conectar Google Drive" (`/settings/backup`) fica desabilitado (422 claro), sem quebrar nada.
- Testar sem esperar o cron das 10:15: `docker compose exec php-fpm php artisan backups:sync-google-drive` — envia o backup local mais recente se a loja já tiver conectado uma conta (via `/settings/backup`).
- Testes automatizados (`tests/Feature/Backup/`) usam `Http::fake()` para simular toda a API do Google — não precisam de credenciais reais nem de internet. Só a autorização humana do fluxo de dispositivo (abrir `google.com/device` e digitar o código) não dá para automatizar; validar manualmente ao menos uma vez com uma conta Google real.
