<?php

/**
 * Consultas de inventário no TOTVS RM (TINVENTARIO / TITMINVENTARIO).
 */
class InventarioRM
{
    private const CODCOLIGADA = 1;

    /** Teto de lotes carregados na tela; acima disso use a busca no servidor. */
    public const LIMITE_LOTES = 4000;



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

        // Local vem dos itens gerados (TITMINVENTARIO). A tabela TINVENTARIOLOCESTOQUE
        // não existe em todos os bancos RM deste hospital.
        $SQL = "SELECT TOP 1 1 AS OK
                FROM TITMINVENTARIO
                WHERE CODCOLIGADA = {$col}
                  AND CODINVENTARIO = '{$inv}'
                  AND (
                        LTRIM(RTRIM(CODLOC)) = '{$loc}'
                     OR RIGHT(REPLICATE('0', 3) + LTRIM(RTRIM(CODLOC)), 3) = '{$loc}'
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
     * Quantidade de itens gerados no inventário RM (TITMINVENTARIO).
     */
    public static function contarItensInventario(string $codinventario, string $codloc = ''): int
    {
        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            return 0;
        }

        $c = new Connection('RM');
        $inv = self::sqlStr($codinventario);
        $col = self::CODCOLIGADA;
        $whereLoc = '';

        if ($codloc !== '') {
            $loc = self::sqlStr(LocaisEstoque::normalizar($codloc));
            $whereLoc = " AND (
                LTRIM(RTRIM(CODLOC)) = '{$loc}'
                OR RIGHT(REPLICATE('0', 3) + LTRIM(RTRIM(CODLOC)), 3) = '{$loc}'
            )";
        }

        $SQL = "SELECT COUNT(*) AS TOTAL
                FROM TITMINVENTARIO
                WHERE CODCOLIGADA = {$col}
                  AND CODINVENTARIO = '{$inv}'
                  {$whereLoc}";
        $c->Consulta($SQL);

        if (!$c->Resultado()) {
            return 0;
        }

        return (int) ($c->linha['TOTAL'] ?? 0);
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
            $whereLoc = " AND (
                LTRIM(RTRIM(I.CODLOC)) = '{$loc}'
                OR RIGHT(REPLICATE('0', 3) + LTRIM(RTRIM(I.CODLOC)), 3) = '{$loc}'
            )";
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

    /**
     * Itens do inventário RM sem controle de lote (sem cadastro em TLOTEPRD).
     *
     * @return array<int, array{idprd: int, codigo: string, nome: string, und: string, codloc: string}>
     */
    public static function listarItensSemLote(string $codinventario, string $codloc = ''): array
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
            $whereLoc = " AND (
                LTRIM(RTRIM(I.CODLOC)) = '{$loc}'
                OR RIGHT(REPLICATE('0', 3) + LTRIM(RTRIM(I.CODLOC)), 3) = '{$loc}'
            )";
        }

        $SQL = "SELECT TOP 4000
                    I.IDPRD,
                    MAX(RTRIM(I.CODLOC)) AS CODLOC,
                    MAX(T.CODIGOPRD) AS CODIGO,
                    MAX(T.NOMEFANTASIA) AS NOME,
                    MAX(TPRODUTODEF.CODUNDCONTROLE) AS UND
                FROM TITMINVENTARIO I
                LEFT JOIN TPRODUTO T ON T.IDPRD = I.IDPRD
                LEFT JOIN TPRODUTODEF ON TPRODUTODEF.IDPRD = I.IDPRD
                WHERE I.CODCOLIGADA = {$col}
                  AND I.CODINVENTARIO = '{$inv}'
                  {$whereLoc}
                  AND NOT EXISTS (
                        SELECT 1 FROM TLOTEPRD L WHERE L.IDPRD = I.IDPRD
                  )
                GROUP BY I.IDPRD
                ORDER BY MAX(T.NOMEFANTASIA), I.IDPRD";
        $c->Consulta($SQL);

        $itens = [];
        while ($c->Resultado()) {
            $itens[] = [
                'idprd'  => (int) ($c->linha['IDPRD'] ?? 0),
                'codigo' => encode_db_value((string) ($c->linha['CODIGO'] ?? '')),
                'nome'   => encode_db_value((string) ($c->linha['NOME'] ?? '')),
                'und'    => encode_db_value((string) ($c->linha['UND'] ?? '')),
                'codloc' => encode_db_value((string) ($c->linha['CODLOC'] ?? '')),
            ];
        }

        return $itens;
    }

    /**
     * Escapa curingas de LIKE além das aspas (% _ [ são curingas no T-SQL).
     */
    private static function sqlLike(string $value): string
    {
        $value = self::sqlStr($value);

        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value);
    }

    /**
     * Condição de CODLOC tolerante a zero à esquerda ('28' e '028' no mesmo banco).
     */
    private static function condicaoCodloc(string $alias, string $codloc): string
    {
        $loc = self::sqlStr(LocaisEstoque::normalizar($codloc));

        if ($loc === '') {
            return '';
        }

        return " AND (
                LTRIM(RTRIM({$alias}.CODLOC)) = '{$loc}'
                OR RIGHT(REPLICATE('0', 3) + LTRIM(RTRIM({$alias}.CODLOC)), 3) = '{$loc}'
            )";
    }

    /**
     * Lotes com registro no local, para os produtos do inventário.
     *
     * A origem é TLOTEPRDLOC (saldo de lote POR LOCAL), não TLOTEPRD direto:
     * TLOTEPRD é o cadastro de lotes do produto e não tem local, então partir
     * dele arrastaria todo o histórico de lotes de cada produto — em almoxarifado
     * de farmácia isso são dezenas por item, quase todos já consumidos.
     *
     * Não filtra SALDOFISICO2 <> 0 de propósito: lote que zerou no sistema mas
     * está na prateleira é exatamente a divergência que o inventário existe para
     * achar. O saldo vai junto na listagem para a conferência ficar visível.
     *
     * $busca filtra no servidor por lote, nome ou código do produto, para quando
     * a lista estourar o limite e o filtro do navegador não alcançar o resto.
     *
     * @return array<int, array{idprd: int, idlote: int, numlote: string, codigo: string, nome: string, und: string, codloc: string, validade: string, saldo: float}>
     */
    public static function listarLotesDoInventario(
        string $codinventario,
        string $codloc = '',
        string $busca = '',
        int $limite = self::LIMITE_LOTES
    ): array {
        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            return [];
        }

        $limite = max(1, min(self::LIMITE_LOTES, $limite));
        $c = new Connection('RM');
        $inv = self::sqlStr($codinventario);
        $col = self::CODCOLIGADA;
        $locItens = self::condicaoCodloc('ITM', $codloc);
        $locLotes = self::condicaoCodloc('LL', $codloc);

        $whereBusca = '';
        $busca = trim($busca);
        if ($busca !== '') {
            $like = self::sqlLike($busca);
            $whereBusca = " AND (
                L.NUMLOTE LIKE '%{$like}%'
                OR T.NOMEFANTASIA LIKE '%{$like}%'
                OR T.CODIGOPRD LIKE '%{$like}%'
            )";
        }

        // DISTINCT no subselect: TITMINVENTARIO pode ter mais de uma linha para o
        // mesmo produto, e isso duplicaria cada lote inflando o SUM do saldo.
        $SQL = "SELECT TOP {$limite}
                    I.IDPRD,
                    LL.IDLOTE,
                    MAX(RTRIM(L.NUMLOTE)) AS NUMLOTE,
                    MAX(RTRIM(LL.CODLOC)) AS CODLOC,
                    MAX(T.CODIGOPRD) AS CODIGO,
                    MAX(T.NOMEFANTASIA) AS NOME,
                    MAX(TPRODUTODEF.CODUNDCONTROLE) AS UND,
                    MAX(L.DATAVALIDADE) AS VALIDADE,
                    SUM(LL.SALDOFISICO2) AS SALDO
                FROM (
                    SELECT DISTINCT ITM.IDPRD
                    FROM TITMINVENTARIO ITM
                    WHERE ITM.CODCOLIGADA = {$col}
                      AND ITM.CODINVENTARIO = '{$inv}'
                      {$locItens}
                ) I
                INNER JOIN TLOTEPRDLOC LL
                    ON LL.IDPRD = I.IDPRD
                   AND LL.CODCOLIGADA = {$col}
                   {$locLotes}
                INNER JOIN TLOTEPRD L
                    ON L.IDPRD = LL.IDPRD
                   AND L.IDLOTE = LL.IDLOTE
                   AND L.CODCOLIGADA = LL.CODCOLIGADA
                LEFT JOIN TPRODUTO T ON T.IDPRD = I.IDPRD
                LEFT JOIN TPRODUTODEF ON TPRODUTODEF.IDPRD = I.IDPRD
                WHERE 1 = 1
                  {$whereBusca}
                GROUP BY I.IDPRD, LL.IDLOTE
                ORDER BY MAX(T.NOMEFANTASIA), MAX(L.DATAVALIDADE), MAX(RTRIM(L.NUMLOTE))";
        $c->Consulta($SQL);

        $lotes = [];
        while ($c->Resultado()) {
            $lotes[] = [
                'idprd'    => (int) ($c->linha['IDPRD'] ?? 0),
                'idlote'   => (int) ($c->linha['IDLOTE'] ?? 0),
                'numlote'  => encode_db_value((string) ($c->linha['NUMLOTE'] ?? '')),
                'codigo'   => encode_db_value((string) ($c->linha['CODIGO'] ?? '')),
                'nome'     => encode_db_value((string) ($c->linha['NOME'] ?? '')),
                'und'      => encode_db_value((string) ($c->linha['UND'] ?? '')),
                'codloc'   => encode_db_value((string) ($c->linha['CODLOC'] ?? '')),
                'validade' => self::formatDate($c->linha['VALIDADE'] ?? null),
                'saldo'    => (float) ($c->linha['SALDO'] ?? 0),
            ];
        }

        return $lotes;
    }

    /**
     * Inventários em aberto no RM (TINVENTARIO.STATUS = 'A').
     *
     * @return array<int, array{
     *   codinventario: string,
     *   status: string,
     *   status_label: string,
     *   codloc: string,
     *   local_nome: string,
     *   data: string,
     *   data_status: string,
     *   itens: int
     * }>
     */
    public static function listarAbertos(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $col = self::CODCOLIGADA;

        $c = new Connection('RM');
        $SQL = "SELECT TOP {$limit}
                    RTRIM(CODINVENTARIO) AS CODINVENTARIO,
                    RTRIM(CAST(STATUS AS VARCHAR(10))) AS STATUS,
                    DATABASEINVENTARIO,
                    DATASTATUS
                FROM TINVENTARIO
                WHERE CODCOLIGADA = {$col}
                  AND RTRIM(CAST(STATUS AS VARCHAR(10))) = 'A'
                ORDER BY DATABASEINVENTARIO DESC, CODINVENTARIO DESC";
        $c->Consulta($SQL);

        $lista = [];
        while ($c->Resultado()) {
            $cod = encode_db_value((string) ($c->linha['CODINVENTARIO'] ?? ''));
            $codloc = '';
            if (class_exists('ZMDCODBARRAS')) {
                $parsed = ZMDCODBARRAS::parseCodigoInventario($cod);
                if (!empty($parsed['valid'])) {
                    $codloc = (string) $parsed['codloc'];
                }
            }
            if ($codloc === '' && preg_match('/^\d{2}\.(\d{3})\.\d{3}$/', $cod, $m)) {
                $codloc = $m[1];
            }

            $lista[] = [
                'codinventario' => $cod,
                'status'        => encode_db_value((string) ($c->linha['STATUS'] ?? 'A')),
                'status_label'  => 'Aberto',
                'codloc'        => $codloc,
                'local_nome'    => $codloc !== '' ? LocaisEstoque::nome($codloc) : '',
                'data'          => self::formatDateTime($c->linha['DATABASEINVENTARIO'] ?? null),
                'data_status'   => self::formatDateTime($c->linha['DATASTATUS'] ?? null),
                'itens'         => 0,
            ];
        }

        foreach ($lista as $i => $item) {
            if ($item['codinventario'] !== '') {
                $lista[$i]['itens'] = self::contarItensInventario($item['codinventario'], $item['codloc']);
            }
        }

        return $lista;
    }

    /**
     * Só a data — validade de lote não tem hora útil.
     *
     * @param mixed $value
     */
    private static function formatDate($value): string
    {
        return self::formatDateTime($value, 'd/m/Y');
    }

    /**
     * @param mixed $value
     */
    private static function formatDateTime($value, string $formato = 'd/m/Y H:i'): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format($formato);
        }
        if ($value instanceof DateTime) {
            return $value->format($formato);
        }
        if (is_object($value) && method_exists($value, 'format')) {
            try {
                return (string) $value->format($formato);
            } catch (Throwable $e) {
                // continua
            }
        }
        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);
            if ($ts !== false) {
                return date($formato, $ts);
            }
            return $value;
        }
        return '';
    }

    /**
     * @deprecated Use ZMDCODBARRAS::idprdDoBarcode(), dona do layout do código.
     */
    public static function idprdDoBarcode(string $codigobarras): int
    {
        return ZMDCODBARRAS::idprdDoBarcode($codigobarras);
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
                  AND IDPRD = {$idprd}
                  AND (
                        LTRIM(RTRIM(CODLOC)) = '{$loc}'
                     OR RIGHT(REPLICATE('0', 3) + LTRIM(RTRIM(CODLOC)), 3) = '{$loc}'
                  )";
        $c->Consulta($SQL);

        return (bool) $c->Resultado();
    }
}
