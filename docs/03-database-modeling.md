# Modelagem do Banco de Dados (PostgreSQL)

O banco segue o padrão do Eloquent ORM. Nomes de tabela e coluna em **inglês** (ver convenção em `02-design-patterns.md`).

**Legenda de status:** ✅ implementada · ⬜ pendente

> Esta modelagem substitui uma versão anterior mais simples. Duas mudanças relevantes em relação a ela: (1) o controle de acesso deixa de usar tabelas relacionais `roles`/`permissions` e passa a usar um campo `role` (enum) direto em `users` — decisão fechada no doc `06-claude-instruction.md`, já que o sistema tem apenas 3 papéis fixos e não há necessidade de permissões granulares configuráveis; (2) o estoque deixa de viver em `products` e passa para `product_variations`, pois produtos têm variação por cor/tamanho/SKU.

## 1. Controle de Acesso
- ✅ **users**: id, name, email, password, `role` (enum: `admin`, `cashier`, `seller` — enum PHP `App\Enums\UserRole`), commission_percent (nullable — comissão do vendedor), active (boolean), soft deletes, timestamps.
  - Não existe tabela `sellers` separada: o vendedor **é** o usuário logado (decisão fechada).
  - Autorização por papel via Policies do Laravel + middleware `role:`, não por tabela de permissões.

## 2. Configuração da Loja
- ✅ **store_settings**: registro único (id fixo = 1, acessado via `StoreSetting::current()`). name, trade_name (nullable), cnpj (nullable), email (nullable), phone (nullable), mobile_phone (nullable), endereço estruturado (zip_code, address, address_number, address_complement, neighborhood, city, state — mesmo padrão de `customers`/`suppliers`), logo_path (nullable, upload real via `POST /store-settings/logo`, disco `public`, servido pelo nginx em `/storage/...`), `require_seller_on_sale` (boolean — se a seleção de vendedor no PDV é obrigatória), `auto_open_cash_register` (boolean — se o caixa abre automaticamente ao iniciar o expediente).

## 3. Tabelas de framework (criadas pelo Laravel — Sprint 0)
- ✅ **sessions** — obrigatória: a autenticação é por cookie de sessão com driver `database` (ver `01-architecture.md`).
- ✅ **personal_access_tokens** — criada pelo Sanctum; sem uso no fluxo atual (auth é por cookie), reservada caso um cliente por token seja necessário no futuro (ex.: terminal mobile).
- ✅ **cache**, **jobs** — drivers `database` padrão do Laravel.
- ✅ **password_reset_tokens** — padrão do framework; recuperação de senha por e-mail está fora do escopo (loja local, admin troca senhas), mantida por ser inofensiva.

## 4. Cadastros Base
- ⬜ **categories**: id, name, description, soft deletes.
- ⬜ **subcategories**: id, category_id, name, soft deletes.
- ⬜ **brands**: id, name, soft deletes.
- ⬜ **units**: id, name, abbreviation (ex.: "Unidade" / "UN", "Caixa" / "CX").
- ⬜ **suppliers**: id, corporate_name, trade_name, cnpj, contact, address (nullable — opcional na v1), soft deletes.
- ⬜ **customers**: id, name, mobile_phone, phone (nullable), email (nullable), document (cpf/cnpj), is_company (boolean), birth_date (nullable), zip_code, address, address_number, address_complement, neighborhood, city, state, notes, soft deletes.
- ✅ **products**: id, name, `type` (enum: `product`, `service`, `kit`), `active` (boolean, default `true` — produto descontinuado/pausado some da busca do PDV sem apagar histórico; não confundir com exclusão, que é para cadastros duplicados de verdade), unit_id, location (nullable — posição física no estoque), category_id, subcategory_id, brand_id, fiscal_fields (json, nullable — reservado para integração futura com sistema fiscal separado; não usado no MVP), soft deletes, timestamps.
- ✅ **product_variations** (SKU — **o estoque vive aqui, não em `products`**): id, product_id, color (nullable), size (nullable), ean_gtin (nullable), product_code, cost_price, markup, sale_price, `current_quantity`, min_quantity, max_quantity, `wholesale_min_qty` (nullable — quantidade mínima do item na venda para valer o preço de atacado; Sprint 4.1), `wholesale_price` (nullable, obrigatório em conjunto com `wholesale_min_qty`; Sprint 4.1), soft deletes, timestamps.
  - Frontend calcula `sale_price` automaticamente a partir de `cost_price`/`markup` (custo × (1 + margem/100)) nos formulários de cadastro — não é uma coluna derivada no banco, `sale_price` continua editável e persistido normalmente.
  - **Regra de integridade:** `current_quantity` nunca é editado diretamente — só muda como efeito de um `INSERT` em `stock_movements` (inclusive o estoque inicial de um produto novo, que deve gerar um `stock_movements` do tipo `adjustment` com origem "estoque inicial"). Essa regra é reforçada em código (só o `AdjustStockAction`/`RegisterStockEntryAction` grava em `current_quantity`, nunca um Controller direto).

## 5. Estoque (Kardex)
- ⬜ **stock_movements**: id, product_variation_id, `type` (enum: `in`, `out`, `adjustment`, `sale`), quantity, origin (texto/enum curto — ex.: "manual", "sale", "supplier_entry"), reference_id (nullable — id da venda/entrada relacionada, quando houver), user_id, created_at.

## 6. Caixa
- ✅ **cash_registers**: id, opened_at, opening_amount, `status` (enum: `open`, `closed`), closed_at (nullable), closing_amount (nullable), opened_by (fk `users.id`), closed_by (fk `users.id`, nullable), notes.
  - Regra: **um caixa aberto por vez** na loja inteira (não por terminal, não por usuário).
  - `expected_amount`/`difference_amount` são calculados on-the-fly (`CashRegister::expectedAmount()`, via `bcmath`), nunca persistidos — não há coluna nova além do que já estava fechado aqui.
- ✅ **cash_operations**: id, cash_register_id, user_id (quem lançou), `type` (enum: `in`, `out`), `origin` (enum: `sale`, `cash_withdrawal`, `cash_reinforcement`, `adjustment`), `reference_id` (nullable, sem FK — mesmo padrão de `stock_movements.reference_id`; o que referencia depende de `origin`, hoje só `sale` → `sales.id`, usado pela tela de Caixa pra abrir o detalhe de itens de uma venda), payment_method_id (nullable), amount, notes, created_at.
  - `origin: sale` já gera `cash_operations` desde a Sprint 3 (`RegisterSaleAction`).
- ✅ **payment_methods**: id, name, active_on_pos (boolean).

## 7. Vendas / Atendimento
- ✅ **sales**: id, number, customer_id (nullable), `seller_id` (fk `users.id`), cash_register_id (fk), subtotal, `discount_type` (enum: `fixed`, `percentage`), `discount_value` (valor digitado pelo operador), discount (valor absoluto já resolvido, persistido), total, payment_method_id, notes, `status` (enum: `pending`, `completed`, `canceled`; `canceled` passou a ser usado na Sprint 4.1, via `CancelSaleAction`), `canceled_reason` (nullable), `canceled_at` (nullable), `canceled_by` (fk `users.id`, nullable) — os 3 últimos preenchidos só no cancelamento (Sprint 4.1), created_at.
- ✅ **sale_items**: id, sale_id, product_variation_id, quantity, unit_price, `discount_type`, `discount_value`, discount, total, `is_wholesale` (boolean, default `false` — marca se o `unit_price` gravado veio do preço de atacado da variação; Sprint 4.1).

## 8. Regras de negócio associadas ao modelo
- Venda **exige caixa aberto** (`sales.cash_register_id` sempre referencia um `cash_registers` com `status = open` no momento da criação).
- Venda **não permite estoque negativo** desde a Sprint 4.1 — `RegisterSaleAction` valida `current_quantity >= quantity` por item antes de gravar (422 se faltar, transação inteira abortada); revoga a decisão da Sprint 3, que permitia. `AdjustStockAction` também rejeita `new_quantity` negativo.
- No PDV, vendedor é pré-selecionado pelo usuário logado, mas trocável (inclusive via atalho F3, Sprint 4.1); obrigatoriedade controlada por `store_settings.require_seller_on_sale`.
- Cliente é opcional em `sales.customer_id`.
- Desconto existe tanto em `sale_items.discount` (por item) quanto em `sales.discount` (no total) — em ambos os casos, o operador escolhe entre valor fixo (R$) ou percentual (`discount_type`/`discount_value`); o valor absoluto efetivamente aplicado fica sempre persistido em `discount`, nunca recalculado depois.
- Preço de atacado (Sprint 4.1): se a variação tem `wholesale_min_qty`/`wholesale_price` preenchidos e a quantidade do item na venda atinge o mínimo, `RegisterSaleAction` usa `wholesale_price` como `unit_price` do item em vez de `sale_price`, marcando `sale_items.is_wholesale = true`.
- Cancelamento de venda (Sprint 4.1): `CancelSaleAction` marca `sales.status = canceled`, devolve os itens ao estoque (`stock_movements` tipo `in`) e lança uma `cash_operations` compensatória (tipo `out`, origem `adjustment`) — nunca deleta a venda nem o lançamento de caixa original; exige caixa aberto no momento do cancelamento (mesma regra da venda). `RemoveCashOperationAction` rejeita remoção de `cash_operations` com origem `sale`/`adjustment` — só lançamentos manuais (sangria/reforço) podem ser removidos.
- Valores monetários: `decimal` (nunca float); percentuais como `decimal(5,2)`.

## Fase 2 (opcional, fora do núcleo)
Tabelas não implementadas no MVP, reservadas para depois do núcleo de venda estar rodando:
- `accounts_receivable`, `installments` — crediário / contas a receber com parcelas.
- `accounts_payable`, `expenses` — contas a pagar e despesas.
- `stock_entries` — entradas de estoque via XML de nota fiscal do fornecedor.
