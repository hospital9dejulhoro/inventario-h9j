#!/bin/bash
# Roda a suite inteira. Devolve 0 quando tudo passa, 1 na primeira falha,
# para poder entrar num gancho de deploy.
#
# Uso:  bash tests/rodar.sh

cd "$(dirname "$0")/.." || exit 1

falhas=0
total=0

for teste in tests/*.php; do
    total=$((total + 1))
    nome="$(basename "$teste")"
    saida="$(php "$teste" 2>&1)"
    codigo=$?

    if [ $codigo -eq 0 ]; then
        printf '  ok    %-26s %s\n' "$nome" "$(echo "$saida" | tail -1)"
    else
        falhas=$((falhas + 1))
        printf '  FALHA %-26s\n' "$nome"
        echo "$saida" | sed 's/^/        /'
    fi
done

echo
if [ $falhas -eq 0 ]; then
    echo "$total teste(s), todos passaram."
    exit 0
fi

echo "$falhas de $total teste(s) falharam."
exit 1
