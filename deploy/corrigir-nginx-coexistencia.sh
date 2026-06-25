#!/bin/bash
# Corrige deploy apos instalacao que conflitou com outros sistemas
# Uso: sudo bash corrigir-nginx-coexistencia.sh

set -e

if [ "$(id -u)" -ne 0 ]; then
  echo "Execute com sudo"
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PORT="${INVENTARIO_PORT:-9080}"
PHP_FPM_SOCK="/var/run/php/php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm.sock"

echo "==> Restaurando site default (se existir)..."
if [ -f /etc/nginx/sites-available/default ]; then
  ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
fi

echo "==> Removendo config antiga na porta 80..."
rm -f /etc/nginx/sites-enabled/inventario

echo "==> Aplicando inventario na porta ${PORT}..."
sed -e "s|__PHP_FPM_SOCK__|${PHP_FPM_SOCK}|g" \
    -e "s|__INVENTARIO_PORT__|${PORT}|g" \
    "${SCRIPT_DIR}/nginx-inventario-8080.conf" > /etc/nginx/sites-available/inventario-rm

ln -sf /etc/nginx/sites-available/inventario-rm /etc/nginx/sites-enabled/inventario-rm

nginx -t

echo "==> Recarregando Nginx..."
if systemctl is-active --quiet nginx; then
  systemctl reload nginx
else
  systemctl start nginx || nginx -s reload
fi

echo ""
echo "Corrigido! Inventario: http://172.20.0.43:${PORT}/"
echo "Outros sistemas na porta 80 devem voltar ao normal."
