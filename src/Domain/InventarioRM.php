<?php

/**
 * Consultas de inventário no TOTVS RM (TINVENTARIO / TITMINVENTARIO).
 */
class InventarioRM
{
    private const CODCOLIGADA = 1;

    /**
     * TINVENTARIO.STATUS é varchar(1). No banco deste hospital aparecem três
     * valores: 'A' aberto, 'E' encerrado e 'P' em processamento — este último
     * só em registros de 2017 a 2020, abandonados pela migração.
     *
     * Contar só faz sentido no aberto. O encerrado já foi apurado no RM: gravar
     * nele muda o resultado de uma apuração fechada sem que o RM saiba.
     */
    public const STATUS_ABERTO = 'A';

    /** @var array<string, string> */
    private const ROTULOS_STATUS = [
        'A' => 'Aberto',
        'E' => 'Encerrado',
        'P' => 'Em processamento',
    ];

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

    public static function rotuloStatus(string $status): string
    {
        $status = strtoupper(trim($status));

        return self::ROTULOS_STATUS[$status] ?? ($status !== '' ? $status : 'desconhecido');
    }

    /**
     * Regra pura: este STATUS aceita gravação?
     *
     * Status vazio é o inventário que não tem linha em TINVENTARIO — avulsa, ou
     * código órfão de antes da máscara. Não é assunto desta regra, e quem chama
     * já trata: a gravação exige existir no RM, a exclusão precisa poder limpar.
     */
    public static function statusPermiteGravar(string $status): bool
    {
        $status = strtoupper(trim($status));

        return $status === '' || $status === self::STATUS_ABERTO;
    }

    /**
     * Motivo do bloqueio, pronto para a tela. '' quando pode gravar.
     */
    public static function motivoStatusBloqueia(string $codinventario, string $status): string
    {
        if (self::statusPermiteGravar($status)) {
            return '';
        }

        return "Inventário {$codinventario} está " . self::rotuloStatus($status)
            . ' no RM e não aceita mais contagem. Para continuar, reabra o inventário no RM.';
    }

    /**
     * Mesma regra para quem só tem o código em mãos — a edição e a exclusão de
     * lançamento, que não passam por validarParaUso().
     *
     * Custa uma consulta; as telas que já validaram usam o status que têm.
     */
    public static function motivoNaoPodeGravar(string $codinventario): string
    {
        $codinventario = trim($codinventario);

        // Avulsa é rascunho fora do RM: não há status para consultar.
        if ($codinventario === '' || ZMDCODBARRAS::ehCodigoAvulso($codinventario)) {
            return '';
        }

        $existe = self::existeNoRm($codinventario);
        if (!$existe['valid']) {
            // Sem linha no RM não há apuração fechada para proteger. Quem grava
            // já é barrado por validarParaUso(); quem exclui precisa limpar.
            return '';
        }

        return self::motivoStatusBloqueia($codinventario, $existe['status']);
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
     * `valid` responde "dá para abrir esta tela"; `pode_gravar`, "dá para
     * gravar nela". São perguntas diferentes: inventário encerrado continua
     * valendo para consultar o que foi contado, só não aceita contagem nova.
     *
     * @return array{valid: bool, status: string, error: string,
     *               pode_gravar: bool, motivo_bloqueio: string}
     */
    public static function validarParaUso(string $codinventario, string $codloc): array
    {
        $existe = self::existeNoRm($codinventario);
        if (!$existe['valid']) {
            return [
                'valid'           => false,
                'status'          => '',
                'error'           => $existe['error'],
                'pode_gravar'     => false,
                'motivo_bloqueio' => $existe['error'],
            ];
        }

        $bloqueio = self::motivoStatusBloqueia($codinventario, $existe['status']);

        if (!self::localPertenceAoInventario($codinventario, $codloc)) {
            $codloc = LocaisEstoque::normalizar($codloc);
            $nome = LocaisEstoque::nome($codloc);
            $label = $nome !== '' ? "{$codloc} — {$nome}" : $codloc;

            return [
                'valid'           => false,
                'status'          => $existe['status'],
                'error'           => "O local {$label} não está vinculado ao inventário {$codinventario} no RM.",
                'pode_gravar'     => false,
                'motivo_bloqueio' => $bloqueio,
            ];
        }

        return [
            'valid'           => true,
            'status'          => $existe['status'],
            'error'           => '',
            'pode_gravar'     => $bloqueio === '',
            'motivo_bloqueio' => $bloqueio,
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
                -- Sem LTRIM/RTRIM nos dois lados: funcao sobre a coluna impede
                -- o banco de usar indice, e esta juncao passava de 11s para a
                -- folha do 065. Conferido no banco do hospital: CODLOC tem
                -- sempre 3 caracteres preenchidos com zero nas duas tabelas, e
                -- nao ha um unico par que o trim case e a igualdade simples
                -- nao. O = de char ja ignora espaco a direita.
                LEFT JOIN TPRDLOC PRDLOC
                    ON PRDLOC.IDPRD = I.IDPRD
                   AND PRDLOC.CODLOC = I.CODLOC
                -- Produto sem nenhum lote cadastrado, como juncao em vez de
                -- NOT EXISTS correlacionado: medido no banco do hospital, o
                -- NOT EXISTS reexecutava a busca em TLOTEPRD uma vez por
                -- produto do local e levava a consulta a mais de um segundo.
                LEFT JOIN (
                    SELECT DISTINCT IDPRD FROM TLOTEPRD
                ) COMLOTE
                    ON COMLOTE.IDPRD = I.IDPRD
                WHERE I.CODCOLIGADA = ?
                  AND I.CODINVENTARIO = ?
                  {$whereLoc}
                  AND COMLOTE.IDPRD IS NULL
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
     * Aceita os mesmos filtros da consulta por lote (busca e grupo contábil)
     * para que as duas metades da posição do local respondam igual.
     *
     * @return array<int, array<string, mixed>> mesma forma de listarPosicaoPorLote
     */
    public static function listarItensSemLoteDoLocal(
        string $codloc,
        bool $somenteComSaldo = false,
        string $busca = '',
        string $grupoContabil = ''
    ): array {
        $codloc = LocaisEstoque::normalizar($codloc);
        if ($codloc === '') {
            return [];
        }

        $limite = self::LIMITE_ITENS;
        $params = self::paramsCodloc($codloc);
        $whereSaldo = $somenteComSaldo ? ' AND PRDLOC.SALDOFISICO2 <> 0' : '';

        $whereGrupo = '';
        $grupoContabil = trim($grupoContabil);
        if ($grupoContabil !== '') {
            $whereGrupo = ' AND LTRIM(RTRIM(PRDDEF.CODTB2FAT)) = ?';
            $params[] = $grupoContabil;
        }

        $whereBusca = '';
        $busca = trim($busca);
        if ($busca !== '') {
            $digitos = preg_replace('/\D/', '', $busca);

            if (strlen($digitos) === 13) {
                // Etiqueta com lote não pode casar com item sem lote: o
                // parâmetro impossível deixa a consulta voltar vazia sem
                // inventar um resultado.
                if (ZMDCODBARRAS::idloteDoBarcode($digitos) > 0) {
                    return [];
                }
                $whereBusca = ' AND PRD.IDPRD = ?';
                $params[] = ZMDCODBARRAS::idprdDoBarcode($digitos);
            } else {
                // Sem NUMLOTE para procurar; sobram nome e código do produto.
                $like = self::sqlLike($busca);
                $whereBusca = " AND (
                    PRD.NOMEFANTASIA LIKE '%{$like}%'
                    OR PRD.CODIGOPRD LIKE '%{$like}%'
                )";
            }
        }

        // O custo entra porque a conferência calcula o valor da diferença com
        // ele, e o relatório de posição mostra o valor financeiro.
        $SQL = "SELECT TOP {$limite}
                    PRD.IDPRD,
                    MAX(RTRIM(PRDLOC.CODLOC)) AS CODLOC,
                    MAX(PRD.CODIGOPRD) AS CODIGO,
                    MAX(PRD.NOMEFANTASIA) AS NOME,
                    MAX(PRDDEF.CODUNDCONTROLE) AS UND,
                    MAX(LTRIM(RTRIM(PRDDEF.CODTB2FAT))) AS GRUPOCOD,
                    MAX(GRUP.DESCRICAO) AS GRUPONOME,
                    MAX(LOC.NOME) AS LOCALNOME,
                    MAX(PRDLOC.SALDOFISICO2) AS SALDO,
                    MAX(CUST.CUSTOMEDIO) AS CUSTOMEDIO
                FROM TPRODUTO PRD
                INNER JOIN TPRODUTODEF PRDDEF
                    ON PRD.IDPRD = PRDDEF.IDPRD
                   AND PRD.CODCOLPRD = PRDDEF.CODCOLIGADA
                INNER JOIN TPRDLOC PRDLOC
                    ON PRD.IDPRD = PRDLOC.IDPRD
                   AND PRD.CODCOLPRD = PRDLOC.CODCOLIGADA
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
                -- Produto sem nenhum lote cadastrado, como juncao em vez de
                -- NOT EXISTS correlacionado: medido no banco do hospital, o
                -- NOT EXISTS reexecutava a busca em TLOTEPRD uma vez por
                -- produto do local e levava a consulta a mais de um segundo.
                LEFT JOIN (
                    SELECT DISTINCT IDPRD FROM TLOTEPRD
                ) COMLOTE
                    ON COMLOTE.IDPRD = PRD.IDPRD
                WHERE PRD.INATIVO = 0
                  AND " . self::condicaoCodloc('PRDLOC') . "
                  {$whereSaldo}
                  {$whereGrupo}
                  {$whereBusca}
                  AND COMLOTE.IDPRD IS NULL
                GROUP BY PRD.IDPRD
                ORDER BY MAX(PRD.NOMEFANTASIA), PRD.IDPRD";

        $c = new Connection('RM');
        $c->Consulta($SQL, $params);

        return self::mapearItensSemLote($c);
    }

    /**
     * Posição completa do local: o que tem lote e o que não tem.
     *
     * As duas metades vêm de consultas diferentes porque as tabelas são
     * diferentes — TLOTEPRDLOC só existe para produto com lote, e um INNER
     * JOIN nela apaga todo o resto. Era por isso que um local de gaze, luva e
     * seringa gerava relatório de posição vazio: nenhum desses produtos tem
     * lote, então nenhum aparecia.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarPosicaoDoLocal(
        string $codloc,
        string $busca = '',
        string $grupoContabil = '',
        bool $somenteComSaldo = true
    ): array {
        $linhas = array_merge(
            self::listarPosicaoPorLote($codloc, $busca, $grupoContabil, $somenteComSaldo),
            self::listarItensSemLoteDoLocal($codloc, $somenteComSaldo, $busca, $grupoContabil)
        );

        // Cada metade vem ordenada por nome; juntas, precisam ser reordenadas
        // ou o relatório sairia com os sem lote todos no fim.
        usort($linhas, function ($a, $b) {
            $porNome = strcasecmp((string) $a['nome'], (string) $b['nome']);
            if ($porNome !== 0) {
                return $porNome;
            }
            return strcmp((string) $a['numlote'], (string) $b['numlote']);
        });

        return $linhas;
    }

    /**
     * A posição do local foi cortada por algum teto?
     *
     * Tem de olhar metade por metade. Somar as duas e comparar com a soma dos
     * tetos esconde o caso comum: 4000 lotes (no teto) mais 10 itens sem lote
     * dá 4010, que não alcança 8000 — e a lista saía cortada sem aviso nenhum,
     * com totais incompletos passando por completos.
     *
     * @param array<int, array<string, mixed>> $linhas
     */
    public static function posicaoTruncada(array $linhas): bool
    {
        $comLote = 0;
        $semLote = 0;

        foreach ($linhas as $linha) {
            if ((int) $linha['idlote'] > 0) {
                $comLote++;
            } else {
                $semLote++;
            }
        }

        return $comLote >= self::LIMITE_LOTES || $semLote >= self::LIMITE_ITENS;
    }

    /**
     * @return array<int, array{idprd: int, codigo: string, nome: string, und: string, codloc: string, saldo: float}>
     */
    private static function mapearItensSemLote(Connection $c): array
    {
        $itens = [];
        while ($c->Resultado()) {
            $saldo = (float) ($c->linha['SALDO'] ?? 0);
            $custo = (float) ($c->linha['CUSTOMEDIO'] ?? 0);

            // Mesma forma da posição por lote, com os campos de lote vazios:
            // assim as duas metades se juntam sem o chamador ter de saber de
            // qual consulta cada linha veio. IDLOTE 0 é "sem lote" em todo o
            // sistema, inclusive no layout do código de barras.
            $itens[] = [
                'idprd'            => (int) ($c->linha['IDPRD'] ?? 0),
                'idlote'           => 0,
                'numlote'          => '',
                'codigo'           => encode_db_value((string) ($c->linha['CODIGO'] ?? '')),
                'nome'             => encode_db_value((string) ($c->linha['NOME'] ?? '')),
                'und'              => encode_db_value((string) ($c->linha['UND'] ?? '')),
                'codloc'           => encode_db_value((string) ($c->linha['CODLOC'] ?? '')),
                'local_nome'       => encode_db_value((string) ($c->linha['LOCALNOME'] ?? '')),
                'grupo_cod'        => encode_db_value((string) ($c->linha['GRUPOCOD'] ?? '')),
                'grupo_nome'       => encode_db_value((string) ($c->linha['GRUPONOME'] ?? '')),
                'validade'         => '',
                'saldo'            => $saldo,
                'custo_medio'      => $custo,
                'saldo_financeiro' => $saldo * $custo,
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

        // "Só com saldo" pergunta sobre O LOTE, e só o lote responde.
        //
        // Aqui também havia PRDLOC.SALDOFISICO2 <> 0, que é o saldo do PRODUTO
        // no local. Com ele, um produto cujo total no local fecha em zero
        // sumia inteiro da lista — inclusive os lotes dele que TINHAM saldo.
        // Dois efeitos, os dois ruins:
        //
        //  - a folha de contagem perdia item que está fisicamente na
        //    prateleira, e ninguém conta o que não aparece;
        //  - marcar "incluir lotes zerados" trazia de volta lotes COM saldo,
        //    então o total de itens subia, o que não é o que a caixa promete.
        //
        // O saldo do produto continua valendo onde a pergunta é sobre o
        // produto (a folha "sem lote"), não sobre o lote.
        $whereSaldo = $somenteComSaldo ? ' AND LOTLOC.SALDOFISICO2 <> 0' : '';

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
                $idprdBusca = ZMDCODBARRAS::idprdDoBarcode($digitos);
                $idloteBusca = ZMDCODBARRAS::idloteDoBarcode($digitos);
                $params[] = $idprdBusca;

                if ($idloteBusca > 0) {
                    $whereBusca = ' AND PRD.IDPRD = ? AND LOTLOC.IDLOTE = ?';
                    $params[] = $idloteBusca;
                } else {
                    // Codigo de etiqueta sem lote: o produto esta la, o lote e
                    // zero. Procurar por IDLOTE = 0 nao acha nada, porque lote
                    // zero nao existe em TLOTEPRDLOC - e quem digitou o codigo
                    // de uma etiqueta ilegivel ficava sem nenhum resultado.
                    // Mostrar todos os lotes do produto deixa a pessoa escolher.
                    $whereBusca = ' AND PRD.IDPRD = ?';
                }
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

        // A posição do local traz o que tem lote e o que não tem. Só a metade
        // com lote fazia todo item contado pela tela "Sem lote" cair no balde
        // de sobra — o produto existia no local, tinha saldo, foi contado
        // certo, e o relatório o acusava de excedente.
        $posicao = self::listarPosicaoDoLocal($codloc, '', '', true);
        $truncado = self::posicaoTruncada($posicao);
        $contagem = ZMDCODBARRAS::contagemPorProdutoLote($codinventario);

        return self::reconciliar($posicao, $contagem, $truncado);
    }

    /**
     * Cruza posição com contagem. Separado do acesso ao banco de propósito:
     * é aqui que mora a regra de "contado / não contado / sobra", e ela é a
     * parte que precisa de teste.
     *
     * @param array<int, array<string, mixed>> $posicao
     * @param array<string, array<string, mixed>> $contagem chave "idprd:idlote"
     * @return array{itens: array<int, array<string, mixed>>, totais: array<string, float|int>, truncado: bool}
     */
    public static function reconciliar(array $posicao, array $contagem, bool $truncado = false): array
    {
        $t = [
            'esperados' => 0, 'contados' => 0, 'nao_contados' => 0, 'sobras' => 0,
            'saldo' => 0.0, 'contado' => 0.0, 'diferenca' => 0.0, 'valor_diferenca' => 0.0,
        ];

        $itens = [];

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
            'truncado' => $truncado,
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

        // Mesmo critério da lista, nas duas metades: com lote o saldo que
        // manda é o do lote, sem lote é o do produto no local. Um seletor que
        // divergisse ofereceria grupo que a lista não mostra — ou, num local
        // só de item sem lote, viria vazio e o filtro parecia quebrado.
        $whereLote = $somenteComSaldo ? ' AND LOTLOC.SALDOFISICO2 <> 0' : '';
        $whereProduto = $somenteComSaldo ? ' AND PRDLOC.SALDOFISICO2 <> 0' : '';
        $condLoc = self::condicaoCodloc('PRDLOC');
        $temGrupo = " AND PRDDEF.CODTB2FAT IS NOT NULL
                  AND LTRIM(RTRIM(PRDDEF.CODTB2FAT)) <> ''";

        $SQL = "SELECT DISTINCT GRUPOCOD, GRUPONOME FROM (
                    SELECT
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
                      AND {$condLoc}
                      {$whereLote}
                      {$temGrupo}

                    UNION

                    SELECT
                        LTRIM(RTRIM(PRDDEF.CODTB2FAT)) AS GRUPOCOD,
                        GRUP.DESCRICAO AS GRUPONOME
                    FROM TPRODUTO PRD
                    INNER JOIN TPRODUTODEF PRDDEF
                        ON PRD.IDPRD = PRDDEF.IDPRD
                       AND PRD.CODCOLPRD = PRDDEF.CODCOLIGADA
                    INNER JOIN TPRDLOC PRDLOC
                        ON PRD.IDPRD = PRDLOC.IDPRD
                       AND PRD.CODCOLPRD = PRDLOC.CODCOLIGADA
                    LEFT JOIN TTB2 GRUP
                        ON PRDDEF.CODTB2FAT = GRUP.CODTB2FAT
                       AND GRUP.CODCOLIGADA = PRDDEF.CODCOLIGADA
                    -- Produto que nao tem lote nenhum cadastrado, escrito como
                    -- juncao em vez de NOT EXISTS correlacionado.
                    --
                    -- Medido no banco do hospital, local 065: com o NOT EXISTS
                    -- esta metade levava 1,694s; assim, 0,076s, com o mesmo
                    -- resultado. O plano deixa de reexecutar a busca em TLOTEPRD
                    -- uma vez por produto do local.
                    LEFT JOIN (
                        SELECT DISTINCT IDPRD FROM TLOTEPRD
                    ) SEMLOTE
                        ON SEMLOTE.IDPRD = PRD.IDPRD
                    WHERE PRD.INATIVO = 0
                      AND {$condLoc}
                      {$whereProduto}
                      {$temGrupo}
                      AND SEMLOTE.IDPRD IS NULL
                ) X
                ORDER BY GRUPONOME, GRUPOCOD";

        $c = new Connection('RM');
        // O CODLOC entra duas vezes: uma por metade da UNION.
        $c->Consulta($SQL, array_merge(self::paramsCodloc($codloc), self::paramsCodloc($codloc)));

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
    /**
     * Abertos de um local só.
     *
     * Serve à vinculação de contagem avulsa, que só aceita destino no mesmo
     * local — mover contagem para o inventário de outro local misturaria
     * prateleiras diferentes na mesma apuração.
     *
     * Filtra em PHP sobre listarAbertos() porque o CODLOC não está em
     * TINVENTARIO: ele sai da máscara do próprio código.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function abertosDoLocal(string $codloc): array
    {
        $codloc = LocaisEstoque::normalizar($codloc);
        if ($codloc === '') {
            return [];
        }

        return array_values(array_filter(self::listarAbertos(), function ($inv) use ($codloc) {
            return ($inv['codloc'] ?? '') === $codloc;
        }));
    }

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
