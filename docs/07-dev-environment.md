# Ambiente de Desenvolvimento & Runbook

Guia operacional do projeto: como subir a stack, rodar validações, e as armadilhas conhecidas (todas encontradas e resolvidas na Sprint 0 — não redescobrir do zero).

## Pré-requisitos no host
- Docker + Docker Compose (PHP e Composer **não** precisam estar instalados no host — tudo roda em container).
- Node 22+ e npm (para o dev server e validações do frontend).

## Subindo a stack pela primeira vez

```bash
# 1. Variáveis de UID/GID (o compose usa default 1000; exporte se seu usuário for outro)
export UID=$(id -u) GID=$(id -g)

# 2. Sobe banco, API, scheduler e nginx
docker compose up -d

# 3. Configura o backend (primeira vez)
cp backend/.env.example backend/.env
docker compose exec php-fpm php artisan key:generate
docker compose exec php-fpm php artisan migrate --seed   # cria admin@loja.local / password

# 4. Gera a SPA e publica no volume servido pelo nginx
docker compose --profile build run --rm nuxt-build

# 5. Acessa http://localhost — login com o admin do seed (trocar a senha!)
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
docker compose --profile build run --rm nuxt-build
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
| `502 Bad Gateway` em tudo | nginx cacheia o IP do container `php-fpm`; ao recriar o container, o IP muda | `docker compose restart nginx` |
| `404 File not found` vindo do PHP em `/api/*` | Caminho do `SCRIPT_FILENAME` divergente entre nginx e php-fpm | `backend/public` deve estar montado no nginx no **mesmo** caminho absoluto do container PHP (`/var/www/html/public`) — já configurado; não alterar um lado só |
| Arquivos do repo com dono `root` | Comando rodado em container sem `-u`, ou volume nomeado aninhado dentro do bind mount | Prevenção: `docker compose exec` (usuário já correto) ou `-u $(id -u):$(id -g)` em `docker run`; **nunca** declarar volume nomeado dentro de `./backend`. Limpeza: `docker run --rm -v "$(pwd)":/app alpine rm -rf /app/<caminho>` |
| Login não persiste (autentica mas `/api/me` dá 401) | Host de acesso fora de `SANCTUM_STATEFUL_DOMAINS`, ou requisição sem `Referer`/`Origin` da mesma origem | Incluir o host/IP usado no navegador em `SANCTUM_STATEFUL_DOMAINS`; em testes de API manuais (curl), enviar `-H "Referer: http://localhost"` |
| `CSRF token mismatch` (419) após login | O login **regenera** a sessão e o token CSRF | Reler o cookie `XSRF-TOKEN` após o login (o `useApi` do front já faz isso automaticamente por ler o cookie a cada requisição) |
| `nuxi typecheck` quebra com `ERR_PACKAGE_PATH_NOT_EXPORTED` | TypeScript 7 instalado (incompatível com `vue-tsc`) | Manter `typescript@^5` no `package.json` (já pinado) |
| Teste com processo externo não vê os dados | `RefreshDatabase` mantém os dados numa transação não commitada, invisível para conexões externas (ex.: `pg_dump`) | Usar `DatabaseMigrations` nesse teste (ver `BackupRestoreTest`) |
| Componente (`<BaseButton>`, `<BaseInput>`...) não aparece na tela — sem erro no build/typecheck | Nuxt prefixa componentes auto-importados pelo nome da subpasta (`components/ui/BaseButton.vue` vira `<UiBaseButton>`, não `<BaseButton>`); Vue falha em resolver a tag em runtime e renderiza só o texto do slot (ou nada) — **build e `nuxi typecheck` passam normalmente**, isso não é erro de compilação | `components: [{ path: '~/components', pathPrefix: false }]` no `nuxt.config.ts` (já configurado). Para conferir o nome real registrado sem abrir navegador: `grep BaseButton frontend/.nuxt/components.d.ts` |
| `curl`/`typecheck`/build "verdes" mas a tela não funciona | O front é 100% client-rendered (`ssr:false`) — `curl` só vê o HTML estático vazio (a Nitro não prerenderiza conteúdo), nunca executa o JS que monta a tela de verdade. **Bug de runtime do Vue não aparece em nenhuma validação que não execute JS no navegador.** | Não existe substituto para abrir no navegador (ou usar uma ferramenta de automação de browser, se disponível) antes de dar uma tela por concluída; validação de HTTP status não é validação de UI |

## Deploy / atualização na máquina da loja

```bash
git pull
export UID=$(id -u) GID=$(id -g)
docker compose build
docker compose up -d
docker compose exec php-fpm php artisan migrate --force
docker compose --profile build run --rm nuxt-build
docker compose restart nginx   # containers recriados = IP novo (ver armadilhas)
```

> Encapsular isso em um `deploy.sh` está no backlog (`05-sprints.md`, melhorias transversais).

### Configuração de produção (LAN) — checklist do `.env`
- `APP_ENV=production`, `APP_DEBUG=false`.
- `APP_URL=http://<ip-do-servidor>` (ex.: `http://192.168.0.10`).
- **`SANCTUM_STATEFUL_DOMAINS=<ip-do-servidor>`** — o host que os terminais digitam no navegador. Sem isso o login não persiste nos terminais (o Sanctum só trata como sessão stateful requisições vindas dessas origens).
- **`SESSION_DOMAIN=null`** — cookie restrito ao host exato; funciona para acesso por IP. (`localhost` só serve para dev.)
- Trocar `DB_PASSWORD` e a senha do usuário seed `admin@loja.local`.

## Backup
- Onde ficam: `backend/storage/app/backup/` (disco `backups`), no host da loja — apontar essa pasta para um HD externo/segundo disco é recomendado.
- Agendamento: `backup:clean` 01:30 e `backup:run` 02:00 (diários), via container `scheduler`.
- Restore manual (para teste periódico ou desastre):
  ```bash
  unzip <arquivo>.zip -d /tmp/restore
  docker compose exec -T postgres psql -U comercial -d comercial < /tmp/restore/db-dumps/postgresql-comercial.sql
  ```
- O teste automatizado `BackupRestoreTest` valida o ciclo completo (backup → restore → conferência de dados) em toda execução da suíte.
