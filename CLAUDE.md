# Sistema Comercial - Loja Local (On-Premise)

> **Instrução nº 1 (sempre válida, todo prompt):** toda resposta em texto ao usuário (mensagens de chat, resumos, perguntas de esclarecimento) deve ser em português — independente do idioma em que o usuário escrever. Isso é além da regra já existente de "interface 100% em português"/"código 100% em inglês" abaixo, que trata do conteúdo do sistema, não da conversa com o desenvolvedor.

## Descrição do Projeto
Desenvolvimento de um sistema de gestão comercial e PDV (Ponto de Venda) projetado para rodar exclusivamente em rede local (LAN). O sistema gerencia cadastros, estoque, caixa e fluxo de venda/atendimento para uma única loja, não sendo SaaS e não emitindo cupons fiscais (apenas comprovante interno não fiscal).

**Segmento-alvo:** o sistema nasce pensado para o varejo de **autopeças, motopeças, oficinas, lava-jato e ferragens/parafusaria** — segmentos com um padrão comum: catálogo com muita variação de SKU (medida, cor, aplicação de veículo), giro de peça avulsa e, às vezes, venda casada com serviço (ex.: lavagem, montagem). Isso influencia decisões já tomadas na modelagem (`03-database-modeling.md`): `products.type` aceita `service` (não só `product`/`kit`) para cobrir itens como lavagem/montagem lançados no mesmo PDV; `product_variations` carrega `color`/`size` genéricos (não campos fixos de "medida de parafuso" ou "aplicação de veículo") de propósito, para não prender o cadastro a um segmento só.
Ainda assim, a modelagem e as regras de negócio (cadastros, estoque, caixa, venda) são **deliberadamente genéricas** — nenhuma tabela ou campo é exclusivo de autopeças. Ao avaliar uma nova funcionalidade, preferir a solução que sirva ao varejo em geral (ex.: campo livre, enum extensível) a uma que amarre o sistema ao segmento-alvo, desde que isso não custe complexidade extra agora.

## Stack Tecnológica (versões reais em uso)
- **Backend:** Laravel 12 (PHP 8.4) em `backend/` — API RESTful, autenticação Laravel Sanctum em **modo SPA** (cookie de sessão, mesma origem via nginx)
- **Frontend:** Nuxt 4 (Vue 3, Composition API, estrutura `app/`) em `frontend/` — SPA (`ssr: false`), Tailwind CSS 4, Pinia
- **Banco de Dados:** PostgreSQL 16 (+ banco `comercial_testing` dedicado à suíte de testes, criado pelo init script do container)
- **Infraestrutura:** Docker Compose (`postgres`, `php-fpm`, `scheduler`, `nginx`, `nuxt-build`), on-premise na LAN da loja, `restart: unless-stopped`

> Nota: o doc 06 citava "Nuxt 3"; o projeto foi iniciado já no Nuxt 4 (estrutura `app/`), mantendo tudo que os docs definem (SPA `ssr:false`, Composition API, Pinia).
> Nota: migrado de Laravel 11 para 12 logo após a Sprint 0 — o 11 já havia passado da janela de suporte de segurança (lançado mar/2024, ~18 meses de bugfix + 24 de segurança). Laravel 12 é o major estável de referência agora; upgrade sem breaking changes relevantes para o código já escrito (suíte de 15 testes revalidada, tudo verde).

## Convenções (obrigatórias em todo código gerado)
- **Código 100% em inglês:** tabelas, colunas, models, controllers, Actions, variáveis, funções.
- **Interface 100% em português:** todo texto visível ao usuário (labels, mensagens de erro/validação, comprovante).
- **Padrão de negócio:** Actions (uma classe por operação, ex. `RegisterSaleAction`), não Services genéricos. Ver `02-design-patterns.md` para a lista completa do que não fazer (sem repositories, sem DDD/hexagonal, sem CQRS).
- **Erros de API padronizados:** `{ "message": "...", "errors": {...} }` em toda resposta de erro; API-only — guest em rota protegida recebe 401 JSON, nunca redirect (ver `02-design-patterns.md`).
- **Testes:** cobertura ampla — todo CRUD e toda Action ganham feature tests como parte da entrega, não como item separado.
- **Concorrência:** qualquer operação que mexe em estoque e/ou caixa roda em `DB::transaction()` com `lockForUpdate()`.

## Fluxo de Trabalho (obrigatório em toda tarefa de desenvolvimento)
1. **Branch:** nunca desenvolver na `master`. Antes de qualquer alteração, criar `feat/<nome-curto-da-atividade>` (ou `fix/`, `chore/`, conforme o caso).
2. **Iteração de frontend, use o dev server com hot-reload (`cd frontend && npm run dev`), não o build via Docker.** Rodar o build a cada ajuste pequeno desperdiça tempo à toa — mas **ao terminar e validar uma tarefa de frontend (antes de apresentar o resumo pro usuário), sempre republicar via `./deploy-frontend.sh` automaticamente, sem esperar o usuário pedir.** O usuário testa pelo nginx (`loja.local`/`localhost`, sem porta), não pelo `:3000` do dev server — se essa etapa for pulada, a tela nova simplesmente não aparece pra ele, ele reporta "continua a tela antiga", e isso já se repetiu várias vezes. `./deploy-frontend.sh` (raiz do repo) encapsula `docker compose build --no-cache nuxt-build` + `docker compose --profile build run --rm nuxt-build` + `docker compose restart nginx` — o `--no-cache` é obrigatório (ver "Armadilhas conhecidas" em `docs/07-dev-environment.md`). Não é opcional feito só quando o usuário perceber a tela desatualizada.
3. **Validação antes do commit:** backend — `docker compose exec php-fpm php artisan test`; frontend — `npx nuxi typecheck` e `npm run generate`. Se qualquer validação falhar, corrigir e revalidar; **não prosseguir com erros**.
4. **Commit somente com aprovação:** nunca commitar por conta própria. Ao terminar e validar, apresentar um resumo do que foi feito e aguardar aprovação explícita do usuário. Após aprovado: um único commit agrupando a tarefa, padrão Conventional Commits (`feat:`, `fix:`, `chore:`...).
5. **Docker sem root:** nenhum arquivo do repositório pode ser criado/alterado como root — comandos em containers sempre com o UID/GID do host. Regras e comandos prontos em `docs/07-dev-environment.md`.

## Índice de Documentação
Para manter o contexto organizado, as definições detalhadas estão divididas nos arquivos abaixo. **Sempre consulte estes documentos antes de implementar novas funcionalidades.**

1. [Arquitetura do Sistema](./docs/01-architecture.md) — infraestrutura, deploy on-premise, autenticação, impressão térmica, backup, permissões no Docker.
2. [Padrões de Projeto (Design Patterns)](./docs/02-design-patterns.md) — Actions, tratamento de erros, estrutura de pastas real (Laravel e Nuxt 4), padrões de teste.
3. [Modelagem do Banco de Dados](./docs/03-database-modeling.md) — inclui o módulo de Caixa e variações/SKU de produto; marca o que já foi implementado.
4. [Roadmap Geral](./docs/04-roadmap.md) — fases 0 a 5, riscos e mitigações, status por fase.
5. [Sprints de Desenvolvimento](./docs/05-sprints.md) — backlog sequencial, sprint 0 a 6, com progresso marcado.
6. [Spec Original (histórico)](./docs/06-claude-instruction.md) — levantamento funcional que originou os docs 1-5; mantido como referência, mas **em caso de conflito, os docs 1-5 prevalecem** (refletem as decisões tomadas depois: nomenclatura em inglês, cobertura ampla de testes, versões reais da stack).
7. [Ambiente de Desenvolvimento & Runbook](./docs/07-dev-environment.md) — como subir a stack, rodar testes, validar, fazer deploy na loja e evitar as armadilhas conhecidas (permissões, 502 do nginx, cookies na LAN).
8. [Design System](./docs/08-design-system.md) — cores (marca amarelo/preto vs. status semântico), tipografia, sombras, padrões de componente (extraídos de telas de referência do AppLoja). **Documento vivo**, atualizado conforme mais telas de referência forem enviadas.
