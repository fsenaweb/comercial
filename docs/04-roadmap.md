# Roadmap de Desenvolvimento

Entrega incremental, priorizando chegar o quanto antes ao **núcleo de venda** (Fase 2) — é o que efetivamente coloca a loja rodando no sistema. Tudo depois disso é incremento sobre um sistema já funcional.

| Fase | Escopo | Status |
|---|---|---|
| **Fase 0 — Fundação** | Docker Compose, Laravel 12 + Nuxt 4 (SPA), auth (Sanctum cookie), `users` com papéis simples, layout base, configuração da loja (`store_settings`), backup local agendado + teste de restore, design system (`docs/08`) aplicado às telas | ✅ **Concluída** (Sprint 0, 2026-07-11, branch `feat/sprint-0-fundacao`) |
| **Fase 1 — Cadastros** | Categorias/subcategorias/marcas/unidades → produtos + variações/SKU (com estoque inicial) → clientes → fornecedores. Vendedores já cobertos por `users` | ⬜ Próxima |
| **Fase 2 — Núcleo de venda (MVP que roda a loja)** | Caixa (abertura/fechamento/operações) + PDV + `RegisterSaleAction` (transacional, com lock de estoque) + comprovante térmico. **Esta é a fase que define se o projeto entrega valor real** | ⬜ |
| **Fase 3 — Estoque avançado & histórico** | Entradas de estoque, ajustes manuais, Kardex por variação, histórico de vendas e de caixa (consulta e filtros) | ⬜ |
| **Fase 4 — Relatórios** | Vendas por dia/produto/vendedor/categoria, alertas de estoque mínimo, dashboard de fechamento de caixa | ⬜ |
| **Fase 5 (opcional)** | Crediário/contas a receber com parcelas, contas a pagar, despesas, entrada de estoque via XML | ⬜ |

Pendências conscientes da Fase 0 (não bloqueiam as próximas fases):
- Upload do backup ao **Google Drive** (camada 2) — plugar como disk adicional do laravel-backup quando conveniente; a camada local já opera.
- **Script de deploy** (`deploy.sh`) encapsulando o runbook de atualização (ver `07-dev-environment.md`).

## Fora de escopo (não construir)
Itens do produto comercial que inspirou o levantamento funcional (AppLoja), mas que não se aplicam a este sistema de loja única:
- Multi-tenancy, planos/assinatura, cobrança, suporte pago.
- Emissão fiscal (NFC-e/NFe) — resolvido por outro sistema já existente na loja.
- Loja virtual / sincronização de catálogo.
- Integração com IA/MCP.
- Onboarding e landing de marketing.

## Riscos e mitigações

| Risco | Impacto | Mitigação |
|---|---|---|
| Máquina única falha | Perda total do negócio | Backup em camadas (local sempre + Drive quando houver internet) + restore testado (teste automatizado `BackupRestoreTest` + restore manual periódico) |
| Impressão térmica via navegador | PDV não imprime o comprovante | Plano B: ESC/POS via QZ Tray ou helper local; reservar tempo na Fase 2 para esse risco |
| Atualizar o app na máquina da loja | Manutenção travada, ninguém técnico no local | Runbook documentado (`07-dev-environment.md`) e scriptado futuramente |
| Cookies/login quebram nos terminais da LAN | Ninguém loga no PDV | `SANCTUM_STATEFUL_DOMAINS` com o IP do servidor; `SESSION_DOMAIN=null` (ver 07) |
| Gold-plating / não entregar | Projeto morre 70% pronto, loja nunca usa o sistema | Priorizar Fase 2 (núcleo de venda) antes de qualquer refinamento de Fases 3-5 |
| Concorrência entre terminais (venda simultânea do último item) | Estoque inconsistente, venda duplicada | `DB::transaction()` + `lockForUpdate()` obrigatório em `RegisterSaleAction` |
