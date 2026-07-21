<template>
  <div class="flex flex-col gap-5 text-sm text-txt-secondary">
    <div>
      <h3 class="font-display text-base font-bold text-txt-primary">PDV (Ponto de Venda)</h3>
      <p class="mt-1.5">
        Tela dedicada à venda, pensada para uso rápido com teclado ou leitor de código de barras.
        O carrinho, o vendedor e o cliente ficam sempre visíveis; só a finalização exige caixa
        aberto.
      </p>
    </div>

    <img
      src="/manual/screenshots/pdv.jpg"
      alt="Tela do PDV com o carrinho de itens, seleção de vendedor/cliente e formas de pagamento"
      class="w-full rounded-xl border border-border shadow-card"
    >

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Adicionar itens</h4>
      <p class="mt-1.5">
        Digite ou leia o código de barras no campo de busca e pressione <strong class="text-txt-primary">Enter</strong> —
        um código exato inclui o item direto no carrinho. Também é possível buscar por nome do
        produto ou abrir a busca completa com <strong class="text-txt-primary">F2</strong>.
        Desconto por item (valor fixo ou percentual) fica disponível na linha do carrinho.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Vendedor e cliente</h4>
      <p class="mt-1.5">
        O vendedor vem pré-selecionado pelo login, mas pode ser trocado com
        <strong class="text-txt-primary">F3</strong> (útil quando quem está operando o caixa não é
        quem atendeu o cliente). O cliente é opcional — use "Trocar" para vincular um já cadastrado
        ou "Novo cliente" para um cadastro rápido sem sair do PDV.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Preço de atacado</h4>
      <p class="mt-1.5">
        Quando um item tem preço de atacado cadastrado e a quantidade no carrinho atinge o mínimo,
        aparece um checkbox na linha para aplicar esse preço — nunca é aplicado automaticamente.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Pagamento e finalização</h4>
      <p class="mt-1.5">
        Escolha a forma de pagamento e o valor. Para dividir o pagamento entre duas ou mais formas
        (ex.: parte em Pix, parte em dinheiro), ligue o botão "Dividir pagamento" e adicione quantas
        linhas forem necessárias — a soma precisa bater exatamente com o total da venda.
        "Finalizar Venda" só fica habilitado com o caixa aberto e o pagamento completo.
      </p>
      <p class="mt-1.5">
        Depois de finalizar, escolha o formato de impressão do comprovante (bobina 80mm, 58mm ou
        papel A4).
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Orçamento em vez de venda</h4>
      <p class="mt-1.5">
        Se o cliente ainda não vai fechar a compra, use "Salvar Orçamento" — funciona com o carrinho
        montado, mesmo sem caixa aberto, e não baixa estoque nem lança no caixa. Veja a seção
        "Orçamentos" para os próximos passos.
      </p>
    </div>
  </div>
</template>
