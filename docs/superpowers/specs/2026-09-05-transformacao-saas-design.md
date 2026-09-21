# Transformação do Comercial em produto SaaS — Design

**Data:** 2026-09-05
**Status:** Aguardando revisão do usuário

## Contexto

O `Comercial` (este repositório) nasceu como um sistema de gestão comercial/PDV sob medida para a loja do irmão do desenvolvedor: on-premise, single-tenant, sem internet, deploy via Docker Compose numa máquina da própria loja. O sistema ganhou repercussão e há interesse em comercializá-lo como um produto SaaS, atendendo múltiplos clientes a partir de uma única instalação em nuvem.

Este documento registra o design acordado para essa transformação. Ele **não** cobre a implementação em si — isso é responsabilidade do plano de implementação gerado a partir daqui (via skill `writing-plans`).

## Fora de escopo deste documento

Dois itens foram deliberadamente deixados de fora, por decisão explícita do usuário, e serão tratados em conversas futuras antes de virarem tarefas de implementação:

- **Layout/design system do novo produto** — será definido depois, com telas de referência.
- **Novos menus/funcionalidades específicas do SaaS** — serão levantados depois.

Este design cobre a **fundação técnica** (multi-tenancy, autenticação, billing, infraestrutura, reaproveitamento de código) necessária pra qualquer funcionalidade de produto vir a ser construída em cima.

## Repositório e estratégia de reaproveitamento

- **Novo repositório**, em pasta separada, no mesmo nível do `Comercial` atual (não um fork Git ligado ao histórico deste repo — uma cópia de partida independente).
- **Nome da pasta/repo:** a decidir pelo usuário (sugestões: `comercial-saas`, `comercial-cloud`, ou um nome de marca comercial próprio, se já houver).
- **Backend:** parte do Laravel deste projeto como ponto de partida — reaproveita as Actions e regras de negócio de estoque/caixa/venda já validadas em produção (ver `docs/02-design-patterns.md` e `docs/03-database-modeling.md` deste repo). Toda tabela de negócio (produtos, vendas, movimentações de caixa, clientes, etc.) ganha uma coluna `tenant_id`.
- **Frontend:** greenfield sobre Nuxt 4 — o layout muda, então poucas telas são copiadas literalmente. Reaproveita-se a estrutura de cliente HTTP único (`useApi`, cookie httpOnly + `X-XSRF-TOKEN`) e o padrão de organização de páginas.
- **Reaproveitado do projeto `arcoreal`** (`/home/fsenaweb/Sites/Clientes/maconaria/arcoreal`), via **cópia direta de arquivos** (não extração como pacote Composer compartilhado — com apenas dois produtos, o custo de manter um pacote versionado não se paga ainda; reavaliar se surgir um terceiro produto):
  - Multi-tenancy: model `Tenant`, middleware `ResolveTenant` (`api/app/Http/Middleware/ResolveTenant.php`), `TenantOnboardingService`.
  - Billing: `PaymentGatewayInterface` e a implementação `AsaasGateway` (`api/app/Services/Payment/`), models `Subscription`, `Invoice`, `InvoiceItem`, `PaymentConfiguration`, `ChargePayment`, jobs `ProcessWebhookPaymentJob`, `SyncPaymentStatusJob`, `GenerateSubscriptionChargesJob`, e os controllers/rotas de webhook do Asaas.
  - Painel administrativo completo (super-admin): `AdminPlatformOnly` middleware (checa `role === 'admin_platform'` no model de usuário), `LogAdminAction` middleware (auditoria), e os controllers `Admin*` (CRUD de tenants, planos, cobranças, relatórios, tickets/leads).
  - Landing page pública como rota (`/`) dentro do próprio app Nuxt, consumindo uma API pública de planos (`GET /public/plans`), seguindo o padrão do `arcoreal` (`web/app/components/Landing`).
  - Runbook de produção completo (ver seção Infraestrutura abaixo).
- Ao copiar esse código, remover/adaptar tudo que é específico do domínio do `arcoreal` (maçonaria) — o que se aproveita é a infraestrutura de tenancy/billing/admin, não regras de negócio daquele produto.

## Multi-tenancy

- **Modelo:** row-level com coluna `tenant_id` em cada tabela de negócio — **não** schema-per-tenant. Um Global Scope do Eloquent (na model base) injeta `WHERE tenant_id = ?` automaticamente em toda consulta.
- **Resolução do tenant:** via usuário autenticado (`auth()->user()->tenant`), replicando `ResolveTenant` do `arcoreal`. Não há subdomínio por cliente — tudo roda em um único domínio (`www.exemplo.com.br`, placeholder até o domínio real ser definido).
- **Consequência de design:** e-mail de login é único **globalmente** (não por tenant) — se dois clientes diferentes tentarem cadastrar o mesmo e-mail, o segundo cadastro é bloqueado com mensagem clara pedindo outro e-mail.
- **Risco aceito:** uma query crua fora do Eloquent, ou um relacionamento sem o scope aplicado, pode vazar dado entre tenants. Mitigação: Global Scope centralizado na model base (nunca opcional por model individual) + testes de isolamento entre tenants (o `arcoreal` já tem uma suíte em `api/tests/Feature/Tenant` a ser usada como referência).

## Autenticação

- Cookie httpOnly de sessão (Laravel Sanctum, modo SPA) — mesmo padrão já usado no `Comercial` atual, viável aqui porque front e back continuam na mesma origem (domínio único). Nada de token em `localStorage`.
- Fluxo idêntico ao já documentado em `docs/01-architecture.md` deste repo: `GET /sanctum/csrf-cookie` → `POST /api/login` com `X-XSRF-TOKEN` → cookie de sessão autentica as chamadas seguintes.

## Cadastro de tenant (onboarding)

- **Self-service** é o caminho principal: o próprio cliente cria a conta pela landing page.
- **Cadastro manual** continua disponível como alternativa: vocês criam a conta (e-mail + senha padrão) para o cliente, que troca a senha no primeiro acesso — útil para quem prefere ser atendido diretamente.

## Billing

- Gateway: **Asaas**, integrado **desde o lançamento** (não adiado) — reaproveitando o `AsaasGateway` e toda a cadeia de `Subscription`/`Invoice`/webhooks do `arcoreal`.
- **Planos:** estrutura simples de planos (preço/periodicidade) já no lançamento, **sem** feature-gating granular por plano nesta fase — todo tenant pagante tem acesso às mesmas funcionalidades, independente do plano contratado. A tabela `TenantFeature` do `arcoreal` (trava de funcionalidade por plano) fica para uma fase futura, se fizer sentido comercialmente.
- **Trial:** cadastro self-service inclui período de trial (duração exata a definir na fase de planejamento comercial, fora do escopo técnico deste documento).

## Painel administrativo (super-admin)

- Reaproveitado **por completo** do `arcoreal` já no MVP (não uma versão mínima) — CRUD de tenants, planos, cobranças, monitoramento de uso, alertas, tickets de suporte, auditoria de ações administrativas.
- Justificativa: expectativa de volume relevante de clientes em pouco tempo torna inviável administrar tenants "na mão" (acesso direto a banco/Asaas) desde o início.

## Landing page

- Rota pública (`/`) dentro do mesmo app Nuxt do produto — não um site institucional separado. Menor superfície de infraestrutura (um repositório, um pipeline de deploy).
- Escopo mínimo no lançamento: apresentação do produto, planos, e CTA de cadastro self-service. A estrutura completa de captação de leads do `arcoreal` (Sprint 33) não é replicada agora — pode vir depois.

## Infraestrutura e deploy

- **Hospedagem:** VPS, mesmo provedor do `arcoreal` (Hostinger) — reaproveita runbook e script de setup já validados.
- **Orquestração:** Docker Compose, adicionando ao que o `Comercial` já usa (nginx, php-fpm, postgres): **Redis** + um worker de fila (`queue:work`) — novo requisito, necessário para os jobs assíncronos de billing (webhook do Asaas não pode depender de processamento síncrono).
- **CI/CD:** GitHub Actions fazendo deploy no VPS (mecanismo exato — SSH direto vs. outra estratégia — fica para o plano de implementação).
- **Ambientes:** sem staging nesta fase, seguindo o mesmo precedente do `arcoreal` em produção — a suíte de testes é a principal rede de segurança antes de cada deploy. Reavaliar se o risco começar a doer.
- **Storage:** Cloudflare R2, com dois buckets separados (mídia pública, ex. imagens de produto; backups privados) — mesmo padrão do `arcoreal`.

## Backup e disaster recovery

Reaproveitado do runbook do `arcoreal` (`docs/30-deploy-producao.md`, `docs/31-runbook-operacoes.md` daquele projeto):

- `pg_dump` diário via cron no host, **criptografado com GPG assimétrico** antes do upload (backup ilegível mesmo com o VPS comprometido).
- Upload para bucket privado dedicado no R2, com rotação Grandfather-Father-Son (diário 7 dias / semanal 4 semanas / mensal 12 meses via lifecycle do próprio bucket).
- Segredos do processo de backup isolados num arquivo `.env` próprio (permissão 600), separado das credenciais da aplicação.
- Falha de backup dispara alerta por e-mail.

## Observabilidade

- Sentry (`sentry/sentry-laravel`) para captura de erros.
- UptimeRobot monitorando um endpoint de health check (`/up`).
- Logs estruturados em JSON.
- E-mail transacional via Amazon SES.

## Divisão de trabalho entre agentes de IA

O usuário pretende paralelizar parte da implementação entre Claude Code e outro agente (Gemini/Codex) rodando em paralelo, cada um em seu próprio terminal/branch — não há integração automática entre os agentes nesta sessão.

- **Critério de divisão:** por camada — um agente cuida do backend (Laravel: multi-tenancy, billing, painel admin), o outro do frontend (Nuxt: layout novo, landing, telas). Pontualmente, dentro de uma mesma sprint, tarefas de frontend independentes entre si também podem ser divididas entre os dois agentes.
- **Implicação pro plano de implementação:** as tarefas devem ser escritas com fronteiras de arquivo claras e dependências explícitas entre tarefas de backend e frontend, para permitir execução paralela sem conflito de merge.

## Riscos e mitigações

| Risco | Mitigação |
|---|---|
| Vazamento de dado entre tenants (row-level) | Global Scope centralizado + suíte de testes de isolamento (reaproveitada do `arcoreal`) |
| Código copiado do `arcoreal` carrega acoplamento com domínio de maçonaria | Revisão explícita de cada arquivo copiado antes de integrar, removendo o que for específico daquele domínio |
| Ausência de staging | Suíte de testes ampla como rede de segurança; reavaliar necessidade de staging conforme a base de clientes crescer |
| Jobs assíncronos novos (Redis/fila) nunca usados neste projeto antes | Reaproveitar os jobs já testados do `arcoreal` como referência de implementação, não escrever do zero |
| Dois agentes de IA trabalhando em paralelo sem coordenação automática | Plano de implementação com fronteiras de arquivo/tarefa explícitas por camada (backend vs. frontend) |

## Próximos passos

1. Usuário revisa este documento e aprova (ou pede ajustes).
2. Gerar o plano de implementação detalhado (skill `writing-plans`), já estruturado por camada (backend/frontend) para permitir a divisão de trabalho entre os dois agentes de IA.
3. Primeira tarefa do plano: criar o novo repositório/pasta e copiar os arquivos reaproveitáveis do `Comercial` e do `arcoreal`, já adaptando-os ao modelo de tenancy definido aqui.
4. Fases seguintes (pós-MVP): usar a Fase 3 de sprints do `arcoreal` (`docs/sprints-fase3.md` daquele projeto) como referência para estruturar sprints de testes E2E, segurança e SEO do novo produto — a serem detalhadas quando o MVP estiver de pé.
