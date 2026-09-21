# Bootstrap do repositório comercial-saas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar o repositório `comercial-saas` a partir de uma cópia limpa (sem histórico Git) do código versionado do `Comercial`, conectá-lo ao repositório remoto já criado (`github.com/fsenaweb/comercial-saas`), adaptar documentação/identidade do produto, e fazer o primeiro push com uma baseline que sobe e passa nos testes — deixando o terreno pronto pra os planos seguintes (multi-tenancy, billing, painel admin, infraestrutura) que serão escritos separadamente, um por subsistema.

**Architecture:** Cópia de arquivos via `git archive` (pega exatamente o que está versionado no `Comercial`, sem `vendor/`, `node_modules/` ou histórico), novo `git init` no destino, ajustes pontuais de identidade/isolamento (nome do produto, banco, porta, projeto Docker Compose) pra rodar em paralelo ao `Comercial` na mesma máquina de desenvolvimento, sem alterar nenhuma regra de negócio.

**Tech Stack:** Laravel 12 (PHP 8.4), Nuxt 4, PostgreSQL 16, Docker Compose — idêntico ao `Comercial` nesta primeira etapa. Nenhuma peça nova (Redis, R2, Asaas, tenancy) entra neste plano.

**Spec:** `docs/superpowers/specs/2026-09-05-transformacao-saas-design.md`

## Global Constraints

- Multi-tenancy será row-level com coluna `tenant_id` (não schema-per-tenant) — **não implementado neste plano**, fica para um plano futuro.
- Autenticação: cookie httpOnly (Sanctum modo SPA), nunca token em `localStorage` — herdado sem alteração nesta etapa.
- Código 100% em inglês, interface 100% em português — convenção herdada do `Comercial`, mantida sem exceção.
- Toda operação de estoque/caixa roda em `DB::transaction()` com `lockForUpdate()` — herdado sem alteração.
- Branch discipline: a partir do **segundo** commit no novo repositório, nunca desenvolver direto na branch principal (`main`) — só o commit inicial de bootstrap (Task 3) vai direto pra `main`, por ser a fundação do repositório, sem nada anterior a proteger.
- Docker: containers nunca rodam como root; comandos avulsos sempre com UID/GID do host.
- Reaproveitamento de código do `arcoreal` é por cópia direta de arquivos, nunca pacote Composer compartilhado — mas nenhum arquivo do `arcoreal` é copiado neste plano (isso é escopo dos planos de multi-tenancy/billing/admin).
- Pré-requisito de execução: o commit do spec doc (`docs/superpowers/specs/2026-09-05-transformacao-saas-design.md`) e deste próprio plano precisam existir no `Comercial` (branch `chore/plano-transformacao-saas`) antes da Task 1 — o `git archive HEAD` da Task 1 é o que leva esses documentos pro novo repositório.

---

### Task 1: Copiar a árvore de arquivos versionados do Comercial para a nova pasta

**Files:**
- Create: `/mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas/` (toda a árvore, espelhando os 567 arquivos rastreados pelo `git ls-files` do `Comercial`)

**Interfaces:**
- Consumes: HEAD da branch `chore/plano-transformacao-saas` do `Comercial` (deve conter o commit do spec doc e deste plano — ver Global Constraints).
- Produces: pasta `comercial-saas/` com a mesma estrutura de `backend/`, `frontend/`, `docker/`, `docs/`, `scripts/`, `deploy.sh`, `deploy.bat`, `deploy-frontend.sh`, `deploy-frontend.bat`, `docker-compose.yml`, `.dockerignore`, `.gitignore`, `CLAUDE.md`, `CHANGELOG.md` — sem `.git/`, sem `vendor/`, sem `node_modules/`.

- [ ] **Step 1: Confirmar que o commit do spec/plano existe no HEAD atual**

Run: `git -C /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/Comercial log --oneline -3`
Expected: o commit mais recente inclui o spec doc e este plano (procurar pela mensagem de commit correspondente — se ainda não foi commitado, parar aqui e commitar antes de continuar).

- [ ] **Step 2: Criar a pasta de destino**

```bash
mkdir -p /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas
```

- [ ] **Step 3: Exportar a árvore versionada do Comercial pra dentro da nova pasta**

```bash
git -C /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/Comercial archive HEAD | \
  tar -x -C /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas
```

- [ ] **Step 4: Verificar que a contagem de arquivos bate**

```bash
git -C /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/Comercial ls-files | wc -l
find /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas -type f | wc -l
```
Expected: os dois números batem (ambos devem ser o mesmo total — ex. 567 arquivos, mais os 2 novos do spec/plano se já commitados nesta branch).

- [ ] **Step 5: Confirmar que nada sensível veio junto (checagem rápida)**

```bash
find /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas -iname ".env" -o -iname "vendor" -o -iname "node_modules"
```
Expected: nenhuma saída (esses diretórios/arquivos são ignorados pelo Comercial e portanto nunca foram rastreados pelo Git — `git archive` não os inclui).

---

### Task 2: Isolar a nova stack Docker da stack do Comercial (nomes, porta, banco)

Sem isso, subir o `comercial-saas` na mesma máquina de desenvolvimento entra em conflito com os containers do `Comercial` (mesma porta 80, mesmo nome de rede/volume Docker Compose).

**Files:**
- Modify: `comercial-saas/docker-compose.yml`
- Modify: `comercial-saas/backend/.env.example`

**Interfaces:**
- Consumes: `docker-compose.yml` e `.env.example` copiados na Task 1.
- Produces: stack Docker com projeto nomeado `comercial_saas`, Postgres com banco/usuário `comercial_saas`, nginx exposto na porta `8090` do host — nenhum conflito com os containers do `Comercial` (projeto `comercial` na porta `80`).

- [ ] **Step 1: Nomear o projeto Compose e isolar porta/banco**

Editar `comercial-saas/docker-compose.yml`: adicionar a chave `name` no topo do arquivo, trocar `POSTGRES_DB`/`POSTGRES_USER`/`POSTGRES_PASSWORD` de `comercial` para `comercial_saas`, e trocar o mapeamento de porta do nginx de `"80:80"` para `"8090:80"`.

```yaml
name: comercial_saas

services:
  postgres:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: comercial_saas
      POSTGRES_USER: comercial_saas
      POSTGRES_PASSWORD: comercial_saas
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/init-testing-db.sh:/docker-entrypoint-initdb.d/init-testing-db.sh:ro
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U comercial_saas"]
      interval: 5s
      timeout: 5s
      retries: 10
```

(as demais seções — `php-fpm`, `scheduler`, `nuxt-build` — permanecem exatamente como estão; só a seção `nginx` muda a porta:)

```yaml
  nginx:
    image: nginx:alpine
    restart: unless-stopped
    ports:
      - "8090:80"
```

- [ ] **Step 2: Atualizar o `.env.example` do backend com os novos valores**

Editar `comercial-saas/backend/.env.example`:

```
APP_NAME="Comercial SaaS"
DB_CONNECTION=pgsql
DB_DATABASE=comercial_saas
```

(demais linhas do arquivo permanecem inalteradas — `SANCTUM_STATEFUL_DOMAINS` e `SESSION_DOMAIN` só mudam quando o domínio real for definido, fora do escopo deste plano).

- [ ] **Step 3: Verificar que não sobrou nenhuma referência a "comercial" puro nos dois arquivos editados**

```bash
grep -n "comercial\b" /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas/docker-compose.yml
grep -n "DB_DATABASE=comercial$" /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas/backend/.env.example
```
Expected: nenhuma ocorrência de `comercial` isolado (só `comercial_saas`/`Comercial SaaS`) — se aparecer algo, é sinal de uma edição incompleta.

---

### Task 3: Adaptar CLAUDE.md, CHANGELOG.md e README pro novo produto

**Files:**
- Modify: `comercial-saas/CLAUDE.md`
- Modify: `comercial-saas/CHANGELOG.md`

**Interfaces:**
- Consumes: `CLAUDE.md`/`CHANGELOG.md` copiados do `Comercial` na Task 1.
- Produces: `CLAUDE.md` refletindo o produto SaaS (multi-tenant, billing, infra nova) mantendo as convenções de código/testes/branch já validadas; `CHANGELOG.md` reiniciado a partir da baseline herdada.

- [ ] **Step 1: Reescrever `comercial-saas/CLAUDE.md`**

Substituir o conteúdo inteiro do arquivo por:

```markdown
# Comercial SaaS - Plataforma Multi-tenant

> **Instrução nº 1 (sempre válida, todo prompt):** toda resposta em texto ao usuário (mensagens de chat, resumos, perguntas de esclarecimento) deve ser em português — independente do idioma em que o usuário escrever.

> **Instrução nº 2 (sempre válida, todo prompt, sem exceção):** nunca criar, editar ou escrever qualquer arquivo do repositório enquanto o branch atual for `main`. Antes da **primeira** alteração de qualquer tarefa, rodar `git status --short` + `git branch --show-current`; se estiver na `main`, criar e trocar para `feat/<nome-curto>` (ou `fix/`, `chore/`) **antes** de qualquer `Edit`/`Write`/`Bash` que altere arquivo — não depois. Se perceber que uma alteração já foi feita na `main` por engano, criar a branch imediatamente a partir do estado atual e avisar o usuário do lapso.

## Descrição do Projeto

Versão SaaS multi-tenant do sistema de gestão comercial e PDV originalmente construído como `Comercial` (projeto irmão, on-premise/single-tenant, mesmo desenvolvedor). Este produto atende múltiplos clientes (lojas de autopeças, motopeças, oficinas, lava-jato, ferragens/parafusaria) a partir de uma única instalação em nuvem, com cadastro self-service, cobrança recorrente e um painel administrativo central.

Decisões de arquitetura e o racional completo da transformação estão em `docs/superpowers/specs/2026-09-05-transformacao-saas-design.md` (herdado do `Comercial` — trata da migração de on-premise/single-tenant pra SaaS multi-tenant).

**Multi-tenancy:** row-level com coluna `tenant_id` em toda tabela de negócio + Global Scope no Eloquent — **não** schema-per-tenant. Tenant resolvido pelo usuário autenticado, sem subdomínio (domínio único).

## Stack Tecnológica

- **Backend:** Laravel 12 (PHP 8.4) em `backend/` — API RESTful, Sanctum modo SPA (cookie httpOnly de sessão), Redis (cache/fila — fila é requisito novo em relação ao `Comercial`, por causa dos jobs assíncronos de billing).
- **Frontend:** Nuxt 4 (Vue 3, Composition API) em `frontend/` — SPA (`ssr: false`), Tailwind CSS 4, Pinia. Layout próprio (não herdado do `Comercial` — ainda a ser definido).
- **Banco de Dados:** PostgreSQL 16 (+ banco `comercial_saas_testing` dedicado à suíte de testes).
- **Billing:** Asaas — integração e modelos de assinatura/fatura portados do projeto `arcoreal` (ver spec).
- **Storage:** Cloudflare R2 (bucket de mídia + bucket de backup, separados).
- **Infraestrutura:** VPS (Hostinger) + Docker Compose, deploy via GitHub Actions. Sem ambiente de staging nesta fase.
- **Observabilidade:** Sentry (erros), UptimeRobot (uptime), logs estruturados em JSON, SES (e-mail transacional).

## Convenções (obrigatórias em todo código gerado)

- **Código 100% em inglês:** tabelas, colunas, models, controllers, Actions, variáveis, funções.
- **Interface 100% em português:** todo texto visível ao usuário.
- **Padrão de negócio:** Actions (uma classe por operação), não Services genéricos.
- **Erros de API padronizados:** `{ "message": "...", "errors": {...} }`; API-only, guest em rota protegida recebe 401 JSON, nunca redirect.
- **Testes:** cobertura ampla — todo CRUD, toda Action e todo Global Scope de tenancy ganham feature tests.
- **Concorrência:** qualquer operação de estoque e/ou caixa roda em `DB::transaction()` com `lockForUpdate()`.
- **Isolamento entre tenants:** nenhuma query de negócio pode contornar o Global Scope de `tenant_id` — qualquer exceção precisa de justificativa explícita revisada, nunca silenciosa.

## Fluxo de Trabalho

1. **Branch:** nunca desenvolver na `main`. Antes de qualquer alteração, criar `feat/<nome-curto>` (ou `fix/`, `chore/`).
2. **Iteração de frontend:** dev server com hot-reload (`cd frontend && npm run dev`). Ao terminar e validar uma tarefa de frontend, republicar via `./deploy-frontend.sh` antes de apresentar o resumo ao usuário (mesmo racional do `Comercial`: o usuário testa via nginx, não via `:3000`).
3. **Validação antes do commit:** backend — `docker compose exec php-fpm php artisan test`; frontend — `npx nuxi typecheck` e `npm run generate`. Não prosseguir com erros.
4. **Commit somente com aprovação:** nunca commitar por conta própria. Apresentar um resumo e aguardar aprovação explícita. Conventional Commits (`feat:`, `fix:`, `chore:`...).
5. **Docker sem root:** nenhum arquivo do repositório pode ser criado/alterado como root — comandos em containers sempre com o UID/GID do host.
6. **CHANGELOG.md a cada PR:** toda tarefa com efeito visível pro usuário final ganha uma linha em `CHANGELOG.md` (`[Unreleased]`), no mesmo commit/PR.

## Índice de Documentação

Herdado do `Comercial` (`docs/01-architecture.md` a `docs/11-migracao-sistema-legado.md`) — **alguns documentos descrevem a arquitetura on-premise/single-tenant original e ainda não refletem o modelo SaaS** (sinalizado com um aviso no topo de cada um). Consulte `docs/superpowers/specs/2026-09-05-transformacao-saas-design.md` para o design da transformação e os planos em `docs/superpowers/plans/` para o estado de cada subsistema (multi-tenancy, billing, painel admin, infraestrutura) conforme forem implementados.
```

- [ ] **Step 2: Reescrever `comercial-saas/CHANGELOG.md`**

Substituir o conteúdo pelo formato padrão [Keep a Changelog](https://keepachangelog.com/), reiniciado a partir da baseline herdada:

```markdown
# Changelog

Todas as mudanças notáveis deste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).

## [Unreleased]

### Added
- Bootstrap do repositório a partir da baseline do `Comercial` v1.1.0 (código de estoque/caixa/venda herdado, ver `docs/superpowers/specs/2026-09-05-transformacao-saas-design.md`).
```

- [ ] **Step 3: Conferir que os dois arquivos foram salvos corretamente**

```bash
head -5 /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas/CLAUDE.md
head -10 /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas/CHANGELOG.md
```
Expected: título `# Comercial SaaS - Plataforma Multi-tenant` no primeiro arquivo, seção `## [Unreleased]` no segundo.

---

### Task 4: Sinalizar docs herdados que ficaram desatualizados com o modelo SaaS

**Files:**
- Modify: `comercial-saas/docs/01-architecture.md`
- Modify: `comercial-saas/docs/03-database-modeling.md`
- Modify: `comercial-saas/docs/07-dev-environment.md`

**Interfaces:**
- Consumes: os três arquivos copiados na Task 1 (descrevem arquitetura on-premise/single-tenant original).
- Produces: os mesmos arquivos com um aviso no topo, sem alterar o restante do conteúdo (que continua válido como referência histórica até os planos de multi-tenancy/infraestrutura os substituírem).

- [ ] **Step 1: Adicionar aviso no topo de `docs/01-architecture.md`**

Inserir logo após o título `# Arquitetura do Sistema`:

```markdown
> ⚠️ **Documento herdado do `Comercial` (on-premise, single-tenant).** Descreve a arquitetura original — parte dela (autenticação, infraestrutura Docker) é reaproveitada como está; multi-tenancy, billing e o novo runbook de produção (VPS + Redis + R2 + CI/CD) ainda não estão refletidos aqui. Ver `docs/superpowers/specs/2026-09-05-transformacao-saas-design.md`.
```

- [ ] **Step 2: Adicionar aviso equivalente no topo de `docs/03-database-modeling.md`**

```markdown
> ⚠️ **Documento herdado do `Comercial` (single-tenant).** A modelagem de estoque/caixa/venda descrita aqui é reaproveitada, mas nenhuma tabela ainda tem a coluna `tenant_id` nem o Global Scope de tenancy — isso é escopo de um plano futuro. Ver `docs/superpowers/specs/2026-09-05-transformacao-saas-design.md`.
```

- [ ] **Step 3: Adicionar aviso equivalente no topo de `docs/07-dev-environment.md`**

```markdown
> ⚠️ **Documento herdado do `Comercial`.** O runbook de ambiente de desenvolvimento aqui descrito continua válido para rodar a stack localmente; o runbook de **produção** (VPS, backup, observabilidade) é novo e será documentado separadamente quando o plano de infraestrutura for implementado. Ver `docs/superpowers/specs/2026-09-05-transformacao-saas-design.md`.
```

- [ ] **Step 4: Verificar que os três avisos foram inseridos**

```bash
grep -l "Documento herdado do" /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas/docs/01-architecture.md /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas/docs/03-database-modeling.md /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas/docs/07-dev-environment.md
```
Expected: os três caminhos listados na saída.

---

### Task 5: Subir a stack localmente e confirmar que a baseline copiada funciona

Isso garante que a cópia não quebrou nada antes de fazer o primeiro push — se algo estiver errado, é mais barato descobrir agora do que depois de publicado.

**Files:**
- Create: `comercial-saas/backend/.env` (local, não versionado — gerado a partir do `.env.example` editado na Task 2)

**Interfaces:**
- Consumes: `docker-compose.yml` e `.env.example` da Task 2.
- Produces: stack rodando em `http://localhost:8090`, suíte de testes do backend passando.

- [ ] **Step 1: Preparar o `.env` local do backend**

```bash
cd /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas
cp backend/.env.example backend/.env
```

- [ ] **Step 2: Subir a stack com o UID/GID do host (regra de Docker sem root)**

```bash
UID=$(id -u) GID=$(id -g) docker compose up -d --build postgres php-fpm
```
Expected: os dois containers sobem sem erro (`docker compose ps` mostra `postgres` e `php-fpm` como `running`/`healthy`).

- [ ] **Step 3: Gerar a chave da aplicação e rodar as migrations**

```bash
docker compose exec php-fpm php artisan key:generate
docker compose exec php-fpm php artisan migrate
```
Expected: `Application key set successfully.` seguido da lista de migrations aplicadas sem erro.

- [ ] **Step 4: Rodar a suíte de testes existente (herdada do Comercial, ainda sem mudanças)**

```bash
docker compose exec php-fpm php artisan test
```
Expected: todos os testes passam (mesmo resultado que no `Comercial` original, já que nenhuma regra de negócio foi alterada neste plano).

- [ ] **Step 5: Validar que o frontend também foi copiado corretamente**

```bash
cd frontend
npm install
npx nuxi typecheck
cd ..
```
Expected: `npm install` conclui sem erro e o typecheck não acusa nenhum erro de tipo (mesmo resultado que no `Comercial` original — nenhuma tela foi alterada neste plano).

- [ ] **Step 6: Derrubar a stack de verificação**

```bash
docker compose down
```

---

### Task 6: Conectar ao repositório remoto e fazer o primeiro push

**Files:** nenhum arquivo novo — só operações Git.

**Interfaces:**
- Consumes: pasta `comercial-saas/` validada na Task 5, repositório remoto vazio `github.com/fsenaweb/comercial-saas` (confirmado vazio, sem branch padrão).
- Produces: repositório remoto com um commit inicial na branch `main`, espelhando a baseline local.

- [ ] **Step 1: Inicializar o repositório Git local**

```bash
cd /mnt/74fff64a-e168-4716-8a88-689a97246253/fsenaweb/Sites/Sistemas/comercial-saas
git init
git branch -m main
```

- [ ] **Step 2: Conectar ao remote (SSH, mesmo padrão usado no Comercial)**

```bash
git remote add origin git@github.com:fsenaweb/comercial-saas.git
```

- [ ] **Step 3: Conferir o que vai entrar no commit inicial**

```bash
git add -A
git status --short | head -30
git status --short | wc -l
```
Expected: a contagem bate com o total de arquivos da Task 1 (mais os arquivos criados/editados nas Tasks 2, 3 e 4) — **nenhum `backend/.env` na lista** (deve estar ignorado pelo `backend/.gitignore` herdado).

- [ ] **Step 4: Commit inicial**

```bash
git commit -m "$(cat <<'EOF'
chore: bootstrap comercial-saas a partir do Comercial v1.1.0

Baseline single-tenant herdada do Comercial (backend, frontend, docker,
documentação), com identidade/isolamento adaptados para rodar como
produto independente (nome, banco, porta). Multi-tenancy, billing e
infraestrutura de produção ainda não implementados — ver
docs/superpowers/specs/2026-09-05-transformacao-saas-design.md.
EOF
)"
```

- [ ] **Step 5: Push pro repositório remoto**

```bash
git push -u origin main
```
Expected: push aceito, branch `main` criada no remoto como padrão (repositório deixa de aparecer como vazio no GitHub).

- [ ] **Step 6: Confirmar no GitHub**

```bash
gh repo view fsenaweb/comercial-saas --json isEmpty,defaultBranchRef,pushedAt
```
Expected: `"isEmpty": false`, `"defaultBranchRef": {"name": "main"}`.

---

## Depois deste plano

Este plano só entrega a fundação (código herdado + repositório publicado). Os subsistemas do design ainda não implementados — cada um deve virar um plano próprio, escrito de dentro do `comercial-saas` quando o trabalho começar (possivelmente por outro agente, conforme a divisão por camada combinada):

- Multi-tenancy (`tenant_id` + Global Scope em todas as tabelas de negócio)
- Autenticação/onboarding de tenant (self-service + criação manual)
- Billing (Asaas, portado do `arcoreal`)
- Painel administrativo completo (portado do `arcoreal`)
- Landing page
- Infraestrutura de produção (VPS, Redis/fila, R2, backup, observabilidade, CI/CD)
