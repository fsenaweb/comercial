# Instalação na Loja (Windows 10)

Tutorial passo a passo para colocar o sistema no ar pela primeira vez num PC
da loja rodando **Windows 10**, a partir do zero (máquina nova, sem nada
instalado). Para quem já desenvolve no projeto e só precisa do runbook do
dia a dia (subir a stack local, comandos de teste, deploy), use
`07-dev-environment.md` — este documento é especificamente sobre a máquina
final da loja, na primeira instalação.

Se o servidor da loja acabar sendo Linux em vez de Windows, o processo é o
mesmo, trocando só os comandos específicos de Windows (Docker Desktop,
`deploy.bat`, atalho do Chrome) pelos equivalentes já documentados em
`01-architecture.md`/`07-dev-environment.md` (`deploy.sh`, etc.).

## Visão geral do fluxo

O código nunca é copiado manualmente para o PC da loja. O fluxo é sempre:

1. Desenvolve-se e valida-se numa máquina de desenvolvimento (como esta).
2. O código aprovado é enviado (`git push`) para o repositório remoto no
   GitHub (`git@github.com:fsenaweb/comercial.git`).
3. O PC da loja **puxa** (`git pull`) desse mesmo repositório — na primeira
   vez via `git clone`, depois via `deploy.bat`.

Isso significa que, antes de instalar na loja, o código já precisa estar
commitado e enviado ao GitHub.

## 1. Pré-requisitos no PC da loja

- **Docker Desktop** (traz o backend **WSL2**, necessário para rodar
  containers Linux no Windows 10 — o instalador já orienta a ativação do
  WSL2 se ainda não estiver ligado).
- **Git for Windows** (inclui um terminal Git Bash, mas os comandos abaixo
  também funcionam no `cmd.exe`/PowerShell).

Depois de instalar os dois, abra o Docker Desktop uma vez e confirme que ele
está rodando (ícone na bandeja do sistema) antes de continuar.

## 2. Autenticar com o GitHub nessa máquina

O repositório é privado, então o PC da loja precisa de uma forma de acesso.
A mais simples:

1. Gerar uma chave SSH nova nessa máquina (`ssh-keygen`, no Git Bash).
2. Cadastrar a chave pública em GitHub → Settings → SSH and GPG keys.

Alternativa sem SSH: usar a URL HTTPS do repositório com um
[personal access token](https://github.com/settings/tokens) no lugar da
senha quando o Git pedir.

## 3. Clonar o repositório (primeira vez)

```
git clone git@github.com:fsenaweb/comercial.git
cd comercial
```

## 4. Configurar o `.env` do backend (primeira vez, manual)

Os arquivos `.env` não vão para o Git (guardam segredo: senha do banco,
credenciais do Google Drive etc.) — por isso esse passo não é automatizado
pelo `deploy.bat`.

```
copy backend\.env.example backend\.env
```

Editar `backend\.env` e ajustar, no mínimo:

| Variável | O que colocar |
|---|---|
| `APP_URL` | `http://loja.local` (ou o IP/hostname que a loja vai usar) |
| `SANCTUM_STATEFUL_DOMAINS` | o mesmo host acima (sem `http://`), para o cookie de sessão da SPA funcionar |
| `DB_PASSWORD` | trocar o valor padrão do exemplo por uma senha própria |
| `GOOGLE_OAUTH_CLIENT_ID` / `GOOGLE_OAUTH_CLIENT_SECRET` | só se for usar o backup automático no Google Drive (ver `01-architecture.md`, seção de backup) — sem isso, o recurso fica desabilitado sem quebrar o resto do sistema |

As demais variáveis já vêm com valores que funcionam sem alteração.

## 5. Subir a stack pela primeira vez

Com o Docker Desktop aberto e rodando:

```
docker compose up -d
docker compose exec php-fpm php artisan key:generate
docker compose exec php-fpm php artisan migrate --seed
docker compose exec php-fpm php artisan storage:link
docker compose --profile build run --rm nuxt-build
```

- `migrate --seed` cria o usuário administrador inicial:
  `admin@loja.local` / `password` — **troque essa senha no primeiro login**
  (Administração → Usuários e Permissões).
- `storage:link` é o link simbólico que permite o upload de arquivos (ex.:
  logo da loja) ser servido pelo nginx — sem ele, imagens enviadas dão 404.

## 6. Acessar o sistema

Por padrão, o nginx responde em `http://localhost` (porta 80) na própria
máquina. Para os outros terminais da loja acessarem pelo nome `loja.local`
em vez do IP puro, adicione uma linha no arquivo de hosts do Windows
(`C:\Windows\System32\drivers\etc\hosts`, como Administrador) apontando
`loja.local` para o IP da máquina servidor — o nginx já responde por
qualquer `Host` (`server_name _;`), não precisa reconfigurar nada nele.

## 7. Configurar impressão sem diálogo (terminais de PDV/caixa)

Cada terminal que vai imprimir comprovante/etiqueta direto (sem a caixa de
diálogo de impressão do Chrome aparecer a cada venda) precisa:

1. Ter a impressora térmica configurada como **impressora padrão** do
   Windows nesse terminal.
2. Abrir o navegador com a flag `--kiosk-printing`, por exemplo via um
   atalho:
   ```
   "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing --kiosk http://loja.local
   ```
   Colocar esse atalho na pasta de inicialização do Windows
   (`shell:startup`) para abrir sozinho no boot do terminal. `--kiosk` é
   opcional (tela cheia, sem barra de endereço).

Detalhes e o porquê dessa flag em `01-architecture.md`, seção
"Impressão Térmica".

## 8. Atualizações futuras

Depois da instalação inicial, toda atualização de versão é um único
comando, rodado na raiz do repositório (`cd comercial`):

```
deploy.bat
```

Ele encapsula `git pull` + rebuild dos containers + `migrate --force` +
republicação da SPA — mesmo runbook do `deploy.sh` usado em desenvolvimento,
adaptado para `cmd.exe`. Não precisa repetir nenhum passo das seções 3-5.

## Checklist final

- [ ] Docker Desktop instalado e rodando
- [ ] Git for Windows instalado, acesso ao repositório GitHub configurado
- [ ] Repositório clonado
- [ ] `backend\.env` configurado (`APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `DB_PASSWORD`)
- [ ] Stack no ar (`docker compose up -d` + migrate/seed/storage:link + build da SPA)
- [ ] Login testado, senha do `admin@loja.local` trocada
- [ ] Hostname `loja.local` resolvendo nos terminais (arquivo hosts), se aplicável
- [ ] Impressora térmica configurada como padrão + atalho `--kiosk-printing` nos terminais de PDV/caixa
