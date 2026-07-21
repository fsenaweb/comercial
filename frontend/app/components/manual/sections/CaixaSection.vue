<template>
  <div class="flex flex-col gap-5 text-sm text-txt-secondary">
    <div>
      <h3 class="font-display text-base font-bold text-txt-primary">Caixa</h3>
      <p class="mt-1.5">
        Só é possível registrar vendas com o caixa aberto. Cada terminal tem um único caixa aberto
        por vez - não é possível abrir um segundo enquanto o primeiro estiver aberto.
      </p>
    </div>

    <img
      src="/manual/screenshots/caixa.jpg"
      alt="Tela de Caixa com o extrato de operações do dia e os botões de sangria, reforço e fechamento"
      class="w-full rounded-xl border border-border shadow-card"
    >

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Abrir o caixa</h4>
      <p class="mt-1.5">
        No menu, acesse <strong class="text-txt-primary">Caixa</strong>. Se não houver caixa aberto,
        informe o valor inicial (fundo de troco) e uma observação opcional, e confirme.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Sangria e reforço</h4>
      <p class="mt-1.5">
        Com o caixa aberto, use os botões <strong class="text-txt-primary">Sangria</strong> (retirar
        dinheiro do caixa) e <strong class="text-txt-primary">Reforço</strong> (colocar dinheiro a
        mais) informando valor e motivo. Um lançamento indevido pode ser removido antes do
        fechamento - depois de fechado, o extrato não pode mais ser alterado.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Fechar o caixa</h4>
      <p class="mt-1.5">
        Confira o extrato do dia (vendas, sangrias, reforços) e clique em
        <strong class="text-txt-primary">Fechar caixa</strong>. Depois de fechado, o caixa vira
        histórico - para vender de novo, é preciso abrir um novo caixa.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Histórico</h4>
      <p class="mt-1.5">
        A listagem de caixas mostra todos os caixas já abertos/fechados, com filtro por período e
        status. Clique em um caixa para ver o detalhe das operações, incluindo o botão
        "Ver itens da venda" em cada lançamento de venda.
      </p>
    </div>
  </div>
</template>
