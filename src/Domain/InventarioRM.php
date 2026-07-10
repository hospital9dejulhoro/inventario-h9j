<?php

/**
 * Consultas de inventário no TOTVS RM (TINVENTARIO / TITMINVENTARIO).
 */
class InventarioRM
{
    private const CODCOLIGADA = 1;

    /**
     * Escapa aspas simples para uso em literais SQL (valores já normalizados).
     */
    private static function sqlStr(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /**
     * @return array{valid: bool, codinventario: string, status: string, error: string}
     */
    public static function existeNoRm(string $codinventario): array
    {
        $codinventario = trim($codinventario);
        $result = [
            'valid'         => false,
            'codinventario' => $codinventario,
            'status'        => '',
            'error'         => '',
        ];

        if ($codinventario === '') {
            $result['error'] = 'Informe o código do inventário.';
            return $result;
        }

        $c = new Connection('RM');
        $inv = self::sqlStr($codinventario);
        $SQL = "SELECT TOP 1 CODINVENTARIO, STATUS
                FROM TINVENTARIO
                WHERE CODCOLIGADA = " . self::CODCOLIGADA . "
                  AND CODINVENTARIO = '{$inv}'";
        $c->Consulta($SQL);

        if (!$c->Resultado()) {
            $result['error'] = "Inventário {$codinventario} não está cadastrado no RM (TINVENTARIO). Cadastre-o antes de usar na leitura.";
            return $result;
        }

        $result['valid'] = true;
        $result['codinventario'] = encode_db_value($c->linha['CODINVENTARIO'] ?? $codinventario);
        $result['status'] = encode_db_value((string) ($c->linha['STATUS'] ?? ''));

        return $result;
    }

    public static function localPertenceAoInventario(string $codinventario, string $codloc): bool
    {
        $codinventario = trim($codinventario);
        $codloc = LocaisEstoque::normalizar($codloc);

        if ($codinventario === '' || $codloc === '') {
            return false;
        }

        $c = new Connection('RM');
        $inv = self::sqlStr($codinventario);
        $loc = self::sqlStr($codloc);
        $col = self::CODCOLIGADA;

        $SQL = "SELECT TOP 1 1 AS OK
                WHERE EXISTS (
                    SELECT 1 FROM TINVENTARIOLOCESTOQUE
                    WHERE CODCOLIGADA = {$col}
                      AND CODINVENTARIO = '{$inv}'
                      AND LTRIM(RTRIM(CODLOC)) = '{$loc}'
                )
                OR EXISTS (
                    SELECT 1 FROM TITMINVENTARIO
                    WHERE CODCOLIGADA = {$col}
                      AND CODINVENTARIO = '{$inv}'
                      AND LTRIM(RTRIM(CODLOC)) = '{$loc}'
                )";
        $c->Consulta($SQL);

        return (bool) $c->Resultado();
    }

    /**
     * Valida inventário + local para uso na leitura.
     *
     * @return array{valid: bool, status: string, error: string}
     */
    public static function validarParaUso(string $codinventario, string $codloc): array
    {
        $existe = self::existeNoRm($codinventario);
        if (!$existe['valid']) {
            return [
                'valid'  => false,
                'status' => '',
                'error'  => $existe['error'],
            ];
        }

        if (!self::localPertenceAoInventario($codinventario, $codloc)) {
            $codloc = LocaisEstoque::normalizar($codloc);
            $nome = LocaisEstoque::nome($codloc);
            $label = $nome !== '' ? "{$codloc} — {$nome}" : $codloc;

            return [
                'valid'  => false,
                'status' => $existe['status'],
                'error'  => "O local {$label} não está vinculado ao inventário {$codinventario} no RM.",
            ];
        }

        return [
            'valid'  => true,
            'status' => $existe['status'],
            'error'  => '',
        ];
    }

    /**
     * Itens gerados no inventário RM (TITMINVENTARIO).
     *
     * @return array<int, array{idprd: string, nome: string, und: string, codloc: string, numlote: string}>
     */
    public static function listarItensInventario(string $codinventario, string $codloc = ''): array
    {
        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            return [];
        }

        $c = new Connection('RM');
        $inv = self::sqlStr($codinventario);
        $col = self::CODCOLIGADA;
        $whereLoc = '';

        if ($codloc !== '') {
            $loc = self::sqlStr(LocaisEstoque::normalizar($codloc));
            $whereLoc = " AND LTRIM(RTRIM(I.CODLOC)) = '{$loc}'";
        }

        $SQL = "SELECT TOP 2000 I.IDPRD, I.CODLOC, T.NOMEFANTASIA AS NOME,
                       TPRODUTODEF.CODUNDCONTROLE AS UND
                FROM TITMINVENTARIO I
                LEFT JOIN TPRODUTO T ON T.IDPRD = I.IDPRD
                LEFT JOIN TPRODUTODEF ON TPRODUTODEF.IDPRD = I.IDPRD
                WHERE I.CODCOLIGADA = {$col}
                  AND I.CODINVENTARIO = '{$inv}'
                  {$whereLoc}
                ORDER BY T.NOMEFANTASIA, I.IDPRD";
        $c->Consulta($SQL);

        $itens = [];
        while ($c->Resultado()) {
            $itens[] = [
                'idprd'   => encode_db_value((string) ($c->linha['IDPRD'] ?? '')),
                'nome'    => encode_db_value($c->linha['NOME'] ?? ''),
                'und'     => encode_db_value($c->linha['UND'] ?? ''),
                'codloc'  => encode_db_value($c->linha['CODLOC'] ?? ''),
                'numlote' => '',
            ];
        }

        return $itens;
    }

    public static function idprdDoBarcode(string $codigobarras): int
    {
        $digits = preg_replace('/\D/', '', $codigobarras);
        if (strlen($digits) < 7) {
            return 0;
        }

        return (int) substr($digits, 0, 7);
    }

    public static function itemPertenceAoInventario(string $codinventario, string $codloc, int $idprd): bool
    {
        $codinventario = trim($codinventario);
        $codloc = LocaisEstoque::normalizar($codloc);

        if ($codinventario === '' || $codloc === '' || $idprd <= 0) {
            return false;
        }

        $c = new Connection('RM');
        $inv = self::sqlStr($codinventario);
        $loc = self::sqlStr($codloc);
        $col = self::CODCOLIGADA;

        $SQL = "SELECT TOP 1 1 AS OK
                FROM TITMINVENTARIO
                WHERE CODCOLIGADA = {$col}
                  AND CODINVENTARIO = '{$inv}'
                  AND LTRIM(RTRIM(CODLOC)) = '{$loc}'
                  AND IDPRD = {$idprd}";
        $c->Consulta($SQL);

        return (bool) $c->Resultado();
    }
}
