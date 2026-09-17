<?php

/**
 * Consultas de inventário no TOTVS RM (TINVENTARIO / TITMINVENTARIO).
 */
class InventarioRM
{
    private const CODCOLIGADA = 1;

    /** Teto de lotes carregados na tela; acima disso use a busca no servidor. */
    public const LIMITE_LOTES = 4000;

    /** Teto de produtos na folha "sem lote". */
    public const LIMITE_ITENS = 4000;

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
        $c->Consulta(
            'SELECT TOP 1 CODINVENTARIO, STATUS
             FROM TINVENTARIO
             WHERE CODCOLIGADA = ? AND CODINVENTARIO = ?',
            [self::CODCOLIGADA, $codinventario]
        );

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

        // Local vem dos itens gerados (TITMINVENTARIO). A tabela TINVENTARIOLOCESTOQUE
        // não existe em todos os bancos RM deste hospital.
        $c->Consulta(
            'SELECT TOP 1 1 AS OK
             FROM TITMINVENTARIO
             WHERE CODCOLIGADA = ?
               AND CODINVENTARIO = ?
               AND ' . self::condicaoCodloc(''),
            array_merge([self::CODCOLIGADA, $codinventario], self::paramsCodloc($codloc))
        );

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

        $params = [self::CODCOLIGADA, $codinventario];
        $whereLoc = '';

        if (LocaisEstoque::normalizar($codloc) !== '') {
            $whereLoc = ' AND ' . self::condicaoCodloc('');
            $params = array_merge($params, self::paramsCodloc($codloc));
        }

        $c = new Connection('RM');
        $c->Consulta(
            "SELECT COUNT(*) AS TOTAL
             FROM TITMINVENTARIO
             WHERE CODCOLIGADA = ? AND CODINVENTARIO = ?{$whereLoc}",
            $params
        );

        if (!$c->Resultado()) {
            return 0;
        }

        return (int) ($c->linha['TOTAL'] ?? 0);
    }

    /**
     * Itens do inventário RM sem controle de lote (sem cadastro em TLOTEPRD).
     *
     * Contagem avulsa não tem itens gerados no RM: a lista vem da posição de
     * estoque do local. Sem esse desvio, a tela "Sem lote" abria vazia numa
     * avulsa e os produtos sem lote simplesmente não tinham como ser contados
     * — a tela "Por lote" também não os mostra, porque ela só existe para quem
     * tem lote.
     *
     * @return array<int, array{idprd: int, codigo: string, nome: string, und: string, codloc: string, saldo: float}>
     */
    public static function listarItensSemLote(
        string $codinventario,
        string $codloc = '',
        bool $avulso = false,
        bool $somenteComSaldo = false
    ): array {
        if ($avulso) {
            return self::listarItensSemLoteDoLocal($codloc, $somenteComSaldo);
        }

        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            return [];
        }

        $limite = self::LIMITE_ITENS;
        $params = [self::CODCOLIGADA, $codinventario];
        $whereLoc = '';

        if (LocaisEstoque::normalizar($codloc) !== '') {
            $whereLoc = ' AND ' . self::condicaoCodloc('I');
            $params = array_merge($params, self::paramsCodloc($codloc));
        }

        // PRDLOC entra como LEFT JOIN só para mostrar o saldo ao lado da
        // contagem; produto sem linha de posição no local continua na folha.
        $SQL = "SELECT TOP {$limite}
                    I.IDPRD,
                    MAX(RTRIM(I.CODLOC)) AS CODLOC,
                    MAX(T.CODIGOPRD) AS CODIGO,
                    MAX(T.NOMEFANTASIA) AS NOME,
                    MAX(TPRODUTODEF.CODUNDCONTROLE) AS UND,
                    MAX(PRDLOC.SALDOFISICO2) AS SALDO
                FROM TITMINVENTARIO I
                LEFT JOIN TPRODUTO T ON T.IDPRD = I.IDPRD
                LEFT JOIN TPRODUTODEF ON TPRODUTODEF.IDPRD = I.IDPRD
                LEFT JOIN TPRDLOC PRDLOC
                    ON PRDLOC.IDPRD = I.IDPRD
                   AND LTRIM(RTRIM(PRDLOC.CODLOC)) = LTRIM(RTRIM(I.CODLOC))
                WHERE I.CODCOLIGADA = ?
                  AND I.CODINVENTARIO = ?
                  {$whereLoc}
                  AND NOT EXISTS (
                        SELECT 1 FROM TLOTEPRD L WHERE L.IDPRD = I.IDPRD
                  )
                GROUP BY I.IDPRD
                ORDER BY MAX(T.NOMEFANTASIA), I.IDPRD";

        $c = new Connection('RM');
        $c->Consulta($SQL, $params);

        return self::mapearItensSemLote($c);
    }

    /**
     * Produtos sem controle de lote que têm posição no local.
     *
     * É a folha "sem lote" da contagem avulsa. Espelha a fonte que a tela de
     * lotes usa (TPRDLOC), só que do lado de quem não tem TLOTEPRD.
     *
     * @return array<int, array{idprd: int, codigo: string, nome: string, und: string, codloc: string, saldo: float}>
     */
    public static function listarItensSemLoteDoLocal(string $codloc, bool $somenteComSaldo = false): array
    {
        $codloc = LocaisEstoque::normalizar($codloc);
        if ($codloc === '') {
            return [];
        }

        $limite = self::LIMITE_ITENS;
        $whereSaldo = $somenteComSaldo ? ' AND PRDLOC.SALDOFISICO2 <> 0' : '';

        $SQL = "SELECT TOP {$limite}
                    PRD.IDPRD,
                    MAX(RTRIM(PRDLOC.CODLOC)) AS CODLOC,
                    MAX(PRD.CODIGOPRD) AS CODIGO,
                    MAX(PRD.NOMEFANTASIA) AS NOME,
                    MAX(PRDDEF.CODUNDCONTROLE) AS UND,
                    MAX(PRDLOC.SALDOFISICO2) AS SALDO
                FROM TPRODUTO PRD
                INNER JOIN TPRODUTODEF PRDDEF
                    ON PRD.IDPRD = PRDDEF.IDPRD
                   AND PRD.CODCOLPRD = PRDDEF.CODCOLIGADA
                INNER JOIN TPRDLOC PRDLOC
                    ON PRD.IDPRD = PRDLOC.IDPRD
                   AND PRD.CODCOLPRD = PRDLOC.CODCOLIGADA
                WHERE PRD.INATIVO = 0
                  AND " . self::condicaoCodloc('PRDLOC') . "
                  {$whereSaldo}
                  AND NOT EXISTS (
                        SELECT 1 FROM TLOTEPRD L WHERE L.IDPRD = PRD.IDPRD
                  )
                GROUP BY PRD.IDPRD
                ORDER BY MAX(PRD.NOMEFANTASIA), PRD.IDPRD";

        $c = new Connection('RM');
        $c->Consulta($SQL, self::paramsCodloc($codloc));

        return self::mapearItensSemLote($c);
    }

    /**
     * @return array<int, array{idprd: int, codigo: string, nome: string, und: string, codloc: string, saldo: float}>
     */
    private static function mapearItensSemLote(Connection $c): array
    {
        $itens = [];
        while ($c->Resultado()) {
            $itens[] = [
                'idprd'  => (int) ($c->linha['IDPRD'] ?? 0),
                'codigo' => encode_db_value((string) ($c->linha['CODIGO'] ?? '')),
                'nome'   => encode_db_value((string) ($c->linha['NOME'] ?? '')),
                'und'    => encode_db_value((string) ($c->linha['UND'] ?? '')),
                'codloc' => encode_db_value((string) ($c->linha['CODLOC'] ?? '')),
                'saldo'  => (float) ($c->linha['SALDO'] ?? 0),
            ];
        }

        return $itens;
    }

    /**
     * Escapa curingas de LIKE além das aspas (% _ [ são curingas no T-SQL).
     */
    private static function sqlLike(string $value): string
    {
        $value = str_replace("'", "''", $value);

        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value);
    }

    /**
     * Condição de CODLOC tolerante a zero à esquerda ('28' e '028' no mesmo banco).
     *
     * Devolve SQL com dois marcadores; os valores vêm de paramsCodloc().
     */
    private static function condicaoCodloc(string $alias): string
    {
        $prefixo = $alias !== '' ? $alias . '.' : '';

        return "(
                LTRIM(RTRIM({$prefixo}CODLOC)) = ?
                OR RIGHT(REPLICATE('0', 3) + LTRIM(RTRIM({$prefixo}CODLOC)), 3) = ?
            )";
    }

    /**
     * @return array<int, string>
     */
    private static function paramsCodloc(string $codloc): array
    {
        $loc = LocaisEstoque::normalizar($codloc);

        return [$loc, $loc];
    }

    /**
     * Posição de estoque por lote no local — base da tela de contagem por lote.
     *
     * Segue a consulta de posição usada pela farmácia: TPRODUTO -> TPRDLOC ->
     * TLOTEPRDLOC -> TLOTEPRD, com grupo contábil, nome do local e custo médio.
     *
     * Diferença proposital: TTB2, TLOC e TPRDCUSTOFILIAL entram como LEFT JOIN.
     * Como INNER, produto sem grupo contábil ou sem custo cadastrado sumiria da
     * lista em silêncio — aceitável num relatório de valor, perigoso numa folha
     * de contagem, onde o item some e ninguém conta.
     *
     * @return array<int, array{idprd:int, idlote:int, numlote:string, codigo:string,
     *     nome:string, und:string, codloc:string, local_nome:string, grupo_cod:string,
     *     grupo_nome:string, validade:string, saldo:float, custo_medio:float,
     *     saldo_financeiro:float}>
     */
    public static function listarPosicaoPorLote(
        string $codloc,
        string $busca = '',
        string $grupoContabil = '',
        bool $somenteComSaldo = true,
        int $limite = self::LIMITE_LOTES
    ): array {
        $codloc = LocaisEstoque::normalizar($codloc);
        if ($codloc === '') {
            return [];
        }

        $limite = max(1, min(self::LIMITE_LOTES, $limite));
        $params = self::paramsCodloc($codloc);

        $whereSaldo = '';
        if ($somenteComSaldo) {
            $whereSaldo = ' AND PRDLOC.SALDOFISICO2 <> 0
                  AND LOTLOC.SALDOFISICO2 <> 0';
        }

        $whereGrupo = '';
        $grupoContabil = trim($grupoContabil);
        if ($grupoContabil !== '') {
            $whereGrupo = ' AND LTRIM(RTRIM(PRDDEF.CODTB2FAT)) = ?';
            $params[] = $grupoContabil;
        }

        $whereBusca = '';
        $busca = trim($busca);
        if ($busca !== '') {
            // 13 digitos e etiqueta: o codigo carrega IDPRD e IDLOTE, e nenhum
            // dos dois aparece no nome, no codigo do produto ou no numero do
            // lote - procurar como texto nunca acharia nada.
            $digitos = preg_replace('/\D/', '', $busca);

            if (strlen($digitos) === 13) {
                $whereBusca = ' AND PRD.IDPRD = ? AND LOTLOC.IDLOTE = ?';
                $params[] = ZMDCODBARRAS::idprdDoBarcode($digitos);
                $params[] = ZMDCODBARRAS::idloteDoBarcode($digitos);
            } else {
                // O LIKE fica no SQL (com curingas escapados) porque o padrão
                // %texto% precisa ser montado antes de virar parâmetro.
                $like = self::sqlLike($busca);
                $whereBusca = " AND (
                    LOT.NUMLOTE LIKE '%{$like}%'
                    OR PRD.NOMEFANTASIA LIKE '%{$like}%'
                    OR PRD.CODIGOPRD LIKE '%{$like}%'
                )";
            }
        }

        // GROUP BY protege contra duplicacao: TPRDLOC pode ter linha por filial,
        // e sem ele cada lote apareceria repetido com o saldo contado a mais.
        $SQL = "SELECT TOP {$limite}
                    PRD.IDPRD,
                    LOTLOC.IDLOTE,
                    MAX(PRD.NOMEFANTASIA) AS NOME,
                    MAX(PRD.CODIGOPRD) AS CODIGO,
                    MAX(PRDDEF.CODUNDCONTROLE) AS UND,
                    MAX(LTRIM(RTRIM(PRDDEF.CODTB2FAT))) AS GRUPOCOD,
                    MAX(GRUP.DESCRICAO) AS GRUPONOME,
                    MAX(RTRIM(PRDLOC.CODLOC)) AS CODLOC,
                    MAX(LOC.NOME) AS LOCALNOME,
                    MAX(RTRIM(LOT.NUMLOTE)) AS NUMLOTE,
                    MAX(LOT.DATAVALIDADE) AS DATAVALIDADE,
                    MAX(LOTLOC.SALDOFISICO2) AS SALDO,
                    MAX(CUST.CUSTOMEDIO) AS CUSTOMEDIO
                FROM TPRODUTO PRD
                INNER JOIN TPRODUTODEF PRDDEF
                    ON PRD.IDPRD = PRDDEF.IDPRD
                   AND PRD.CODCOLPRD = PRDDEF.CODCOLIGADA
                INNER JOIN TPRDLOC PRDLOC
                    ON PRD.IDPRD = PRDLOC.IDPRD
                   AND PRD.CODCOLPRD = PRDLOC.CODCOLIGADA
                INNER JOIN TLOTEPRDLOC LOTLOC
                    ON LOTLOC.IDPRD = PRDLOC.IDPRD
                   AND LOTLOC.CODCOLIGADA = PRDLOC.CODCOLIGADA
                   AND LOTLOC.CODLOC = PRDLOC.CODLOC
                INNER JOIN TLOTEPRD LOT
                    ON LOT.IDPRD = LOTLOC.IDPRD
                   AND LOT.IDLOTE = LOTLOC.IDLOTE
                   AND LOT.CODCOLIGADA = LOTLOC.CODCOLIGADA
                LEFT JOIN TTB2 GRUP
                    ON PRDDEF.CODTB2FAT = GRUP.CODTB2FAT
                   AND GRUP.CODCOLIGADA = PRDDEF.CODCOLIGADA
                LEFT JOIN TLOC LOC
                    ON PRDLOC.CODLOC = LOC.CODLOC
                   AND PRDLOC.CODFILIAL = LOC.CODFILIAL
                   AND LOC.CODCOLIGADA = PRDLOC.CODCOLIGADA
                LEFT JOIN TPRDCUSTOFILIAL CUST
                    ON PRDLOC.IDPRD = CUST.IDPRD
                   AND PRDLOC.CODFILIAL = CUST.CODFILIAL
                   AND CUST.CODCOLIGADA = PRDLOC.CODCOLIGADA
                WHERE PRD.INATIVO = 0
                  AND " . self::condicaoCodloc('PRDLOC') . "
                  {$whereSaldo}
                  {$whereGrupo}
                  {$whereBusca}
                GROUP BY PRD.IDPRD, LOTLOC.IDLOTE
                ORDER BY MAX(PRD.NOMEFANTASIA), MAX(LOT.DATAVALIDADE), MAX(RTRIM(LOT.NUMLOTE))";

        $c = new Connection('RM');
        $c->Consulta($SQL, $params);

        $linhas = [];
        while ($c->Resultado()) {
            $saldo = (float) ($c->linha['SALDO'] ?? 0);
            $custo = (float) ($c->linha['CUSTOMEDIO'] ?? 0);
            $linhas[] = [
                'idprd'            => (int) ($c->linha['IDPRD'] ?? 0),
                'idlote'           => (int) ($c->linha['IDLOTE'] ?? 0),
                'numlote'          => encode_db_value((string) ($c->linha['NUMLOTE'] ?? '')),
                'codigo'           => encode_db_value((string) ($c->linha['CODIGO'] ?? '')),
                'nome'             => encode_db_value((string) ($c->linha['NOME'] ?? '')),
                'und'              => encode_db_value((string) ($c->linha['UND'] ?? '')),
                'codloc'           => encode_db_value((string) ($c->linha['CODLOC'] ?? '')),
                'local_nome'       => encode_db_value((string) ($c->linha['LOCALNOME'] ?? '')),
                'grupo_cod'        => encode_db_value((string) ($c->linha['GRUPOCOD'] ?? '')),
                'grupo_nome'       => encode_db_value((string) ($c->linha['GRUPONOME'] ?? '')),
                'validade'         => self::formatDate($c->linha['DATAVALIDADE'] ?? null),
                'saldo'            => $saldo,
                'custo_medio'      => $custo,
                'saldo_financeiro' => $saldo * $custo,
            ];
        }

        return $linhas;
    }

    /**
     * IDPRDs que o RM gerou no inventário para o local.
     *
     * Posição de estoque e inventário são conjuntos diferentes: pode haver item
     * com saldo no local que ficou de fora do inventário. A tela mostra os dois,
     * mas só grava contagem para quem está aqui dentro.
     *
     * @return array<int, bool> idprd => true
     */
    public static function idprdsDoInventario(string $codinventario, string $codloc): array
    {
        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            return [];
        }

        $params = [self::CODCOLIGADA, $codinventario];
        $whereLoc = '';

        if (LocaisEstoque::normalizar($codloc) !== '') {
            $whereLoc = ' AND ' . self::condicaoCodloc('ITM');
            $params = array_merge($params, self::paramsCodloc($codloc));
        }

        $c = new Connection('RM');
        $c->Consulta(
            "SELECT DISTINCT ITM.IDPRD
             FROM TITMINVENTARIO ITM
             WHERE ITM.CODCOLIGADA = ?
               AND ITM.CODINVENTARIO = ?
               {$whereLoc}",
            $params
        );

        $mapa = [];
        while ($c->Resultado()) {
            $idprd = (int) ($c->linha['IDPRD'] ?? 0);
            if ($idprd > 0) {
                $mapa[$idprd] = true;
            }
        }

        return $mapa;
    }

    /**
     * Conferência do local: o que era esperado, o que foi contado e o que faltou.
     *
     * Cruza a posição de estoque do local com a contagem gravada. São três
     * situações, e a terceira é a que um relatório ingênuo esconderia:
     *
     *  - contado      : estava na posição e foi contado (diferença = contado - saldo)
     *  - nao_contado  : estava na posição e ninguém contou
     *  - sobra        : foi contado mas não estava na posição — lote zerado achado
     *                   na prateleira, ou item bipado que não tem saldo no local
     *
     * A posição usa só saldo diferente de zero: lote zerado não é algo que se
     * "deixou de contar", seria ruído na lista de pendências.
     *
     * @return array{itens: array<int, array<string, mixed>>, totais: array<string, float|int>, truncado: bool}
     */
    public static function conferenciaDoLocal(string $codinventario, string $codloc): array
    {
        $totaisZerados = [
            'esperados' => 0, 'contados' => 0, 'nao_contados' => 0, 'sobras' => 0,
            'saldo' => 0.0, 'contado' => 0.0, 'diferenca' => 0.0, 'valor_diferenca' => 0.0,
        ];

        $codinventario = trim($codinventario);
        $codloc = LocaisEstoque::normalizar($codloc);
        if ($codinventario === '' || $codloc === '') {
            return ['itens' => [], 'totais' => $totaisZerados, 'truncado' => false];
        }

        $posicao = self::listarPosicaoPorLote($codloc, '', '', true);
        $contagem = ZMDCODBARRAS::contagemPorProdutoLote($codinventario);

        $itens = [];
        $t = $totaisZerados;

        foreach ($posicao as $linha) {
            $chave = $linha['idprd'] . ':' . $linha['idlote'];
            $temContagem = isset($contagem[$chave]);
            $contado = $temContagem ? (float) $contagem[$chave]['quantidade'] : 0.0;
            $saldo = (float) $linha['saldo'];
            $diferenca = $contado - $saldo;
            $custo = (float) $linha['custo_medio'];

            $itens[] = [
                'situacao'        => $temContagem ? 'contado' : 'nao_contado',
                'idprd'           => $linha['idprd'],
                'idlote'          => $linha['idlote'],
                'nome'            => $linha['nome'],
                'codigo'          => $linha['codigo'],
                'und'             => $linha['und'],
                'numlote'         => $linha['numlote'],
                'validade'        => $linha['validade'],
                'grupo'           => $linha['grupo_nome'] !== '' ? $linha['grupo_nome'] : $linha['grupo_cod'],
                'saldo'           => $saldo,
                'contado'         => $contado,
                'diferenca'       => $temContagem ? $diferenca : 0.0,
                'valor_diferenca' => $temContagem ? $diferenca * $custo : 0.0,
                'bipagens'        => $temContagem ? (int) $contagem[$chave]['bipagens'] : 0,
            ];

            $t['esperados']++;
            $t['saldo'] += $saldo;
            if ($temContagem) {
                $t['contados']++;
                $t['contado'] += $contado;
                $t['diferenca'] += $diferenca;
                $t['valor_diferenca'] += $diferenca * $custo;
                unset($contagem[$chave]);
            } else {
                $t['nao_contados']++;
            }
        }

        // Sobrou contagem sem linha na posição: tudo isso é excedente.
        foreach ($contagem as $linha) {
            $contado = (float) $linha['quantidade'];
            $itens[] = [
                'situacao'        => 'sobra',
                'idprd'           => $linha['idprd'],
                'idlote'          => $linha['idlote'],
                'nome'            => $linha['nome'],
                'codigo'          => '',
                'und'             => $linha['und'],
                'numlote'         => $linha['numlote'],
                'validade'        => '',
                'grupo'           => '',
                'saldo'           => 0.0,
                'contado'         => $contado,
                'diferenca'       => $contado,
                'valor_diferenca' => 0.0,
                'bipagens'        => (int) $linha['bipagens'],
            ];

            $t['sobras']++;
            $t['contado'] += $contado;
            $t['diferenca'] += $contado;
        }

        // Não contados primeiro: é a lista de pendências de quem está contando.
        $ordem = ['nao_contado' => 0, 'sobra' => 1, 'contado' => 2];
        usort($itens, function ($a, $b) use ($ordem) {
            $pa = $ordem[$a['situacao']];
            $pb = $ordem[$b['situacao']];
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            return strcasecmp((string) $a['nome'], (string) $b['nome']);
        });

        return [
            'itens'    => $itens,
            'totais'   => $t,
            'truncado' => count($posicao) >= self::LIMITE_LOTES,
        ];
    }

    /**
     * Grupos contábeis presentes no local — alimenta o filtro da tela.
     *
     * Consulta própria em vez de derivar das linhas já carregadas: com um grupo
     * já filtrado, a lista traria só ele e o filtro viraria um beco sem saída.
     *
     * @return array<string, string> código => descrição
     */
    public static function gruposContabeisDoLocal(string $codloc, bool $somenteComSaldo = true): array
    {
        $codloc = LocaisEstoque::normalizar($codloc);
        if ($codloc === '') {
            return [];
        }

        $whereSaldo = $somenteComSaldo
            ? ' AND PRDLOC.SALDOFISICO2 <> 0 AND LOTLOC.SALDOFISICO2 <> 0'
            : '';

        $SQL = "SELECT DISTINCT
                    LTRIM(RTRIM(PRDDEF.CODTB2FAT)) AS GRUPOCOD,
                    GRUP.DESCRICAO AS GRUPONOME
                FROM TPRODUTO PRD
                INNER JOIN TPRODUTODEF PRDDEF
                    ON PRD.IDPRD = PRDDEF.IDPRD
                   AND PRD.CODCOLPRD = PRDDEF.CODCOLIGADA
                INNER JOIN TPRDLOC PRDLOC
                    ON PRD.IDPRD = PRDLOC.IDPRD
                   AND PRD.CODCOLPRD = PRDLOC.CODCOLIGADA
                INNER JOIN TLOTEPRDLOC LOTLOC
                    ON LOTLOC.IDPRD = PRDLOC.IDPRD
                   AND LOTLOC.CODCOLIGADA = PRDLOC.CODCOLIGADA
                   AND LOTLOC.CODLOC = PRDLOC.CODLOC
                LEFT JOIN TTB2 GRUP
                    ON PRDDEF.CODTB2FAT = GRUP.CODTB2FAT
                   AND GRUP.CODCOLIGADA = PRDDEF.CODCOLIGADA
                WHERE PRD.INATIVO = 0
                  AND " . self::condicaoCodloc('PRDLOC') . "
                  {$whereSaldo}
                  AND PRDDEF.CODTB2FAT IS NOT NULL
                  AND LTRIM(RTRIM(PRDDEF.CODTB2FAT)) <> ''
                ORDER BY GRUP.DESCRICAO, GRUPOCOD";

        $c = new Connection('RM');
        $c->Consulta($SQL, self::paramsCodloc($codloc));

        $grupos = [];
        while ($c->Resultado()) {
            $cod = encode_db_value((string) ($c->linha['GRUPOCOD'] ?? ''));
            if ($cod !== '') {
                $grupos[$cod] = encode_db_value((string) ($c->linha['GRUPONOME'] ?? ''));
            }
        }

        return $grupos;
    }

    /**
     * Inventários em aberto no RM (TINVENTARIO.STATUS = 'A').
     *
     * A contagem de itens sai de um JOIN agregado, não de uma consulta por
     * linha: com 40 inventários abertos, o laço anterior fazia 41 idas ao SQL
     * Server só para montar a tela inicial, e era aí que o tempo estourava.
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

        $SQL = "SELECT TOP {$limit}
                    RTRIM(INV.CODINVENTARIO) AS CODINVENTARIO,
                    RTRIM(CAST(INV.STATUS AS VARCHAR(10))) AS STATUS,
                    INV.DATABASEINVENTARIO,
                    INV.DATASTATUS,
                    ISNULL(ITENS.TOTAL, 0) AS ITENS
                FROM TINVENTARIO INV
                OUTER APPLY (
                    SELECT COUNT(*) AS TOTAL
                    FROM TITMINVENTARIO I
                    WHERE I.CODCOLIGADA = INV.CODCOLIGADA
                      AND I.CODINVENTARIO = INV.CODINVENTARIO
                ) ITENS
                WHERE INV.CODCOLIGADA = ?
                  AND RTRIM(CAST(INV.STATUS AS VARCHAR(10))) = 'A'
                ORDER BY INV.DATABASEINVENTARIO DESC, INV.CODINVENTARIO DESC";

        $c = new Connection('RM');
        $c->Consulta($SQL, [self::CODCOLIGADA]);

        $lista = [];
        while ($c->Resultado()) {
            $cod = encode_db_value((string) ($c->linha['CODINVENTARIO'] ?? ''));
            $codloc = '';
            $parsed = ZMDCODBARRAS::parseCodigoInventario($cod);
            if (!empty($parsed['valid'])) {
                $codloc = (string) $parsed['codloc'];
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
                'itens'         => (int) ($c->linha['ITENS'] ?? 0),
            ];
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

    public static function itemPertenceAoInventario(string $codinventario, string $codloc, int $idprd): bool
    {
        $codinventario = trim($codinventario);
        $codloc = LocaisEstoque::normalizar($codloc);

        if ($codinventario === '' || $codloc === '' || $idprd <= 0) {
            return false;
        }

        $c = new Connection('RM');
        $c->Consulta(
            'SELECT TOP 1 1 AS OK
             FROM TITMINVENTARIO
             WHERE CODCOLIGADA = ?
               AND CODINVENTARIO = ?
               AND IDPRD = ?
               AND ' . self::condicaoCodloc(''),
            array_merge([self::CODCOLIGADA, $codinventario, $idprd], self::paramsCodloc($codloc))
        );

        return (bool) $c->Resultado();
    }
}
