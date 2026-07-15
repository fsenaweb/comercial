@echo off
REM Equivalente Windows de deploy-frontend.sh (Docker Desktop, backend WSL2).
REM Reconstroi e republica a SPA do frontend no volume servido pelo nginx.
REM
REM O --no-cache eh obrigatorio: "docker compose build" sem essa flag pode
REM reaproveitar uma camada antiga e pular o "COPY frontend/ ./" mais recente
REM (ver docs/07-dev-environment.md, "Armadilhas conhecidas").
REM
REM Sem passar UID/GID: o truque de UID/GID do host (usado no deploy-frontend.sh
REM para nao gerar arquivo dono de root em bind mount Linux) nao se aplica aqui
REM - o Docker Desktop no Windows mapeia permissoes de arquivo por fora desse
REM mecanismo, entao os defaults do docker-compose.yml (UID/GID 1000) bastam.
setlocal enabledelayedexpansion
cd /d "%~dp0"

echo Reconstruindo o frontend (--no-cache)...
docker compose build --no-cache nuxt-build
if errorlevel 1 goto :error

docker compose --profile build run --rm nuxt-build
if errorlevel 1 goto :error

docker compose restart nginx
if errorlevel 1 goto :error

echo Frontend republicado.
exit /b 0

:error
echo ERRO: republicacao do frontend interrompida.
exit /b 1
