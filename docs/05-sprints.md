# Sprints (Backlog Técnico)

Backlog sequencial. Cada sprint mapeia para uma fase do `04-roadmap.md`. Convenção de nomenclatura e padrões em `02-design-patterns.md`; modelo de dados em `03-database-modeling.md`.

Toda sprint inclui testes automatizados como parte da entrega (cobertura ampla — não é item à parte, é critério de "pronto").

## Sprint 0: Fundação (Fase 0)
- [ ] Inicializar projeto Laravel 11 (API) e Nuxt 3 (SPA, `ssr: false`).
- [ ] Configurar `docker-compose.yml`: `postgres`, `php-fpm`, `nginx` (serve Nuxt + proxy `/api` → Laravel), `nuxt` (build), `scheduler` (cron). Todos com `restart: unless-stopped`.
- [ ] Configurar conexão do banco de dados no Laravel.
- [ ] Migration de `users` já com `role` (enum: admin/cashier/seller), `commission_percent`, `active` — sem tabelas `roles`/`permissions`.
- [ ] Implementar autenticação via Laravel Sanctum (login/logout) + Policies básicas por papel.
- [ ] Criar tela de Login no Nuxt, store `useAuthStore` (Pinia), guard de rota (`middleware/auth.ts`).
- [ ] Migration + CRUD simples de `store_settings` (registro único) e tela de configuração da loja no back-office.
- [ ] Configurar `spatie/laravel-backup`: dump local agendado via `scheduler`.
- [ ] Testes: feature tests de login/logout, autorização por papel, e teste de restauração do backup gerado (`restore` executado contra um dump de teste).

## Sprint 1: Cadastros (Fase 1)
- [ ] API CRUD (Model, Migration, Controller, Form Requests, Resources) para `categories`, `subcategories`, `brands`, `units`.
- [ ] API CRUD para `products` + `product_variations` (SKU), incluindo registro do estoque inicial via `stock_movements` (nunca gravar `current_quantity` direto).
- [ ] API CRUD para `customers` e `suppliers`.
- [ ] Telas Nuxt de listagem e formulário para todas as entidades acima.
- [ ] Máscaras no frontend: moeda, CPF/CNPJ, CEP.
- [ ] Testes: feature tests de CRUD (create/update/delete/list + validação de Form Request) para cada entidade acima.

## Sprint 2: Caixa (parte da Fase 2)
- [ ] Migrations e Models para `cash_registers`, `cash_operations`, `payment_methods`.
- [ ] `OpenCashRegisterAction` e `CloseCashRegisterAction`, com regra de **um caixa aberto por vez** (`CashRegisterAlreadyOpenException`).
- [ ] Endpoints de abertura/fechamento/consulta de operações do caixa atual.
- [ ] Tela de abertura/fechamento de caixa no Nuxt (`useCashRegisterStore`).
- [ ] Testes: feature tests para abrir/fechar caixa (incluindo tentativa de abrir um segundo caixa e de vender sem caixa aberto).

## Sprint 3: PDV, Venda e Comprovante (parte da Fase 2 — o coração do sistema)
- [ ] Migrations para `sales` e `sale_items` (referenciando `seller_id`, `cash_register_id`).
- [ ] Interface do PDV no Nuxt: leitura de código de barras, busca por nome, carrinho (`useCartStore`), seleção de vendedor (pré-selecionado pelo login, trocável conforme `store_settings.require_seller_on_sale`) e cliente (opcional), desconto por item e no total.
- [ ] `RegisterSaleAction` dentro de `DB::transaction()` com `lockForUpdate()` nas `product_variations` envolvidas: valida caixa aberto, cria `sale` + `sale_items`, gera `stock_movements` (saída, permite negativo) e `cash_operations` (entrada), decrementa `current_quantity`.
- [ ] Rota Blade do comprovante (`/sales/{id}/receipt`), CSS `@page { size: 80mm auto }`, marcação "DOCUMENTO NÃO FISCAL", código de barras/QR do nº da venda. Front abre em nova janela e chama `window.print()`.
- [ ] Testes: feature tests do fluxo completo de venda (caminho feliz, venda sem caixa aberto, estoque indo negativo, concorrência entre duas vendas simultâneas do mesmo item via `lockForUpdate`), e teste de renderização da rota de comprovante.

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
