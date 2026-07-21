<template>
  <div class="flex flex-col gap-5 text-sm text-txt-secondary">
    <div>
      <h3 class="font-display text-base font-bold text-txt-primary">Instalação e primeiro acesso</h3>
      <p class="mt-1.5">
        O sistema roda inteiro dentro da rede da loja (não é um serviço na internet). Esta seção é
        um resumo para o Administrador — o passo a passo técnico completo fica no repositório do
        projeto, em <code class="rounded bg-surface-subtle px-1.5 py-0.5 text-xs">docs/07-dev-environment.md</code>.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Atualizar o sistema</h4>
      <p class="mt-1.5">
        No computador/servidor da loja, execute o script de atualização
        (<code class="rounded bg-surface-subtle px-1.5 py-0.5 text-xs">deploy.sh</code> no Linux ou
        <code class="rounded bg-surface-subtle px-1.5 py-0.5 text-xs">deploy.bat</code> no Windows).
        Ele baixa a versão mais recente, reconstrói os containers e atualiza a tela sem precisar de
        nenhum passo manual adicional.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Primeiro acesso</h4>
      <p class="mt-1.5">
        Acesse o sistema pelo navegador no endereço da loja na rede local (ex.:
        <code class="rounded bg-surface-subtle px-1.5 py-0.5 text-xs">loja.local</code> ou o IP do
        servidor) e entre com o usuário administrador inicial. Troque a senha padrão e cadastre os
        demais usuários (Administração &gt; Usuários e Permissões) antes de colocar o sistema em uso
        no dia a dia.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Impressão sem diálogo do navegador</h4>
      <p class="mt-1.5">
        No terminal do caixa/PDV, configure o atalho do Chrome com a flag
        <code class="rounded bg-surface-subtle px-1.5 py-0.5 text-xs">--kiosk-printing</code> —
        assim, o comprovante e as etiquetas imprimem direto na impressora térmica padrão do sistema
        operacional, sem abrir a janela de impressão do navegador a cada venda. O passo a passo
        completo (Windows/Linux) está em
        <code class="rounded bg-surface-subtle px-1.5 py-0.5 text-xs">docs/01-architecture.md</code>.
      </p>
    </div>
  </div>
</template>
