<?php

/**
 * Domínio: leitura de código de barras para inventário TOTVS RM.
 * Regras de negócio preservadas da versão original (h9).
 */
class ZMDCODBARRAS
{
    /**
     * Teto de linhas da listagem em tela.
     *
     * O total real de bipagens NÃO sai de count() sobre essa lista — use
     * totalBipagens(), senão o contador congela ao bater neste limite.
     */
    public const LIMITE_LISTAGEM = 1000;

    private $id;
    private $codigobarras;
    private $codinventario;
    private $quantidade;
    private $codloc;
    private $nome;
    private $und;
    private $numlote;

    /**
     * Forma da coluna ZMDCODBARRAS.QUANTIDADE — decimal(10,2).
     *
     * Passar do teto não trunca: o SQL Server recusa a gravação inteira com
     * erro aritmético, e em produção o motivo técnico não vai para a tela — o
     * operador veria só "não foi possível gravar". Melhor recusar antes, dizendo
     * o que houve.
     */
    public const QUANTIDADE_CASAS = 2;
    public const QUANTIDADE_MAXIMA = 99999999.99;

    /** RECCREATEDBY / RECCREATEDON, só leitura na tela. */
    private $criadoPor = '';
    private $criadoEm = '';

    /**
     * SQL base compartilhado — evita duplicação entre listagens.
     */
    /**
     * Usuário do RM para RECCREATEDBY / RECMODIFIEDBY.
     *
     * É o CODUSUARIO que a sessão já guarda desde o login — a identidade
     * sempre esteve disponível na hora de gravar, só não era escrita.
     *
     * O corte acompanha o tamanho da coluna (varchar(50)): um login maior faria
     * o INSERT inteiro falhar e derrubaria a contagem por causa do campo de
     * auditoria.
     */
    public const AUDITORIA_USUARIO_MAX = 50;

    private static function usuarioAuditoria(): string
    {
        $usuario = class_exists('SessionManager') ? trim(SessionManager::getUsername()) : '';

        // Sem sessão (fila, script), fica registrado o sistema em vez de vazio.
        return $usuario !== ''
            ? mb_substr($usuario, 0, self::AUDITORIA_USUARIO_MAX)
            : 'INVENTARIO';
    }

    private static function baseSelectSql(): string
    {
        // Sem TLOTEPRD aqui: o numero do lote vem depois, em mapearResultado().
        // A juncao por IDPRD - coluna sem indice - fazia esta listagem levar 25s
        // num inventario de 6.700 lancamentos, que e a tela de Leitura abrindo.
        return "SELECT TOP " . self::LIMITE_LISTAGEM . " zmd.ID, ZMD.CODIGOBARRAS, ZMD.CODINVENTARIO, ZMD.QUANTIDADE, ZMD.CODLOC, ZMD.RECCREATEDBY, ZMD.RECCREATEDON, T.NOMEFANTASIA AS NOME, TPRODUTODEF.CODUNDCONTROLE AS UND
                FROM ZMDCODBARRAS ZMD
                LEFT JOIN TPRODUTO T ON T.IDPRD = CONVERT(INT,SUBSTRING(ZMD.CODIGOBARRAS,0,7))
                LEFT JOIN TPRODUTODEF ON TPRODUTODEF.IDPRD = T.IDPRD";
    }

    /**
     * @param string $inventario
     * @return ZMDCODBARRAS[]
     */
    public static function listarPorInventario($inventario)
    {
        $c = new Connection('RM');
        // Ordena pela coluna, nao pela posicao dela. baseSelectSql() e
        // compartilhado: bastava alguem por uma coluna antes do ID para a lista
        // passar a ordenar por outra coisa, calada. E como o TOP corta em
        // LIMITE_LISTAGEM, a tela mostraria as leituras mais ANTIGAS em vez das
        // recentes - o contrario do que quem esta bipando precisa ver.
        $c->Consulta(
            self::baseSelectSql() . ' WHERE ZMD.CODINVENTARIO = ? ORDER BY ZMD.ID DESC',
            [(string) $inventario]
        );

        return self::mapearResultado($c);
    }

    /**
     * @param Connection $c
     * @return ZMDCODBARRAS[]
     */
    private static function mapearResultado(Connection $c): array
    {
        $arrayZMD = [];
        $idlotes = [];

        while ($c->Resultado()) {
            $zmd = new ZMDCODBARRAS();
            $zmd->setId(encode_db_value($c->linha['ID']));
            $zmd->setCodigobarras(encode_db_value($c->linha['CODIGOBARRAS']));
            $zmd->setCodinventario(encode_db_value($c->linha['CODINVENTARIO']));
            $zmd->setQuantidade(encode_db_value($c->linha['QUANTIDADE']));
            $zmd->setCodloc(encode_db_value($c->linha['CODLOC']));
            $zmd->setNome(encode_db_value($c->linha['NOME']));
            $zmd->setUnd(encode_db_value($c->linha['UND']));
            // RECCREATEDON vem como DateTime; encode_db_value espera texto.
            $zmd->setCriadoPor(encode_db_value($c->linha['RECCREATEDBY'] ?? ''));
            $zmd->setCriadoEm(formatar_data_hora($c->linha['RECCREATEDON'] ?? null));

            $idlote = self::idloteDoBarcode($zmd->getCodigobarras());
            if ($idlote > 0) {
                $idlotes[$idlote] = true;
            }

            array_push($arrayZMD, $zmd);
        }

        // O numero do lote vem numa consulta propria, pela chave primaria de
        // TLOTEPRD. Como juncao ele custava 25s nesta listagem.
        $numeros = self::numerosDeLote(array_keys($idlotes));

        foreach ($arrayZMD as $zmd) {
            $codigo = $zmd->getCodigobarras();
            $chave = self::idprdDoBarcode($codigo) . ':' . self::idloteDoBarcode($codigo);
            $zmd->setNumlote($numeros[$chave] ?? '');
        }

        return $arrayZMD;
    }

    /**
     * Mensagem técnica da última gravação que falhou, para quem quiser logar
     * ou exibir em modo debug.
     *
     * @var string
     */
    public static $ultimoErro = '';

    /**
     * Motivo da recusa em linguagem de quem está contando.
     *
     * Separado de $ultimoErro porque os dois têm destinos diferentes: recusa de
     * validação é instrução para o operador e precisa aparecer sempre; erro
     * técnico do banco vai para o log e só chega à tela em modo debug.
     *
     * @var string
     */
    public static $ultimoMotivo = '';

    /**
     * Texto para a tela depois de uma gravação que falhou.
     *
     * As quatro telas montavam essa decisão cada uma do seu jeito — uma mostrava
     * o erro técnico sempre, duas só em debug, e a de leitura não mostrava nada.
     *
     * @param string $generico Texto de fallback já no contexto da tela.
     */
    public static function mensagemDaFalha(string $generico): string
    {
        if (self::$ultimoMotivo !== '') {
            return self::$ultimoMotivo;
        }

        if (self::$ultimoErro !== '' && !empty($GLOBALS['appConfig']['debug'])) {
            return $generico . ' ' . self::$ultimoErro;
        }

        return $generico;
    }

    /**
     * Recusa gravar o que as consultas de leitura não conseguiriam ler depois.
     *
     * CONVERT(INT, SUBSTRING(CODIGOBARRAS, 0, 7)) é usado por praticamente
     * toda consulta de totais. Uma única linha com código não numérico faz
     * esse CONVERT falhar e derruba o relatório do inventário inteiro — não só
     * a linha ruim. O mesmo vale para a quantidade: texto que o TRY_CAST não
     * lê vira NULL e some de todas as somas em silêncio.
     *
     * @return string '' quando está tudo certo, ou o motivo da recusa.
     */
    private function motivoParaNaoGravar(bool $exigirInventario = true): string
    {
        $codigo = (string) $this->codigobarras;
        if (strlen($codigo) !== 13 || preg_match('/\D/', $codigo)) {
            return 'Código de barras inválido: são exatamente 13 dígitos.';
        }

        $quantidade = normalizar_quantidade($this->quantidade);
        if ($quantidade === null) {
            return 'Quantidade inválida.';
        }

        if ($motivoFaixa = self::motivoQuantidadeForaDaColuna($quantidade)) {
            return $motivoFaixa;
        }

        // O UPDATE não mexe no CODINVENTARIO, então só o INSERT exige um.
        if ($exigirInventario && trim((string) $this->codinventario) === '') {
            return 'Inventário não informado.';
        }

        if (!LocaisEstoque::existe((string) $this->codloc)) {
            return 'Local de estoque inválido.';
        }

        return '';
    }

    /**
     * Quantidade que a coluna não comporta.
     *
     * O caso real não é alguém contar cem milhões: é o leitor de código de
     * barras disparar no campo de quantidade e mandar treze dígitos.
     *
     * @return string '' quando cabe, ou o motivo.
     */
    private static function motivoQuantidadeForaDaColuna(float $quantidade): string
    {
        if (abs($quantidade) > self::QUANTIDADE_MAXIMA) {
            return 'Quantidade acima do limite (' . formatar_quantidade(self::QUANTIDADE_MAXIMA)
                . '). Confira se o leitor não disparou no campo de quantidade.';
        }

        return '';
    }

    public function save()
    {
        self::$ultimoErro = '';
        self::$ultimoMotivo = '';

        $motivo = $this->motivoParaNaoGravar();
        if ($motivo !== '') {
            self::$ultimoMotivo = $motivo;
            log_erro('ZMDCODBARRAS::save', $motivo . ' (codigo=' . $this->codigobarras . ', qtd=' . $this->quantidade . ')');
            return false;
        }

        // GETDATE() em vez de data do PHP: com vários operadores, o relógio
        // que vale é o do banco, não o de cada servidor de aplicação.
        $c = new Connection('RM');
        $ok = $c->manipula(
            'INSERT INTO ZMDCODBARRAS (CODIGOBARRAS, CODINVENTARIO, QUANTIDADE, CODLOC, RECCREATEDBY, RECCREATEDON)
             VALUES (?, ?, ?, ?, ?, GETDATE())',
            [
                (string) $this->codigobarras,
                (string) $this->codinventario,
                quantidade_para_banco((float) normalizar_quantidade($this->quantidade)),
                LocaisEstoque::normalizar((string) $this->codloc),
                self::usuarioAuditoria(),
            ]
        );

        if (!$ok) {
            self::$ultimoErro = $c->erro;
        }

        return $ok;
    }

    /**
     * Código sintético de 13 dígitos para item sem lote.
     *
     * Layout lido por todas as consultas: IDPRD nos dígitos 1-6 e IDLOTE nos
     * dígitos 8-12. No SQL Server, SUBSTRING(CODIGOBARRAS, 0, 7) começa no 1º
     * caractere e devolve 6 (start + length - 1), não 7 — gravar o IDPRD em 7
     * dígitos deslocava tudo e o item era lido como outro produto.
     * IDLOTE zerado significa "sem lote".
     *
     * Devolve '' quando o IDPRD não cabe em 6 dígitos, para o chamador recusar a
     * gravação em vez de gravar um código que seria lido como outro produto.
     */
    public static function barcodeSemLote(int $idprd): string
    {
        return self::barcodeComLote($idprd, 0);
    }

    /**
     * Código sintético de 13 dígitos: IDPRD(1-6) + 0 + IDLOTE(8-12) + 0.
     *
     * As posições 7 e 13 do código impresso são dígitos que nenhuma consulta lê,
     * por isso vão zeradas no código gerado. IDLOTE 0 significa "sem lote".
     *
     * Devolve '' quando IDPRD ou IDLOTE não cabem no layout, para o chamador
     * recusar a gravação em vez de gravar algo lido como outro item.
     */
    public static function barcodeComLote(int $idprd, int $idlote = 0): string
    {
        $idlote = max(0, $idlote);

        // Produto zero nao existe. Antes isso devolvia '0000000000000' - um
        // codigo de aparencia valida para um produto que nao ha. Os dois
        // endpoints de gravacao ja recusam idprd <= 0 antes de chegar aqui, mas
        // a funcao devolvia '' para todo outro valor fora de faixa e um codigo
        // para este: a proxima chamada que esquecesse a guarda gravaria fantasma
        // em vez de receber erro.
        if ($idprd <= 0 || $idprd > 999999 || $idlote > 99999) {
            return '';
        }

        return str_pad((string) $idprd, 6, '0', STR_PAD_LEFT)
            . '0'
            . str_pad((string) $idlote, 5, '0', STR_PAD_LEFT)
            . '0';
    }

    /** IDPRD gravado no código de barras (dígitos 1-6). */
    public static function idprdDoBarcode(string $codigobarras): int
    {
        $digits = preg_replace('/\D/', '', $codigobarras);

        return strlen($digits) >= 6 ? (int) substr($digits, 0, 6) : 0;
    }

    /** IDLOTE gravado no código de barras (dígitos 8-12). 0 = sem lote. */
    public static function idloteDoBarcode(string $codigobarras): int
    {
        $digits = preg_replace('/\D/', '', $codigobarras);

        return strlen($digits) >= 12 ? (int) substr($digits, 7, 5) : 0;
    }

    /**
     * Quantidade já contada por produto + lote.
     *
     * @return array<string, float> chave "idprd:idlote" => quantidade
     */
    public static function totaisPorProdutoLote(string $codinventario): array
    {
        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            return [];
        }

        $c = new Connection('RM');
        $SQL = "SELECT CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 0, 7)) AS IDPRD,
                       CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 8, 5)) AS IDLOTE,
                       SUM(TRY_CAST(REPLACE(LTRIM(RTRIM(CAST(ZMD.QUANTIDADE AS VARCHAR(30)))), ',', '.') AS DECIMAL(18, 4))) AS QUANTIDADE
                FROM ZMDCODBARRAS ZMD
                WHERE ZMD.CODINVENTARIO = ?
                GROUP BY CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 0, 7)),
                         CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 8, 5))";
        $c->Consulta($SQL, [$codinventario]);

        $mapa = [];
        while ($c->Resultado()) {
            $idprd = (int) ($c->linha['IDPRD'] ?? 0);
            if ($idprd > 0) {
                $idlote = (int) ($c->linha['IDLOTE'] ?? 0);
                $mapa[$idprd . ':' . $idlote] = (float) ($c->linha['QUANTIDADE'] ?? 0);
            }
        }

        return $mapa;
    }

    /**
     * Quantidade já contada por produto neste inventário.
     *
     * @return array<int, float> idprd => quantidade
     */
    public static function totaisPorProduto(string $codinventario): array
    {
        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            return [];
        }

        $c = new Connection('RM');
        $SQL = "SELECT CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 0, 7)) AS IDPRD,
                       SUM(TRY_CAST(REPLACE(LTRIM(RTRIM(CAST(ZMD.QUANTIDADE AS VARCHAR(30)))), ',', '.') AS DECIMAL(18, 4))) AS QUANTIDADE
                FROM ZMDCODBARRAS ZMD
                WHERE ZMD.CODINVENTARIO = ?
                GROUP BY CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 0, 7))";
        $c->Consulta($SQL, [$codinventario]);

        $mapa = [];
        while ($c->Resultado()) {
            $idprd = (int) ($c->linha['IDPRD'] ?? 0);
            if ($idprd > 0) {
                $mapa[$idprd] = (float) ($c->linha['QUANTIDADE'] ?? 0);
            }
        }

        return $mapa;
    }

    /**
     * O que foi contado, por produto + lote, com nome e lote resolvidos.
     *
     * Irmã de totaisPorProdutoLote(), que fica de fora dos JOINs de propósito:
     * aquela roda a cada gravação e só precisa do número, esta alimenta o
     * relatório e precisa identificar o item.
     *
     * @return array<string, array{idprd:int, idlote:int, nome:string, und:string,
     *     numlote:string, bipagens:int, quantidade:float}> chave "idprd:idlote"
     */
    public static function contagemPorProdutoLote(string $codinventario): array
    {
        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            return [];
        }

        $c = new Connection('RM');
        $SQL = "SELECT
                    CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 0, 7)) AS IDPRD,
                    CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 8, 5)) AS IDLOTE,
                    MAX(T.NOMEFANTASIA) AS NOME,
                    MAX(TPRODUTODEF.CODUNDCONTROLE) AS UND,
                    COUNT(*) AS BIPAGENS,
                    SUM(TRY_CAST(REPLACE(LTRIM(RTRIM(CAST(ZMD.QUANTIDADE AS VARCHAR(30)))), ',', '.') AS DECIMAL(18, 4))) AS QUANTIDADE
                FROM ZMDCODBARRAS ZMD
                LEFT JOIN TPRODUTO T
                    ON T.IDPRD = CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 0, 7))
                LEFT JOIN TPRODUTODEF ON TPRODUTODEF.IDPRD = T.IDPRD
                WHERE ZMD.CODINVENTARIO = ?
                GROUP BY CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 0, 7)),
                         CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 8, 5))";
        $c->Consulta($SQL, [$codinventario]);

        $mapa = [];
        while ($c->Resultado()) {
            $idprd = (int) ($c->linha['IDPRD'] ?? 0);
            if ($idprd <= 0) {
                continue;
            }
            $idlote = (int) ($c->linha['IDLOTE'] ?? 0);
            $mapa[$idprd . ':' . $idlote] = [
                'idprd'      => $idprd,
                'idlote'     => $idlote,
                'nome'       => encode_db_value((string) ($c->linha['NOME'] ?? '')),
                'und'        => encode_db_value((string) ($c->linha['UND'] ?? '')),
                'numlote'    => '',
                'bipagens'   => (int) ($c->linha['BIPAGENS'] ?? 0),
                'quantidade' => (float) ($c->linha['QUANTIDADE'] ?? 0),
            ];
        }

        return self::comNumeroDoLote($mapa);
    }

    /**
     * Preenche o número do lote, numa consulta à parte.
     *
     * TLOTEPRD só tem índice em (CODCOLIGADA, IDLOTE). Enquanto o número vinha
     * por junção, ela casava também por IDPRD — coluna sem índice — e o plano
     * que o SQL Server escolhia era instável: a MESMA consulta levava 0,1s ou
     * 23s conforme o plano em cache. Numa contagem de 30 mil lançamentos, a
     * conferência do relatório simplesmente parava.
     *
     * Buscando por IDLOTE, a chave primária resolve. O par produto/lote é
     * conferido aqui: lote cujo IDPRD não bate com o do código de barras fica
     * sem número, exatamente como a junção fazia — e isso acontece de verdade,
     * em código de barras com produto e lote que não combinam.
     *
     * @param array<string, array<string, mixed>> $mapa
     * @return array<string, array<string, mixed>>
     */
    /**
     * NUMLOTE de cada lote, pela chave primária (CODCOLIGADA, IDLOTE).
     *
     * @param array<int, int> $idlotes
     * @return array<string, string> "idprd:idlote" => número do lote
     */
    private static function numerosDeLote(array $idlotes): array
    {
        $idlotes = array_values(array_filter(array_map('intval', $idlotes)));
        if ($idlotes === []) {
            return [];
        }

        // Inteiros já saneados por intval: um marcador por item estouraria o
        // limite de parâmetros com mil lotes.
        $lista = implode(',', $idlotes);

        $c = new Connection('RM');
        $c->Consulta(
            "SELECT IDLOTE, IDPRD, RTRIM(NUMLOTE) AS NUMLOTE
             FROM TLOTEPRD
             WHERE CODCOLIGADA = 1 AND IDLOTE IN ({$lista})"
        );

        $numeros = [];
        while ($c->Resultado()) {
            $numeros[(int) $c->linha['IDPRD'] . ':' . (int) $c->linha['IDLOTE']]
                = encode_db_value((string) ($c->linha['NUMLOTE'] ?? ''));
        }

        return $numeros;
    }

    private static function comNumeroDoLote(array $mapa): array
    {
        $idlotes = [];
        foreach ($mapa as $linha) {
            if ((int) $linha['idlote'] > 0) {
                $idlotes[(int) $linha['idlote']] = true;
            }
        }

        $numeros = self::numerosDeLote(array_keys($idlotes));

        foreach ($mapa as $chave => $linha) {
            if (isset($numeros[$chave])) {
                $mapa[$chave]['numlote'] = $numeros[$chave];
            }
        }

        return $mapa;
    }

    /** Primeiro numero da faixa reservada a contagens avulsas. */
    public const NUMERO_AVULSO_INICIAL = 900;

    /**
     * Um codigo avulso e um AA.LLL.NNN bem formado com NNN a partir de 900 que
     * simplesmente nao existe em TINVENTARIO.
     *
     * Manter o mesmo formato em vez de inventar "AVULSA-..." tem tres motivos:
     * cabe na coluna CODINVENTARIO como qualquer outro, todas as telas e
     * relatorios ja sabem ler, e quando o inventario de verdade for criado no RM
     * basta renomear - nao ha migracao de formato.
     */
    public static function ehCodigoAvulso(string $codinventario): bool
    {
        $parsed = self::parseCodigoInventario($codinventario);

        return !empty($parsed['valid'])
            && (int) $parsed['numero'] >= self::NUMERO_AVULSO_INICIAL;
    }

    /**
     * Proximo codigo avulso livre para o ano e local.
     *
     * Olha tanto a contagem ja gravada quanto TINVENTARIO: se alguem cadastrou
     * um inventario de verdade na faixa 9xx, nao podemos escolher o mesmo numero
     * e comecar a misturar contagens.
     *
     * @return array{codinventario: string, numero: int, error: string}
     */
    public static function proximoCodigoAvulso(string $codloc, ?string $ano = null): array
    {
        $codloc = LocaisEstoque::normalizar($codloc);
        $falha = ['codinventario' => '', 'numero' => 0, 'error' => ''];

        if ($codloc === '' || !LocaisEstoque::existe($codloc)) {
            $falha['error'] = 'Local de estoque invalido para abrir contagem avulsa.';
            return $falha;
        }

        $ano = $ano !== null && $ano !== '' ? substr(preg_replace('/\D/', '', $ano), -2) : date('y');
        $prefixo = $ano . '.' . $codloc . '.';
        $inicio = self::NUMERO_AVULSO_INICIAL;

        $c = new Connection('RM');
        $SQL = "SELECT MAX(N) AS ULTIMO FROM (
                    SELECT TRY_CAST(RIGHT(RTRIM(CODINVENTARIO), 3) AS INT) AS N
                    FROM ZMDCODBARRAS
                    WHERE LEFT(RTRIM(CODINVENTARIO), 7) = ?
                    UNION ALL
                    SELECT TRY_CAST(RIGHT(RTRIM(CODINVENTARIO), 3) AS INT) AS N
                    FROM TINVENTARIO
                    WHERE CODCOLIGADA = 1
                      AND LEFT(RTRIM(CODINVENTARIO), 7) = ?
                ) X
                WHERE N >= {$inicio}";
        $c->Consulta($SQL, [$prefixo, $prefixo]);

        $ultimo = 0;
        if ($c->Resultado()) {
            $ultimo = (int) ($c->linha['ULTIMO'] ?? 0);
        }

        $numero = max($ultimo + 1, $inicio);
        if ($numero > 999) {
            $falha['error'] = 'A faixa de contagens avulsas do local ' . $codloc . ' acabou neste ano.';
            return $falha;
        }

        return [
            'codinventario' => $prefixo . str_pad((string) $numero, 3, '0', STR_PAD_LEFT),
            'numero'        => $numero,
            'error'         => '',
        ];
    }

    /**
     * Passa a contagem de um codigo para outro - o caminho de volta do avulso
     * para o inventario que o RM criou depois.
     *
     * @return array{ok: bool, movidos: int, error: string}
     */
    public static function renomearInventario(string $de, string $para): array
    {
        $de = trim($de);
        $para = trim($para);
        $r = ['ok' => false, 'movidos' => 0, 'error' => ''];

        if ($de === '' || $para === '') {
            $r['error'] = 'Informe o codigo de origem e o de destino.';
            return $r;
        }

        if ($de === $para) {
            $r['error'] = 'Os codigos de origem e destino sao o mesmo.';
            return $r;
        }

        $total = self::contarPorInventario($de);
        if ($total === 0) {
            $r['error'] = "Nao ha contagem gravada em {$de}.";
            return $r;
        }

        $c = new Connection('RM');

        if (!$c->manipula(
            'UPDATE ZMDCODBARRAS SET CODINVENTARIO = ?, RECMODIFIEDBY = ?, RECMODIFIEDON = GETDATE()
             WHERE CODINVENTARIO = ?',
            [$para, self::usuarioAuditoria(), $de]
        )) {
            $r['error'] = 'Não foi possível mover a contagem. Tente novamente.';
            return $r;
        }

        $r['ok'] = true;
        $r['movidos'] = $total;

        return $r;
    }

    /**
     * Contagens avulsas em aberto: faixa 9xx que ainda nao existe em TINVENTARIO.
     *
     * A checagem contra TINVENTARIO vai junto na consulta de proposito. Sem ela,
     * um inventario de verdade cadastrado na faixa 9xx apareceria aqui como
     * avulso e seria oferecido para "vincular" a si mesmo.
     *
     * @return array<int, array{codinventario: string, codloc: string, local_nome: string, bipagens: int, quantidade: float}>
     */
    public static function listarAvulsos(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));
        $inicio = self::NUMERO_AVULSO_INICIAL;

        $c = new Connection('RM');
        $SQL = "SELECT TOP {$limit}
                    RTRIM(Z.CODINVENTARIO) AS CODINVENTARIO,
                    RTRIM(MAX(Z.CODLOC)) AS CODLOC,
                    COUNT(*) AS BIPAGENS,
                    SUM(TRY_CAST(REPLACE(LTRIM(RTRIM(CAST(Z.QUANTIDADE AS VARCHAR(30)))), ',', '.') AS DECIMAL(18, 4))) AS QUANTIDADE
                FROM ZMDCODBARRAS Z
                WHERE TRY_CAST(RIGHT(RTRIM(Z.CODINVENTARIO), 3) AS INT) >= {$inicio}
                  -- Mesma forma que ehCodigoAvulso() exige. Sem isto a lista
                  -- mostrava codigos que o descarte depois recusava: o codigo de
                  -- barras '0013710175975' termina em 975, passava no >= 900 e
                  -- aparecia como avulsa com 482 lancamentos - mas 'Descartar
                  -- avulsa' negava, e mandava para a tela de Leitura, que
                  -- tambem recusa. Os lancamentos ficavam a vista e presos.
                  AND RTRIM(Z.CODINVENTARIO) LIKE '[0-9][0-9].[0-9][0-9][0-9].[0-9][0-9][0-9]'
                  AND NOT EXISTS (
                        SELECT 1 FROM TINVENTARIO T
                        WHERE T.CODCOLIGADA = 1
                          AND RTRIM(T.CODINVENTARIO) = RTRIM(Z.CODINVENTARIO)
                  )
                GROUP BY RTRIM(Z.CODINVENTARIO)
                ORDER BY RTRIM(Z.CODINVENTARIO) DESC";
        $c->Consulta($SQL);

        $lista = [];
        while ($c->Resultado()) {
            $cod = encode_db_value((string) ($c->linha['CODINVENTARIO'] ?? ''));
            $codloc = encode_db_value((string) ($c->linha['CODLOC'] ?? ''));
            if ($codloc === '') {
                $parsed = self::parseCodigoInventario($cod);
                if (!empty($parsed['valid'])) {
                    $codloc = (string) $parsed['codloc'];
                }
            }
            $lista[] = [
                'codinventario' => $cod,
                'codloc'        => $codloc,
                'local_nome'    => $codloc !== '' ? LocaisEstoque::nome($codloc) : '',
                'bipagens'      => (int) ($c->linha['BIPAGENS'] ?? 0),
                'quantidade'    => (float) ($c->linha['QUANTIDADE'] ?? 0),
            ];
        }

        return $lista;
    }

    /**
     * Substitui a contagem acumulada de um produto + lote pelo valor informado.
     *
     * Apaga e grava dentro de UMA transação. A versão anterior fazia
     * SELECT MAX(ID) -> INSERT -> DELETE ID <= max fora de transação, e dois
     * operadores corrigindo o mesmo lote ao mesmo tempo somavam em vez de
     * substituir: os dois liam o mesmo MAX(ID), os dois inseriam, e o DELETE
     * do segundo não alcançava a linha do primeiro. Um lote de 7 "corrigido"
     * para 5 e para 8 ao mesmo tempo terminava com 13, e os dois operadores
     * liam "Total corrigido".
     *
     * Com a transação, o DELETE segura as linhas até o commit: quem chega
     * depois espera, enxerga a linha do primeiro e a substitui. O último a
     * gravar vence, que é o que "corrigir" quer dizer. E como a operação é
     * atômica, apagar antes de inserir deixou de ter risco — não existe mais
     * instante em que a contagem fica zerada para os outros.
     *
     * Quantidade zero significa apagar a contagem daquele lote.
     *
     * @return array{ok: bool, apagados: int, error: string}
     */
    public static function corrigirTotalProdutoLote(
        string $codinventario,
        int $idprd,
        int $idlote,
        float $quantidade,
        string $codloc
    ): array {
        $r = ['ok' => false, 'apagados' => 0, 'error' => ''];
        $codinventario = trim($codinventario);

        if ($codinventario === '' || $idprd <= 0) {
            $r['error'] = 'Produto ou inventário inválido.';
            return $r;
        }

        if ($quantidade < 0) {
            $r['error'] = 'Quantidade não pode ser negativa.';
            return $r;
        }

        // A correção monta o próprio INSERT e não passa por motivoParaNaoGravar().
        if ($motivoFaixa = self::motivoQuantidadeForaDaColuna($quantidade)) {
            $r['error'] = $motivoFaixa;
            return $r;
        }

        // ISNUMERIC + LEN protegem o CONVERT: uma linha com codigo fora do
        // layout faria o CONVERT falhar e derrubaria a correcao inteira.
        $filtroItem = "CODINVENTARIO = ?
                  AND LEN(CODIGOBARRAS) = 13
                  AND CODIGOBARRAS NOT LIKE '%[^0-9]%'
                  AND CONVERT(INT, SUBSTRING(CODIGOBARRAS, 0, 7)) = ?
                  AND CONVERT(INT, SUBSTRING(CODIGOBARRAS, 8, 5)) = ?";
        $filtroParams = [$codinventario, $idprd, $idlote];

        $codigobarras = '';
        if ($quantidade > 0) {
            $codigobarras = self::barcodeComLote($idprd, $idlote);
            if ($codigobarras === '') {
                $r['error'] = 'Produto ou lote não cabe no código de 13 dígitos.';
                return $r;
            }

            if (!LocaisEstoque::existe($codloc)) {
                $r['error'] = 'Local de estoque inválido.';
                return $r;
            }
        }

        $c = new Connection('RM');
        $c->iniciarTransacao();

        try {
            $apagados = $c->manipulaContando("DELETE FROM ZMDCODBARRAS WHERE {$filtroItem}", $filtroParams);

            if ($apagados < 0) {
                throw new DatabaseException('Falha ao limpar a contagem anterior.', $c->erro);
            }

            if ($apagados === 0 && $quantidade <= 0) {
                $c->desfazerTransacao();
                $r['error'] = 'Não há contagem gravada para este lote.';
                return $r;
            }

            if ($quantidade > 0) {
                $ok = $c->manipula(
                    'INSERT INTO ZMDCODBARRAS (CODIGOBARRAS, CODINVENTARIO, QUANTIDADE, CODLOC, RECCREATEDBY, RECCREATEDON)
                     VALUES (?, ?, ?, ?, ?, GETDATE())',
                    [
                        $codigobarras,
                        $codinventario,
                        quantidade_para_banco($quantidade),
                        LocaisEstoque::normalizar($codloc),
                        self::usuarioAuditoria(),
                    ]
                );

                if (!$ok) {
                    throw new DatabaseException('Falha ao gravar a contagem corrigida.', $c->erro);
                }
            }

            $c->confirmarTransacao();
        } catch (Throwable $e) {
            $c->desfazerTransacao();
            log_erro('corrigirTotalProdutoLote', $e->getMessage());
            $r['error'] = 'Não foi possível corrigir o total. Nada foi alterado — tente de novo.';
            return $r;
        }

        // A correção apaga lançamentos: as colunas de auditoria somem com eles,
        // então o que aconteceu só fica registrado aqui.
        log_auditoria('corrigir total', "inv={$codinventario} idprd={$idprd} idlote={$idlote} "
            . "novo={$quantidade} substituiu={$apagados}");

        $r['ok'] = true;
        $r['apagados'] = $apagados;

        return $r;
    }

    public function atualizar()
    {
        self::$ultimoErro = '';
        self::$ultimoMotivo = '';

        $id = (int) $this->id;
        if ($id <= 0) {
            self::$ultimoMotivo = 'Registro não informado.';
            return false;
        }

        // Mesma regra do save(): a edição não pode gravar o que a leitura não
        // consegue reprocessar depois.
        $motivo = $this->motivoParaNaoGravar(false);
        if ($motivo !== '') {
            self::$ultimoMotivo = $motivo;
            return false;
        }

        $c = new Connection('RM');
        $ok = $c->manipula(
            'UPDATE ZMDCODBARRAS SET CODIGOBARRAS = ?, QUANTIDADE = ?, CODLOC = ?,
                    RECMODIFIEDBY = ?, RECMODIFIEDON = GETDATE()
             WHERE ID = ?',
            [
                (string) $this->codigobarras,
                quantidade_para_banco((float) normalizar_quantidade($this->quantidade)),
                LocaisEstoque::normalizar((string) $this->codloc),
                self::usuarioAuditoria(),
                $id,
            ]
        );

        if (!$ok) {
            self::$ultimoErro = $c->erro;
        }

        return $ok;
    }

    /**
     * @param string|int $id
     */
    public static function excluirPorId($id)
    {
        self::$ultimoErro = '';

        $id = (int) $id;
        if ($id <= 0) {
            self::$ultimoErro = 'Registro não informado.';
            return false;
        }

        // Lê antes de apagar: depois do DELETE não sobra nada para registrar,
        // nem as colunas de auditoria da linha. Uma consulta a mais numa
        // operação rara é o preço de saber o que foi removido.
        $c = new Connection('RM');
        $c->Consulta(
            'SELECT CODIGOBARRAS, CODINVENTARIO, QUANTIDADE, CODLOC FROM ZMDCODBARRAS WHERE ID = ?',
            [$id]
        );

        $antes = $c->Resultado()
            ? 'cod=' . trim((string) ($c->linha['CODIGOBARRAS'] ?? ''))
              . ' inv=' . trim((string) ($c->linha['CODINVENTARIO'] ?? ''))
              . ' qtd=' . trim((string) ($c->linha['QUANTIDADE'] ?? ''))
              . ' loc=' . trim((string) ($c->linha['CODLOC'] ?? ''))
            : 'linha nao encontrada';

        $ok = $c->manipula('DELETE FROM ZMDCODBARRAS WHERE ID = ?', [$id]);

        if (!$ok) {
            self::$ultimoErro = $c->erro;
            return false;
        }

        log_auditoria('excluir lancamento', "id={$id} {$antes}");

        return true;
    }

    /**
     * Inventários que já têm bipagem em ZMDCODBARRAS.
     *
     * @return array<int, array{codinventario: string, codloc: string, local_nome: string, bipagens: int, quantidade: float}>
     */
    public static function listarInventariosComContagem(int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));
        $c = new Connection('RM');
        $SQL = "SELECT TOP {$limit}
                    RTRIM(ZMD.CODINVENTARIO) AS CODINVENTARIO,
                    RTRIM(MAX(ZMD.CODLOC)) AS CODLOC,
                    COUNT(*) AS BIPAGENS,
                    SUM(TRY_CAST(REPLACE(LTRIM(RTRIM(CAST(ZMD.QUANTIDADE AS VARCHAR(30)))), ',', '.') AS DECIMAL(18, 4))) AS QUANTIDADE
                FROM ZMDCODBARRAS ZMD
                GROUP BY RTRIM(ZMD.CODINVENTARIO)
                ORDER BY RTRIM(ZMD.CODINVENTARIO) DESC";
        $c->Consulta($SQL);

        $lista = [];
        while ($c->Resultado()) {
            $cod = encode_db_value((string) ($c->linha['CODINVENTARIO'] ?? ''));
            $codloc = encode_db_value((string) ($c->linha['CODLOC'] ?? ''));
            if ($codloc === '') {
                $parsed = self::parseCodigoInventario($cod);
                if (!empty($parsed['valid'])) {
                    $codloc = (string) $parsed['codloc'];
                }
            }
            $lista[] = [
                'codinventario' => $cod,
                'codloc'        => $codloc,
                'local_nome'    => $codloc !== '' ? LocaisEstoque::nome($codloc) : '',
                'bipagens'      => (int) ($c->linha['BIPAGENS'] ?? 0),
                'quantidade'    => (float) ($c->linha['QUANTIDADE'] ?? 0),
            ];
        }

        return $lista;
    }

    /**
     * Relatório de contagem: totais + itens agrupados por produto/lote.
     *
     * @return array{
     *   totais: array{bipagens: int, quantidade: float, produtos: int, lotes: int},
     *   itens: array<int, array{idprd: int, codigo: string, nome: string, und: string, lote: string, codloc: string, bipagens: int, quantidade: float}>
     * }
     */
    public static function relatorioContagem(string $codinventario): array
    {
        $vazio = [
            'totais' => ['bipagens' => 0, 'quantidade' => 0.0, 'produtos' => 0, 'lotes' => 0],
            'itens'  => [],
        ];

        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            return $vazio;
        }

        $c = new Connection('RM');
        $SQL = "SELECT
                    CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 0, 7)) AS IDPRD,
                    T.CODIGOPRD AS CODIGO,
                    T.NOMEFANTASIA AS NOME,
                    TPRODUTODEF.CODUNDCONTROLE AS UND,
                    CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 8, 5)) AS IDLOTE,
                    RTRIM(ZMD.CODLOC) AS CODLOC,
                    COUNT(*) AS BIPAGENS,
                    SUM(TRY_CAST(REPLACE(LTRIM(RTRIM(CAST(ZMD.QUANTIDADE AS VARCHAR(30)))), ',', '.') AS DECIMAL(18, 4))) AS QUANTIDADE
                FROM ZMDCODBARRAS ZMD
                LEFT JOIN TPRODUTO T ON T.IDPRD = CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 0, 7))
                LEFT JOIN TPRODUTODEF ON TPRODUTODEF.IDPRD = T.IDPRD
                WHERE ZMD.CODINVENTARIO = ?
                GROUP BY
                    CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 0, 7)),
                    T.CODIGOPRD,
                    T.NOMEFANTASIA,
                    TPRODUTODEF.CODUNDCONTROLE,
                    CONVERT(INT, SUBSTRING(ZMD.CODIGOBARRAS, 8, 5)),
                    RTRIM(ZMD.CODLOC)";
        $c->Consulta($SQL, [$codinventario]);

        /*
         * O número do lote entra depois, e o agrupamento é refeito aqui.
         *
         * Antes a consulta agrupava pelo TEXTO do lote, o que exigia juntar
         * TLOTEPRD por IDPRD — coluna sem índice. Essa junção fazia o plano do
         * SQL Server variar de 0,1s para 23s com o mesmo dado, e era ela que
         * travava o relatório de um inventário com 30 mil lançamentos.
         *
         * Agora a consulta agrupa por IDLOTE (barato) e a junção some. O texto
         * do lote vem da busca por chave primária, e as linhas voltam a ser
         * fundidas por produto + texto do lote + local — exatamente o
         * agrupamento de antes, inclusive quando dois IDLOTE diferentes do
         * mesmo produto carregam o mesmo número.
         */
        $brutos = [];
        $idlotes = [];
        while ($c->Resultado()) {
            $linha = [
                'idprd'      => (int) ($c->linha['IDPRD'] ?? 0),
                'idlote'     => (int) ($c->linha['IDLOTE'] ?? 0),
                'codigo'     => encode_db_value(trim((string) ($c->linha['CODIGO'] ?? ''))),
                'nome'       => encode_db_value((string) ($c->linha['NOME'] ?? '')),
                'und'        => encode_db_value((string) ($c->linha['UND'] ?? '')),
                'codloc'     => encode_db_value((string) ($c->linha['CODLOC'] ?? '')),
                'bipagens'   => (int) ($c->linha['BIPAGENS'] ?? 0),
                'quantidade' => (float) ($c->linha['QUANTIDADE'] ?? 0),
            ];
            $brutos[] = $linha;
            if ($linha['idlote'] > 0) {
                $idlotes[$linha['idlote']] = true;
            }
        }

        $numeros = self::numerosDeLote(array_keys($idlotes));

        $itens = [];
        $qtd = 0.0;
        $bipagens = 0;
        $produtos = [];
        $lotes = [];
        $porChave = [];

        foreach ($brutos as $linha) {
            $idprd = $linha['idprd'];
            $lote = $numeros[$idprd . ':' . $linha['idlote']] ?? '';
            $q = $linha['quantidade'];
            $b = $linha['bipagens'];
            $qtd += $q;
            $bipagens += $b;
            if ($idprd > 0) {
                $produtos[$idprd] = true;
            }
            // Chaveado por produto+lote: dois produtos diferentes podem usar o
            // mesmo NUMLOTE, e contando só pelo texto do lote os dois viravam um.
            if (trim($lote) !== '') {
                $lotes[$idprd . ':' . $lote] = true;
            }

            $chave = $idprd . '|' . $lote . '|' . $linha['codloc'];
            if (isset($porChave[$chave])) {
                $itens[$porChave[$chave]]['bipagens'] += $b;
                $itens[$porChave[$chave]]['quantidade'] += $q;
                continue;
            }

            $porChave[$chave] = count($itens);
            $itens[] = [
                'idprd'      => $idprd,
                'codigo'     => $linha['codigo'],
                'nome'       => $linha['nome'],
                'und'        => $linha['und'],
                'lote'       => $lote,
                'codloc'     => $linha['codloc'],
                'bipagens'   => $b,
                'quantidade' => $q,
            ];
        }

        // A ordenação era do ORDER BY da consulta; com o lote resolvido depois,
        // ela vem para cá.
        usort($itens, function ($a, $b) {
            return [$a['nome'], $a['lote'], $a['codloc']] <=> [$b['nome'], $b['lote'], $b['codloc']];
        });

        return [
            'totais' => [
                'bipagens'   => $bipagens,
                'quantidade' => $qtd,
                'produtos'   => count($produtos),
                'lotes'      => count($lotes),
            ],
            'itens' => $itens,
        ];
    }

    /**
     * Conta registros de um inventário.
     */
    public static function contarPorInventario(string $codinventario): int
    {
        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            return 0;
        }

        $c = new Connection('RM');
        $c->Consulta('SELECT COUNT(*) AS TOTAL FROM ZMDCODBARRAS WHERE CODINVENTARIO = ?', [$codinventario]);

        if (!$c->Resultado()) {
            return 0;
        }

        return (int) ($c->linha['TOTAL'] ?? 0);
    }

    /**
     * Total de bipagens de vários inventários numa consulta só.
     *
     * A home e a lista de inventários abertos mostram o total de cada linha.
     * Uma consulta por linha significava dezenas de idas ao SQL Server só para
     * pintar a tela — e era o suficiente para estourar o tempo quando a lista
     * crescia.
     *
     * @param string[] $codigos
     * @return array<string, int> codinventario => total
     */
    public static function contarPorInventarios(array $codigos): array
    {
        $codigos = array_values(array_unique(array_filter(array_map('trim', $codigos), function ($v) {
            return $v !== '';
        })));

        if ($codigos === []) {
            return [];
        }

        $marcadores = implode(', ', array_fill(0, count($codigos), '?'));

        $c = new Connection('RM');
        $c->Consulta(
            "SELECT RTRIM(CODINVENTARIO) AS CODINVENTARIO, COUNT(*) AS TOTAL
             FROM ZMDCODBARRAS
             WHERE RTRIM(CODINVENTARIO) IN ({$marcadores})
             GROUP BY RTRIM(CODINVENTARIO)",
            $codigos
        );

        $mapa = array_fill_keys($codigos, 0);
        while ($c->Resultado()) {
            $cod = trim(encode_db_value((string) ($c->linha['CODINVENTARIO'] ?? '')));
            if ($cod !== '') {
                $mapa[$cod] = (int) ($c->linha['TOTAL'] ?? 0);
            }
        }

        return $mapa;
    }

    /**
     * Total real de bipagens do inventário.
     *
     * A listagem em tela é limitada a LIMITE_LISTAGEM. Enquanto o limite não é
     * atingido, os registros carregados JÁ são o total e não vale gastar uma
     * consulta; passando do limite, count() mentiria e o COUNT vira obrigatório.
     *
     * @param ZMDCODBARRAS[] $registrosCarregados
     */
    public static function totalBipagens(string $codinventario, array $registrosCarregados): int
    {
        $carregados = count($registrosCarregados);

        if ($carregados < self::LIMITE_LISTAGEM) {
            return $carregados;
        }

        return self::contarPorInventario($codinventario);
    }

    /**
     * Quantas vezes este código já foi bipado no inventário e a quantidade somada.
     *
     * Serve para avisar o operador sobre releitura do mesmo produto/lote. O
     * registro é mantido de propósito — contar duas caixas do mesmo lote é
     * legítimo —, mas a bipagem repetida por engano precisa ficar visível.
     *
     * @return array{leituras: int, quantidade: float}
     */
    public static function resumoDoCodigo(string $codinventario, string $codigobarras): array
    {
        $vazio = ['leituras' => 0, 'quantidade' => 0.0];

        $codinventario = trim($codinventario);
        $codigobarras = preg_replace('/\D/', '', $codigobarras);

        if ($codinventario === '' || $codigobarras === '') {
            return $vazio;
        }

        // Compara por IDPRD + IDLOTE, não pela string do código: o mesmo
        // produto/lote pode chegar bipado da etiqueta (dígitos 7 e 13 com os
        // valores impressos) ou gerado pela tela de lotes (esses dígitos
        // zerados). São códigos diferentes para o mesmo item, e comparar texto
        // deixaria a releitura passar despercebida entre as duas telas.
        $idprd = self::idprdDoBarcode($codigobarras);
        $idlote = self::idloteDoBarcode($codigobarras);

        if ($idprd <= 0) {
            return $vazio;
        }

        $c = new Connection('RM');
        $SQL = "SELECT COUNT(*) AS LEITURAS,
                       SUM(TRY_CAST(REPLACE(LTRIM(RTRIM(CAST(QUANTIDADE AS VARCHAR(30)))), ',', '.') AS DECIMAL(18, 4))) AS QUANTIDADE
                FROM ZMDCODBARRAS
                WHERE CODINVENTARIO = ?
                  AND LEN(CODIGOBARRAS) = 13
                  AND CODIGOBARRAS NOT LIKE '%[^0-9]%'
                  AND CONVERT(INT, SUBSTRING(CODIGOBARRAS, 0, 7)) = ?
                  AND CONVERT(INT, SUBSTRING(CODIGOBARRAS, 8, 5)) = ?";
        $c->Consulta($SQL, [$codinventario, $idprd, $idlote]);

        if (!$c->Resultado()) {
            return $vazio;
        }

        return [
            'leituras'   => (int) ($c->linha['LEITURAS'] ?? 0),
            'quantidade' => (float) ($c->linha['QUANTIDADE'] ?? 0),
        ];
    }

    /**
     * Remove todos os registros de um inventário.
     */
    public static function excluirPorInventario(string $codinventario): bool
    {
        self::$ultimoErro = '';

        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            self::$ultimoErro = 'Inventário não informado.';
            return false;
        }

        // manipulaContando em vez de manipula: apagar a contagem inteira é a
        // operação mais destrutiva do sistema, e quantas linhas se foram é a
        // informação que faltaria depois.
        $c = new Connection('RM');
        $apagados = $c->manipulaContando('DELETE FROM ZMDCODBARRAS WHERE CODINVENTARIO = ?', [$codinventario]);

        if ($apagados < 0) {
            self::$ultimoErro = $c->erro;
            return false;
        }

        log_auditoria('excluir contagem', "inv={$codinventario} lancamentos={$apagados}");

        return true;
    }

    /**
     * Máscara do código de inventário: AA.LLL.NNN (ano.local.número).
     * Ex.: 26.065.002 — o local (LLL) deve existir em LocaisEstoque.
     *
     * @return array{valid: bool, formatted: string, ano: string, codloc: string, numero: string, nome_local: string, error: string}
     */
    public static function parseCodigoInventario(string $codigo): array
    {
        $result = [
            'valid'      => false,
            'formatted'  => '',
            'ano'        => '',
            'codloc'     => '',
            'numero'     => '',
            'nome_local' => '',
            'error'      => '',
        ];

        $digits = preg_replace('/\D/', '', trim($codigo));

        if (strlen($digits) !== 8) {
            $result['error'] = 'Código do inventário deve seguir o formato AA.LLL.NNN (ano.local.número), ex.: 26.065.002.';
            return $result;
        }

        $ano = substr($digits, 0, 2);
        $codloc = substr($digits, 2, 3);
        $numero = substr($digits, 5, 3);
        $formatted = $ano . '.' . $codloc . '.' . $numero;

        $result['ano'] = $ano;
        $result['codloc'] = $codloc;
        $result['numero'] = $numero;
        $result['formatted'] = $formatted;

        $local = LocaisEstoque::validar($codloc);
        if (!$local['valid']) {
            $result['error'] = $local['error'];
            return $result;
        }

        $result['valid'] = true;
        $result['nome_local'] = $local['nome'];

        return $result;
    }

    /**
     * Formata para AA.LLL.NNN quando houver 8 dígitos; caso contrário devolve o valor original.
     * Não valida o local — use parseCodigoInventario() para validação completa.
     */
    public static function formatCodigoInventario(string $codigo): string
    {
        $digits = preg_replace('/\D/', '', trim($codigo));
        if (strlen($digits) !== 8) {
            return trim($codigo);
        }

        return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3);
    }

    /**
     * @return array<string, string>
     */
    public static function inventarioQueryParams(string $codloc, string $inventario, string $quantidade = '1'): array
    {
        return [
            'CODLOC'        => $codloc,
            'CODINVENTARIO' => $inventario,
            'QUANTIDADE'    => $quantidade,
        ];
    }

    /**
     * Valida código de barras antes de gravar (13 dígitos, produto e lote no RM).
     *
     * @return array{valid: bool, errors: string[], warnings: string[], nome: string, und: string, lote: string, idprd: int}
     */
    public static function validarCodigoBarras(string $codigobarras): array
    {
        $result = [
            'valid'    => false,
            'errors'   => [],
            'warnings' => [],
            'nome'     => '',
            'und'      => '',
            'lote'     => '',
            'idprd'    => 0,
        ];

        $codigobarras = preg_replace('/\D/', '', $codigobarras);

        if (strlen($codigobarras) !== 13) {
            $result['errors'][] = 'O código de barras deve ter exatamente 13 dígitos.';
            return $result;
        }

        $c = new Connection('RM');
        $SQL = "SELECT T.NOMEFANTASIA AS NOME, T.IDPRD, TPRODUTODEF.CODUNDCONTROLE AS UND, TLOTEPRD.NUMLOTE
                FROM TPRODUTO T
                LEFT JOIN TPRODUTODEF ON TPRODUTODEF.IDPRD = T.IDPRD
                LEFT JOIN TLOTEPRD ON TLOTEPRD.IDPRD = T.IDPRD
                    AND TLOTEPRD.IDLOTE = ?
                WHERE T.IDPRD = ?";

        $c->Consulta($SQL, [self::idloteDoBarcode($codigobarras), self::idprdDoBarcode($codigobarras)]);

        if (!$c->Resultado()) {
            $result['errors'][] = 'Produto não encontrado no RM para este código de barras.';
            return $result;
        }

        $result['nome'] = encode_db_value($c->linha['NOME'] ?? '');
        $result['und'] = encode_db_value($c->linha['UND'] ?? '');
        $result['lote'] = encode_db_value($c->linha['NUMLOTE'] ?? '');
        $result['idprd'] = (int) ($c->linha['IDPRD'] ?? 0);

        if (trim($result['nome']) === '') {
            $result['errors'][] = 'Produto não encontrado no RM para este código de barras.';
            return $result;
        }

        if (trim($result['lote']) === '') {
            $result['warnings'][] = 'Lote não encontrado no RM — verifique o código.';
        }

        $result['valid'] = true;
        return $result;
    }

    public function getQuantidade()
    {
        return $this->quantidade;
    }

    public function setQuantidade($quantidade)
    {
        $this->quantidade = $quantidade;
    }

    public function getCodloc()
    {
        return $this->codloc;
    }

    public function setCodloc($codloc)
    {
        $this->codloc = $codloc;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getCodigobarras()
    {
        return $this->codigobarras;
    }

    public function setCodigobarras($codigobarras)
    {
        $this->codigobarras = $codigobarras;
    }

    public function getCodinventario()
    {
        return $this->codinventario;
    }

    public function setCodinventario($codinventario)
    {
        $this->codinventario = $codinventario;
    }

    public function getUnd()
    {
        return $this->und;
    }

    public function setUnd($und)
    {
        $this->und = $und;
    }

    public function getNome()
    {
        return $this->nome;
    }

    public function setNome($nome)
    {
        $this->nome = $nome;
    }

    public function getCriadoPor()
    {
        return $this->criadoPor;
    }

    public function setCriadoPor($criadoPor)
    {
        $this->criadoPor = $criadoPor;
    }

    public function getCriadoEm()
    {
        return $this->criadoEm;
    }

    public function setCriadoEm($criadoEm)
    {
        $this->criadoEm = $criadoEm;
    }

    public function getNumlote()
    {
        return $this->numlote;
    }

    public function setNumlote($numlote)
    {
        $this->numlote = $numlote;
    }
}
