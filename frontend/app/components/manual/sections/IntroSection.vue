<template>
  <div class="flex flex-col gap-5 text-sm text-txt-secondary">
    <div>
      <h3 class="font-display text-base font-bold text-txt-primary">Bem-vindo ao Sistema Comercial</h3>
      <p class="mt-1.5">
        Este manual explica como usar o dia a dia do sistema: caixa, PDV, cadastros, estoque,
        financeiro, relatórios e backup. Pressione <strong class="text-txt-primary">F4</strong> a
        qualquer momento, em qualquer tela, para abrir ou fechar este manual - sem precisar sair do
        que você estava fazendo.
      </p>
    </div>

    <img
      src="/manual/screenshots/dashboard.jpg"
      alt="Painel inicial (Dashboard) do sistema, com KPIs de vendas do dia e alertas de estoque"
      class="w-full rounded-xl border border-border shadow-card"
    >

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Papéis de usuário</h4>
      <p class="mt-1.5">
        Cada pessoa que usa o sistema tem um login com um papel. O papel decide o que aparece no
        menu e quais ações a pessoa pode fazer.
      </p>
      <ul class="mt-3 flex flex-col gap-2.5">
        <li class="rounded-xl border border-border p-3">
          <span class="font-semibold text-txt-primary">Administrador</span> - acesso completo:
          produtos, fornecedores, clientes, usuários, configurações da loja, contas a pagar,
          despesas, backup e restauração, além de tudo que o Caixa e o Vendedor fazem.
        </li>
        <li class="rounded-xl border border-border p-3">
          <span class="font-semibold text-txt-primary">Caixa</span> - abre/fecha o caixa, registra
          sangria e reforço, cancela e converte vendas/orçamentos, faz ajustes e entradas de
          estoque, lança e recebe pagamentos do crediário, além de tudo que o Vendedor faz.
        </li>
        <li class="rounded-xl border border-border p-3">
          <span class="font-semibold text-txt-primary">Vendedor</span> - usa o PDV para vender,
          cria e consulta orçamentos, cadastra e edita clientes, consulta relatórios. Não abre/fecha
          caixa nem mexe em estoque/financeiro.
        </li>
      </ul>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Este manual</h4>
      <p class="mt-1.5">
        Use os itens à esquerda (ou os botões "Anterior"/"Próxima" no rodapé) para navegar entre os
        assuntos. As seções mostradas aqui já são filtradas conforme o seu papel - um Vendedor, por
        exemplo, não vê a seção "Backup e restauração".
      </p>
    </div>
  </div>
</template>
