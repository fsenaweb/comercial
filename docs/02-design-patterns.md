# Padrões de Projeto (Design Patterns) & Boas Práticas

## Filosofia geral
Arquitetura **pragmática**: Actions + Form Requests + Eloquent. **Sem DDD/hexagonal** — seria over-engineering para um sistema de uma loja só. O objetivo é código legível e testável, não cerimônia arquitetural.

**Não fazer:** repositories abstraindo o Eloquent, interface para tudo, CQRS, event sourcing, bounded contexts.

## Convenção de nomenclatura
- **Código 100% em inglês:** nomes de tabelas, colunas, models, controllers, Actions, variáveis, métodos e funções — tanto no Laravel quanto no Nuxt. Ex.: `products`, `product_variations`, `RegisterSaleAction`, `stock_quantity`.
- **Interface 100% em português:** todo texto visível ao usuário final (labels, mensagens de validação, mensagens de erro, textos de botão, conteúdo do comprovante) é em português — a loja opera em português, então tradução de mensagens (`lang/pt_BR`, mensagens customizadas em `FormRequest::messages()`) é obrigatória em toda validação e resposta de erro.
- Não misturar os dois: nenhum nome de coluna/model em português, nenhuma mensagem de UI em inglês.

## Backend (Laravel)

Para evitar "Fat Controllers" e seguir os princípios SOLID:

1. **Actions:** a regra de negócio não fica no Controller nem em Services genéricos — fica em **uma classe por operação de negócio**. O Controller apenas recebe a requisição (já validada por um Form Request), chama a Action correspondente e retorna a resposta via API Resource.
   - Exemplos: `RegisterSaleAction`, `OpenCashRegisterAction`, `CloseCashRegisterAction`, `AdjustStockAction`, `RegisterStockEntryAction`.
   - Cada Action é invocável (`__invoke` ou método `execute`), recebe dados já validados (DTO simples ou array) e devolve o resultado (ex.: a `Sale` criada).
2. **Form Requests:** toda validação de dados de entrada é feita em Form Requests do Laravel, com mensagens de erro em português via `messages()`.
3. **API Resources:** os dados retornados para o frontend são formatados usando API Resources (ex.: `ProductResource`, `SaleResource`), ocultando campos sensíveis e padronizando o payload.
4. **Soft Deletes:** registros de cadastros principais (produtos, variações, clientes, usuários, categorias) não são deletados fisicamente — usam a trait `SoftDeletes` para preservar histórico e integridade referencial de vendas passadas.
5. **Regra de ouro — transações e concorrência:** toda operação que mexe em **estoque** e/ou **caixa** roda dentro de `DB::transaction()` com `lockForUpdate()` nas linhas afetadas (ex.: `product_variations` durante uma venda), para evitar condições de corrida entre terminais (ex.: dois terminais vendendo a última unidade do mesmo item simultaneamente).

### Tratamento de erros
- **Exceptions de domínio** para regras de negócio que impedem uma operação, capturadas centralmente no `app/Exceptions/Handler.php` (ou `bootstrap/app.php` no Laravel 11) e convertidas em respostas JSON padronizadas.
  - Exemplos: `CashRegisterClosedException` (venda sem caixa aberto), `CashRegisterAlreadyOpenException`.
  - Nota: estoque **negativo é permitido** por decisão de produto — portanto não existe uma `InsufficientStockException` bloqueante; a baixa ocorre mesmo abaixo de zero, e isso é uma decisão consciente, não uma omissão.
- **Formato de erro padronizado** na API:
  ```json
  {
    "message": "Não é possível vender sem um caixa aberto.",
    "errors": { "cash_register": ["..."] }
  }
  ```
  Mesma estrutura usada tanto para erros de validação (422) quanto para exceptions de domínio (409/422, conforme o caso), para o frontend tratar de forma uniforme.
- Códigos HTTP: `422` para validação e regras de negócio violadas, `401`/`403` para autenticação/autorização, `404` para recurso inexistente, `409` para conflito de estado (ex.: tentar abrir um caixa já aberto), `500` reservado para falhas não tratadas (logadas, nunca expostas em detalhe ao cliente).
- No frontend, um composable único (`useApiError` ou interceptor central do client HTTP) traduz essas respostas em mensagens exibidas ao usuário — nenhuma tela deve tratar erro de API isoladamente.

### Estrutura de pastas (Laravel)
```
app/
  Actions/            # RegisterSaleAction, OpenCashRegisterAction, AdjustStockAction...
  Http/
    Controllers/Api/  # Controllers finos, um por recurso
    Requests/         # Form Requests
    Resources/        # API Resources
  Models/
  Exceptions/         # Exceptions de domínio (CashRegisterClosedException...)
  Policies/           # Autorização por papel (admin/caixa/vendedor)
database/
  migrations/
  factories/          # Necessárias para a cobertura ampla de testes
  seeders/
routes/
  api.php
  web.php             # Só a rota do comprovante térmico
tests/
  Feature/            # Um diretório por domínio (Sales/, CashRegister/, Stock/, Products/...)
  Unit/                # Actions e regras de negócio isoladas
```

## Frontend (Nuxt.js / Vue.js)

1. **Composition API (`<script setup>`):** sintaxe moderna do Vue 3 para componentes lógicos e limpos.
2. **Componentização (Dumb/Smart):**
   - Componentes de UI puros (botões, inputs, tabelas) ficam separados de componentes de regra de negócio (tela de PDV, formulários conectados à API).
3. **Composables:** lógica reutilizável (formatação de moeda, máscaras, chamadas de API, tratamento de erro) vai para `useSomething.ts`.
4. **Pinia:** um store por domínio relevante ao estado global (`useAuthStore`, `useCashRegisterStore`, `useCartStore` no PDV) — não centralizar tudo em um único store monolítico.

### Estrutura de pastas (Nuxt)
```
components/
  ui/           # Botão, Input, Modal, Table — sem regra de negócio
  sales/        # Componentes do PDV/carrinho
  products/
  cash-register/
  ...           # um diretório por domínio
composables/
  useApiError.ts
  useCurrency.ts
  useCashRegister.ts
  ...
stores/         # Pinia, um arquivo por domínio
pages/          # Roteamento por arquivo do Nuxt
layouts/
  default.vue
  pos.vue       # Layout dedicado ao PDV (foco em teclado, tela cheia)
middleware/     # Guards de rota (auth, role)
```

## Testes automatizados
Cobertura **ampla**, não restrita a caminhos críticos:

- **Backend:** Feature tests (Pest ou PHPUnit) para todos os endpoints de CRUD (create/update/delete/list + validação via Form Request) e para todas as Actions, incluindo os fluxos de erro (ex.: venda sem caixa aberto, tentar fechar caixa já fechado). Actions financeiras (`RegisterSaleAction`, `OpenCashRegisterAction`, `CloseCashRegisterAction`, `AdjustStockAction`) recebem também testes que validem concorrência (`lockForUpdate`) e a restauração de backup é validada por um teste dedicado (ver 01-architecture.md, seção Backup).
- **Frontend:** testes unitários (Vitest) para composables com lógica não-trivial (cálculo de carrinho, máscaras, tratamento de erro) e para stores Pinia com regras de negócio (ex.: `useCartStore`).
- Cada sprint do backlog (`05-sprints.md`) inclui a tarefa de teste como parte da entrega, não como item separado ao final.
