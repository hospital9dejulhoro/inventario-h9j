<?php

/**
 * Camada de acesso ao SQL Server.
 *
 * Duas regras que a versão anterior não tinha:
 *
 *  1. Consulta que falha LEVANTA exceção. Antes deixava Resultado() em false,
 *     indistinguível de "zero linhas" — e a tela dizia "inventário não está
 *     cadastrado no RM" quando na verdade a consulta tinha quebrado.
 *  2. Todo valor variável vai por parâmetro ($params), não concatenado no SQL.
 */
class Connection
{
    /** @var resource|false */
    public $id;

    /** @var resource|false */
    public $res;

    /** @var array|false */
    public $linha;

    /** Mensagem técnica da última escrita que falhou (manipula). @var string */
    public $erro = '';

    /**
     * Conexões reaproveitadas dentro da mesma requisição, por ambiente/banco.
     *
     * Antes era uma por consulta: um único bipe chegava a abrir dezenas de
     * conexões no SQL Server.
     *
     * @var array<string, resource>
     */
    private static $conexoes = [];

    public function __construct($db = 'RM')
    {
        $this->abre($db);
    }

    public function abre($db)
    {
        $current = EnvironmentManager::getCurrent();

        if ($current === null) {
            throw new DatabaseException('Nenhum ambiente selecionado. Volte à tela inicial e conecte-se.');
        }

        $chave = (string) EnvironmentManager::getCurrentKey()
            . '|' . (string) ($current['host'] ?? '')
            . '|' . (string) ($current['database'] ?? '');

        if (isset(self::$conexoes[$chave])) {
            $this->id = self::$conexoes[$chave];
            return;
        }

        // Sem a extensão, o que aparecia era "Call to undefined function" — um
        // erro de PHP para um problema de instalação do servidor.
        if (!function_exists('sqlsrv_connect')) {
            throw new DatabaseException(
                'A extensão PHP sqlsrv não está instalada ou habilitada neste servidor.',
                'sqlsrv_connect indisponivel (php.ini)'
            );
        }

        $this->id = @sqlsrv_connect($current['host'], EnvironmentManager::buildConnectionInfo($current));

        if ($this->id === false) {
            $detalhe = DatabaseException::formatarErros(sqlsrv_errors());
            throw new DatabaseException(
                'Não foi possível conectar ao banco do ambiente ' . (string) ($current['label'] ?? '') . '.',
                'conectar em ' . (string) ($current['host'] ?? '') . ' — ' . $detalhe
            );
        }

        self::$conexoes[$chave] = $this->id;
    }

    /**
     * Executa um SELECT. Os valores vão em $params, nunca dentro de $sql.
     *
     * @param string $sql
     * @param array<int, mixed> $params
     */
    public function Consulta($sql = '', array $params = [])
    {
        // Libera o statement anterior deste objeto e zera a linha: com a conexão
        // compartilhada, uma consulta que falhe não pode devolver a linha da anterior.
        if ($this->res) {
            @sqlsrv_free_stmt($this->res);
        }

        $this->res = false;
        $this->linha = false;
        $this->erro = '';

        if ($sql === '' || $sql === null) {
            return;
        }

        $this->res = sqlsrv_query($this->id, $sql, $params, self::opcoesStatement());

        if ($this->res === false) {
            throw self::excecaoDaConsulta($sql);
        }
    }

    /**
     * Executa INSERT / UPDATE / DELETE.
     *
     * Devolve bool porque quem chama já tem uma mensagem própria para a tela,
     * mas agora guarda o motivo em $this->erro e o registra no log — antes a
     * causa era apagada com um `$this->erro = ''`.
     *
     * @param string $sql
     * @param array<int, mixed> $params
     */
    public function manipula($sql = '', array $params = [])
    {
        $this->erro = '';

        $stmt = sqlsrv_query($this->id, $sql, $params, self::opcoesStatement());

        if ($stmt !== false) {
            @sqlsrv_free_stmt($stmt);
            return true;
        }

        $this->erro = DatabaseException::formatarErros(sqlsrv_errors());
        log_erro('Connection::manipula', $this->erro . ' -- SQL: ' . preg_replace('/\s+/', ' ', $sql));

        return false;
    }

    /**
     * Quantas linhas a última escrita afetou. Útil para distinguir "apagou"
     * de "não havia nada para apagar".
     *
     * @param array<int, mixed> $params
     */
    public function manipulaContando(string $sql, array $params = []): int
    {
        $this->erro = '';

        $stmt = sqlsrv_query($this->id, $sql, $params, self::opcoesStatement());

        if ($stmt === false) {
            $this->erro = DatabaseException::formatarErros(sqlsrv_errors());
            log_erro('Connection::manipulaContando', $this->erro . ' -- SQL: ' . preg_replace('/\s+/', ' ', $sql));
            return -1;
        }

        $linhas = sqlsrv_rows_affected($stmt);
        @sqlsrv_free_stmt($stmt);

        return is_int($linhas) && $linhas >= 0 ? $linhas : 0;
    }

    /**
     * Transação sobre a conexão desta requisição.
     *
     * A conexão é compartilhada por requisição, então tudo que rodar entre
     * iniciar e confirmar entra na mesma transação — inclusive um `new
     * Connection('RM')` criado no meio. É de propósito, mas exige que o trecho
     * transacionado não chame nada que grave por fora do assunto.
     */
    public function iniciarTransacao(): void
    {
        if (!sqlsrv_begin_transaction($this->id)) {
            throw new DatabaseException(
                'Não foi possível iniciar a gravação no banco.',
                'begin_transaction: ' . DatabaseException::formatarErros(sqlsrv_errors())
            );
        }
    }

    public function confirmarTransacao(): void
    {
        if (!sqlsrv_commit($this->id)) {
            throw new DatabaseException(
                'Não foi possível concluir a gravação no banco.',
                'commit: ' . DatabaseException::formatarErros(sqlsrv_errors())
            );
        }
    }

    /** Desfaz sem levantar: já estamos tratando um erro quando isto é chamado. */
    public function desfazerTransacao(): void
    {
        if (!@sqlsrv_rollback($this->id)) {
            log_erro('Connection::desfazerTransacao', DatabaseException::formatarErros(sqlsrv_errors()));
        }
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

    /**
     * Opções de statement: teto de tempo por consulta.
     *
     * Sem isso, uma consulta de posição num local grande fica presa até o
     * PHP-FPM derrubar a requisição inteira — e aí nem a mensagem de erro
     * chega na tela. Com o teto, o SQL Server devolve o erro de timeout e o
     * app consegue dizer ao operador para estreitar o filtro.
     *
     * @return array<string, mixed>
     */
    private static function opcoesStatement(): array
    {
        return ['QueryTimeout' => EnvironmentManager::queryTimeout()];
    }

    private static function excecaoDaConsulta(string $sql): DatabaseException
    {
        $errors = sqlsrv_errors();
        $detalhe = DatabaseException::formatarErros($errors)
            . ' -- SQL: ' . preg_replace('/\s+/', ' ', $sql);

        if (DatabaseException::ehTimeout($errors)) {
            return new DatabaseException(
                'A consulta passou de ' . EnvironmentManager::queryTimeout() . ' segundos e foi interrompida. '
                . 'Estreite o filtro (grupo contábil, busca ou um local menor) e tente de novo.',
                $detalhe
            );
        }

        return new DatabaseException(
            'Falha ao consultar o banco do RM. Tente novamente; se persistir, avise a TI.',
            $detalhe
        );
    }
}
