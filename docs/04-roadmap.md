# Roadmap de Desenvolvimento

Entrega incremental, priorizando chegar o quanto antes ao **núcleo de venda** (Fase 2) — é o que efetivamente coloca a loja rodando no sistema. Tudo depois disso é incremento sobre um sistema já funcional.

- **Fase 0 — Fundação:** Docker Compose, Laravel + Nuxt (SPA), auth (Sanctum), `users` com papéis simples (admin/caixa/vendedor), layout base, configuração da loja (`store_settings`, registro único), backup básico (dump local agendado).
- **Fase 1 — Cadastros:** categorias/subcategorias/marcas/unidades → produtos + variações/SKU (com estoque inicial) → clientes → vendedores (já cobertos por `users`, sem cadastro à parte).
- **Fase 2 — Núcleo de venda (MVP que roda a loja):** caixa (abertura/fechamento/operações) + PDV + `RegisterSaleAction` (transacional, com lock de estoque) + comprovante térmico. **Esta é a fase que define se o projeto entrega valor real.**
- **Fase 3 — Estoque avançado & histórico:** entradas de estoque, ajustes manuais, Kardex por variação, histórico de vendas e de caixa (consulta e filtros).
- **Fase 4 — Relatórios:** vendas por dia/produto/vendedor/categoria, alertas de estoque mínimo, dashboard de fechamento de caixa.
- **Fase 5 (opcional, depois do núcleo estável):** crediário/contas a receber com parcelas, contas a pagar, despesas, entrada de estoque via XML.

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
| Máquina única falha | Perda total do negócio | Backup em camadas (local sempre + Drive quando houver internet) + restore testado periodicamente |
| Impressão térmica via navegador | PDV não imprime o comprovante | Plano B: ESC/POS via QZ Tray ou helper local; reservar tempo na Fase 2 para esse risco |
| Atualizar o app na máquina da loja | Manutenção travada, ninguém técnico no local | Processo documentado e scriptado (`git pull` + `docker compose up -d --build` + `migrate`) |
| Gold-plating / não entregar | Projeto morre 70% pronto, loja nunca usa o sistema | Priorizar Fase 2 (núcleo de venda) antes de qualquer refinamento de Fases 3-5 |
| Concorrência entre terminais (venda simultânea do último item) | Estoque inconsistente, venda duplicada | `DB::transaction()` + `lockForUpdate()` obrigatório em `RegisterSaleAction` |
