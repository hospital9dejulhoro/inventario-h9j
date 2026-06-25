#!/bin/bash
# Atualiza a aplicacao no servidor via git pull
# Uso: sudo bash atualizar-servidor.sh

set -e

APP_DIR="/var/www/inventario"
BRANCH="${1:-main}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Execute com sudo"
  exit 1
fi

if [ ! -d "$APP_DIR/.git" ]; then
  echo "Repositorio git nao encontrado em $APP_DIR"
  echo "Clone primeiro: git clone <URL> $APP_DIR"
  exit 1
fi

cd "$APP_DIR"

if [ ! -f config/environments.php ]; then
  echo "AVISO: config/environments.php nao existe."
  echo "Copie: cp config/environments.example.php config/environments.php"
  exit 1
fi

git fetch origin
git checkout "$BRANCH"
git pull origin "$BRANCH"

chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;

systemctl restart php8.3-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

echo "Atualizado com sucesso ($(git rev-parse --short HEAD))"
