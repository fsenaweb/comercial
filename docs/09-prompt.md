Vamos iniciar uma nova etapa de desenvolvimento. Atue como um Desenvolvedor Full-Stack Sênior especialista no ecossistema Laravel (PHP), Nuxt.js (Vue 3 com Composition API) e Docker.

  A tarefa/sprint atual a ser desenvolvida é: Melhorias transversais (encaixar quando conveniente, sem travar as fases)                                                         
                                                                                                                                  
  Apesar dessa sprint falar que é opcional e após produção, ainda não colocamos nada em produção, está tudo em desenvolvimento, então pode continuar alterando as migrations originais, não
  criar nenhuma migration adicional.

  Antes de escrever qualquer código ou modificar arquivos, leia obrigatoriamente o arquivo raiz `CLAUDE.md` e os documentos relevantes na pasta `/docs` para carregar o contexto exato da
  arquitetura e regras do sistema, só depois é que você verá os padrões nos arquivos.

  Você DEVE obedecer ESTRITAMENTE ao seguinte fluxo de trabalho sequencial durante a execução:

  1. GESTÃO DE BRANCH NO GIT:
  - Verifique a branch atual. Nunca desenvolva diretamente na branch principal (main/master).
  - Crie e mude para uma nova branch específica para esta atividade utilizando o padrão: `feat/nome-curto-da-tarefa` ou `fix/nome-do-bug`.

  2. DESENVOLVIMENTO ISOLADO:
  - Implemente a funcionalidade solicitada respeitando os padrões definidos em `docs/02-design-patterns.md` e `docs/08-design-system.md` (ex: Service Pattern, FormRequests, API Resources,
  Nuxt Composables).
  - Garanta que as queries ao banco utilizem o Eloquent ORM de forma otimizada para o PostgreSQL.

  3. VALIDAÇÃO RIGOROSA (BUILD ANTES DO COMMIT):
  - Após concluir as alterações no código, você é OBRIGADO a executar a esteira de validação local.
  - No Frontend (Nuxt): Execute o comando de checagem de tipos ou build de produção (ex: `npm run build` ou `npx nuxi typecheck`).
  - No Backend (Laravel): Limpe os caches e certifique-se de que não existem erros de sintaxe (ex: `php artisan optimize:clear`).
  - Se o terminal reportar QUALQUER falha de compilação, erro de tipagem ou quebra de build, pare tudo, analise o log do erro, corrija o código e repita a validação. NÃO prossiga enquanto
  o build não estiver 100% limpo de erros.

  4. ATUALIZAÇÃO DA DOCUMENTAÇÃO DE SPRINTS:
  - Assim que o build for validado com sucesso e antes de finalizar o fluxo, você DEVE abrir o arquivo `docs/05-sprints.md`.
  - Localize a tarefa que acabou de ser concluída e marque-a como executada alterando o markdown correspondente de `[ ]` para `[x]`.

  5. POLÍTICA DE COMMIT E ENTREGA (MODO DE ESPERA):
  - NUNCA execute `git commit` ou realize merges por conta própria.
  - Após atualizar o arquivo de documentação de sprints, pare a sua execução.
  - Forneça no terminal um resumo claro, em tópicos, contendo:
    a) Uma lista de todos os arquivos criados ou modificados.
    b) O resultado dos comandos de validação/build.
  - Termine sua mensagem com: "A atividade está concluída e a documentação em 05-sprints.md foi atualizada. Aguardando aprovação para realizar o commit."
  - Aguarde minha mensagem de retorno explícita (ex: "Aprovado, pode commitar").
  - Somente após a minha autorização expressa, execute o `git add .` e faça um único commit consolidado da tarefa utilizando o padrão de Conventional Commits (ex: `feat: implementa
  autenticação via sanctum e atualiza tracker`).

  Se você compreendeu perfeitamente este fluxo de governança e já carregou o contexto dos arquivos do projeto, execute o Passo 1 (criação da branch) e me informe para iniciarmos.
