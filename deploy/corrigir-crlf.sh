#!/bin/bash
# Corrige fim de linha Windows (CRLF) nos scripts .sh
# Uso: bash corrigir-crlf.sh

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
for f in "$SCRIPT_DIR"/*.sh; do
  [ -f "$f" ] || continue
  sed -i 's/\r$//' "$f"
  chmod +x "$f"
  echo "Corrigido: $f"
done
