<template>
  <div class="flex flex-col gap-5 text-sm text-txt-secondary">
    <div>
      <h3 class="font-display text-base font-bold text-txt-primary">Cadastros</h3>
      <p class="mt-1.5">
        Base de dados usada em todo o sistema: produtos (com suas variações/SKU), clientes e
        fornecedores.
      </p>
    </div>

    <img
      src="/manual/screenshots/produtos.jpg"
      alt="Listagem de produtos com busca, categoria e indicador de estoque"
      class="w-full rounded-xl border border-border shadow-card"
    >

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Produtos e variações</h4>
      <p class="mt-1.5">
        Cada produto pode ter uma ou mais variações (cor, tamanho, medida - o que fizer sentido para
        o item), cada uma com seu próprio código, código de barras, custo, preço de venda e
        estoque. O campo "Margem" calcula o preço de venda automaticamente a partir do custo
        (continua editável manualmente depois). Preço de atacado é opcional, por quantidade mínima.
        Um produto "Inativo" some da busca do PDV sem apagar o histórico de vendas já feitas com
        ele.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Clientes</h4>
      <p class="mt-1.5">
        Qualquer papel pode cadastrar e editar cliente (inclusive direto no PDV, no cadastro
        rápido) - só a exclusão é restrita ao Administrador.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Fornecedores</h4>
      <p class="mt-1.5">
        Cadastro usado nas entradas de estoque, na importação de XML de NF-e e nas contas a pagar.
        Gerenciado pelo Administrador.
      </p>
    </div>
  </div>
</template>
