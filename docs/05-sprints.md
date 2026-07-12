# Sprints (Backlog Técnico)

Backlog sequencial. Cada sprint mapeia para uma fase do `04-roadmap.md`. Convenção de nomenclatura e padrões em `02-design-patterns.md`; modelo de dados em `03-database-modeling.md`; comandos de validação em `07-dev-environment.md`.

Toda sprint segue o fluxo de trabalho do `CLAUDE.md` (branch própria → desenvolvimento → validação com tudo verde → aprovação → commit único) e inclui testes automatizados como parte da entrega (cobertura ampla — não é item à parte, é critério de "pronto").

## Sprint 0: Fundação (Fase 0) — ✅ CONCLUÍDA (2026-07-11, branch `feat/sprint-0-fundacao`)
- [x] Inicializar projeto Laravel (API) e Nuxt (SPA, `ssr: false`) — *entregue com Nuxt 4 (estrutura `app/`) e, logo em seguida, migrado de Laravel 11 para 12 (11 já fora da janela de suporte de segurança).*
- [x] Configurar `docker-compose.yml`: `postgres` (+ init do banco de testes), `php-fpm`, `nginx` (serve SPA + fastcgi `/api`, `/sales`, `/sanctum`), `nuxt-build` (profile `build`, gera a SPA no volume `nuxt_dist`), `scheduler` (cron). Todos com `restart: unless-stopped`; imagem PHP com UID/GID do host (sem arquivos root no repo).
- [x] Configurar conexão do banco de dados no Laravel (pgsql, `pt_BR`, `America/Sao_Paulo`).
- [x] Migration de `users` já com `role` (enum: admin/cashier/seller), `commission_percent`, `active`, soft deletes — sem tabelas `roles`/`permissions`.
- [x] Autenticação via Laravel Sanctum **modo SPA (cookie de sessão)**: `LoginAction`, login/logout/me, exceptions de domínio (`InvalidCredentialsException`, `InactiveUserException`), middleware `role:`, `StoreSettingPolicy`, API-only sem redirects.
- [x] Tela de Login no Nuxt, `useAuthStore` (Pinia), `useApi`/`useApiError`, middleware global de rota, layout base com logout.
- [x] Migration + endpoints de `store_settings` (registro único via `StoreSetting::current()`) e tela de configuração da loja.
- [x] Configurar `spatie/laravel-backup`: dump local diário via `scheduler` (clean 01:30, run 02:00), disco dedicado `backups`.
- [x] Seeder do admin inicial (`admin@loja.local` / `password` — trocar em produção).
- [x] Testes: 15 feature tests passando — login/logout/perfil (incl. usuário inativo e validações), autorização por papel em `store_settings`, e `BackupRestoreTest` que **restaura** o dump em banco descartável e confere os dados.
- [x] Validação end-to-end via nginx (porta 80): CSRF → login → sessão → logout, typecheck e build da SPA limpos.
- [x] `docs/08-design-system.md` criado (paleta marca amarelo/preto + status semântico, tipografia Bricolage Grotesque/Hanken Grotesk, padrões de componente), extraído de telas e CSS de referência do AppLoja + logo da loja.
- [x] Telas da Sprint 0 retrocedidas para os tokens do design system (`main.css` com `@theme`, `BaseButton`/`BaseInput`/`login.vue`/`index.vue`/`settings/store.vue`/`layouts/default.vue`); tema fixado sempre claro (`color-scheme: light`, sem seguir o SO) e logo da loja no card de login.
- [x] Bug de componente corrigido: `components: [{ pathPrefix: false }]` no `nuxt.config.ts` — sem isso, `<BaseButton>`/`<BaseInput>` não resolviam em runtime (build/typecheck passavam normalmente; só aparecia com o app rodando no navegador). Documentado em `07-dev-environment.md`.
- [x] Hostname local `loja.local` via `/etc/hosts` + `SANCTUM_STATEFUL_DOMAINS`/`SESSION_DOMAIN=null`, ensaiando a exigência de produção (acesso por IP/hostname da LAN, não `localhost`).

## Sprint 1: Cadastros (Fase 1) — ✅ CONCLUÍDA (2026-07-11, branch `feat/sprint-1-cadastros`)
- [x] API CRUD (Model, Migration, Controller, Form Requests, Resources, Policy) para `categories`, `subcategories`, `brands`, `units`.
- [x] API CRUD para `products` + `product_variations` (SKU), incluindo registro do estoque inicial via `stock_movements` (nunca gravar `current_quantity` direto) — a migration de `stock_movements` entra aqui por dependência. *`CreateProductVariationAction` cria a variação e o `stock_movements` (tipo `adjustment`, origem "estoque inicial") dentro de `DB::transaction()`; updates de variação nunca tocam `current_quantity`.*
- [x] API CRUD para `customers` e `suppliers`. *`customers` com policy diferenciada: admin/cashier/seller podem criar e editar, só admin exclui — cliente é cadastrado no fluxo de venda por qualquer papel.*
- [x] Telas Nuxt de listagem e formulário para todas as entidades acima (componentes de tabela/formulário reutilizáveis em `components/ui/`: `BaseSelect`, `BaseTextarea`, `BaseTable` genérica, além de `BaseInput`/`BaseButton` já existentes).
- [x] Máscaras no frontend: moeda, CPF/CNPJ, CEP (composables testáveis: `useCurrencyMask`, `useDocumentMask`, `useCepMask`).
- [x] Testes: feature tests de CRUD (create/update/delete/list + validação de Form Request + autorização por papel) para cada entidade acima (70 testes no total, incluindo o backend pré-existente); Vitest para os composables de máscara (11 testes) — `vitest` adicionado como devDependency (`npm run test`).
- [x] Validação end-to-end via nginx: `docker compose exec php-fpm php artisan test` (70 passed), `npx nuxi typecheck` e `npm run generate` limpos, telas verificadas no navegador (Playwright headless) incluindo criação de categoria, produto e variação com estoque inicial.
- [x] Armadilha nova documentada: o serviço `nuxt-build` do compose faz `COPY frontend/` **na imagem** (não é bind mount) — editar código do front e rodar só `docker compose --profile build run --rm nuxt-build` serve a build antiga. É preciso `docker compose build nuxt-build` antes (ver `07-dev-environment.md`).

## Sprint 2: Caixa (parte da Fase 2)
- [ ] Migrations e Models para `cash_registers`, `cash_operations`, `payment_methods` (+ seeder de formas de pagamento padrão).
- [ ] `OpenCashRegisterAction` e `CloseCashRegisterAction`, com regra de **um caixa aberto por vez** (`CashRegisterAlreadyOpenException`) e lançamentos de sangria/reforço.
- [ ] Endpoints de abertura/fechamento/consulta de operações do caixa atual.
- [ ] Tela de abertura/fechamento de caixa no Nuxt (`useCashRegisterStore`).
- [ ] Testes: feature tests para abrir/fechar caixa (incluindo tentativa de abrir um segundo caixa, fechar caixa já fechado, sangria/reforço).

## Sprint 3: PDV, Venda e Comprovante (parte da Fase 2 — o coração do sistema)
- [ ] Migrations para `sales` e `sale_items` (referenciando `seller_id`, `cash_register_id`).
- [ ] Interface do PDV no Nuxt (layout dedicado `pos.vue`, foco em teclado): leitura de código de barras, busca por nome, carrinho (`useCartStore`), seleção de vendedor (pré-selecionado pelo login, trocável conforme `store_settings.require_seller_on_sale`) e cliente (opcional), desconto por item e no total.
- [ ] `RegisterSaleAction` dentro de `DB::transaction()` com `lockForUpdate()` nas `product_variations` envolvidas: valida caixa aberto (`CashRegisterClosedException`), cria `sale` + `sale_items`, gera `stock_movements` (saída, permite negativo) e `cash_operations` (entrada), decrementa `current_quantity`.
- [ ] Rota Blade do comprovante (`/sales/{id}/receipt`), CSS `@page { size: 80mm auto }`, marcação "DOCUMENTO NÃO FISCAL", código de barras/QR do nº da venda. Front abre em nova janela e chama `window.print()`. *Reservar tempo para o risco de impressão (plano B: QZ Tray/ESC-POS).*
- [ ] Testes: feature tests do fluxo completo de venda (caminho feliz, venda sem caixa aberto, estoque indo negativo, concorrência entre duas vendas simultâneas do mesmo item via `lockForUpdate`), e teste de renderização da rota de comprovante; Vitest para o cálculo do carrinho (`useCartStore`).

## Sprint 4: Estoque avançado & Histórico (Fase 3)
- [ ] `AdjustStockAction` (ajuste manual) e `RegisterStockEntryAction` (entrada de mercadoria) — únicas vias de escrita em `product_variations.current_quantity`, sempre via `stock_movements`.
- [ ] Tela de ajuste manual de estoque no Nuxt.
- [ ] Tela de Kardex: histórico de movimentações por variação, com filtros.
- [ ] Telas/endpoints de histórico de vendas e histórico de caixas (fechamentos anteriores), com filtros por período/vendedor.
- [ ] Testes: feature tests para as Actions de estoque (incluindo concorrência) e para os endpoints de histórico/filtros.

## Sprint 5: Relatórios e Dashboard (Fase 4)
- [ ] Endpoints de relatório: vendas por dia, por produto, por vendedor, por categoria.
- [ ] Alertas de estoque abaixo do mínimo (`current_quantity < min_quantity`).
- [ ] Dashboard inicial no Nuxt: total vendido no dia, vendas por vendedor, alertas de estoque.
- [ ] Testes: feature tests dos endpoints de relatório (valores agregados corretos para os cenários de dados de teste).

## Sprint 6 (opcional — Fase 5, após núcleo estável em produção)
- [ ] Crediário: `accounts_receivable` + `installments`, tela de lançamento e baixa de parcelas.
- [ ] `accounts_payable` + `expenses`, tela de lançamento.
- [ ] Entrada de estoque via XML de nota fiscal do fornecedor (`stock_entries`).
- [ ] Testes: feature tests para os fluxos acima, seguindo o mesmo padrão de cobertura das sprints anteriores.

## Melhorias transversais (encaixar quando conveniente, sem travar as fases)
- [ ] Upload do backup ao Google Drive (camada 2 — rclone ou disk adicional do laravel-backup).
- [ ] `deploy.sh` encapsulando o runbook de atualização da loja (ver `07-dev-environment.md`).
- [x] CRUD de usuários (login/papel/comissão/ativo) no back-office — tela "Usuários e Permissões" em Administração, admin-only, com trava de auto-desativação (`SelfDeactivationException`). *Vendedores* (perfil comercial mais completo — CPF/CNPJ, endereço, data de contratação, vínculo opcional com um usuário de login) é uma entidade **separada**, ainda não implementada — ver item abaixo.
- [ ] Tela "Vendedores" (Cadastros) — perfil de equipe comercial distinto de usuário de login: nova tabela `sellers` (nome, documento com toggle PF/PJ, contato, endereço, comissão, data de nascimento/contratação, observações, `user_id` nullable pra associar a uma conta de login existente).
  - [ ] Depois que `sellers` existir, voltar no modal de "Usuários e Permissões" (`frontend/app/pages/users.vue`) e adicionar a seção "Dados do vendedor" quando o papel selecionado for Vendedor — igual à referência do AppLoja: campo "Associar vendedor" (linka a um `seller` já existente) ou preenche os dados ali mesmo pra criar um `seller` novo já vinculado ao `user_id` recém-criado. Não dá pra fazer isso antes de `sellers` existir — depende da tarefa acima.
