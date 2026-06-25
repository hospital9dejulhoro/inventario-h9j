@echo off
REM Sincroniza todos os arquivos da aplicacao para o servidor
set SERVIDOR=adalton@172.20.0.43
set ORIGEM=c:\xampp\htdocs\www\inventario
set DEST=/tmp/inventario-sync

echo Enviando para %SERVIDOR%:%DEST% ...
scp -r "%ORIGEM%\bootstrap.php" "%ORIGEM%\index.php" "%ORIGEM%\inventario.php" "%ORIGEM%\conectar.php" "%ORIGEM%\desconectar.php" "%ORIGEM%\diagnostico.php" %SERVIDOR%:%DEST%/
scp -r "%ORIGEM%\config" "%ORIGEM%\src" "%ORIGEM%\views" "%ORIGEM%\assets" %SERVIDOR%:%DEST%/

echo.
echo No servidor execute:
echo   sudo cp -r %DEST%/* /var/www/inventario/
echo   sudo chown -R www-data:www-data /var/www/inventario
echo   sudo systemctl restart php8.3-fpm
echo.
echo Teste: http://172.20.0.43:9080/diagnostico.php
pause
