@echo off
REM Painel de manutencao da loja (menu interativo) - equivalente Windows de
REM deploy.sh. Atualizar o sistema, ver status/logs dos containers e
REM reiniciar. Ver docs/07-dev-environment.md, "Deploy / atualizacao na
REM maquina da loja", pro runbook que a opcao "Atualizar sistema" encapsula:
REM git pull + rebuild dos servicos + migrations + publicacao da SPA.
REM
REM Rodar direto na maquina da loja, na raiz do repo, com a stack ja no ar
REM (docker compose up -d rodado ao menos uma vez antes).
setlocal enabledelayedexpansion
cd /d "%~dp0"

REM Trecho classico pra gerar o caractere ESC (0x1B) num .bat, sem precisar
REM de nada externo - habilita cor no cmd.exe do Windows 10+. Se o terminal
REM nao processar ANSI, so aparece o codigo de escape junto do texto; o
REM script continua funcionando normalmente do mesmo jeito.
for /F %%a in ('echo prompt $E^|cmd') do set "ESC=%%a"
set "GREEN=%ESC%[92m"
set "RED=%ESC%[91m"
set "YELLOW=%ESC%[93m"
set "CYAN=%ESC%[96m"
set "BOLD=%ESC%[1m"
set "RESET=%ESC%[0m"

REM Duracao da ultima atualizacao bem-sucedida (segundos), usada so pra
REM estimar a barra de progresso da proxima vez - nao existe na primeira
REM execucao. Arquivo local, fora do Git (.gitignore).
set "TIMING_FILE=%~dp0.deploy-last-duration"
set "PREV_DURATION=0"
if exist "%TIMING_FILE%" set /p PREV_DURATION=<"%TIMING_FILE%"

:menu
cls
call :banner
echo   Painel de manutencao - maquina da loja
echo.
echo   1. Atualizar sistema
echo   2. Ver status do sistema
echo   3. Ver logs
echo   4. Reiniciar sistema
echo   5. Sair
echo.
choice /C 12345 /N /M "  Escolha uma opcao: "
if errorlevel 5 goto :sair
if errorlevel 4 (
    call :reiniciar
    goto :pausar_menu
)
if errorlevel 3 (
    call :logs
    goto :pausar_menu
)
if errorlevel 2 (
    call :status
    goto :pausar_menu
)
if errorlevel 1 (
    call :atualizar
    goto :pausar_menu
)
goto :menu

:pausar_menu
echo.
pause
goto :menu

:banner
echo %CYAN%%BOLD%
echo   ========================================================
echo     JP PARAFUSOS E ACESSORIOS - SISTEMA COMERCIAL
echo   ========================================================
echo %RESET%
exit /b

REM Timestamp em segundos (epoch) - cmd.exe nao tem isso nativo; PowerShell
REM ja vem em qualquer Windows 10 (exigido pelo Docker Desktop mesmo).
:epoch
for /f %%t in ('powershell -NoProfile -Command "[DateTimeOffset]::UtcNow.ToUnixTimeSeconds()"') do set "NOW=%%t"
exit /b

:format_duration
set /a mins=%1/60
set /a secs=%1%%60
set "FORMATTED=!mins!min !secs!s"
exit /b

:bar
set /a filled=%1*30/100
set /a empty=30-filled
set "BARSTR="
for /L %%i in (1,1,!filled!) do set "BARSTR=!BARSTR!#"
for /L %%i in (1,1,!empty!) do set "BARSTR=!BARSTR!-"
set "BARSTR=[!BARSTR!] %1%%"
exit /b

REM Barra de progresso baseada no tempo decorrido em relacao a ultima
REM execucao (mais precisa que "passo N de 7", ja que passos como o
REM rebuild do Docker variam muito de duracao entre si). Sem historico
REM ainda, cai pra fracao de passos concluidos. Trava em 99%% ate o final
REM de verdade, pra nunca mostrar "100%%" com o deploy ainda rodando.
REM %1=passo atual %2=total de passos %3=rotulo do passo
:step
call :epoch
set /a elapsed=NOW-DEPLOY_START
if !PREV_DURATION! GTR 0 (
    set /a pct=elapsed*100/PREV_DURATION
) else (
    set /a stepn=%1-1
    set /a pct=stepn*100/%2
)
if !pct! GTR 99 set "pct=99"
call :bar !pct!
echo %YELLOW%!BARSTR!%RESET% Passo %1/%2 - %~3...
exit /b

:atualizar
cls
call :banner
echo %BOLD%Atualizando o sistema...%RESET%
if !PREV_DURATION! GTR 0 (
    call :format_duration !PREV_DURATION!
    echo Estimativa: ~!FORMATTED! ^(baseado na ultima atualizacao^)
) else (
    echo Estimativa: primeira execucao, sem historico ainda.
)
echo.
call :epoch
set /a DEPLOY_START=NOW

call :step 1 7 "Atualizando codigo (git pull)"
git pull
if errorlevel 1 goto :deploy_erro

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

call :step 2 7 "Reconstruindo imagens"
docker compose build
if errorlevel 1 goto :deploy_erro
docker compose up -d
if errorlevel 1 goto :deploy_erro

call :step 3 7 "Corrigindo permissoes de storage"
REM storage/ e bind mount: se ficar com outro dono no host (root via
REM "exec -u root", restauracao de backup, rebuild com UID diferente), o
REM www-data do container perde escrita em logs/backup - ver
REM docs/07-dev-environment.md, "Armadilhas conhecidas".
docker compose exec -u root php-fpm chown -R www-data:www-data storage bootstrap/cache
docker compose exec -u root php-fpm chmod -R ug+rwX storage bootstrap/cache

call :step 4 7 "Instalando dependencias do Composer"
REM vendor/ nao vai para o Git e o Dockerfile so instala o binario do
REM Composer - como backend/ e bind mount, o install precisa rodar contra
REM o volume ja montado, senao o migrate/artisan abaixo falha.
docker compose exec php-fpm composer install --no-dev --optimize-autoloader
if errorlevel 1 goto :deploy_erro

call :step 5 7 "Rodando migrations"
docker compose exec php-fpm php artisan migrate --force
if errorlevel 1 goto :deploy_erro
docker compose exec php-fpm php artisan storage:link
REM Corrige permissao restritiva (0700) que storage/app/backup possa ter
REM herdado de antes de `visibility => public` (config/filesystems.php).
docker compose exec php-fpm php artisan backups:ensure-directory-permissions

call :step 6 7 "Publicando o frontend"
call "%~dp0deploy-frontend.bat"
if errorlevel 1 goto :deploy_erro

call :step 7 7 "Finalizando"
call :epoch
set /a TOTAL_ELAPSED=NOW-DEPLOY_START
> "%TIMING_FILE%" echo !TOTAL_ELAPSED!

call :format_duration !TOTAL_ELAPSED!
echo.
call :bar 100
echo %GREEN%!BARSTR!%RESET%
echo %GREEN%%BOLD%Sistema atualizado com sucesso em !FORMATTED!.%RESET%
exit /b 0

:deploy_erro
echo.
echo %RED%%BOLD%ERRO: atualizacao interrompida.%RESET%
exit /b 1

:status
cls
call :banner
echo %BOLD%Status dos containers%RESET%
echo.
docker compose ps
exit /b

:logs
cls
call :banner
echo %BOLD%Ver logs%RESET%
echo.
echo   1. php-fpm
echo   2. nginx
echo   3. postgres
echo   4. Todos
echo   5. Voltar
echo.
choice /C 12345 /N /M "  Escolha uma opcao: "
if errorlevel 5 exit /b
if errorlevel 4 (
    docker compose logs --tail=100
    exit /b
)
if errorlevel 3 (
    docker compose logs --tail=100 postgres
    exit /b
)
if errorlevel 2 (
    docker compose logs --tail=100 nginx
    exit /b
)
if errorlevel 1 (
    docker compose logs --tail=100 php-fpm
    exit /b
)
exit /b

:reiniciar
cls
call :banner
echo %BOLD%Reiniciando os containers...%RESET%
echo.
docker compose restart
echo.
echo %GREEN%Reiniciado.%RESET%
exit /b

:sair
echo Ate mais!
exit /b 0
