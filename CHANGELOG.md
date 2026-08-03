# Changelog

Todas as mudanças notáveis do projeto são documentadas aqui. Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

Cada PR/commit relevante para o usuário final (feature nova, correção de bug, mudança de comportamento) deve ganhar uma linha em **Unreleased** antes de virar uma nova versão marcada com tag.

## [Unreleased]

## [1.0.0] - 2026-08-03

Primeira versão estável em produção na loja (JP Parafusos e Acessórios). Marca o baseline antes da implementação de quantidade fracionada de produto — ponto de retorno seguro caso essa mudança de schema precise ser revertida.

### Adicionado
- Sistema completo de PDV: busca de produto (código/nome/código de barras), carrinho, desconto por item e por venda (com teto de 20% e senha de admin acima disso), pagamento único ou dividido, impressão de comprovante não fiscal (bobina 80mm/58mm ou A4).
- Cadastros: produtos (com variações/SKU, atacado), clientes, fornecedores, usuários e permissões por papel (admin/caixa/vendedor).
- Caixa: abertura/fechamento, sangria/suprimento, histórico de operações.
- Estoque: entrada manual, ajuste, kardex, importação de NFe (XML), etiquetas.
- Orçamentos: criação, conversão em venda (com ou sem pagamento dividido).
- Financeiro: contas a pagar/receber, despesas, crediário (fiado) itemizado.
- Relatórios e dashboard.
- Backup local e remoto (Google Drive) com restauração pela tela.
- Manual do usuário (F4) integrado ao sistema.
- Migração do sistema legado (Firebird): produtos e clientes via `php artisan legacy:import`.
- Deploy on-premise via Docker Compose (Linux ou Windows 10 com Docker Desktop), scripts `deploy.sh`/`deploy.bat`.

### Corrigido (pós-implantação, com o cliente já em produção)
- Impressão do histórico de vendas passou a abrir o mesmo seletor de formato (bobina/A4) usado no PDV, em vez de depender do Ctrl+P do navegador.
- Modal de detalhes da venda no histórico agora exibe corretamente a(s) forma(s) de pagamento, inclusive em vendas com pagamento dividido.
- `deploy.bat` não fecha mais a janela sozinho ao terminar (sucesso ou erro), permitindo ler a mensagem final.
- Permissão restritiva (0700) do diretório de backup corrigida no ambiente Windows/Docker Desktop.
- Diversos ajustes pontuais de UX pedidos pelo cliente após a implantação inicial (localização de produto, largura de busca no PDV, layout do PDV em telas menores, etc.).
