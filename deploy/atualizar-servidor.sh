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

# Reescreve environments.php limpo com os 3 ambientes (preserva senhas existentes)
ENV_FILE="config/environments.php"
if [ -f "$ENV_FILE" ]; then
  cp "$ENV_FILE" "${ENV_FILE}.bak.$(date +%Y%m%d%H%M%S)"

  python3 - <<'PY'
import re
from pathlib import Path

path = Path("config/environments.php")
text = path.read_text(encoding="utf-8")

def extract_senha(src: str, key: str, default: str = "rm") -> str:
    # Captura a senha dentro do bloco da chave
    pattern = rf"'{key}'\s*=>\s*\[[^\]]*?'senha'\s*=>\s*'([^']*)'"
    m = re.search(pattern, src, flags=re.S)
    return m.group(1) if m else default

senha_prod = extract_senha(text, "producao")
senha_hml = extract_senha(text, "homologacao", senha_prod)
senha_tst = extract_senha(text, "testes", senha_prod)

content = f"""<?php

/**
 * Configuração centralizada dos ambientes TOTVS RM.
 * Arquivo gerado/normalizado pelo deploy (atualizar-servidor.sh).
 */
return [
    'producao' => [
        'label'                    => 'Produção',
        'host'                     => '172.20.0.10',
        'database'                 => 'CorporeRM',
        'usuario'                  => 'rm',
        'senha'                    => '{senha_prod}',
        'badge_class'              => 'bg-danger',
        'trust_server_certificate' => true,
        'api_url'                  => 'http://172.20.0.21:8051',
    ],
    'homologacao' => [
        'label'                    => 'Homologação',
        'host'                     => '172.20.0.15',
        'database'                 => 'HomologaRM',
        'usuario'                  => 'rm',
        'senha'                    => '{senha_hml}',
        'badge_class'              => 'bg-warning text-dark',
        'trust_server_certificate' => true,
        'api_url'                  => 'http://172.20.0.21:8051',
    ],
    'testes' => [
        'label'                    => 'Testes',
        'host'                     => '172.20.0.15',
        'database'                 => 'ontemrm',
        'usuario'                  => 'rm',
        'senha'                    => '{senha_tst}',
        'badge_class'              => 'bg-info text-dark',
        'trust_server_certificate' => true,
        'api_url'                  => 'http://172.20.0.21:8051',
    ],
];
"""

path.write_text(content, encoding="utf-8")
print("environments.php normalizado: producao, homologacao, testes.")
PY
fi

chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;

systemctl restart php8.3-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

echo "Atualizado com sucesso ($(git rev-parse --short HEAD))"
if [ -f "$ENV_FILE" ]; then
  echo "--- ambientes ---"
  grep -nE "'(producao|homologacao|testes)'|'database'" "$ENV_FILE" || true
fi
