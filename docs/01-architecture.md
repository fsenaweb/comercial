# Arquitetura do Sistema

## Visão Geral
Sistema de gestão comercial e PDV para **uma única loja**, rodando **on-premise** na rede local (LAN) do estabelecimento. Não é SaaS, não tem multi-tenancy, e não depende de internet para operar. Arquitetura Cliente-Servidor clássica, conteinerizada via Docker.

## Backend (API Laravel)
- Laravel 12 (PHP 8.4), atuando exclusivamente como API RESTful (JSON).
- Sem views no lado do servidor, **exceto** a rota do comprovante térmico (ver seção "Impressão Térmica" abaixo), que é a única página renderizada em Blade.
- **API-only sem redirects:** o Laravel por padrão redireciona guests para uma rota nomeada `login` (típica de apps com views) — aqui isso é desativado (`redirectGuestsTo(fn () => null)` em `bootstrap/app.php`) e toda exception é renderizada como JSON. Guest em rota protegida recebe `401` JSON, nunca um redirect ou 500.

## Autenticação (decisão implementada — Sprint 0)
- **Laravel Sanctum em modo SPA (cookie de sessão)**, não token. Motivo: front e back compartilham a mesma origem via proxy do nginx, então o cookie httpOnly de sessão é mais simples e mais seguro que gerenciar token no front (nada de token em localStorage).
- Middleware `statefulApi()` habilitado; sessões na tabela `sessions` (driver `database`).
- Fluxo da SPA: `GET /sanctum/csrf-cookie` (obtém cookie `XSRF-TOKEN`) → `POST /api/login` com header `X-XSRF-TOKEN` → cookie de sessão passa a autenticar as chamadas seguintes → `POST /api/logout` invalida a sessão.
- O Sanctum identifica requisições "stateful" pelo host do `Referer`/`Origin` contra `SANCTUM_STATEFUL_DOMAINS`. **Em produção na LAN, essa variável precisa incluir o IP/host que os terminais digitam no navegador** (ex.: `192.168.0.10`) — sem isso, o login não persiste. Ver `07-dev-environment.md`.
- CORS restrito às origens de desenvolvimento (`CORS_ALLOWED_ORIGINS`); em produção front e back são a mesma origem e CORS não entra em jogo.

## Frontend (Nuxt.js)
- Nuxt 4 em **modo SPA** (`ssr: false`) — não há necessidade de SSR/SEO em um sistema interno de uso local. Código da aplicação dentro de `app/` (estrutura padrão do Nuxt 4).
- Build estático (`npm run generate`) servido pelo nginx.
- Tailwind CSS 4 (via plugin `@tailwindcss/vite`) para estilização.
- Interface responsiva com foco em **agilidade de teclado** (essencial para o PDV: leitura de código de barras, atalhos, navegação sem mouse).
- Gerenciamento de estado global com Pinia.
- Consome a API via cliente único (`useApi`) que envia `credentials: 'include'` e o header `X-XSRF-TOKEN` — nenhuma tela chama `$fetch` direto.

## Banco de Dados (PostgreSQL)
- PostgreSQL 16, escolhido pela robustez em ambientes locais e proteção contra corrupção em quedas de energia.
- Fica isolado em seu próprio container.
- Comunicação estrita com o backend através do Eloquent ORM.
- O init script do container (`docker/postgres/init-testing-db.sh`) cria também o banco **`comercial_testing`**, usado exclusivamente pela suíte de testes (configurado no `phpunit.xml`) — os testes nunca tocam o banco de produção/desenvolvimento.

## Infraestrutura (Docker)

Um `docker-compose.yml` orquestra os seguintes containers:

- **nginx** — serve o build do Nuxt (porta 80) e repassa via fastcgi os prefixos `/api`, `/sales` (comprovante) e `/sanctum` (CSRF cookie) para o `php-fpm`. Front e back compartilham a mesma origem, o que evita problemas de CORS em produção.
- **php-fpm** — aplicação Laravel (API).
- **postgres** — banco de dados (+ init do banco de testes).
- **scheduler** — container dedicado que roda `php artisan schedule:run` a cada 60s, responsável por disparar o backup automático (ver seção "Backup").
- **nuxt-build** — container de build (profile `build`, não fica rodando): gera a SPA estática e a publica no volume `nuxt_dist`, consumido pelo nginx.

Todos os containers de serviço usam `restart: unless-stopped`, garantindo que o sistema suba sozinho quando a máquina da loja for ligada, sem intervenção manual.

### Restrições aprendidas na implementação (não violar)
- **Caminho compartilhado nginx ↔ php-fpm:** o `SCRIPT_FILENAME` do fastcgi é resolvido pelo **php-fpm** na árvore de arquivos *dele*. Por isso o nginx monta `backend/public` no **mesmo caminho absoluto** usado no container PHP (`/var/www/html/public`). Se os caminhos divergirem, o sintoma é `404 File not found` vindo do PHP.
- **502 após recriar o php-fpm:** o nginx resolve o nome `php-fpm` para um IP e o mantém em cache; ao recriar o container do PHP (novo IP), o nginx passa a responder 502 até ser reiniciado (`docker compose restart nginx`).
- **Volumes:** `backend/` inteiro é bind mount no `php-fpm`/`scheduler`. **Nunca aninhar um volume nomeado dentro desse bind mount** — o Docker cria o mount point como root no host e quebra a escrita do `www-data` (foi exatamente o que aconteceu com o diretório de backup na Sprint 0).

## Permissões no Docker (regra do projeto)
Para evitar arquivos do repositório com dono `root` (bind mounts em Linux usam UID/GID numéricos do container):

- A imagem PHP (`docker/php/Dockerfile`) recebe `UID`/`GID` como build args e realinha o `www-data` para o usuário do host; o container roda como `www-data`, **não root**.
- Comandos avulsos em containers (composer, node, etc.) sempre com `-u $(id -u):$(id -g)`; para o dia a dia, preferir `docker compose exec php-fpm ...`, que já roda como o usuário correto.
- Se algum arquivo aparecer como root (`find backend frontend -not -user $USER`), remover/corrigir via container root descartável — nunca conviver com isso.

Comandos prontos e o runbook completo estão em `07-dev-environment.md`.

### Diagrama de implantação (LAN)

```
        Loja (rede interna 192.168.x.x)
 ┌───────────────────────────────────────────────┐
 │  Máquina servidor (Linux ou Windows)           │
 │  ┌─────────────────────────────────────────┐  │
 │  │  Docker Compose                          │  │
 │  │   • nginx      (80: serve SPA + fastcgi  │  │
 │  │                 /api /sales /sanctum)    │  │
 │  │   • php-fpm    (Laravel — API)           │  │
 │  │   • postgres   (dados + banco de testes) │  │
 │  │   • scheduler  (cron: backup)            │  │
 │  │   • nuxt-build (profile: gera a SPA)     │  │
 │  └─────────────────────────────────────────┘  │
 │        │ impressora térmica 58/80mm (USB/rede) │
 └────────┼──────────────────────────────────────┘
          │ LAN
   ┌──────┴──────┬─────────────┐
   │ Terminal PDV│ Back-office │  (navegadores na mesma rede,
   └─────────────┴─────────────┘   nada instalado localmente)
```

- Servidor: **SO ainda não fechado com o cliente** — plano principal é **Linux** (provavelmente Ubuntu), mas o hardware final pode acabar sendo um **Windows 10** já existente na loja; Docker Desktop (WSL2 backend) cobre esse segundo caso sem mudança na stack (mesmo `docker-compose.yml`, mesmas imagens). Decisão de manter os dois planos viáveis (2026-07-15) — ver `deploy.sh` (Linux/macOS) e `deploy.bat` (Windows) em `07-dev-environment.md`. Docker como serviço (sobe no boot) em qualquer um dos dois.
- Topologia inicial: 1 servidor + 3 terminais (navegador), expansível — terminais não instalam nada, apenas abrem o navegador no IP do servidor.
- Latência é de LAN (rápida); não há dependência de internet para vender.
- Mobile (terminal de consulta via WiFi) é **roadmap futuro** — exige HTTPS local para uso de câmera e não terá função de impressão.

## Impressão Térmica (58/80mm)

- O comprovante é gerado por uma **rota Blade no Laravel** (`/sales/{id}/receipt`), renderizada no servidor. O Nuxt abre essa URL em uma nova janela e dispara `window.print()`. Essa abordagem é mais robusta para impressão térmica via navegador do que tentar montar o cupom dentro da SPA.
- CSS `@page { size: 80mm auto }`, impresso pelo driver da impressora instalada no SO.
- Documento marcado **"DOCUMENTO NÃO FISCAL"** no topo — o sistema não emite NFC-e/NFe.
- Conteúdo: cabeçalho da loja, nº da venda, data/hora, itens (nome, qtd, unitário, total), subtotal, desconto, total, forma de pagamento, vendedor, cliente (se houver), observações. Sugestão: incluir um código de barras/QR do nº da venda para facilitar localização em outro sistema (ex.: o sistema fiscal separado).
- **Risco conhecido:** impressão térmica via navegador é historicamente instável entre drivers/SOs. Plano B: **QZ Tray** ou envio direto **ESC/POS** via helper local. Reservar tempo de implementação para esse risco na Sprint em que o comprovante for construído.
- A impressão de etiquetas de preço/código de barras (Sub-sprint B) segue a mesma abordagem: rota Blade própria (`POST /labels/print`), com `@page` ajustado ao tamanho de página/etiqueta configurado pelo usuário e `window.print()` no load. É a segunda (e, junto com o comprovante, única) rota Blade do sistema — ambas listadas no bloco `location ~ ^/(api|sales|sanctum|labels)(/|$)` do `docker/nginx/default.conf`.
- **Impressão sem diálogo do navegador (`--kiosk-printing`):** por decisão de segurança do próprio Chromium, `window.print()` chamado por uma página **sempre** abre a caixa de diálogo de impressão — não existe API JS para imprimir silenciosamente. A solução padrão pra terminais dedicados (PDV, back-office) é abrir o navegador com a flag `--kiosk-printing`: com ela, `window.print()` manda direto pra impressora padrão do SO, sem diálogo nem preview. Funciona igual em **Windows, Linux e macOS** (é uma flag do Chromium/Chrome, não do SO) — único requisito é que a impressora térmica esteja configurada como impressora padrão do Windows/Linux no terminal.
  - **Windows:** criar um atalho apontando pro Chrome com esse alvo (ajustar o caminho do `chrome.exe` e o IP/host do servidor conforme o ambiente):
    ```
    "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing --kiosk http://<ip-do-servidor>
    ```
    Colocar o atalho na pasta de inicialização do Windows (`shell:startup`) pra abrir sozinho no boot do terminal. `--kiosk` (tela cheia, sem barra de endereço) é opcional — pode ser removido se o operador precisar da UI normal do navegador.
  - **Linux:** `google-chrome --kiosk-printing --kiosk http://<ip-do-servidor>` (ou `chromium-browser`, conforme o pacote instalado).
  - Isso é **configuração do terminal, não do sistema** — nenhuma mudança de código, só o comando/atalho usado pra abrir o navegador nas máquinas da loja. Documentar esse atalho no runbook de instalação de terminal quando a Sprint 7 (Manual do Usuário) cobrir a configuração de PDV/terminal.

## Backup e Recuperação (crítico)

- Agendamento via `spatie/laravel-backup` (`backup:clean` 01:30, `backup:run` 02:00, diários), disparado pelo container `scheduler`, gerando dump do PostgreSQL (`pg_dump` — o `postgresql-client` está instalado na imagem PHP).
- **Camadas:**
  1. Cópia local sempre — disco dedicado `backups` (`storage/app/backup`, dentro do bind mount do backend) — não depende de internet.
  2. Upload para **Google Drive quando houver internet** (rclone ou API do Drive, plugado como disk adicional do laravel-backup) — é bônus, não pré-requisito. *Ainda não implementado; previsto para depois do núcleo.*
- Retenção: política padrão do laravel-backup (`backup:clean` diário remove versões antigas).
- **Regra inegociável (já cumprida por teste automatizado):** o processo de **restore** é validado pelo teste `BackupRestoreTest`, que gera um backup real, restaura o dump em um banco descartável e confere os dados — não apenas verifica que o comando rodou. Esse teste roda em toda suíte; além dele, fazer um restore manual periódico na máquina da loja continua sendo boa prática.

## Manutenção e atualização

- Processo de atualização do sistema na máquina da loja: `git pull` + `docker compose build` + `docker compose up -d` + `migrate --force` + rebuild da SPA. Encapsulado em `deploy.sh` (Linux/macOS) e `deploy.bat` (Windows, caso o servidor final seja Windows 10 + Docker Desktop) — ver `07-dev-environment.md`.

## Riscos e mitigações

| Risco | Impacto | Mitigação |
|---|---|---|
| Máquina única falha | Perda total do negócio | Backup em camadas + restore testado (automatizado + manual periódico) |
| "Sem internet" x backup no Drive | Backup remoto impossível | Cópia local sempre; Drive é bônus |
| Impressão térmica no navegador | PDV não imprime | Plano B ESC/POS (QZ Tray); reservar tempo pra isso |
| Atualizar o app na máquina | Manutenção travada | Runbook documentado (07) + script de deploy futuro |
| Cookies/login quebram nos terminais | Ninguém loga no PDV | `SANCTUM_STATEFUL_DOMAINS` com o IP do servidor; `SESSION_DOMAIN=null` (ver 07) |
| Gold-plating / não entregar | Projeto morre 70% pronto | Priorizar chegar ao núcleo de venda (caixa + PDV) rápido; o resto é incremental |
