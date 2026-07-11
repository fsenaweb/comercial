# Arquitetura do Sistema

## Visão Geral
Sistema de gestão comercial e PDV para **uma única loja**, rodando **on-premise** na rede local (LAN) do estabelecimento. Não é SaaS, não tem multi-tenancy, e não depende de internet para operar. Arquitetura Cliente-Servidor clássica, conteinerizada via Docker.

## Backend (API Laravel)
- Laravel 11, atuando exclusivamente como API RESTful (JSON).
- Sem views no lado do servidor, **exceto** a rota do comprovante térmico (ver seção "Impressão Térmica" abaixo), que é a única página renderizada em Blade.
- Autenticação via **Laravel Sanctum** (token ou cookie de SPA).
- CORS liberado apenas para a origem do front na LAN (em produção, front e back são servidos pela mesma origem via proxy do nginx — ver "Infraestrutura" — então CORS praticamente não entra em jogo fora do ambiente de desenvolvimento).

## Frontend (Nuxt.js)
- Nuxt 3 em **modo SPA** (`ssr: false`) — não há necessidade de SSR/SEO em um sistema interno de uso local.
- Build estático servido pelo nginx.
- Tailwind CSS para estilização.
- Interface responsiva com foco em **agilidade de teclado** (essencial para o PDV: leitura de código de barras, atalhos, navegação sem mouse).
- Gerenciamento de estado global com Pinia.
- Consome a API do Laravel via `/api/*` (mesma origem, ver Infraestrutura).

## Banco de Dados (PostgreSQL)
- Escolhido pela robustez em ambientes locais e proteção contra corrupção em quedas de energia.
- Fica isolado em seu próprio container.
- Comunicação estrita com o backend através do Eloquent ORM.

## Infraestrutura (Docker)

Um `docker-compose.yml` orquestra os seguintes containers:

- **nginx** — serve o build do Nuxt (porta 80) e faz proxy reverso de `/api/*` para o `php-fpm` (Laravel). Front e back compartilham a mesma origem, o que evita problemas de CORS em produção.
- **nuxt** — build estático da SPA.
- **php-fpm** — aplicação Laravel (API).
- **postgres** — banco de dados.
- **scheduler** — container dedicado a rodar o cron do Laravel (`schedule:run`), responsável por disparar o backup automático (ver seção "Backup").

Todos os containers usam `restart: unless-stopped`, garantindo que o sistema suba sozinho quando a máquina da loja for ligada, sem intervenção manual.

### Diagrama de implantação (LAN)

```
        Loja (rede interna 192.168.x.x)
 ┌───────────────────────────────────────────────┐
 │  Máquina servidor (Linux)                      │
 │  ┌─────────────────────────────────────────┐  │
 │  │  Docker Compose                          │  │
 │  │   • nginx      (80: serve Nuxt +         │  │
 │  │                 proxy /api → Laravel)    │  │
 │  │   • nuxt       (build SPA — front)       │  │
 │  │   • php-fpm    (Laravel — API)           │  │
 │  │   • postgres   (dados)                   │  │
 │  │   • scheduler  (cron: backup)            │  │
 │  └─────────────────────────────────────────┘  │
 │        │ impressora térmica 58/80mm (USB/rede) │
 └────────┼──────────────────────────────────────┘
          │ LAN
   ┌──────┴──────┬─────────────┐
   │ Terminal PDV│ Back-office │  (navegadores na mesma rede,
   └─────────────┴─────────────┘   nada instalado localmente)
```

- Servidor: **Linux**, Docker como serviço (sobe no boot).
- Topologia inicial: 1 servidor + 3 terminais (navegador), expansível — terminais não instalam nada, apenas abrem o navegador no IP do servidor.
- Latência é de LAN (rápida); não há dependência de internet para vender.
- Mobile (terminal de consulta via WiFi) é **roadmap futuro** — exige HTTPS local para uso de câmera e não terá função de impressão.

## Impressão Térmica (58/80mm)

- O comprovante é gerado por uma **rota Blade no Laravel** (`/sales/{id}/receipt`), renderizada no servidor. O Nuxt abre essa URL em uma nova janela e dispara `window.print()`. Essa abordagem é mais robusta para impressão térmica via navegador do que tentar montar o cupom dentro da SPA.
- CSS `@page { size: 80mm auto }`, impresso pelo driver da impressora instalada no SO.
- Documento marcado **"DOCUMENTO NÃO FISCAL"** no topo — o sistema não emite NFC-e/NFe.
- Conteúdo: cabeçalho da loja, nº da venda, data/hora, itens (nome, qtd, unitário, total), subtotal, desconto, total, forma de pagamento, vendedor, cliente (se houver), observações. Sugestão: incluir um código de barras/QR do nº da venda para facilitar localização em outro sistema (ex.: o sistema fiscal separado).
- **Risco conhecido:** impressão térmica via navegador é historicamente instável entre drivers/SOs. Plano B: **QZ Tray** ou envio direto **ESC/POS** via helper local. Reservar tempo de implementação para esse risco na Sprint em que o comprovante for construído.

## Backup e Recuperação (crítico)

- Agendamento via `spatie/laravel-backup`, rodando no container `scheduler`, gerando dump do PostgreSQL (`pg_dump`).
- **Camadas:**
  1. Cópia local sempre (pasta/HD externo montado no container) — não depende de internet.
  2. Upload para **Google Drive quando houver internet** (rclone ou API do Drive) — é bônus, não pré-requisito.
- Retenção: manter N dias/versões e limpar backups antigos automaticamente.
- **Regra inegociável:** o processo de **restore** deve ser testado periodicamente, não apenas o backup em si. Um backup nunca restaurado é, na prática, um backup que não existe. Isso deve constar como tarefa recorrente (não só de Sprint 0), e coberto por teste automatizado que valide a restauração de um dump de exemplo.

## Manutenção e atualização

- Processo de atualização do sistema na máquina da loja: `git pull` + `docker compose up -d --build` (+ `migrate` quando houver novas migrations). Esse processo deve ser documentado em um script (`deploy.sh` ou similar) para reduzir erro humano, já que é executado localmente por quem estiver disponível na loja, não necessariamente um desenvolvedor.

## Riscos e mitigações

| Risco | Impacto | Mitigação |
|---|---|---|
| Máquina única falha | Perda total do negócio | Backup em camadas + restore testado |
| "Sem internet" x backup no Drive | Backup remoto impossível | Cópia local sempre; Drive é bônus |
| Impressão térmica no navegador | PDV não imprime | Plano B ESC/POS (QZ Tray); reservar tempo pra isso |
| Atualizar o app na máquina | Manutenção travada | Processo documentado: `git pull` + `docker compose up -d --build` + `migrate` |
| Gold-plating / não entregar | Projeto morre 70% pronto | Priorizar chegar ao núcleo de venda (caixa + PDV) rápido; o resto é incremental |
