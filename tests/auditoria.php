<?php

/**
 * Confere que toda escrita em ZMDCODBARRAS preenche as colunas de auditoria
 * do RM e que marcadores e parâmetros batem.
 *
 * Rode com:  php tests/auditoria.php
 * Devolve 0 quando passa e 1 quando falha, para poder entrar num gancho de
 * deploy depois.
 *
 * Não toca no banco: a Connection é substituída por uma que só anota o SQL e
 * os parâmetros que teriam sido enviados. Foi assim que este teste encontrou
 * um UPDATE com cinco marcadores e quatro parâmetros — um erro que teria
 * quebrado toda edição de lançamento em produção.
 */

$_SESSION = ['rm_username' => 'TESTE'];

/* --------- dublês das dependências de infraestrutura --------- */

class DatabaseException extends Exception
{
    public function __construct($mensagem = '', $detalhe = '')
    {
        parent::__construct($mensagem);
    }
    public function detalhe(): string { return ''; }
    public static function formatarErros($erros): string { return ''; }
    public static function ehTimeout($erros): bool { return false; }
}

class Connection
{
    /** @var array<int, array{tipo: string, sql: string, params: array}> */
    public static $ops = [];

    public $linha = false;
    public $erro = '';
    public $res = false;
    private $fila = [];

    public function __construct($db = 'RM') {}

    public function Consulta($sql = '', array $params = [])
    {
        self::$ops[] = ['tipo' => 'SELECT', 'sql' => $sql, 'params' => $params];
        // Uma linha plausível serve para todas as leituras deste teste.
        $this->fila = [[
            'CODIGOBARRAS' => '0102310000510', 'CODINVENTARIO' => '26.028.001',
            'QUANTIDADE' => '3', 'CODLOC' => '028', 'TOTAL' => 7,
        ]];
    }

    public function Resultado()
    {
        $this->linha = array_shift($this->fila);
        return $this->linha !== null;
    }

    public function manipula($sql = '', array $params = [])
    {
        self::$ops[] = ['tipo' => 'ESCRITA', 'sql' => $sql, 'params' => $params];
        return true;
    }

    public function manipulaContando($sql, array $params = []): int
    {
        self::$ops[] = ['tipo' => 'ESCRITA', 'sql' => $sql, 'params' => $params];
        return 7;
    }

    public function iniciarTransacao(): void {}
    public function confirmarTransacao(): void {}
    public function desfazerTransacao(): void {}
}

class EnvironmentManager
{
    public static function getCurrentKey() { return 'testes'; }
    public static function queryTimeout() { return 30; }
}

class SessionManager
{
    public static function getUsername() { return 'TESTE'; }
}

define('APP_ROOT', dirname(__DIR__));
define('DS', DIRECTORY_SEPARATOR);
require APP_ROOT . DS . 'src' . DS . 'Helpers' . DS . 'functions.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'LocaisEstoque.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'ZMDCODBARRAS.php';

/* --------- verificações --------- */

$falhas = 0;

function escritasDe(callable $fn): array
{
    Connection::$ops = [];
    try {
        $fn();
    } catch (Throwable $e) {
        // Sem banco, alguns caminhos abortam; o que importa é o SQL montado.
    }

    return array_values(array_filter(Connection::$ops, function ($op) {
        return $op['tipo'] === 'ESCRITA';
    }));
}

function confere(string $rotulo, callable $fn, array $colunasEsperadas): void
{
    global $falhas;
    $escritas = escritasDe($fn);

    echo "  {$rotulo}\n";

    if ($escritas === []) {
        $falhas++;
        echo "    nenhuma escrita gerada                                   <-- FALHOU\n\n";
        return;
    }

    foreach ($escritas as $op) {
        $sql = preg_replace('/\s+/', ' ', trim($op['sql']));
        $marcadores = substr_count($sql, '?');
        $params = count($op['params']);
        $casa = $marcadores === $params;
        if (!$casa) {
            $falhas++;
        }
        printf("    %-58s ?=%d par=%d %s\n", mb_substr($sql, 0, 58), $marcadores, $params,
            $casa ? 'ok' : '<-- MARCADORES E PARAMETROS NAO BATEM');
    }

    foreach ($colunasEsperadas as $coluna) {
        $achou = false;
        foreach ($escritas as $op) {
            if (strpos($op['sql'], $coluna) !== false) {
                $achou = true;
            }
        }
        if (!$achou) {
            $falhas++;
        }
        printf("    grava %-52s %s\n", $coluna, $achou ? 'ok' : '<-- FALTOU');
    }

    echo "\n";
}

function novoLancamento(): ZMDCODBARRAS
{
    $z = new ZMDCODBARRAS();
    $z->setCodigobarras('0102310000510');
    $z->setCodinventario('26.028.001');
    $z->setQuantidade('3');
    $z->setCodloc('028');

    return $z;
}

echo "Colunas de auditoria em cada escrita de ZMDCODBARRAS\n\n";

confere('save()', function () {
    novoLancamento()->save();
}, ['RECCREATEDBY', 'RECCREATEDON', 'GETDATE()']);

confere('corrigirTotalProdutoLote()', function () {
    ZMDCODBARRAS::corrigirTotalProdutoLote('26.028.001', 10231, 51, 5.0, '028');
}, ['RECCREATEDBY', 'RECCREATEDON']);

confere('atualizar()', function () {
    $z = novoLancamento();
    $z->setId(42);
    $z->atualizar();
}, ['RECMODIFIEDBY', 'RECMODIFIEDON']);

confere('renomearInventario()', function () {
    ZMDCODBARRAS::renomearInventario('26.028.901', '26.028.001');
}, ['RECMODIFIEDBY', 'RECMODIFIEDON']);

confere('excluirPorId()', function () {
    ZMDCODBARRAS::excluirPorId(42);
}, ['DELETE FROM ZMDCODBARRAS']);

confere('excluirPorInventario()', function () {
    ZMDCODBARRAS::excluirPorInventario('26.028.001');
}, ['DELETE FROM ZMDCODBARRAS']);

echo "O usuário da sessão chega nos parâmetros\n";
$escritas = escritasDe(function () { novoLancamento()->save(); });
$temUsuario = in_array('TESTE', $escritas[0]['params'], true);
if (!$temUsuario) {
    $falhas++;
}
printf("  %-60s %s\n\n", json_encode($escritas[0]['params']), $temUsuario ? 'ok' : '<-- usuario ausente');

echo "A listagem traz autor e momento para a tela\n";
Connection::$ops = [];
ZMDCODBARRAS::listarPorInventario('26.028.001');
$select = Connection::$ops[0]['sql'];
foreach (['RECCREATEDBY', 'RECCREATEDON'] as $coluna) {
    $achou = strpos($select, $coluna) !== false;
    if (!$achou) {
        $falhas++;
    }
    printf("  SELECT traz %-48s %s\n", $coluna, $achou ? 'ok' : '<-- FALTOU');
}

echo "\n" . ($falhas === 0
    ? "TODAS AS ESCRITAS CONFEREM\n"
    : "{$falhas} PROBLEMA(S)\n");

exit($falhas === 0 ? 0 : 1);
