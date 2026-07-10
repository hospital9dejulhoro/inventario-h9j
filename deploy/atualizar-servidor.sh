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

# Garante os 3 ambientes: producao, homologacao (HomologaRM) e testes (ontemrm)
ENV_FILE="config/environments.php"
if [ -f "$ENV_FILE" ]; then
  cp "$ENV_FILE" "${ENV_FILE}.bak.$(date +%Y%m%d%H%M%S)"

  python3 - <<'PY'
from pathlib import Path

path = Path("config/environments.php")
text = path.read_text(encoding="utf-8")
changed = False

homolog_block = """
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

testes_block = """
    'testes' => [
        'label'                    => 'Testes',
        'host'                     => '172.20.0.15',
        'database'                 => 'ontemrm',
        'usuario'                  => 'rm',
        'senha'                    => 'rm',
        'badge_class'              => 'bg-info text-dark',
        'trust_server_certificate' => true,
    ],
"""

if "'homologacao'" not in text:
    if "    'testes'" in text:
        text = text.replace("    'testes'", homolog_block + "    'testes'", 1)
    else:
        text = text.replace("];", homolog_block + "];", 1)
    changed = True
    print("Ambiente homologacao inserido.")

if "'testes'" not in text:
    if "    'homologacao'" in text:
        # Insere testes depois do bloco homologacao (antes do ]); final)
        text = text.replace("];", testes_block + "];", 1)
    else:
        text = text.replace("];", testes_block + "];", 1)
    changed = True
    print("Ambiente testes (ontemrm) inserido.")

if changed:
    path.write_text(text, encoding="utf-8")
else:
    print("Ambientes producao/homologacao/testes ja presentes.")
PY
fi

chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;

systemctl restart php8.3-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

echo "Atualizado com sucesso ($(git rev-parse --short HEAD))"
if [ -f "$ENV_FILE" ]; then
  grep -E "'(producao|homologacao|testes)'|database" "$ENV_FILE" || true
fi
