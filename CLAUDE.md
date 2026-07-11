# Sistema Comercial - Loja Local (On-Premise)

## Descrição do Projeto
Desenvolvimento de um sistema de gestão comercial e PDV (Ponto de Venda) projetado para rodar exclusivamente em rede local (LAN). O sistema gerencia cadastros, estoque, e fluxo de pré-venda/atendimento para uma única loja, não sendo SaaS e não emitindo cupons fiscais (apenas comprovante interno).

## Stack Tecnológica
- **Backend:** Laravel 11 (PHP) - API RESTful, autenticação via Sanctum
- **Frontend:** Nuxt 3 (Vue.js) - SPA (`ssr: false`), Tailwind, Pinia
- **Banco de Dados:** PostgreSQL
- **Infraestrutura:** Docker Compose, on-premise na LAN da loja (`restart: unless-stopped`)

## Convenções (obrigatórias em todo código gerado)
- **Código 100% em inglês:** tabelas, colunas, models, controllers, Actions, variáveis, funções.
- **Interface 100% em português:** todo texto visível ao usuário (labels, mensagens de erro/validação, comprovante).
- **Padrão de negócio:** Actions (uma classe por operação, ex. `RegisterSaleAction`), não Services genéricos. Ver `02-design-patterns.md` para a lista completa do que não fazer (sem repositories, sem DDD/hexagonal, sem CQRS).
- **Testes:** cobertura ampla — todo CRUD e toda Action ganham feature tests como parte da entrega, não como item separado.
- **Concorrência:** qualquer operação que mexe em estoque e/ou caixa roda em `DB::transaction()` com `lockForUpdate()`.

## Índice de Documentação
Para manter o contexto organizado, as definições detalhadas estão divididas nos arquivos abaixo. **Sempre consulte estes documentos antes de implementar novas funcionalidades.**

1. [Arquitetura do Sistema](./docs/01-architecture.md) — infraestrutura, deploy on-premise, impressão térmica, backup.
2. [Padrões de Projeto (Design Patterns)](./docs/02-design-patterns.md) — Actions, tratamento de erros, estrutura de pastas, testes.
3. [Modelagem do Banco de Dados](./docs/03-database-modeling.md) — inclui o módulo de Caixa e variações/SKU de produto.
4. [Roadmap Geral](./docs/04-roadmap.md) — fases 0 a 5, riscos e mitigações.
5. [Sprints de Desenvolvimento](./docs/05-sprints.md) — backlog sequencial, sprint 0 a 6.
6. [Spec Original (histórico)](./docs/06-claude-instruction.md) — levantamento funcional que originou os docs 1-5; mantido como referência, mas **em caso de conflito, os docs 1-5 prevalecem** (foram atualizados por último e refletem as decisões de nomenclatura/testes tomadas em 2026-07-11).
