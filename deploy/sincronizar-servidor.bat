@echo off
REM Sincroniza a aplicacao para o servidor.
REM Prefira o caminho por Git (git push + atualizar-servidor.sh): ele deixa
REM registro do que esta no ar e permite voltar atras. Use isto so quando o
REM Git nao estiver disponivel.
set SERVIDOR=adalton@172.20.0.43
set ORIGEM=c:\xampp\htdocs\www\inventario
set DEST=/tmp/inventario-sync

echo Enviando para %SERVIDOR%:%DEST% ...
ssh %SERVIDOR% "mkdir -p %DEST%"
scp "%ORIGEM%\bootstrap.php" "%ORIGEM%\index.php" "%ORIGEM%\inventario.php" "%ORIGEM%\inventario-item.php" %SERVIDOR%:%DEST%/
scp "%ORIGEM%\conectar.php" "%ORIGEM%\desconectar.php" "%ORIGEM%\vincular-contagem.php" %SERVIDOR%:%DEST%/
scp "%ORIGEM%\por-lote.php" "%ORIGEM%\por-lote-salvar.php" %SERVIDOR%:%DEST%/
scp "%ORIGEM%\sem-lote.php" "%ORIGEM%\sem-lote-salvar.php" %SERVIDOR%:%DEST%/
scp "%ORIGEM%\relatorio.php" "%ORIGEM%\posicao.php" %SERVIDOR%:%DEST%/
scp -r "%ORIGEM%\src" "%ORIGEM%\views" "%ORIGEM%\assets" %SERVIDOR%:%DEST%/

echo.
echo No servidor execute:
echo   sudo cp -r %DEST%/* /var/www/inventario/
echo   sudo chown -R www-data:www-data /var/www/inventario
echo   sudo systemctl restart php8.3-fpm
echo.
echo NAO copie a pasta config/: environments.php do servidor tem as senhas
echo de producao e seria sobrescrito pelo arquivo local.
echo.
echo Teste: http://172.20.0.43:9080/
pause
