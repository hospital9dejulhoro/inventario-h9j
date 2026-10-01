<?php

/**
 * Confere que TODA consulta tem um valor para cada marcador '?'.
 *
 * Rode com:  php tests/sql-parametros.php
 * Devolve 0 quando passa e 1 quando falha.
 *
 * O teste de auditoria já faz isso para as escritas, e foi assim que apareceu
 * um UPDATE com cinco marcadores e quatro parâmetros. As leituras ficavam de
 * fora, e são a maioria das consultas: cada combinação de filtro monta um SQL
 * diferente, com um número diferente de marcadores. Um descompasso só aparece
 * quando aquela combinação é usada — ou seja, em produção, no meio de uma
 * contagem.
 *
 * Não toca no banco: a Connection é substituída por uma que só anota o SQL e
 * os parâmetros que teriam sido enviados.
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
    /** @var array<int, array{sql: string, params: array}> */
    public static $ops = [];

    public $linha = false;
    public $erro = '';
    public $res = false;
    private $fila = [];

    public function __construct($db = 'RM') {}

    public function Consulta($sql = '', array $params = [])
    {
        self::$ops[] = ['sql' => $sql, 'params' => $params];
        // Uma linha plausível basta: o objetivo é percorrer o caminho que
        // monta o SQL, não conferir o que volta.
        $this->fila = [[
            'ID' => 1, 'IDPRD' => 231, 'IDLOTE' => 51, 'ULTIMO' => 0, 'TOTAL' => 0,
            'CODIGOBARRAS' => '0002310000510', 'CODINVENTARIO' => '26.028.001',
            'QUANTIDADE' => '3', 'CODLOC' => '028', 'NUMLOTE' => 'L1',
            'NOME' => 'ITEM', 'UND' => 'CX', 'CODIGO' => 'P1', 'SALDO' => 5.0,
            'CUSTOMEDIO' => 2.0, 'BIPAGENS' => 1, 'STATUS' => 'A',
            'GRUPOCOD' => '01', 'GRUPONOME' => 'MED', 'LOCALNOME' => 'FARMACIA',
            'OK' => 1, 'ITENS' => 3, 'DATAVALIDADE' => null,
            'DATABASEINVENTARIO' => null, 'DATASTATUS' => null,
        ]];
    }

    public function Resultado()
    {
        $this->linha = array_shift($this->fila);
        return $this->linha !== null;
    }

    public function manipula($sql = '', array $params = [])
    {
        self::$ops[] = ['sql' => $sql, 'params' => $params];
        return true;
    }

    public function manipulaContando($sql, array $params = []): int
    {
        self::$ops[] = ['sql' => $sql, 'params' => $params];
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
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'InventarioRM.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'ZMDCODBARRAS.php';

/* --------- verificações --------- */

$falhas = 0;
$consultas = 0;

/**
 * Conta marcadores fora de literais de texto: um '?' dentro de aspas é
 * conteúdo, não parâmetro.
 */
function marcadores(string $sql): int
{
    return substr_count(preg_replace("/'[^']*'/", "''", $sql), '?');
}

function exercitar(string $rotulo, callable $fn): void
{
    global $falhas, $consultas;
    Connection::$ops = [];

    try {
        $fn();
    } catch (Throwable $e) {
        echo "  ERRO  {$rotulo}: " . $e->getMessage() . "\n";
        $falhas++;
        return;
    }

    if (Connection::$ops === []) {
        printf("  --    %-44s nenhuma consulta neste caminho\n", $rotulo);
        return;
    }

    $problemas = 0;
    foreach (Connection::$ops as $i => $op) {
        $consultas++;
        $m = marcadores($op['sql']);
        $p = count($op['params']);
        if ($m !== $p) {
            $falhas++;
            $problemas++;
            printf(
                "  FALHA %-44s consulta %d: %d marcador(es) para %d parâmetro(s)\n        %s\n",
                $rotulo, $i + 1, $m, $p, substr(preg_replace('/\s+/', ' ', $op['sql']), 0, 150)
            );
        }
    }

    if ($problemas === 0) {
        printf("  ok    %-44s %d consulta(s)\n", $rotulo, count(Connection::$ops));
    }
}

echo "InventarioRM\n";
exercitar('existeNoRm', fn() => InventarioRM::existeNoRm('26.028.001'));
exercitar('localPertenceAoInventario', fn() => InventarioRM::localPertenceAoInventario('26.028.001', '028'));
exercitar('contarItensInventario com local', fn() => InventarioRM::contarItensInventario('26.028.001', '028'));
exercitar('contarItensInventario sem local', fn() => InventarioRM::contarItensInventario('26.028.001', ''));
exercitar('idprdsDoInventario', fn() => InventarioRM::idprdsDoInventario('26.028.001', '028'));
exercitar('listarAbertos', fn() => InventarioRM::listarAbertos());
exercitar('custosPorProduto', fn() => InventarioRM::custosPorProduto([231, 232, 233], '028'));
exercitar('custosPorProduto lista vazia', fn() => InventarioRM::custosPorProduto([], '028'));
exercitar('listarItensSemLote do RM', fn() => InventarioRM::listarItensSemLote('26.028.001', '028', false));
exercitar('listarItensSemLote avulsa', fn() => InventarioRM::listarItensSemLote('26.028.900', '028', true, true));

foreach ([false, true] as $avulso) {
    $rotulo = $avulso ? 'avulsa' : 'do RM';
    exercitar("itemContavel com lote {$rotulo}", fn() => InventarioRM::itemContavel('26.028.001', '028', 231, 51, $avulso));
    exercitar("itemContavel sem lote {$rotulo}", fn() => InventarioRM::itemContavel('26.028.001', '028', 231, 0, $avulso));
}

echo "\nPosição — cada filtro muda o SQL\n";
$combinacoes = [
    ['sem filtro',       '',          '',   true],
    ['busca em texto',   'dipirona',  '',   true],
    ['grupo contábil',   '',          '01', true],
    ['busca e grupo',    'gaze',      '03', true],
    ['incluindo zerados', '',         '',   false],
    ['tudo junto',       'luva',      '02', false],
];
foreach ($combinacoes as [$rotulo, $q, $g, $saldo]) {
    exercitar("listarPosicaoPorLote — {$rotulo}", fn() => InventarioRM::listarPosicaoPorLote('028', $q, $g, $saldo));
    exercitar("listarItensSemLoteDoLocal — {$rotulo}", fn() => InventarioRM::listarItensSemLoteDoLocal('028', $saldo, $q, $g));
}

echo "\nBusca por etiqueta de 13 dígitos\n";
$comLote = ZMDCODBARRAS::barcodeComLote(231, 51);
$semLote = ZMDCODBARRAS::barcodeSemLote(231);
exercitar('posição por lote — etiqueta com lote', fn() => InventarioRM::listarPosicaoPorLote('028', $comLote, '', true));
exercitar('posição por lote — etiqueta sem lote', fn() => InventarioRM::listarPosicaoPorLote('028', $semLote, '', true));
exercitar('sem lote — etiqueta com lote', fn() => InventarioRM::listarItensSemLoteDoLocal('028', true, $comLote, ''));
exercitar('sem lote — etiqueta sem lote', fn() => InventarioRM::listarItensSemLoteDoLocal('028', true, $semLote, ''));

echo "\nGrupos contábeis\n";
exercitar('gruposContabeisDoLocal só com saldo', fn() => InventarioRM::gruposContabeisDoLocal('028', true));
exercitar('gruposContabeisDoLocal todos', fn() => InventarioRM::gruposContabeisDoLocal('028', false));

echo "\nZMDCODBARRAS\n";
exercitar('listarPorInventario', fn() => ZMDCODBARRAS::listarPorInventario('26.028.001'));
exercitar('totaisPorProdutoLote', fn() => ZMDCODBARRAS::totaisPorProdutoLote('26.028.001'));
exercitar('totaisPorProduto', fn() => ZMDCODBARRAS::totaisPorProduto('26.028.001'));
exercitar('contagemPorProdutoLote', fn() => ZMDCODBARRAS::contagemPorProdutoLote('26.028.001'));
exercitar('relatorioContagem', fn() => ZMDCODBARRAS::relatorioContagem('26.028.001'));
exercitar('contarPorInventario', fn() => ZMDCODBARRAS::contarPorInventario('26.028.001'));
exercitar('contarPorInventarios três', fn() => ZMDCODBARRAS::contarPorInventarios(['26.028.001', '26.028.900', '26.065.002']));
exercitar('contarPorInventarios um', fn() => ZMDCODBARRAS::contarPorInventarios(['26.028.001']));
exercitar('contarPorInventarios vazio', fn() => ZMDCODBARRAS::contarPorInventarios([]));
exercitar('resumoDoCodigo', fn() => ZMDCODBARRAS::resumoDoCodigo('26.028.001', $comLote));
exercitar('validarCodigoBarras', fn() => ZMDCODBARRAS::validarCodigoBarras($comLote));
exercitar('proximoCodigoAvulso', fn() => ZMDCODBARRAS::proximoCodigoAvulso('028'));
exercitar('listarAvulsos', fn() => ZMDCODBARRAS::listarAvulsos());
exercitar('listarInventariosComContagem', fn() => ZMDCODBARRAS::listarInventariosComContagem());
exercitar('excluirPorInventario', fn() => ZMDCODBARRAS::excluirPorInventario('26.028.900'));
exercitar('excluirPorId', fn() => ZMDCODBARRAS::excluirPorId(10));
exercitar('renomearInventario', fn() => ZMDCODBARRAS::renomearInventario('26.028.900', '26.028.001'));
exercitar('corrigirTotalProdutoLote', fn() => ZMDCODBARRAS::corrigirTotalProdutoLote('26.028.001', 231, 51, 7.0, '028'));
exercitar('corrigirTotalProdutoLote zerando', fn() => ZMDCODBARRAS::corrigirTotalProdutoLote('26.028.001', 231, 51, 0.0, '028'));
exercitar('corrigirTotalProdutoLote sem lote', fn() => ZMDCODBARRAS::corrigirTotalProdutoLote('26.028.001', 231, 0, 4.0, '028'));

exercitar('save', function () {
    $z = new ZMDCODBARRAS();
    $z->setCodigobarras(ZMDCODBARRAS::barcodeComLote(231, 51));
    $z->setCodinventario('26.028.001');
    $z->setQuantidade('2');
    $z->setCodloc('028');
    $z->save();
});

exercitar('atualizar', function () {
    $z = new ZMDCODBARRAS();
    $z->setId(10);
    $z->setCodigobarras(ZMDCODBARRAS::barcodeComLote(231, 51));
    $z->setCodinventario('26.028.001');
    $z->setQuantidade('3');
    $z->setCodloc('028');
    $z->atualizar();
});

echo "\n";
if ($falhas === 0) {
    echo "TODAS AS CONSULTAS CONFEREM ({$consultas} verificadas)\n";
    exit(0);
}

echo "{$falhas} CONSULTA(S) COM MARCADORES E PARÂMETROS EM NÚMEROS DIFERENTES\n";
exit(1);
