#!/bin/bash
# Instalacao Inventario RM - Ubuntu 24.04 + Nginx + PHP-FPM
# NAO altera sites existentes — usa porta 8080 por padrao
# Uso: sudo bash install-ubuntu.sh

set -e

if [ "$(id -u)" -ne 0 ]; then
  echo "Execute com sudo: sudo bash install-ubuntu.sh"
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
DEPLOY_DIR="/var/www/inventario"
INVENTARIO_PORT="${INVENTARIO_PORT:-9080}"

echo "==> Atualizando pacotes..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq

echo "==> Instalando PHP-FPM e dependencias (nginx nao sera substituido)..."
apt-get install -y \
  php-fpm \
  php-cli \
  php-curl \
  php-xml \
  php-mbstring \
  php-soap \
  php-dev \
  php-pear \
  build-essential \
  curl \
  gnupg \
  unixodbc-dev \
  nginx-common

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_FPM_SOCK="/var/run/php/php${PHP_VERSION}-fpm.sock"
echo "    PHP: ${PHP_VERSION}"
echo "    FPM socket: ${PHP_FPM_SOCK}"

echo "==> Instalando Microsoft ODBC Driver 18..."
if [ ! -f /etc/apt/sources.list.d/microsoft-prod.list ]; then
  curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg
  curl -fsSL https://packages.microsoft.com/config/ubuntu/24.04/prod.list | tee /etc/apt/sources.list.d/microsoft-prod.list > /dev/null
  apt-get update -qq
fi

ACCEPT_EULA=Y apt-get install -y msodbcsql18

echo "==> Instalando extensoes PHP sqlsrv..."
if ! php -m 2>/dev/null | grep -qi sqlsrv; then
  printf "\n" | pecl install sqlsrv
  printf "\n" | pecl install pdo_sqlsrv
fi

echo "extension=sqlsrv.so" > "/etc/php/${PHP_VERSION}/mods-available/sqlsrv.ini"
echo "extension=pdo_sqlsrv.so" > "/etc/php/${PHP_VERSION}/mods-available/pdo_sqlsrv.ini"
phpenmod -v "${PHP_VERSION}" -s cli sqlsrv pdo_sqlsrv 2>/dev/null || phpenmod sqlsrv pdo_sqlsrv
phpenmod -v "${PHP_VERSION}" -s fpm sqlsrv pdo_sqlsrv 2>/dev/null || true

echo "==> Configurando site Inventario na porta ${INVENTARIO_PORT}..."
mkdir -p "$DEPLOY_DIR"

if [ -f "${SCRIPT_DIR}/nginx-inventario-8080.conf" ]; then
  sed -e "s|__PHP_FPM_SOCK__|${PHP_FPM_SOCK}|g" \
      -e "s|__INVENTARIO_PORT__|${INVENTARIO_PORT}|g" \
      "${SCRIPT_DIR}/nginx-inventario-8080.conf" > /etc/nginx/sites-available/inventario-rm
  ln -sf /etc/nginx/sites-available/inventario-rm /etc/nginx/sites-enabled/inventario-rm
fi

echo "    NAO remove sites existentes em sites-enabled/"

nginx -t

echo "==> Ajustando permissoes..."
chown -R www-data:www-data "$DEPLOY_DIR"
find "$DEPLOY_DIR" -type d -exec chmod 755 {} \;
find "$DEPLOY_DIR" -type f -exec chmod 644 {} \;

systemctl restart "php${PHP_VERSION}-fpm"
systemctl enable "php${PHP_VERSION}-fpm"

echo "==> Recarregando Nginx (sem parar outros sistemas)..."
if systemctl is-active --quiet nginx; then
  systemctl reload nginx
elif nginx -s reload 2>/dev/null; then
  echo "    Nginx recarregado via nginx -s reload"
else
  echo "    AVISO: Nginx systemd inativo. Tentando iniciar..."
  systemctl start nginx || {
    echo ""
    echo "ERRO: Nginx nao iniciou. Outro processo pode estar na porta 80."
    echo "Execute: sudo bash ${SCRIPT_DIR}/diagnostico-nginx.sh"
    echo "O Inventario foi configurado na porta ${INVENTARIO_PORT}."
    echo "Apos corrigir o Nginx: sudo nginx -t && sudo systemctl reload nginx"
    exit 1
  }
fi

echo ""
echo "=============================================="
echo " Instalacao concluida!"
echo "=============================================="
php -m | grep -i sqlsrv && echo "sqlsrv CLI: OK" || echo "AVISO: sqlsrv CLI ausente"
php-fpm8.3 -m 2>/dev/null | grep -i sqlsrv && echo "sqlsrv FPM: OK" || true
echo ""
echo "URL: http://172.20.0.43:${INVENTARIO_PORT}/"
echo "Outros sistemas na porta 80 permanecem intactos."
