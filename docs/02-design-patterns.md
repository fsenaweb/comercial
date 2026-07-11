# Padrões de Projeto (Design Patterns) & Boas Práticas

## Filosofia geral
Arquitetura **pragmática**: Actions + Form Requests + Eloquent. **Sem DDD/hexagonal** — seria over-engineering para um sistema de uma loja só. O objetivo é código legível e testável, não cerimônia arquitetural.

**Não fazer:** repositories abstraindo o Eloquent, interface para tudo, CQRS, event sourcing, bounded contexts.

## Convenção de nomenclatura
- **Código 100% em inglês:** nomes de tabelas, colunas, models, controllers, Actions, variáveis, métodos e funções — tanto no Laravel quanto no Nuxt. Ex.: `products`, `product_variations`, `RegisterSaleAction`, `stock_quantity`.
- **Interface 100% em português:** todo texto visível ao usuário final (labels, mensagens de validação, mensagens de erro, textos de botão, conteúdo do comprovante) é em português — mensagens customizadas em `FormRequest::messages()` e nas exceptions de domínio são obrigatórias.
- Não misturar os dois: nenhum nome de coluna/model em português, nenhuma mensagem de UI em inglês.

## Backend (Laravel)

Para evitar "Fat Controllers" e seguir os princípios SOLID:

1. **Actions:** a regra de negócio não fica no Controller nem em Services genéricos — fica em **uma classe por operação de negócio**, com método `execute()`. O Controller apenas recebe a requisição (já validada por um Form Request), chama a Action correspondente e retorna a resposta via API Resource.
   - Exemplos já implementados: `LoginAction`, `UpdateStoreSettingAction`. Próximas: `RegisterSaleAction`, `OpenCashRegisterAction`, `CloseCashRegisterAction`, `AdjustStockAction`, `RegisterStockEntryAction`.
2. **Form Requests:** toda validação de dados de entrada é feita em Form Requests do Laravel, com mensagens de erro em português via `messages()`. Organizados por domínio (`app/Http/Requests/Auth/`, `app/Http/Requests/StoreSetting/`...).
3. **API Resources:** os dados retornados para o frontend são formatados usando API Resources (ex.: `UserResource`, `StoreSettingResource`), ocultando campos sensíveis e padronizando o payload (envelope `data`).
4. **Enums nativos do PHP** (`app/Enums/`): valores fechados de domínio viram backed enums com método `label()` retornando o texto em português (ex.: `UserRole::Cashier->label()` → `"Caixa"`). O cast no Model garante type-safety de ponta a ponta.
5. **Soft Deletes:** registros de cadastros principais (produtos, variações, clientes, usuários, categorias) não são deletados fisicamente — usam a trait `SoftDeletes` para preservar histórico e integridade referencial de vendas passadas.
6. **Registro único (singleton de configuração):** tabelas de exatamente uma linha (`store_settings`) expõem um método estático `Model::current()` que retorna o registro id=1, criando-o com defaults explícitos na primeira leitura. Detalhes que importam (aprendidos na Sprint 0): setar `id` fora do mass assignment; passar os defaults booleanos no construtor (o `save()` não recarrega defaults de coluna no objeto em memória); zerar `wasRecentlyCreated` para o `JsonResource` não responder `201` num GET.
7. **Regra de ouro — transações e concorrência:** toda operação que mexe em **estoque** e/ou **caixa** roda dentro de `DB::transaction()` com `lockForUpdate()` nas linhas afetadas (ex.: `product_variations` durante uma venda), para evitar condições de corrida entre terminais (ex.: dois terminais vendendo a última unidade do mesmo item simultaneamente).

### Tratamento de erros
- **API-only, sem redirects:** em `bootstrap/app.php`, `redirectGuestsTo(fn () => null)` e `shouldRenderJsonWhen(fn () => true)`. Sem isso, o Laravel tenta redirecionar guests para uma rota `login` inexistente e responde 500 em vez de 401 (bug real encontrado na Sprint 0).
- **Exceptions de domínio** para regras de negócio que impedem uma operação, cada uma com seu próprio método `render()` devolvendo o JSON padronizado e o status correto — o controller não faz try/catch.
  - Implementadas: `InvalidCredentialsException` (422), `InactiveUserException` (403). Próximas: `CashRegisterClosedException`, `CashRegisterAlreadyOpenException` (409).
  - Nota: estoque **negativo é permitido** por decisão de produto — portanto não existe uma `InsufficientStockException` bloqueante; a baixa ocorre mesmo abaixo de zero, e isso é uma decisão consciente, não uma omissão.
- **Formato de erro padronizado** na API:
  ```json
  {
    "message": "Não é possível vender sem um caixa aberto.",
    "errors": { "cash_register": ["..."] }
  }
  ```
  Mesma estrutura para erros de validação (422) e exceptions de domínio (403/409/422, conforme o caso), para o frontend tratar de forma uniforme.
- Códigos HTTP: `422` validação e regras de negócio violadas, `401` não autenticado, `403` sem permissão/usuário inativo, `404` recurso inexistente, `409` conflito de estado (ex.: abrir caixa já aberto), `500` reservado para falhas não tratadas (logadas, nunca detalhadas ao cliente).
- **Autorização em camadas:** Policies (`can:` na rota ou `authorize()` no Form Request) para regras por recurso; middleware `role:admin,cashier` para bloqueio grosso por papel. Resposta de negação: 403 com mensagem em português.
- No frontend, o composable `useApiError` traduz essas respostas (`parse()`, `firstFieldError()`) — nenhuma tela interpreta erro de API por conta própria.

### Estrutura de pastas (Laravel — real, criada na Sprint 0)
```
backend/
  app/
    Actions/            # Um diretório por domínio: Auth/, StoreSetting/, Sales/...
    Enums/              # UserRole, futuros: CashOperationType, SaleStatus...
    Exceptions/         # Exceptions de domínio, cada uma com render() próprio
    Http/
      Controllers/Api/  # Controllers finos, um por recurso
      Middleware/       # EnsureUserHasRole (alias 'role')
      Requests/         # Form Requests, por domínio
      Resources/        # API Resources
    Models/
    Policies/           # Autorização por papel (admin/cashier/seller)
  bootstrap/app.php     # statefulApi, alias de middleware, config de exceptions
  database/
    factories/          # Com states semânticos: ->admin(), ->cashier(), ->inactive()
    migrations/
    seeders/            # DatabaseSeeder cria o admin inicial
  routes/
    api.php             # Todas as rotas da API (prefixo /api automático)
    web.php             # Só a rota do comprovante térmico
  tests/
    Feature/            # Um diretório por domínio: Auth/, StoreSetting/, Backup/...
    Unit/
```

## Frontend (Nuxt 4 / Vue.js)

1. **Composition API (`<script setup lang="ts">`):** sintaxe moderna do Vue 3 para componentes lógicos e limpos, sempre com TypeScript.
2. **Componentização (Dumb/Smart):** componentes de UI puros (`components/ui/` — `BaseButton`, `BaseInput`...) ficam separados de componentes de regra de negócio (um diretório por domínio).
3. **Composables:** lógica reutilizável vai para `useSomething.ts`. Dois já são fundacionais:
   - `useApi()` — **único** cliente HTTP do app: `$fetch` pré-configurado com `baseURL`, `credentials: 'include'` e injeção automática do header `X-XSRF-TOKEN` lido do cookie. Nenhuma tela chama `$fetch` cru.
   - `useApiError()` — normaliza o formato de erro padronizado da API.
4. **Pinia:** um store por domínio (`useAuthStore`; futuros: `useCashRegisterStore`, `useCartStore`) — não centralizar tudo em um único store monolítico.
5. **Middleware global de autenticação** (`middleware/auth.global.ts`): em toda navegação, garante usuário carregado (`/api/me`), manda guest para `/login` e usuário logado para fora dela.
6. **TypeScript fixado em `^5.x`:** o `vue-tsc` atual não suporta TypeScript 7 (`ERR_PACKAGE_PATH_NOT_EXPORTED`). Não subir de major sem verificar compatibilidade.

### Estrutura de pastas (Nuxt 4 — real, criada na Sprint 0)
No Nuxt 4 o código da aplicação vive dentro de `app/`:
```
frontend/
  app/
    assets/css/main.css   # @import "tailwindcss"
    components/
      ui/                 # BaseButton, BaseInput — sem regra de negócio
      ...                 # um diretório por domínio (sales/, products/...)
    composables/
      useApi.ts           # cliente HTTP único + ensureCsrfCookie()
      useApiError.ts
    layouts/
      default.vue         # header com usuário/logout
      # pos.vue futuro — layout dedicado ao PDV (tela cheia, foco em teclado)
    middleware/
      auth.global.ts
    pages/                # roteamento por arquivo
    stores/               # Pinia, um arquivo por domínio
    app.vue               # NuxtLayout + NuxtPage
  nuxt.config.ts          # ssr:false, @pinia/nuxt, tailwind, runtimeConfig.public.apiBase
  .env.example            # NUXT_PUBLIC_API_BASE para dev local
```

## Testes automatizados
Cobertura **ampla**, não restrita a caminhos críticos:

- **Backend:** Feature tests (PHPUnit) para todos os endpoints de CRUD (fluxos de sucesso, validação e autorização — incluindo o papel que **não** pode) e para todas as Actions, incluindo os fluxos de erro (ex.: venda sem caixa aberto). Rodam contra o banco `comercial_testing` (PostgreSQL real, não sqlite — mesmas semânticas de lock/transação da produção).
- **Padrões específicos aprendidos na Sprint 0:**
  - O `Tests\TestCase` base injeta o header `Referer` com a origem da app — simula o caminho stateful do Sanctum (cookie/sessão), o mesmo que a SPA usa em produção.
  - `RefreshDatabase` (transação revertida) é o padrão. **Exceção:** testes que disparam processos externos com conexão própria ao banco (ex.: `pg_dump` no teste de backup) usam `DatabaseMigrations` — dados presos numa transação de teste não commitada são invisíveis para o processo externo.
  - Factories com states semânticos (`User::factory()->admin()`, `->cashier()`, `->inactive()`) em vez de arrays soltos nos testes.
  - O teste de backup (`BackupRestoreTest`) **restaura** o dump num banco descartável e confere os dados — testar o restore, não só o backup, é regra inegociável do projeto.
- **Actions financeiras** (`RegisterSaleAction`, caixa, estoque) recebem também testes de concorrência (`lockForUpdate`).
- **Frontend:** testes unitários (Vitest) para composables com lógica não-trivial (cálculo de carrinho, máscaras, tratamento de erro) e stores Pinia com regras de negócio (ex.: `useCartStore`) — entram a partir da Sprint em que essa lógica surgir.
- Cada sprint do backlog (`05-sprints.md`) inclui a tarefa de teste como parte da entrega, não como item separado ao final.
