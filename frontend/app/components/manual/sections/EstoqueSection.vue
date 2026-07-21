<template>
  <div class="flex flex-col gap-5 text-sm text-txt-secondary">
    <div>
      <h3 class="font-display text-base font-bold text-txt-primary">Estoque e Compras</h3>
      <p class="mt-1.5">
        Toda movimentação de estoque (venda, entrada, ajuste, cancelamento) fica registrada no
        Kardex — o sistema nunca altera a quantidade de um produto sem deixar rastro.
      </p>
    </div>

    <img
      src="/manual/screenshots/kardex.jpg"
      alt="Tela de Kardex com o histórico de movimentações de estoque, filtrado por produto e período"
      class="w-full rounded-xl border border-border shadow-card"
    >

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Entrada de estoque</h4>
      <p class="mt-1.5">
        Use "Entradas de Estoque" para lançar mercadoria recebida manualmente (produto, quantidade e
        motivo/origem). Para entrada com nota fiscal do fornecedor, use "Importar XML de NF-e" — o
        sistema lê o arquivo, casa os itens pelo código de barras (GTIN/EAN), permite corrigir itens
        não reconhecidos, atualizar o custo e gerar automaticamente as contas a pagar da nota.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Ajuste de estoque</h4>
      <p class="mt-1.5">
        Use quando a quantidade contada fisicamente não bate com o sistema (perda, quebra, erro de
        contagem). Informe a quantidade contada — o sistema calcula e mostra a diferença antes de
        salvar.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Kardex</h4>
      <p class="mt-1.5">
        Histórico completo de todas as movimentações, com filtro por produto, tipo (entrada, saída,
        ajuste) e período.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Etiquetas</h4>
      <p class="mt-1.5">
        Gera etiquetas de preço/código de barras para impressão, com presets prontos para bobina
        térmica ou papel A4 — configure o layout (colunas, fonte, conteúdo) uma vez e reaproveite
        nas próximas impressões.
      </p>
    </div>
  </div>
</template>
