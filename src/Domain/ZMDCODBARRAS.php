<?php

/**
 * Domínio: leitura de código de barras para inventário TOTVS RM.
 * Regras de negócio preservadas da versão original (h9).
 */
class ZMDCODBARRAS
{
    private $id;
    private $codigobarras;
    private $codinventario;
    private $quantidade;
    private $codloc;
    private $nome;
    private $und;
    private $numlote;

    /**
     * SQL base compartilhado — evita duplicação entre listagens.
     */
    private static function baseSelectSql(): string
    {
        return "SELECT TOP 1000 zmd.ID, ZMD.CODIGOBARRAS, ZMD.CODINVENTARIO, ZMD.QUANTIDADE, ZMD.CODLOC, T.NOMEFANTASIA AS NOME, TPRODUTODEF.CODUNDCONTROLE AS UND, TLOTEPRD.NUMLOTE
                FROM ZMDCODBARRAS ZMD
                LEFT JOIN TPRODUTO T ON T.IDPRD = CONVERT(INT,SUBSTRING(ZMD.CODIGOBARRAS,0,7))
                LEFT JOIN TPRODUTODEF ON TPRODUTODEF.IDPRD = T.IDPRD
                LEFT JOIN TLOTEPRD ON TLOTEPRD.IDPRD = T.IDPRD AND TLOTEPRD.IDLOTE = CONVERT(INT,SUBSTRING(CODIGOBARRAS,8,5))";
    }

    /**
     * @param string $inventario
     * @return ZMDCODBARRAS[]
     */
    public static function listarPorInventario($inventario)
    {
        $c = new Connection('RM');
        $SQL = self::baseSelectSql() . " WHERE ZMD.CODINVENTARIO = '$inventario' ORDER BY 1 DESC ";
        $c->Consulta($SQL);

        return self::mapearResultado($c);
    }

    /**
     * @return ZMDCODBARRAS[]
     */
    public static function listarTodos()
    {
        $c = new Connection('RM');
        $SQL = self::baseSelectSql() . " ORDER BY 1 DESC ";
        $c->Consulta($SQL);

        return self::mapearResultado($c);
    }

    /**
     * @param Connection $c
     * @return ZMDCODBARRAS[]
     */
    private static function mapearResultado(Connection $c): array
    {
        $arrayZMD = [];

        while ($c->Resultado()) {
            $zmd = new ZMDCODBARRAS();
            $zmd->setId(encode_db_value($c->linha['ID']));
            $zmd->setCodigobarras(encode_db_value($c->linha['CODIGOBARRAS']));
            $zmd->setCodinventario(encode_db_value($c->linha['CODINVENTARIO']));
            $zmd->setQuantidade(encode_db_value($c->linha['QUANTIDADE']));
            $zmd->setCodloc(encode_db_value($c->linha['CODLOC']));
            $zmd->setNome(encode_db_value($c->linha['NOME']));
            $zmd->setUnd(encode_db_value($c->linha['UND']));
            $zmd->setNumlote(encode_db_value($c->linha['NUMLOTE']));

            array_push($arrayZMD, $zmd);
        }

        return $arrayZMD;
    }

    public function save()
    {
        $c = new Connection('RM');
        $SQL = "INSERT INTO ZMDCODBARRAS (CODIGOBARRAS,CODINVENTARIO,QUANTIDADE,CODLOC) VALUES ('{$this->codigobarras}','{$this->codinventario}','{$this->quantidade}','{$this->codloc}')";
        return $c->manipula($SQL);
    }

    public function atualizar()
    {
        if (empty($this->id)) {
            return false;
        }

        $c = new Connection('RM');
        $SQL = "UPDATE ZMDCODBARRAS SET CODIGOBARRAS='{$this->codigobarras}', QUANTIDADE='{$this->quantidade}', CODLOC='{$this->codloc}' WHERE ID='{$this->id}'";
        return $c->manipula($SQL);
    }

    /**
     * @param string|int $id
     */
    public static function excluirPorId($id)
    {
        $c = new Connection('RM');
        $SQL = "DELETE FROM ZMDCODBARRAS WHERE ID='{$id}'";
        return $c->manipula($SQL);
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
        $SQL = "SELECT COUNT(*) AS TOTAL FROM ZMDCODBARRAS WHERE CODINVENTARIO = '{$codinventario}'";
        $c->Consulta($SQL);

        if (!$c->Resultado()) {
            return 0;
        }

        return (int) ($c->linha['TOTAL'] ?? 0);
    }

    /**
     * Remove todos os registros de um inventário.
     */
    public static function excluirPorInventario(string $codinventario): bool
    {
        $codinventario = trim($codinventario);
        if ($codinventario === '') {
            return false;
        }

        $c = new Connection('RM');
        $SQL = "DELETE FROM ZMDCODBARRAS WHERE CODINVENTARIO = '{$codinventario}'";
        return $c->manipula($SQL);
    }

    /**
     * @param string $inventario
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
     * @return array{valid: bool, errors: string[], warnings: string[], nome: string, und: string, lote: string}
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
                    AND TLOTEPRD.IDLOTE = CONVERT(INT, SUBSTRING('{$codigobarras}', 8, 5))
                WHERE T.IDPRD = CONVERT(INT, SUBSTRING('{$codigobarras}', 0, 7))";

        $c->Consulta($SQL);

        if (!$c->Resultado()) {
            $result['errors'][] = 'Produto não encontrado no RM para este código de barras.';
            return $result;
        }

        $result['nome'] = encode_db_value($c->linha['NOME'] ?? '');
        $result['und'] = encode_db_value($c->linha['UND'] ?? '');
        $result['lote'] = encode_db_value($c->linha['NUMLOTE'] ?? '');

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

    public function getNumlote()
    {
        return $this->numlote;
    }

    public function setNumlote($numlote)
    {
        $this->numlote = $numlote;
    }
}
