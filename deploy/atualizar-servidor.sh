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

# Git reclama se o dono do repo difere do usuario que executa o pull
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

if [ ! -f config/environments.php ]; then
  echo "AVISO: config/environments.php nao existe."
  echo "Copie: cp config/environments.example.php config/environments.php"
  exit 1
fi

git fetch origin
git checkout "$BRANCH"
git pull origin "$BRANCH"

# Migra ambiente antigo "testes/ontemrm" -> "homologacao/HomologaRM"
ENV_FILE="config/environments.php"
if [ -f "$ENV_FILE" ] && grep -q "'testes'" "$ENV_FILE"; then
  echo "Migrando ambiente testes -> homologacao (HomologaRM)..."
  cp "$ENV_FILE" "${ENV_FILE}.bak.$(date +%Y%m%d%H%M%S)"
  sed -i \
    -e "s/'testes'/'homologacao'/g" \
    -e "s/'Testes'/'Homologação'/g" \
    -e "s/'ontemrm'/'HomologaRM'/g" \
    "$ENV_FILE"
  echo "Ambiente atualizado em $ENV_FILE (backup .bak criado)."
fi

chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;

systemctl restart php8.3-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

echo "Atualizado com sucesso ($(git rev-parse --short HEAD))"
if grep -q "'homologacao'" "$ENV_FILE" 2>/dev/null; then
  echo "Ambiente Homologação presente em config/environments.php"
fi
