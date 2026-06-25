@echo off
REM Envia a aplicacao inventario para o servidor 172.20.0.43
REM Uso: deploy\enviar-para-servidor.bat

set SERVIDOR=adalton@172.20.0.43
set ORIGEM=c:\xampp\htdocs\www\inventario
set DESTINO=/tmp/inventario

echo Enviando arquivos para %SERVIDOR%:%DESTINO% ...
echo.

scp -r "%ORIGEM%" %SERVIDOR%:%DESTINO%

if %ERRORLEVEL% EQU 0 (
    echo.
    echo Arquivos enviados com sucesso!
    echo.
    echo Agora no servidor SSH execute:
    echo   sudo mkdir -p /var/www
    echo   sudo rm -rf /var/www/inventario
    echo   sudo mv /tmp/inventario /var/www/inventario
    echo   cd /var/www/inventario/deploy ^&^& sudo bash install-ubuntu.sh
    echo.
    echo Acesse: http://172.20.0.43/
) else (
    echo.
    echo Falha no envio. Verifique senha SSH e conectividade.
)

pause
