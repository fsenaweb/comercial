<template>
  <div class="flex flex-col gap-5 text-sm text-txt-secondary">
    <div>
      <h3 class="font-display text-base font-bold text-txt-primary">Financeiro</h3>
      <p class="mt-1.5">
        Controle de crediário (fiado), contas a pagar e despesas da loja — separado do fluxo de
        venda do PDV.
      </p>
    </div>

    <img
      src="/manual/screenshots/financeiro.jpg"
      alt="Visão Financeira com saldo devedor do crediário, contas vencidas e estoque em atenção"
      class="w-full rounded-xl border border-border shadow-card"
    >

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Crediário</h4>
      <p class="mt-1.5">
        Funciona como uma "caderneta": ao lançar uma compra para um cliente (com os produtos reais
        do catálogo, igual ao PDV), a conta do cliente é aberta automaticamente e o estoque é
        decrementado — sem envolver o caixa. O saldo devedor é sempre a soma das compras menos os
        pagamentos já feitos. Um pagamento (parcial ou total) exige caixa aberto, porque é dinheiro
        físico entrando na loja.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Contas a pagar</h4>
      <p class="mt-1.5">
        Lançamentos ligados a um fornecedor, podendo ter várias parcelas. Dar baixa numa parcela não
        toca o caixa (pago por boleto/transferência, fora do PDV) — é só controle financeiro.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Despesas</h4>
      <p class="mt-1.5">
        Lançamentos avulsos, sem fornecedor nem parcelamento (aluguel, água, luz...). Mesma lógica
        de baixa que contas a pagar: sem tocar o caixa.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Visão Financeira</h4>
      <p class="mt-1.5">
        Painel único com saldo devedor do crediário, despesas previstas/pagas no mês (com navegação
        de mês), contas vencidas, maiores contas a pagar do mês, clientes com maior saldo no
        crediário e estoque em atenção.
      </p>
    </div>
  </div>
</template>
