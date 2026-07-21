<template>
  <div class="flex flex-col gap-5 text-sm text-txt-secondary">
    <div>
      <h3 class="font-display text-base font-bold text-txt-primary">Backup e restauração</h3>
      <p class="mt-1.5">
        O sistema faz backup automático diário (09:45/10:00) e mantém duas camadas de segurança:
        cópia local no servidor da loja e envio automático para o Google Drive.
      </p>
    </div>

    <img
      src="/manual/screenshots/backup.jpg"
      alt="Tela de Backup e restauração, com a lista de backups locais e a conexão com o Google Drive"
      class="w-full rounded-xl border border-border shadow-card"
    >

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Baixar um backup</h4>
      <p class="mt-1.5">
        A lista em "Backup e restauração" mostra os backups locais com data e tamanho — clique em
        "Baixar" na linha desejada para salvar o arquivo. O dashboard mostra um aviso quando o
        backup do dia ainda não foi feito.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Google Drive</h4>
      <p class="mt-1.5">
        Clique em "Conectar ao Google Drive" — o sistema mostra um código e um link
        (google.com/device). Abra esse link em qualquer aparelho com internet (celular, outro
        computador) e autorize com a conta Google da loja. A partir daí, o backup do dia é enviado
        automaticamente todo dia, sem precisar repetir esse passo.
      </p>
    </div>

    <div>
      <h4 class="font-display text-sm font-bold text-txt-primary">Restaurar um backup</h4>
      <p class="mt-1.5">
        <strong class="text-rose-600">Atenção: restaurar substitui todos os dados atuais pelos do
        backup escolhido e desconecta todos os terminais, inclusive quem está restaurando.</strong>
        Só é possível com o caixa fechado. O sistema gera um código de confirmação de uso único
        (válido por 5 minutos) que precisa ser digitado para confirmar — use essa opção apenas em
        caso real de perda de dados, nunca como teste.
      </p>
    </div>
  </div>
</template>
