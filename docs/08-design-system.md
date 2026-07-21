# Design System

**Status: v2 — baseado em 9 telas do AppLoja (dashboard, modal de caixa, lista de caixa, modal de cliente, pedidos, produtos) + CSS compilado deles + o logo da nossa loja.** Documento vivo: conforme mais telas de referência forem chegando, atualizar aqui.

> Atualização (Sprint 1, revisão de design): a primeira leva de telas de cadastro (categorias, produtos etc.) saiu **visualmente pobre** em relação a este documento — sem sidebar, sem ícones, sem cards de KPI, `<select>` nativo cru. As seções abaixo foram detalhadas com o que realmente falta implementar; ver "Aplicação no código atual" para o estado depois do reforço.

## Princípio: marca vs. semântica
A AppLoja usa um teal (`--brand: rgb(47,165,153)`) como cor de marca e reserva `emerald`/`rose`/`sky` do Tailwind para semântica de status (sucesso/erro/info). Nós seguimos a mesma separação, trocando só a cor de marca:

- **Cor de marca (amarelo + preto, da nossa loja):** usada em CTA principal, item ativo do menu, foco de input, elementos de chrome (topbar/logo). **Texto sempre preto/escuro em cima do amarelo** (não branco — contraste ruim).
- **Cores de status (semânticas, não mudam com a marca):** verde = sucesso/aberto/positivo, vermelho/rose = erro/perigo/fechado com problema, azul = informativo/link secundário, âmbar mais escuro (não o amarelo de marca) = atenção/alerta — para não confundir "isto é a cor da loja" com "isto precisa da sua atenção".

Isso evita dois problemas: (1) parecer clone da AppLoja (cor de marca é só nossa), e (2) badges de status ficarem ambíguos (verde de "sucesso" não compete com o amarelo de marca).

## Cores

Tokens como CSS custom properties. **Tema sempre claro por padrão — decisão de produto, não segue `prefers-color-scheme` do sistema operacional.** Um sistema de loja usado por vendedores/caixa não deve mudar de aparência sozinho conforme o SO de quem está logado; isso já causou confusão numa validação da Sprint 0 (tela renderizou escura sem ninguém ter pedido). Dark mode só muda via **toggle explícito do usuário** (implementado na Sub-sprint C, 2026-07-16) — persistido por usuário (`users.theme`), não por navegador/localStorage.

| Token | Uso | Valor (claro) | Valor (escuro) |
|---|---|---|---|
| `--brand` | CTA principal, ativo do menu, foco, chrome | amarelo da logo (**amostrar hex exato do arquivo do logo** — usar `amber-400`/`yellow-400` do Tailwind como ponto de partida) | mesmo valor — o amarelo mantém contraste alto com `--brand-ink` em qualquer tema |
| `--brand-ink` | Texto sobre `--brand` | preto/`slate-900` (amarelo não escurece o suficiente pra exigir texto claro) | mesmo valor |
| `--surface` | Fundo da página | `slate-50` (`#f8fafc`) | `slate-900` (`#0f172a`) |
| `--surface-raised` | Cards, modais | branco (`#ffffff`) | `slate-800` (`#1e293b`) |
| `--surface-subtle` | Hover de linha, fundo de seção | `slate-100` (`#f1f5f9`) | `slate-700` (`#334155`) |
| `--border` / `--border-strong` | Bordas | `slate-200` (`#e2e8f0`) / `slate-300` (`#cbd5e1`) | `slate-700` (`#334155`) / `slate-600` (`#475569`) |
| `--txt-primary` / `--txt-secondary` / `--txt-muted` | Texto | `slate-900` (`#0f172a`) / `slate-600` (`#475569`) / `slate-400` (`#94a3b8`) | `slate-100` (`#f1f5f9`) / `slate-300` (`#cbd5e1`) / `slate-500` (`#64748b`) |
| `--ink` | Painéis/chips "sempre escuros com texto branco" (painel de marca do login, filtro ativo do financeiro) — **fixo, não inverte com o tema** | `slate-900` (`#0f172a`) | mesmo valor (achado 2026-07-21: usar `--txt-primary` pra isso quebra no escuro, porque esse token inverte pra claro) |
| `success` (status) | Badge "Aberto", confirmações | `emerald-500`/`emerald-600` | mesmo valor em ambos os temas — badge é um chip com fundo tingido próprio, não depende do fundo da página pra ter contraste (ver "Princípio: marca vs. semântica") |
| `danger` (status) | Erros, "Fechado com pendência", exclusão | `rose-500`/`rose-600` | mesmo valor |
| `info` (status) | Links secundários, badges informativos | `sky-600` | mesmo valor |
| `warning` (status) | Estoque baixo, atenção — **não usa o amarelo de marca** | `amber-600` (mais escuro/alaranjado que o amarelo de marca) | mesmo valor |

> Ação pendente: abrir o arquivo do logo num seletor de cor e cravar o hex exato de `--brand` aqui (hoje é uma aproximação).

Implementação: `frontend/app/assets/css/main.css` define os tokens claros no `@theme` e os escuros num bloco `:root[data-theme="dark"]` (sobrescreve as mesmas custom properties — como todos os componentes já usam utilitários gerados a partir delas, tipo `bg-surface`/`text-txt-primary`, o tema propaga sem precisar de variantes `dark:` do Tailwind espalhadas pelo código). O atributo `data-theme` é setado por `usePreferencesStore` (`frontend/app/stores/preferences.ts`), aplicado assim que `/api/me` resolve e também via um cookie leve (hint) lido por um script inline no `<head>`, pra evitar flash do tema errado antes do Vue montar.

### Tamanho de fonte
3 passos (P/M/G), persistidos por usuário (`users.font_scale`) junto com o tema, aplicados via `font-size` no `<html>` (classes `font-scale-small`/`font-scale-large` em `main.css`; "médio" é o tamanho-base do sistema, sem classe) — escala texto e espaçamento em `rem` do Tailwind junto, não é zoom do navegador. Controles "A-"/"A+" ficam sempre visíveis na topbar (`components/layout/AppearanceControls.vue`), ao lado do toggle de tema — inclusive no PDV, que tem seu próprio cabeçalho fora do layout padrão.

## Tipografia
A referência usa um par display/body, não uma fonte só:
- **Display (títulos, números de KPI):** Bricolage Grotesque (peso 600–800) — grotesca, moderna, com bastante presença. Google Font, gratuita.
- **Corpo (texto, labels, formulários):** Hanken Grotesk (peso 400–700) — mais neutra, alta legibilidade em telas densas de tabela/formulário.
- Fallback: `system-ui, sans-serif` em ambos.
- No Nuxt: `@nuxt/fonts` (auto-hospeda, sem chamada externa a cada load) ou self-host manual dos `.woff2` — decidir na hora de implementar; qualquer uma evita o registro de terceiros do Google Fonts CDN direto no HTML (privacidade/latência).

## Espaçamento, raio e sombra
- **Raio:** generoso. `rounded-xl` (12px) a `rounded-2xl` (16px) em cards/modais; `rounded-full` em pills, badges, avatares — **e também nos botões primário/secundário**, confirmado nos prints de Caixa/Produtos ("Abrir novo caixa", "Nova Venda", "Novo caixa" são todos pill, não `rounded-xl`). Ajuste em relação à v1: `BaseButton` usa `rounded-full`, não `rounded-xl`.
- **Sombra padrão de card:** suave, duas camadas (`0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06)` — equivalente ao `shadow-card` da referência). Nada de sombra dura.
- **Glow em CTA:** botão principal ganha sombra colorida sutil no hover (cor da marca ou do status), não só a sombra neutra — reforça o que é clicável/importante.

## Ícones
A referência usa um set de ícones de traço fino (estilo Lucide/Feather) em **todo lugar**: cada item de menu da sidebar, cada card de KPI (chip colorido com ícone), busca, ordenação de coluna, badges de status, ações de linha (editar/excluir), botões (carrinho, atualizar). A ausência total de ícones foi o maior motivo da v1 das telas de cadastro parecer pobre.
- **Biblioteca:** `lucide-vue-next` (tree-shakeable, um `import` por ícone, estilo de traço compatível com a referência).
- **Tamanho padrão:** 16–18px em botões/tabela, 20px em item de menu da sidebar, 22–24px dentro do chip colorido de KPI.
- **Cor padrão:** herda `currentColor` (segue o texto ao redor); chips de KPI usam a cor do próprio chip (ver StatCard abaixo).

## Padrões de componente (o que vimos nas 9 telas)

- **Shell (sidebar + topbar):** sidebar clara fixa (~260px), coluna própria de altura cheia — **não** é um nav em linha dentro do topbar (erro da v1). Do topo pra baixo: logo + nome do sistema, campo de busca (`Buscar no menu...`), grupos de navegação com ícone + label (grupos como "Cadastros" ficam expandidos por padrão mostrando os itens-filho recuados; grupos futuros como "Estoque e Compras"/"Financeiro" colapsam com chevron), separador, bloco "Suporte", e no rodapé fixo (fora do scroll) as ações de saída. Item ativo: fundo sólido na cor de marca (`--brand`) + texto `--brand-ink`, cantos arredondados (`rounded-xl`), não cobre a largura toda da sidebar (tem padding lateral). Topbar é uma faixa fina só na coluna à direita da sidebar (não atravessa por cima dela), com busca/notificações/usuário — **sem** o dropdown "Aplicativos"/marketplace e **sem** gamificação (XP/nível/streak): não fazem sentido numa ferramenta interna de uma loja só (ver "Fora de escopo" abaixo).
- **Card de KPI** (`components/ui/StatCard.vue`): label uppercase pequeno e cinza, valor grande em negrito (fonte display), subtexto cinza, chip de ícone colorido (`rounded-xl`, ~40px, fundo tingido leve tipo `bg-emerald-100 text-emerald-600`) no canto superior direito do card. Cores do chip variam por card só pra diferenciar visualmente (não carregam semântica de status) — usar uma rotação neutra (emerald/sky/violet/amber em tom claro) exceto quando o card É um alerta de verdade (ex.: estoque baixo), aí sim usa `warning` com borda esquerda tingida (visto no card "REPOSIÇÃO DE ESTOQUE" com borda vermelha/`danger` quando há pendência).
- **Badge de status** (`components/ui/StatusBadge.vue`): pill (`rounded-full`), fundo tingido levemente + texto na cor cheia do status (ex.: `bg-emerald-100 text-emerald-700` para "Aberto").
- **Modal com seções agrupadas:** título + subtítulo no topo; corpo dividido em blocos com label uppercase pequeno acima de cada grupo de campos (ex.: "DADOS PRINCIPAIS", "DOCUMENTOS", "ENDEREÇO" no cadastro de cliente); rodapé com ação secundária (ghost) à esquerda e primária (marca) à direita.
- **Formulário em grid pareado:** campos relacionados lado a lado (Celular/Telefone, CEP sozinho, Endereço/Número, Complemento/Bairro/Cidade/Estado) — reduz o scroll em formulários longos como o de cliente/produto.
- **Toolbar de tabela:** pills de filtro rápido (ex.: "Abertos / Fechados" com contador, ativo com fundo escuro sólido + texto branco), busca com ícone de lupa à esquerda do input, filtro de data — tudo numa linha acima da tabela, antes de qualquer paginação. Contador "Exibindo N de M" abaixo da toolbar, acima da tabela.
- **Ações de linha na tabela:** ícones (lápis para editar, lixeira para excluir), não texto sublinhado — texto sublinhado (v1) é o padrão de link de conteúdo, não de ação de tabela densa.
- **`<select>`:** nunca o nativo cru — mesma casca visual do `BaseInput` (borda, raio, foco em `--brand`), com um chevron (ícone `ChevronDown`) sobreposto à direita substituindo a seta do navegador.
- **Toast de feedback:** fixo, canto inferior central, animação sutil de entrada (fade + leve subida). Substitui alert() nativo.

## Fora de escopo (não trazer da AppLoja)
Coerente com `docs/04-roadmap.md` ("Fora de escopo"):
- Gamificação (nível/XP/streak "em dia agora") — flourish de engajamento de produto SaaS multi-tenant, sem sentido numa ferramenta interna de uma loja.
- Dropdown "Aplicativos"/marketplace — não existe conceito de apps/integrações de terceiros aqui.
- Banner de "migrar dados do sistema antigo" — específico do onboarding comercial da AppLoja.
- Cookie consent flutuante — sistema interno na LAN, sem tracking de terceiros.
- `glass-panel` / `mesh-background` (fundo animado com gradiente) — decorativo de página de marketing; se algum dia fizer sentido, seria só na tela de login, nunca nas telas de operação (PDV/back-office precisam de foco, não de decoração).

## Aplicação no código atual
As telas da Sprint 0 (`login.vue`, `settings/store.vue`) usam os tokens acima (`app/assets/css/main.css`, tema fixo claro via `color-scheme: light` + meta tag, sem seguir o SO).

A partir da revisão de design da Sprint 1: `layouts/default.vue` foi reconstruído com o shell sidebar+topbar (`components/layout/AppSidebar.vue`), `lucide-vue-next` instalado para ícones, `components/ui/StatCard.vue` e `StatusBadge.vue` criados, `BaseButton` passou a `rounded-full`, `BaseSelect` ganhou casca customizada com chevron, `BaseTable` passou a aceitar ações em ícone. Todas as telas de cadastro (`categories`, `subcategories`, `brands`, `units`, `suppliers`, `customers`, `products`) foram atualizadas para esse padrão.
