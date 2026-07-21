<template>
  <div class="flex flex-col gap-5 text-sm text-txt-secondary">
    <div>
      <h3 class="font-display text-base font-bold text-txt-primary">Configurações e usuários</h3>
      <p class="mt-1.5">
        Área restrita ao Administrador, com os dados da loja, o cadastro base do catálogo e o
        gerenciamento de usuários.
      </p>
    </div>

    <img
      src="/manual/screenshots/usuarios.jpg"
      alt="Tela de Usuários e Permissões, com papel, comissão e status ativo de cada login"
      class="w-full rounded-xl border border-border shadow-card"
    >

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Dados da Empresa</h4>
      <p class="mt-1.5">
        Nome, CNPJ, endereço, contato e logo da loja — usados no comprovante, nos relatórios
        exportados e no cabeçalho impresso.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Usuários e Permissões</h4>
      <p class="mt-1.5">
        Cadastro de login, papel (admin/caixa/vendedor), comissão e status ativo/inativo. Um usuário
        não consegue desativar a própria conta.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Configurações (catálogo base)</h4>
      <p class="mt-1.5">
        Categorias, subcategorias, marcas, unidades e formas de pagamento — a base usada nos
        cadastros de produto e no PDV.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Aparência</h4>
      <p class="mt-1.5">
        Tema claro/escuro e tamanho de fonte ficam nos botões no canto superior direito de qualquer
        tela (inclusive no PDV) — a preferência é salva por usuário, não por computador.
      </p>
    </div>
  </div>
</template>
