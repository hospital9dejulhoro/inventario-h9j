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

# Garante ambiente Homologação (HomologaRM) sem remover Testes (ontemrm)
ENV_FILE="config/environments.php"
if [ -f "$ENV_FILE" ] && ! grep -q "'homologacao'" "$ENV_FILE"; then
  echo "Incluindo ambiente homologacao (HomologaRM) em $ENV_FILE..."
  cp "$ENV_FILE" "${ENV_FILE}.bak.$(date +%Y%m%d%H%M%S)"
  python3 - <<'PY'
from pathlib import Path
path = Path("config/environments.php")
text = path.read_text(encoding="utf-8")
block = """
    'homologacao' => [
        'label'                    => 'Homologação',
        'host'                     => '172.20.0.15',
        'database'                 => 'HomologaRM',
        'usuario'                  => 'rm',
        'senha'                    => 'rm',
        'badge_class'              => 'bg-warning text-dark',
        'trust_server_certificate' => true,
    ],
"""
# Insere homologacao antes de testes, se existir; senão antes do ]; final
needle = "    'testes'"
if needle in text:
    text = text.replace(needle, block + needle, 1)
else:
    text = text.replace("];", block + "];", 1)
path.write_text(text, encoding="utf-8")
print("Ambiente homologacao inserido.")
PY
fi

chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;

systemctl restart php8.3-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

echo "Atualizado com sucesso ($(git rev-parse --short HEAD))"
if [ -f "$ENV_FILE" ]; then
  grep -E "'(producao|homologacao|testes)'" "$ENV_FILE" || true
fi
