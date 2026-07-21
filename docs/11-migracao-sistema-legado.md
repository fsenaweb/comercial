# Migração do Sistema Legado (Firebird → Sistema Comercial)

Levantamento e decisões de arquitetura para importar dados reais da loja de
um ERP legado (Delphi + Firebird) para este sistema. **Documento vivo,
trabalho em andamento** — iniciado em 2026-07-18 a partir de um backup
(`.fbk`) fornecido pelo usuário. Nenhum código de importação foi escrito
ainda; este documento registra o que já foi investigado/decidido para não
perder o trabalho entre sessões.

## Contexto

A loja usa hoje um ERP comercial completo em Delphi/Firebird (com módulos
fiscais — NF-e, NFC-e, MDF-e, CT-e, SPED — que este projeto **não** tem,
por escopo: aqui não emitimos documento fiscal). A loja continuará operando
nesse sistema antigo até o dia da virada para o novo sistema.

**Escopo definido pelo cliente (2026-07-18, decisão final):** o cliente vai
começar a operação **do zero** no sistema novo — não quer histórico de
vendas, movimentação de estoque, caixa nem crediário do legado. O único
interesse real é aproveitar os **cadastros de produto e cliente**, que são
grandes demais (13.280 produtos) para redigitar na mão. Toda a investigação
anterior sobre `VENDAS_MASTER`/`MOVIMENTO_ESTOQUE`/`CAIXA`/`CRECEBER` fica
**descartada** (registrada só como nota histórica no fim deste documento,
caso o escopo mude de novo no futuro).

## Escopo final da importação

1. **Produtos** (`PRODUTO` → `products` + `product_variations`), incluindo:
   - Cadastro completo (nome, código de barras, categoria, marca, unidade,
     custo, margem, preço de venda, preço/qtd. de atacado).
   - **Estoque atual** (`QTD_ATUAL`) como estoque inicial, via
     `stock_movements` tipo `adjustment` / origem "estoque inicial" — mesmo
     padrão já usado no cadastro manual de produto
     (`CreateProductVariationAction`, ver `02-design-patterns.md`).
   - Produtos inativos no legado entram como `products.active = false`
     (preserva o cadastro sem poluir a busca do PDV).
2. **Clientes** (`PESSOA` → `customers`, filtrando `CLI = 'S'`).
3. **Dependências mínimas de produto** (não é entrega própria, só o que
   `products`/`product_variations` exigem via FK): `GRUPO` → `categories`,
   `MARCA` → `brands`, `UNIDADE` → `units`.

**Fora do escopo** (descartado, não implementar): `VENDAS_MASTER`,
`VENDAS_DETALHE`, `VENDAS_FPG`, `MOVIMENTO_ESTOQUE` (histórico de
movimentações — só o saldo atual importa), `CAIXA`, `RESUMO_CAIXA`,
`CRECEBER`, `CRRECEBIMENTO`, `USUARIOS`, `VENDEDORES`, `FORMA_PAGAMENTO`
(o sistema novo já tem seed próprio de formas de pagamento e os usuários
serão cadastrados do zero pelo admin). Fornecedores também ficam de fora —
o legado não tem nenhum fornecedor cadastrado (`FORN = 'S'` só ocorre 0
vezes em `PESSOA`).

## Formato do backup e como inspecioná-lo

O usuário fornece um `.zip` contendo um `.fbk` (formato nativo de backup do
Firebird, gerado por `gbak -b`). Para inspecionar/restaurar:

```bash
# Extrair o .fbk do zip
unzip -o backup.zip -d /tmp/fb-restore

# Subir um Firebird 2.5 temporário via Docker
docker run -d --name fb-legacy-restore \
  -e ISC_PASSWORD=masterkey \
  -e EnableLegacyClientAuth=true \
  -v /tmp/fb-restore:/backup \
  jacobalberty/firebird:2.5-sc

# Restaurar o .fbk para um banco consultável (.fdb)
docker exec fb-legacy-restore /usr/local/firebird/bin/gbak -c -v \
  /backup/<arquivo>.fbk /firebird/data/legacy.fdb \
  -user SYSDBA -password masterkey

# Consultar via isql
docker exec fb-legacy-restore /usr/local/firebird/bin/isql \
  -user SYSDBA -password masterkey /firebird/data/legacy.fdb
```

**Charset:** os dumps de texto vêm com acentuação corrompida
(`CART�O`, `DEP�SITO`) porque a conexão de teste não declarou o charset de
origem — bancos Firebird de sistemas Delphi antigos costumam usar
`WIN1252` (a confirmar antes da extração real). O script de extração
precisa declarar o charset correto na conexão, senão os textos extraídos
saem ilegíveis.

## Volume de dados relevante (backup de 2026-07-18)

| Tabela legada | Registros | Observação |
|---|---:|---|
| `PRODUTO` | 13.280 | catálogo completo, **sem grade/variação** (`PRODUTO_GRADE` = 0 linhas) |
| `PESSOA` | 44 | filtrar `CLI = 'S'` — todas as 44 são cliente; 0 fornecedor/funcionário |
| `GRUPO` | 2 | categorias |
| `MARCA` | 5 | marcas |
| `UNIDADE` | 1 | unidades |

## Arquitetura de importação decidida

**Simplificação decidida em 2026-07-18** (depois do teste real em dev): a
extração e a importação rodam **na mesma máquina**, seja qual for — dev
agora, o PC da loja no dia da virada. Não existe mais um passo de
"transportar CSV entre computadores": o script de extração usa só
`docker run` avulso (não entra no `docker-compose.yml` permanente, não
deixa nenhum serviço no ar depois de terminar), então rodá-lo direto no PC
da loja não tem o custo que se imaginava antes (inflar a stack
permanente) — só baixa a imagem do Firebird uma vez (fica em cache local)
e sobe/derruba o container sozinho.

**Passo a passo no PC da loja, no dia da virada:**

```bash
# 1. Copiar o .fbk pra dentro do projeto (raiz ou qualquer pasta — não faz
#    diferença, o script recebe o caminho como argumento)
cp /caminho/do/backup.fbk .

# 2. Extrair pros CSVs (gera em backend/storage/legacy-import/ por padrão,
#    já coberto pelo .gitignore — nunca vai pro Git)
scripts/legacy-import/export-firebird.sh backup.fbk

# 3. Importar pro Postgres, dentro do container php-fpm
docker compose exec php-fpm php artisan legacy:import storage/legacy-import
```

O comando `legacy:import` já está versionado no repositório — chega no PC
da loja via `git pull`/`deploy.bat` normalmente, junto com o resto do
código. Ordem interna da importação, dentro de uma única
`DB::transaction()` (tudo ou nada — se um produto falhar, nada é
gravado): `GRUPO`→`categories`, `MARCA`→`brands`, `UNIDADE`→`units`,
`PRODUTO`→`products`+`product_variations` (+ `stock_movements` de estoque
inicial), `PESSOA`(`CLI='S'`)→`customers`.

**Repetibilidade (implementado e testado com o dump real):** perto da data
de corte, se o `.fbk` estiver mais fresco (produtos/clientes podem ter
mudado desde o backup usado em desenvolvimento), é só repetir os passos
1-3 com o arquivo novo — seguro rodar quantas vezes for preciso: produtos
são casados por `product_code` (`REFERENCIA`, atualiza em vez de duplicar;
diferença de estoque vira um novo `stock_movement` de ajuste com a
diferença) e clientes por `document` quando presente.

**Validado com o dump real completo em desenvolvimento (2026-07-18):**
banco resetado (`migrate:fresh --seed`, mantém só `admin@loja.local`),
importação rodada do zero — **13.278 produtos criados, 44 clientes
criados, em 27 segundos**. Achado real nesse teste: 148 dos 13.280
produtos têm `MARGEM` com valor absurdo no legado (erro de digitação
antigo, ex.: 9.588.000%) — estourava o limite do campo `markup`
(`decimal(7,2)`, máx. ~99999.99) e derrubava a importação inteira (a
transação não commitava nada). Corrigido: `ImportLegacyDataCommand`
normaliza `markup` fora da faixa para `null` em vez de deixar o banco
rejeitar a linha.

## Mapeamento de campos

### `GRUPO` → `categories`
Mapeamento direto: `CODIGO` (referência), `DESCRICAO` → `name`.

### `MARCA` → `brands`
Mapeamento direto: `CODIGO` (referência), `DESCRICAO` → `name`.

### `UNIDADE` → `units`
Mapeamento direto: `CODIGO` → `abbreviation`/`name` conforme o campo
existente em `units` (só 1 linha no legado: `UN`/`UNIDADE`).

### `PRODUTO` → `products` + `product_variations`
Campos relevantes (o restante são campos fiscais que não se aplicam a este
sistema — NCM, CFOP, ICMS, PIS/COFINS, SPED etc., **ignorar**):

| Legado (`PRODUTO`) | Novo sistema |
|---|---|
| `CODIGO` | `product_variations.legacy_code` (preservado à parte — ver decisão abaixo) |
| `CODBARRA` | `product_variations.ean_gtin` (`'SEM GTIN'` ou vazio → `null`) |
| `DESCRICAO` | `products.name` |
| `REFERENCIA` | `product_variations.product_code` — **prioridade do cliente** (2026-07-18): é o código que ele usa para conferência cruzada com o sistema fiscal separado que já possui. 100% preenchida (13.280/13.280), com 387 colisões desempatadas anexando `CODIGO` |
| `GRUPO` (FK) | `categories` (via mapeamento já importado) |
| `FK_MARCA` | `brands` (via mapeamento já importado) |
| `UNIDADE` | `units` (via mapeamento já importado) |
| `PR_CUSTO` | `product_variations.cost_price` |
| `MARGEM` | `product_variations.markup` |
| `PR_VENDA` | `product_variations.sale_price` |
| `QTD_ATUAL` | estoque inicial, via `stock_movements` tipo `adjustment` |
| `QTD_MIN` | `product_variations.min_quantity` |
| `PRECO_ATACADO` / `QTD_ATACADO` | `product_variations.wholesale_price` / `wholesale_min_qty` |
| `ATIVO` (`S`/`N`) | `products.active` |
| `SERVICO` (`S`/`N`) | `products.type = service` |

**Decisão do cliente sobre `REFERENCIA` vs. `CODIGO` (2026-07-18):** ele
usa os dois no dia a dia — a `REFERENCIA` tem prioridade e precisa estar
visível nos relatórios de venda para conferência cruzada com o sistema
fiscal que já usa (aplicativo à parte, não é o ERP legado). Implementado:
`REFERENCIA` vira `product_code` (já era o único campo de código do
sistema, aparece em toda tela/relatório que lista produto); `CODIGO`
passou a ser preservado num campo novo, `product_variations.legacy_code`
(nullable, adicionado editando a migration original de
`product_variations` — nada em produção ainda).

**Segunda rodada da decisão (mesmo dia):** o usuário inicialmente pediu um
terceiro campo (`reference_code`, só informativo) para produtos novos, mas
reconsiderou — mais simples reaproveitar o `legacy_code` já criado como o
"segundo código" permanente do sistema, não só um artefato da migração.
Implementado: `legacy_code` virou campo editável (rótulo "Código Interno")
nos dois formulários completos de produto (`pages/products/index.vue` —
modal "Novo Produto"/"Editar Produto" e modal "Variações"; **não** no
Cadastro Rápido, que continua deliberadamente minimalista e não expõe nem
o `product_code` manualmente). Também exibido na listagem de produtos
(coluna "Cód. Interno") e nos relatórios "Nível de Estoque", "Valor do
Estoque", "Vendas por Produto" e "Lucro Bruto por Produto" (coluna "Código
Interno", ao lado da coluna "Código" que já existia/ganhou nesta sessão).
Não é buscável no PDV — decisão do usuário, `product_code`/código de
barras continuam sendo a única busca de produto na venda.

### `PESSOA` (filtro `CLI = 'S'`) → `customers`
44 registros, todos cliente. Campos principais: `RAZAO`/`FANTASIA` (nome),
`CNPJ` (CPF ou CNPJ, mesmo campo — checar formato antes de gravar),
endereço (`ENDERECO`/`NUMERO`/`BAIRRO`/`CIDADE`/`UF`/`CEP`), contato
(`FONE1`/`CELULAR1`/`EMAIL1`), `ATIVO`.

## Achado de escala: catálogo real quebrava o sistema (2026-07-18)

Depois de fechar a importação, foi feito um teste real completo em
desenvolvimento (`migrate:fresh --seed` + `legacy:import` com o dump real —
13.278 produtos, 44 clientes) para validar que a migração funciona de
ponta a ponta antes do dia da virada. Esse teste — não a importação em si —
revelou que **o sistema nunca tinha sido usado com um catálogo desse
tamanho** (só com os ~24 produtos de demonstração das sprints anteriores) e
quebrava em várias telas:

- **`GET /products`** carregava os 13.278 produtos de uma vez (com todos os
  relacionamentos), gerando ~14MB de JSON e estourando o limite de memória
  do PHP (128MB) — 500 na tela de Produtos.
- **PDV** e **Etiquetas** tinham o mesmo problema: carregavam o catálogo
  inteiro no navegador para montar um índice de busca local (`Map`)
  usado tanto na leitura de código de barras quanto na busca por nome —
  o gargalo mais crítico, por ser a tela usada em toda venda.
- Faltavam índices no banco em `ean_gtin` e `products.name`/`category_id`
  (Postgres não indexa FK automaticamente, diferente do MySQL).

**Corrigido nesta mesma sessão** (fora do escopo original da migração, mas
bloqueava validar a migração de verdade):
- `GET /products` paginado (`page`/`per_page`/`search`), com um endpoint
  novo `GET /products/summary` para os cards agregados (estoque
  total/alertas), que antes eram calculados no navegador iterando sobre o
  catálogo inteiro já carregado.
- Dois endpoints novos — `GET /product-variations/lookup?code=` (match
  exato por código de barras/código do produto, usado no scanner) e
  `GET /product-variations/search?q=&limit=` (autocomplete por nome/código,
  limitado a poucos resultados) — substituindo o `Map` client-side no PDV,
  no seletor F2 e nas Etiquetas (`useProductVariationSearch.ts` reescrito).
- Índices adicionados: `product_variations.ean_gtin`, `products.name`,
  `products.category_id` (migrations originais editadas, nada em produção
  ainda).
- Aviso de produto duplicado (cadastro/cadastro rápido) também migrado de
  comparação client-side para busca no servidor, pelo mesmo motivo.
- **Bugs reais pegos só por causa desse teste com dado real** (não
  apareceriam com 24 produtos de demonstração):
  - `->constrained()->index()` encadeado na mesma coluna gera nome de
    constraint de FK errado no Postgres (virava `"1"` em vez de
    `products_category_id_foreign`) — corrigido separando em duas
    chamadas (`->constrained()` + `$table->index('category_id')` depois).
  - Dois `watch()` novos (aviso de produto duplicado) foram declarados
    antes de `quickForm`/`modalForm` existirem no `<script setup>`,
    causando `Cannot access 'quickForm' before initialization` — corrigido
    reordenando a declaração para depois do `reactive(emptyQuickForm())`.
- Testes novos: `tests/Feature/Product/ProductVariationSearchTest.php`
  (lookup exato, busca, limite, exclusão de produto inativo) e
  pontos de paginação/busca/resumo agregados em `ProductTest.php`.

**Validado de ponta a ponta no navegador** (PDV, Produtos, Etiquetas) com
os 13.278 produtos reais importados — ver `05-sprints.md`/histórico de
sessão para o passo a passo completo da validação.

## Pendências / próximos passos

- [x] **Charset confirmado: `WINDOWS-1252`** (2026-07-18). O banco em si
      tem `RDB$CHARACTER_SET_NAME = NONE` (Firebird não faz nenhuma
      transliteração — devolve os bytes brutos como estão gravados,
      independente do charset declarado na conexão do `isql`). Decodificando
      os bytes brutos manualmente: `0xC3` → `Ã`, `0xC9` → `É`, batendo
      exatamente com `"CARTÃO"`/`"CRÉDITO"` — não é dado corrompido, é
      Windows-1252 puro. O script de extração precisa converter explicitamente
      (`iconv -f WINDOWS-1252 -t UTF-8`, ou equivalente na linguagem usada)
      ao gerar os CSVs — não basta declarar charset na conexão do Firebird.
- [x] **`REFERENCIA`/`CODBARRA` confirmados (2026-07-18):** 13.280 de
      13.280 produtos têm os dois campos preenchidos — sem lacuna, não
      precisa gerar código interno para produto nenhum.
- [x] **`scripts/legacy-import/export-firebird.sh` escrito e testado
      (2026-07-18)** contra o `.fbk` real (13.280 produtos, 44 pessoas) —
      roda o Firebird temporário via `docker run` direto, restaura o
      backup, exporta `GRUPO`/`MARCA`/`UNIDADE`/`PRODUTO`/`PESSOA` (só
      `CLI='S'`) para CSV pipe-delimitado, convertendo Windows-1252 → UTF-8
      com `iconv`. Uso: `scripts/legacy-import/export-firebird.sh
      <arquivo.fbk> [pasta-de-saida]` (padrão:
      `storage/legacy-import/`, já no `.gitignore` do backend).
- [x] **Comando `php artisan legacy:import {path}` escrito**
      (`backend/app/Console/Commands/ImportLegacyDataCommand.php`).
      Idempotente: produtos casados por `product_code` (atualiza em vez de
      duplicar; diferença de estoque vira um `stock_movement` de ajuste
      novo), clientes casados por `document` quando presente. Toda a
      importação roda dentro de uma única `DB::transaction()`.
      **Achados tratados durante a implementação:**
  - `REFERENCIA` tinha 387 colisões (12.893 valores distintos em 13.280
    produtos) — resolvido desempatando com o `CODIGO` legado como sufixo
    (`product_code` vira `REFERENCIA-CODIGO` só nas colisões).
  - Duas linhas de produto eram resíduo do módulo de restaurante do ERP
    legado (`TAXA DE COUVERT`/`TAXA DE SERVIÇO`, `CODIGO` 999999/999998) —
    filtradas explicitamente, não fazem sentido para uma loja de autopeças/
    ferragens.
  - `CODBARRA = 'SEM GTIN'` (4.671 produtos, 35% do catálogo) é um
    placeholder literal do legado, não um código de barras real — tratado
    como `ean_gtin = null`.
  - **Mudança de sistema (não só do importador):** só 3 dos 44 clientes do
    legado têm celular cadastrado. `customers.mobile_phone` era
    obrigatório em todo o sistema novo (schema + validação); decisão do
    usuário foi tornar opcional — `mobile_phone` agora nullable na
    migration original de `customers`, em `StoreCustomerRequest`/
    `UpdateCustomerRequest`, e no frontend (`customers.vue`, `pos.vue`,
    incluindo um null-safety fix na busca de cliente do PDV que fazia
    `.includes()` num valor potencialmente nulo).
- [x] Testes: `backend/tests/Feature/LegacyImport/LegacyImportTest.php`
      (10 testes, fixtures pequenas em `tests/Fixtures/legacy-import/`) —
      mapeamento de categoria/marca/unidade/produto, `SEM GTIN` → null,
      desempate de `REFERENCIA` duplicada, filtro dos produtos de
      restaurante, produto tipo serviço, `stock_movement` de estoque
      inicial, cliente sem celular, cliente pessoa jurídica, e rodar o
      comando duas vezes sem duplicar nada. Suíte completa do backend:
      357 testes, tudo verde.

## Nota histórica (descartado — escopo reduzido em 2026-07-18)

A investigação inicial cobriu também vendas, caixa, estoque histórico e
crediário, incluindo um achado relevante caso esse escopo volte a ser
considerado no futuro: a tabela `CAIXA` do legado (438 linhas) **não** é
uma sessão de caixa como a deste sistema — é um livro-razão financeiro
genérico só com eventos de fechamento (`HISTORICO` no formato
`"FECHAMENTO DO CX:<operador>-<data> <hora>"`), sem abertura/valor inicial
estruturado. A tabela `RESUMO_CAIXA` (2.282 linhas) parecia mais granular e
não chegou a ser investigada a fundo. Essas tabelas (mais `VENDAS_MASTER`
35.529 linhas, `VENDAS_DETALHE` 77.733, `MOVIMENTO_ESTOQUE` 103.604,
`CRECEBER` 3.010) **não serão importadas** — registrado aqui só para não
repetir a investigação caso o cliente peça esse histórico mais adiante.
