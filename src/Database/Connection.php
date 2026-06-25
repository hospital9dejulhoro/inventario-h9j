<?php

/**
 * Camada de acesso ao SQL Server — compatível com a API original.
 */
class Connection
{
    /** @var resource|false */
    public $id;

    /** @var resource|false */
    public $res;

    /** @var int */
    public $qtd;

    /** @var array|false */
    public $linha;

    /** @var string */
    public $erro;

    /** @var array|false */
    public $data;

    public function __construct($db = 'RM')
    {
        $this->abre($db);
    }

    public function abre($db)
    {
        $current = EnvironmentManager::getCurrent();

        if ($current === null) {
            die('Nenhum ambiente selecionado. Retorne à tela inicial e conecte-se.');
        }

        $connectionInfo = EnvironmentManager::buildConnectionInfo($current);

        $this->id = sqlsrv_connect($current['host'], $connectionInfo);

        if ($this->id === false) {
            die(print_r(sqlsrv_errors(), true));
        }
    }

    public function fecha()
    {
        if ($this->id) {
            @sqlsrv_close($this->id);
        }

        $this->id = '';
        $this->res = 0;
        $this->qtd = 0;
        $this->linha = '';
    }

    /**
     * @param string $sql
     */
    public function Consulta($sql = '')
    {
        if ($sql == '') {
            $this->res = 0;
            $this->qtd = 0;
        } else {
            $this->res = sqlsrv_query($this->id, $sql);

            if ($this->res) {
                $this->qtd = sqlsrv_num_rows($this->res);
            } else {
                print_r(sqlsrv_errors());
            }
        }
    }

    public function manipula($sql = '')
    {
        if (sqlsrv_query($this->id, $sql)) {
            return true;
        }

        $this->erro = '';
        return false;
    }

    public function Resultado()
    {
        if ($this->res) {
            $this->linha = sqlsrv_fetch_array($this->res);
        }

        if (!$this->linha) {
            return false;
        }

        return true;
    }

    public function retornaJson()
    {
        $json = [];

        do {
            while ($row = sqlsrv_fetch_array($this->res, SQLSRV_FETCH_ASSOC)) {
                $json[] = $row;
            }
        } while (sqlsrv_next_result($this->res));

        print_r($json);
        $retorno = json_encode($json);
        print_r($retorno);
    }

    public function dados()
    {
        if ($this->res) {
            $this->data = sqlsrv_fetch_array($this->res);
        }

        if (!$this->data) {
            return false;
        }

        return true;
    }

    public function libera()
    {
        // Mantido por compatibilidade com a versão anterior.
    }
}
