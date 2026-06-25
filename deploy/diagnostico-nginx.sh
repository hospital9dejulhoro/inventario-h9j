#!/bin/bash
# Diagnostico e correcao Nginx em servidor com multiplos sistemas
# Uso: sudo bash diagnostico-nginx.sh

echo "========== PORTAS =========="
ss -tlnp | grep -E ':80|:8080|:443' || true

echo ""
echo "========== NGINX SYSTEMD =========="
systemctl status nginx --no-pager -l 2>&1 | head -20

echo ""
echo "========== SITES HABILITADOS =========="
ls -la /etc/nginx/sites-enabled/ 2>/dev/null || true

echo ""
echo "========== PROCESSOS NGINX =========="
ps aux | grep '[n]ginx' || true

echo ""
echo "========== ULTIMOS ERROS =========="
journalctl -u nginx --no-pager -n 15 2>/dev/null || true

echo ""
echo "========== TESTE CONFIG =========="
nginx -t 2>&1 || true

echo ""
echo "========== PHP SQLSRV =========="
php -m 2>/dev/null | grep -i sqlsrv || echo "sqlsrv CLI: ausente"
php-fpm8.3 -m 2>/dev/null | grep -i sqlsrv || echo "sqlsrv FPM: ausente"

echo ""
echo "========== SUGESTOES =========="
echo "1. Restaurar site default se foi removido:"
echo "   sudo ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default"
echo ""
echo "2. Remover config conflitante do inventario na porta 80 (se existir):"
echo "   sudo rm -f /etc/nginx/sites-enabled/inventario"
echo ""
echo "3. Usar inventario apenas na porta 8080:"
echo "   sudo ln -sf /etc/nginx/sites-available/inventario-rm /etc/nginx/sites-enabled/inventario-rm"
echo "   sudo nginx -t && sudo systemctl reload nginx"
echo ""
echo "4. Liberar porta 8080 no firewall:"
echo "   sudo ufw allow 8080/tcp"
