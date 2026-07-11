# Arquitetura — Sistema de PDV/Gestão (uso interno, 1 loja)

**Status:** v3 — spec fechada. Pronta para iniciar a Fase 0 mediante seu "vai".
**Origem:** engenharia reversa *funcional* do AppLoja (76 screenshots, via OCR). Não houve acesso a código, banco ou API — logo, modelo de dados e regras abaixo são **proposta fundamentada**, não fatos do sistema original.

---

## 1. Decisões travadas

| Tema | Decisão |
|---|---|
| Objetivo | Ferramenta interna, **1 loja própria** (sem multi-tenancy, sem SaaS) |
| Implantação | **On-premise**, na LAN da loja, sem depender de internet externa para operar |
| Stack | **Laravel (API) + Nuxt 3 (SPA, `ssr:false`)** — desacoplado. Sua escolha: fluência e manutenção por você > topologia enxuta |
| Banco | **PostgreSQL** |
| Deploy | **Docker Compose** na máquina da loja |
| Comprovante | **Impressão térmica 58/80mm**, documento **NÃO fiscal** |
| Arquitetura de código | **Pragmática**: Actions + Form Requests + Eloquent. **Sem DDD/hexagonal** (over-engineering para este caso) |
| Servidor | **Linux** (Docker como serviço, sobe no boot). Windows fica como alternativa futura |
| Modelo de caixa | **Um caixa único** compartilhado pela loja. Vendas atribuídas ao **vendedor** e filtráveis por ele |
| Identidade | **Atendente = vendedor = usuário que loga.** Sem tabela `sellers` separada (YAGNI) |
| Terminais | 1 servidor + 3 terminais (navegador), **expansível** |
| Mobile | Terminal de consulta via WiFi — **roadmap futuro** (exige HTTPS local p/ câmera; não imprime) |

---

## 2. Escopo

### Dentro (o que constrói)
- Cadastros: produtos + **variações/SKU**, categorias, clientes, vendedores, (fornecedores)
- Usuários e permissões **simples** (papéis: admin, caixa, vendedor)
- Controle de estoque por variação
- Caixa (abertura, operações, fechamento)
- PDV / venda + comprovante térmico
- Relatórios (vendas, estoque)
- Histórico de operações
- Backup automático (local + Google Drive)

### Fora (não construir — eram do produto comercial, não do seu caso)
- Multi-tenancy, planos/assinatura, cobrança, suporte pago
- Emissão fiscal (NFC-e/NFe) — outro sistema faz isso
- Loja virtual / sincronização de catálogo
- Integração com IA/MCP
- Onboarding, landing de marketing

### Fase 2 (opcional, depois do núcleo)
- Crediário / contas a receber com parcelas
- Contas a pagar, despesas
- Entradas de estoque via XML

---

## 3. Arquitetura de implantação (on-premise LAN)

```
        Loja (rede interna 192.168.x.x)
 ┌───────────────────────────────────────────────┐
 │  Máquina servidor (Windows/Linux)              │
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
   │ Terminal PDV│ Back-office │  (navegadores na mesma rede)
   └─────────────┴─────────────┘

   Backup → cópia local (drive/pasta) + Google Drive quando houver internet
```

- **`restart: unless-stopped`** nos containers → sobe sozinho ao ligar a máquina.
- Terminais não instalam nada: só abrem o navegador no IP do servidor.
- Latência é de LAN (rápida). Não há dependência de internet para vender.

---

## 4. Stack e camadas de código

- **Front:** Nuxt 3 em **modo SPA** (`ssr: false`) + Tailwind (reproduz o visual translúcido). Build estático servido pelo nginx.
- **Back:** Laravel 11 como **API REST/JSON**. Sem views (exceto a rota de comprovante — ver seção 8).
- **Auth:** **Laravel Sanctum** (token ou cookie SPA). CORS liberado só para a origem do front na LAN.
- **Comunicação:** nginx serve o Nuxt e faz proxy de `/api/*` para o Laravel (mesma origem → evita dor de CORS).
- **Custo aceito da escolha:** 2 apps, 2 serviços no Compose, auth por token. Gerenciável on-prem; foi trade decidido a favor da sua fluência em Nuxt.
- **Organização do back (SOLID na prática, sem cerimônia):**
  - `Controllers` finos → só orquestram
  - `Form Requests` → validação
  - **`Actions`** → 1 classe por operação de negócio: `RegistrarVendaAction`, `AbrirCaixaAction`, `FecharCaixaAction`, `RegistrarEntradaEstoqueAction`
  - `Models` Eloquent → o próprio "repository" (não abstrair)
- **Regra de ouro:** operação que mexe em estoque + caixa roda dentro de `DB::transaction()` com `lockForUpdate()`.

**Não fazer:** repositories abstraindo Eloquent, interface para tudo, CQRS, event sourcing, bounded contexts.

---

## 5. Modelo de dados (proposta)

Núcleo:

- **users** — nome, email, senha, **role** (`admin` / `caixa` / `vendedor`), **comissao_percent**, ativo
  *(atendente = vendedor = usuário que loga; sem tabela `sellers` separada)*
- **customers** — nome, celular, telefone, email, cpf_cnpj, is_pj, data_nascimento, endereço (cep, logradouro, número, complemento, bairro, cidade, uf), observação
- **suppliers** — razão_social, nome_fantasia, cnpj, contato, endereço *(v1 opcional)*
- **products** — nome, tipo (produto/serviço/kit), unidade_id, localização, category_id, subcategory_id, brand_id, campos_fiscais (nullable)
- **product_variations (SKU — estoque vive aqui)** — product_id, cor, tamanho, ean_gtin, codigo_produto, custo, markup, preco_venda, qtd_atual, qtd_min, qtd_max
- **categories / subcategories / brands / units** — cadastros mestres

Venda e caixa:

- **cash_registers** — data_abertura, valor_abertura, status (aberto/fechado), valor_fechamento, aberto_por (user_id), observação *(um aberto por vez na loja)*
- **cash_operations** — cash_register_id, user_id (quem lançou), tipo (entrada/saída), origem (venda/sangria/reforço/ajuste), payment_method_id, valor, observação
- **payment_methods** — nome, ativo_no_pdv
- **sales** — numero, customer_id (nullable), **seller_id → users.id**, cash_register_id, subtotal, desconto, total, payment_method_id, observações, status, criado_em
- **sale_items** — sale_id, product_variation_id, quantidade, preco_unitario, desconto, total
- **stock_movements** — product_variation_id, tipo (entrada/saída/ajuste/venda), quantidade, origem, referência_id, criado_em

Fase 2: accounts_receivable, installments, accounts_payable, expenses, stock_entries.

---

## 6. Regras de negócio

Legenda: **[F]** visto na tela · **[I]** inferido · **[✓]** decidido.

- **[✓]** Venda **exige caixa aberto** — sem caixa da loja aberto, ninguém vende.
- **[✓]** Venda **permite estoque negativo** — baixa mesmo abaixo de zero e registra o movimento.
- **[✓]** **Um caixa único** por vez; toda venda dos terminais atribui-se a ele e ao **vendedor** logado.
- **[✓]** No PDV, **vendedor pré-selecionado pelo login**, trocável; obrigatório é configurável.
- **[I]** Cliente **opcional** na venda.
- **[F]** Estoque **por variação/SKU**.
- **[F]** Desconto por item **e** no total.
- **[F]** Comprovante gerado ao finalizar.

---

## 7. Fluxo técnico da venda (Fase 2 — o coração)

1. Operador loga no terminal → abre PDV.
2. Sistema verifica **caixa aberto** (abre automático se configurado).
3. Adiciona itens (busca por código de barras / nome) → carrinho.
4. Informa quantidades, descontos, vendedor (se obrigatório), cliente (opcional).
5. Escolhe forma de pagamento → finaliza.
6. **`RegistrarVendaAction` dentro de `DB::transaction()`:**
   - `lockForUpdate()` nas variações → evita venda dupla do último item
   - valida estoque → cria `sale` + `sale_items`
   - gera `stock_movements` (saída) e decrementa `qtd_atual`
   - registra `cash_operation` (entrada) no caixa
7. Renderiza a **view de comprovante** → impressão térmica.

---

## 8. Comprovante térmico (58/80mm)

- **Rota Blade no Laravel** (`/vendas/{id}/comprovante`) renderizada no servidor — o front (Nuxt) abre essa URL em nova janela e dispara `window.print()`. Mais robusto para impressão térmica do que gerar o cupom dentro da SPA.
- CSS `@page { size: 80mm auto }`, impresso pelo driver da impressora instalada no SO.
- Marcado **"DOCUMENTO NÃO FISCAL"** no topo (evita confusão com cupom fiscal).
- Conteúdo: cabeçalho da loja, nº da venda, data/hora, itens (nome, qtd, unit, total), subtotal, desconto, total, forma de pgto, vendedor, cliente (se houver), observações.
- **Sugestão:** imprimir um **código de barras/QR do nº da venda** → o operador do caixa fiscal localiza a venda rápido no outro sistema em vez de redigitar tudo.
- **Risco conhecido:** impressão térmica via navegador é historicamente chata. Plano B se o driver do Windows não cooperar: **QZ Tray** ou envio **ESC/POS** por um helper local.

---

## 9. Backup e recuperação (crítico)

- Task agendada (`spatie/laravel-backup`) → dump do PostgreSQL (`pg_dump`).
- **Camadas:** (1) cópia local sempre (pasta/HD externo montado no container); (2) upload para **Google Drive quando houver internet** (rclone ou API Drive).
- **Regra inegociável:** testar o **restore**, não só o backup. Backup nunca restaurado = backup que você não tem.
- Retenção: manter N dias/versões, limpar antigos.

---

## 10. Riscos e mitigações

| Risco | Impacto | Mitigação |
|---|---|---|
| Máquina única falha | Perda total do negócio | Backup em camadas + restore testado |
| "Sem internet" x backup no Drive | Backup remoto impossível | Cópia local sempre; Drive é bônus |
| Impressão térmica no navegador | PDV não imprime | Plano B ESC/POS; reservar tempo pra isso |
| Atualizar o app na máquina | Manutenção travada | Definir processo: git pull + `docker compose up` |
| Gold-plating / não entregar | Projeto morre 70% pronto | Chegar na Fase 2 rápido; resto é opcional |

---

## 11. Roadmap por fases

- **Fase 0 — Fundação:** Docker, auth, users/roles, layout, config da loja (1 registro), backup básico.
- **Fase 1 — Cadastros:** categorias → produtos+variações/estoque → clientes → vendedores.
- **Fase 2 — Núcleo de venda (MVP que roda a loja):** caixa + PDV + `RegistrarVendaAction` + comprovante térmico.
- **Fase 3 — Estoque & histórico:** entradas, movimentações, histórico de vendas/caixa.
- **Fase 4 — Relatórios:** vendas (dia/produto/vendedor/categoria), estoque.
- **Fase 5 (opcional):** crediário, contas a pagar/receber, despesas.

---

## 12. Decisões — todas fechadas

1. **Internet:** máquina conectada; app opera off; backup ao Drive quando online + cópia local sempre. ✓
2. **Stack:** Laravel API + Nuxt 3 SPA. ✓
3. **Banco:** PostgreSQL. ✓
4. **Servidor:** Linux (Docker como serviço). ✓
5. **Venda:** trava sem caixa aberto; permite estoque negativo. ✓
6. **Caixa:** único, compartilhado; vendas por vendedor. ✓
7. **Identidade:** usuário = vendedor (sem tabela `sellers`). ✓
8. **Terminais:** 1 servidor + 3 (expansível); `lockForUpdate` obrigatório. ✓
9. **Mobile:** roadmap futuro. ✓

**Próximo passo:** iniciar a **Fase 0** mediante seu "vai".
