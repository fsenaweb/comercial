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

- Agendamento via `spatie/laravel-backup` (`backup:clean` 09:45, `backup:run` 10:00, diários — horário definido pelo PM; `pg_dump` não trava o sistema em uso, seguro em horário comercial), disparado pelo container `scheduler`, gerando dump do PostgreSQL (`pg_dump` — o `postgresql-client` está instalado na imagem PHP).
- **Camadas:**
  1. Cópia local sempre — disco dedicado `backups` (`storage/app/backup`, dentro do bind mount do backend) — não depende de internet.
  2. **Upload automático para o Google Drive** (bônus, não pré-requisito — falha aqui nunca compromete a camada 1). *Implementado na Sub-sprint E (2026-07-16), ver `05-sprints.md`.*
- Retenção: política padrão do laravel-backup (`backup:clean` diário remove versões antigas).

### Backup remoto — Google Drive (camada 2)

- **Conta pessoal, não Workspace:** a loja usa uma conta Google One (pessoal, paga só por espaço extra), sem Unidade Compartilhada — uma Service Account sozinha não teria cota própria para receber arquivos. A autenticação é **OAuth com o usuário real** (o admin da loja autoriza uma vez; o app guarda um refresh token e renova o acesso sozinho depois).
- **Fluxo de dispositivo (device flow, RFC 8628), não redirect padrão:** o sistema roda on-premise, acessado por IP puro na LAN (`http://<ip-do-servidor>`, sem HTTPS, sem domínio). O OAuth com `redirect_uri` exige HTTPS ou `http://localhost` — inviável aqui. Por isso o backend usa o fluxo pensado para TVs/dispositivos de entrada limitada: pede um código à Google, mostra esse código + um link (`google.com/device`) na tela "Backup e restauração" (`/settings/backup`), e o admin autoriza em **qualquer aparelho com internet** (celular, notebook) — sem nenhum redirect envolvido.
- **Escopo mínimo:** `drive.file` — o app só enxerga/gerencia os arquivos que ele mesmo cria (uma pasta "Backups - <nome da loja>"), nunca o Drive inteiro da conta.
- **Sem pacote novo do Google:** todas as chamadas (device code, poll de token, refresh, criar pasta, upload, listar) usam `Illuminate\Support\Facades\Http` (Guzzle já é dependência do Laravel) — sem `google/apiclient`.
- **Sem fila/worker:** o projeto não tem `queue:work` rodando (só o `scheduler` com `schedule:run`). O upload ao Drive é síncrono, via comando `backups:sync-google-drive`, agendado às 10:15 (logo depois do `backup:run` às 10:00).
- **Setup único, por ambiente** (feito uma vez pelo desenvolvedor/mantenedor, não pelo lojista):
  1. Criar um projeto no [Google Cloud Console](https://console.cloud.google.com/).
  2. Ativar a **Google Drive API** (menu "APIs e serviços" → "Biblioteca").
  3. Configurar a "Tela de consentimento OAuth" (tipo Externo, com o e-mail de suporte da loja).
  4. Em "Credenciais" → "+ Criar cliente" → **"ID do cliente OAuth"**, escolher **exatamente** o tipo **"TVs e dispositivos de entrada limitada"** ("TVs and Limited Input devices") — esse tipo não pede `redirect_uri`, é o que habilita o device flow.
     - **Armadilha real (achada na validação da Sub-sprint E):** é fácil escolher **"Aplicativo da Web"** por engano — a tela de criação não deixa óbvio que só o tipo "TVs e dispositivos de entrada limitada" libera o grant de device flow. Com o tipo errado, a chamada ao endpoint de device code da Google falha. Se isso acontecer, não dá para só "editar o tipo" do cliente já criado — é preciso criar um cliente novo com o tipo certo (pode excluir o antigo depois).
     - O Client Secret **não aparece na lista** de credenciais (só um trecho truncado do Client ID) — para vê-lo por completo, clicar no **nome** do cliente na listagem, que abre a tela de detalhes com o Client Secret completo e um botão de copiar.
  5. Copiar o Client ID/Secret gerados para `GOOGLE_OAUTH_CLIENT_ID`/`GOOGLE_OAUTH_CLIENT_SECRET` no `.env` da loja e reiniciar o `php-fpm` (`docker compose restart php-fpm`) para carregar o `.env` novo.
  6. **Publicar o app** (mesma tela "Tela de consentimento OAuth" → botão "PUBLICAR APP", muda o status de "Em testes" para "Produção"). **Passo obrigatório, não opcional:** enquanto o app está "Em testes", só e-mails cadastrados como "usuário de teste" conseguem autorizar, **e o refresh token expira sozinho a cada 7 dias** — inviável para um backup diário automático. Publicando (sem precisar da verificação formal da Google, que só é exigida acima de certos limites de uso), o token deixa de expirar sozinho; o preço é que a primeira autorização mostra um aviso "O Google não verificou este app" — normal para apps internos não verificados, resolve-se clicando em "Avançado" → "Acessar (nome do app) (não seguro)".
- Sem essas duas variáveis configuradas, o botão "Conectar Google Drive" fica indisponível (422 com mensagem clara) — o backup local (camada 1) continua funcionando normalmente.
- Token de refresh fica guardado em `store_settings` (campos `google_drive_*`), criptografado (cast `encrypted`) — nunca em `.env` (é credencial da conta do lojista, não do ambiente).
- **Regra inegociável (já cumprida por teste automatizado):** o processo de **restore** é validado pelo teste `BackupRestoreTest`, que gera um backup real, restaura o dump em um banco descartável e confere os dados — não apenas verifica que o comando rodou. Esse teste roda em toda suíte; além dele, fazer um restore manual periódico na máquina da loja continua sendo boa prática.
- **Restauração pela própria tela** (`/settings/backup`, Sub-sprint E, 2026-07-16): antes só existia o restore manual via terminal (runbook em `07-dev-environment.md`) — agora admin pode restaurar direto pela UI, a partir de um backup local ou de um `.zip` enviado manualmente (baixado do Drive, de um pendrive etc.). Deliberadamente cheio de fricção, dado o tanto de gente que pode acessar o sistema e o quanto essa operação é destrutiva:
  - Bloqueado se houver caixa aberto (`RestoreBlockedByOpenCashRegisterException`, 409).
  - Exige um **código de confirmação aleatório de uso único** (`GenerateRestoreConfirmationCodeAction`, 6 caracteres, expira em 5 minutos, `Cache::pull` atômico) — gerado pelo servidor a cada abertura do modal e exibido na tela; validado no backend (`BackupController::restore`), não só no front, para não virar "teatro" (uma palavra fixa poderia ser cravada num script que chamasse a API sem passar pela UI).
  - `RestoreBackupAction`: extrai o zip, corrige um problema real de compatibilidade (dump gerado pelo cliente `psql`/`pg_dump` 17.x da imagem PHP inclui `SET transaction_timeout`, parâmetro que só existe a partir do Postgres 17 — o servidor do projeto é 16.x e rejeita; a linha é removida do dump antes de restaurar), encerra outras conexões ativas com o banco (`pg_terminate_backend`), derruba e recria o banco (`DROP`/`CREATE DATABASE`) e recarrega o dump — e por fim `TRUNCATE sessions`, derrubando as sessões de outros terminais (a do próprio operador é reescrita normalmente ao fim da requisição).
  - Validado por `tests/Feature/Backup/RestoreBackupTest.php` com o mesmo rigor do `BackupRestoreTest` (restaura de verdade contra o banco de teste via `DatabaseMigrations`, não um mock).

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
