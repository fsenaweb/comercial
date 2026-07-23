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

echo Reconstruindo imagens...
docker compose build
if errorlevel 1 goto :error

docker compose up -d
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
exit /b 0

:error
echo ERRO: deploy interrompido.
exit /b 1
