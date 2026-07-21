<template>
  <div class="flex flex-col gap-5 text-sm text-txt-secondary">
    <div>
      <h3 class="font-display text-base font-bold text-txt-primary">Orçamentos</h3>
      <p class="mt-1.5">
        Um orçamento é uma cotação de preço, sem garantia de estoque nem reserva. Ele é numerado
        separado da venda (prefixo <strong class="text-txt-primary">C</strong>, de "Cotação") para
        nunca ser confundido com uma venda de verdade na conferência de caixa.
      </p>
    </div>

    <img
      src="/manual/screenshots/orcamentos.jpg"
      alt="Listagem de orçamentos com filtros por vendedor, status e período"
      class="w-full rounded-xl border border-border shadow-card"
    >

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Criar um orçamento</h4>
      <p class="mt-1.5">
        Monte o carrinho no PDV normalmente e clique em "Salvar Orçamento". É possível informar uma
        validade opcional — passado esse prazo, o orçamento aparece com o selo "Vencido" na
        listagem.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Converter em venda</h4>
      <p class="mt-1.5">
        Na listagem, use "Converter em venda": escolha a(s) forma(s) de pagamento e confirme. Isso
        cria uma venda nova (com número próprio, baixa de estoque e lançamento no caixa) vinculada
        ao orçamento original — o orçamento nunca é apagado, só marcado como convertido. A conversão
        exige caixa aberto e checa a disponibilidade real do estoque na hora.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Cancelar</h4>
      <p class="mt-1.5">
        Um orçamento pendente pode ser cancelado a qualquer momento, sem exigir caixa aberto (ele
        nunca chegou a tocar estoque ou caixa). Um orçamento já convertido não pode mais ser
        cancelado — cancele a venda gerada, se for o caso.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Imprimir</h4>
      <p class="mt-1.5">
        O botão "Imprimir" na linha do orçamento abre o mesmo comprovante usado na venda, mas com o
        texto "ORÇAMENTO — sujeito à disponibilidade de estoque" no lugar da marcação de documento
        não fiscal, e sem bloco de pagamento.
      </p>
    </div>
  </div>
</template>
