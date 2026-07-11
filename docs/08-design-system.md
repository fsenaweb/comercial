# Design System

**Status: v1 — baseado em 4 telas do AppLoja (dashboard, modal de caixa, lista de caixa, modal de cliente) + CSS compilado deles + o logo da nossa loja.** Documento vivo: conforme mais telas de referência forem chegando, atualizar aqui.

## Princípio: marca vs. semântica
A AppLoja usa um teal (`--brand: rgb(47,165,153)`) como cor de marca e reserva `emerald`/`rose`/`sky` do Tailwind para semântica de status (sucesso/erro/info). Nós seguimos a mesma separação, trocando só a cor de marca:

- **Cor de marca (amarelo + preto, da nossa loja):** usada em CTA principal, item ativo do menu, foco de input, elementos de chrome (topbar/logo). **Texto sempre preto/escuro em cima do amarelo** (não branco — contraste ruim).
- **Cores de status (semânticas, não mudam com a marca):** verde = sucesso/aberto/positivo, vermelho/rose = erro/perigo/fechado com problema, azul = informativo/link secundário, âmbar mais escuro (não o amarelo de marca) = atenção/alerta — para não confundir "isto é a cor da loja" com "isto precisa da sua atenção".

Isso evita dois problemas: (1) parecer clone da AppLoja (cor de marca é só nossa), e (2) badges de status ficarem ambíguos (verde de "sucesso" não compete com o amarelo de marca).

## Cores

Tokens como CSS custom properties. **Tema sempre claro — decisão de produto, não segue `prefers-color-scheme` do sistema operacional.** Um sistema de loja usado por vendedores/caixa não deve mudar de aparência sozinho conforme o SO de quem está logado; isso já causou confusão numa validação da Sprint 0 (tela renderizou escura sem ninguém ter pedido). Dark mode via toggle explícito só entra se for pedido no futuro — os valores da AppLoja para tema escuro nem chegaram a ser adaptados aqui por esse motivo.

| Token | Uso | Valor (claro, único tema) |
|---|---|---|
| `--brand` | CTA principal, ativo do menu, foco, chrome | amarelo da logo (**amostrar hex exato do arquivo do logo** — usar `amber-400`/`yellow-400` do Tailwind como ponto de partida) |
| `--brand-ink` | Texto sobre `--brand` | preto/`slate-900` (amarelo não escurece o suficiente pra exigir texto claro) |
| `--surface` | Fundo da página | `slate-50` |
| `--surface-raised` | Cards, modais | branco |
| `--surface-subtle` | Hover de linha, fundo de seção | `slate-100` |
| `--border` / `--border-strong` | Bordas | `slate-200` / `slate-300` |
| `--txt-primary` / `--txt-secondary` / `--txt-muted` | Texto | `slate-900` / `slate-600` / `slate-400` |
| `success` (status) | Badge "Aberto", confirmações | `emerald-500`/`emerald-600` |
| `danger` (status) | Erros, "Fechado com pendência", exclusão | `rose-500`/`rose-600` |
| `info` (status) | Links secundários, badges informativos | `sky-600` |
| `warning` (status) | Estoque baixo, atenção — **não usa o amarelo de marca** | `amber-600` (mais escuro/alaranjado que o amarelo de marca) |

> Ação pendente: abrir o arquivo do logo num seletor de cor e cravar o hex exato de `--brand` aqui (hoje é uma aproximação).

## Tipografia
A referência usa um par display/body, não uma fonte só:
- **Display (títulos, números de KPI):** Bricolage Grotesque (peso 600–800) — grotesca, moderna, com bastante presença. Google Font, gratuita.
- **Corpo (texto, labels, formulários):** Hanken Grotesk (peso 400–700) — mais neutra, alta legibilidade em telas densas de tabela/formulário.
- Fallback: `system-ui, sans-serif` em ambos.
- No Nuxt: `@nuxt/fonts` (auto-hospeda, sem chamada externa a cada load) ou self-host manual dos `.woff2` — decidir na hora de implementar; qualquer uma evita o registro de terceiros do Google Fonts CDN direto no HTML (privacidade/latência).

## Espaçamento, raio e sombra
- **Raio:** generoso. `rounded-xl` (12px) a `rounded-2xl` (16px) em cards/modais; `rounded-full` em pills, badges, avatares e botões de ação circulares.
- **Sombra padrão de card:** suave, duas camadas (`0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06)` — equivalente ao `shadow-card` da referência). Nada de sombra dura.
- **Glow em CTA:** botão principal ganha sombra colorida sutil no hover (cor da marca ou do status), não só a sombra neutra — reforça o que é clicável/importante.

## Padrões de componente (o que vimos nas 4 telas)

- **Shell (sidebar + topbar):** sidebar clara, grupos colapsáveis por seção (nosso caso: Cadastros, Estoque, Caixa/PDV, Relatórios), item ativo com fundo sólido na cor de marca + texto `--brand-ink`. Topbar com busca, notificações, usuário — **sem** o dropdown "Aplicativos"/marketplace e **sem** gamificação (XP/nível/streak): não fazem sentido numa ferramenta interna de uma loja só (ver "Fora de escopo" abaixo).
- **Card de KPI** (`components/ui/StatCard.vue`, futuro): label uppercase pequeno e cinza, valor grande em negrito (fonte display), subtexto cinza, chip de ícone colorido no canto. Card de alerta (ex.: estoque baixo) ganha borda/fundo tingido de `warning`, não de `danger` — reservamos vermelho para erro real, não para "precisa de atenção".
- **Badge de status** (`components/ui/StatusBadge.vue`, futuro): pill (`rounded-full`), fundo tingido levemente + texto na cor cheia do status (ex.: `bg-emerald-100 text-emerald-700` para "Aberto").
- **Modal com seções agrupadas:** título + subtítulo no topo; corpo dividido em blocos com label uppercase pequeno acima de cada grupo de campos (ex.: "DADOS PRINCIPAIS", "DOCUMENTOS", "ENDEREÇO" no cadastro de cliente); rodapé com ação secundária (ghost) à esquerda e primária (marca) à direita.
- **Formulário em grid pareado:** campos relacionados lado a lado (Celular/Telefone, CEP sozinho, Endereço/Número, Complemento/Bairro/Cidade/Estado) — reduz o scroll em formulários longos como o de cliente/produto.
- **Toolbar de tabela:** pills de filtro rápido (ex.: "Abertos / Fechados" com contador), busca, filtro de data — tudo numa linha acima da tabela, antes de qualquer paginação.
- **Toast de feedback:** fixo, canto inferior central, animação sutil de entrada (fade + leve subida). Substitui alert() nativo.

## Fora de escopo (não trazer da AppLoja)
Coerente com `docs/04-roadmap.md` ("Fora de escopo"):
- Gamificação (nível/XP/streak "em dia agora") — flourish de engajamento de produto SaaS multi-tenant, sem sentido numa ferramenta interna de uma loja.
- Dropdown "Aplicativos"/marketplace — não existe conceito de apps/integrações de terceiros aqui.
- Banner de "migrar dados do sistema antigo" — específico do onboarding comercial da AppLoja.
- Cookie consent flutuante — sistema interno na LAN, sem tracking de terceiros.
- `glass-panel` / `mesh-background` (fundo animado com gradiente) — decorativo de página de marketing; se algum dia fizer sentido, seria só na tela de login, nunca nas telas de operação (PDV/back-office precisam de foco, não de decoração).

## Aplicação no código atual
As telas da Sprint 0 (`login.vue`, `layouts/default.vue`, `settings/store.vue`, `BaseButton`/`BaseInput`) já foram retrocedidas para os tokens acima (`app/assets/css/main.css`, tema fixo claro via `color-scheme: light` + meta tag, sem seguir o SO).
