@echo off
REM Equivalente Windows de deploy.sh (servidor Windows 10 + Docker Desktop).
REM Encapsula o runbook completo de atualizacao da loja (ver
REM docs/07-dev-environment.md, "Deploy / atualizacao na maquina da loja"):
REM git pull + rebuild dos servicos + migrations + publicacao da SPA.
REM
REM Rodar direto na maquina da loja, na raiz do repo, com a stack ja no ar
REM (docker compose up -d rodado ao menos uma vez antes).
setlocal enabledelayedexpansion
cd /d "%~dp0"

echo Atualizando codigo (git pull)...
git pull
if errorlevel 1 goto :error

REM .env nao vai para o Git (guarda segredo: senha do banco etc.) - numa
REM instalacao nova, esse arquivo ainda nao existe. Copia o exemplo pra nao
REM travar os comandos abaixo com erro confuso do Laravel; os valores
REM (APP_URL, SANCTUM_STATEFUL_DOMAINS, DB_PASSWORD) ainda precisam ser
REM ajustados manualmente depois pro host real da loja (ver
REM docs/10-instalacao-loja.md).
if not exist "backend\.env" (
    echo backend\.env nao encontrado - copiando de backend\.env.example.
    echo Ajuste APP_URL, SANCTUM_STATEFUL_DOMAINS e DB_PASSWORD depois ^(ver docs/10-instalacao-loja.md^).
    copy "backend\.env.example" "backend\.env"
)

echo Reconstruindo imagens...
docker compose build
if errorlevel 1 goto :error

docker compose up -d
if errorlevel 1 goto :error

REM vendor/ nao vai para o Git (.gitignore) e o Dockerfile so instala o
REM binario do Composer, nao roda "install" - porque backend/ e bind mount,
REM entao um "composer install" rodado durante o build da imagem seria
REM sobrescrito pelo conteudo do host ao subir o container. Por isso o
REM install roda aqui, contra o volume ja montado, senao o artisan abaixo
REM falha por falta de vendor/autoload.php.
echo Instalando dependencias do Composer...
docker compose exec php-fpm composer install --no-dev --optimize-autoloader
if errorlevel 1 goto :error

echo Rodando migrations...
docker compose exec php-fpm php artisan migrate --force
if errorlevel 1 goto :error

REM Idempotente a partir da segunda execucao (link ja existe) - nao aborta o
REM deploy se falhar por esse motivo, mesmo espirito do "|| true" do deploy.sh.
docker compose exec php-fpm php artisan storage:link

REM Corrige permissao restritiva (0700) que storage/app/backup possa ter
REM herdado de antes de `visibility => public` (config/filesystems.php) -
REM achado real em Windows/Docker Desktop, ver docs/07-dev-environment.md.
docker compose exec php-fpm php artisan backups:ensure-directory-permissions

call "%~dp0deploy-frontend.bat"
if errorlevel 1 goto :error

echo Deploy concluido.
pause
exit /b 0

:error
echo ERRO: deploy interrompido.
pause
exit /b 1
