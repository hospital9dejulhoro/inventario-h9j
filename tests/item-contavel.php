<?php

/**
 * O que pode ser contado, e quando o lote é obrigatório.
 *
 * Rode com:  php tests/item-contavel.php
 *
 * Duas regras moram aqui, e as duas mudaram de critério.
 *
 * 1) O que vale ser contado. Antes, só o que o RM tivesse gerado em
 *    TITMINVENTARIO. Agora vale também o que estiver cadastrado no estoque do
 *    local. É união, não troca, e a diferença é grande dos dois lados: no
 *    inventário 26.028.001 o RM gerou 17 produtos enquanto o local tem 647 no
 *    estoque; no 26.027.001 há 9.552 produtos gerados que não têm linha de
 *    estoque nenhuma. Trocar um critério pelo outro tiraria da contagem um
 *    monte de item, em vez de liberar.
 *
 * 2) Quando o lote é obrigatório. Antes o sistema deduzia pela existência de
 *    lote no cadastro; agora lê TPRODUTO.CONTROLADOPORLOTE, que é a declaração.
 *    A diferença é real: 142 produtos ativos estão marcados como controlados e
 *    ainda não têm nenhum lote cadastrado. Pelo critério antigo eles caíam na
 *    folha "sem lote" e eram contados sem lote — no inventário 26.065.003 eram
 *    30 produtos, e no 26.027.001, 24.
 */

class DatabaseException extends Exception
{
    public function __construct($m = '', $d = '') { parent::__construct($m); }
    public function detalhe(): string { return ''; }
    public static function formatarErros($e): string { return ''; }
    public static function ehTimeout($e): bool { return false; }
}

/**
 * Responde a consulta de itemContavel() com o cenário que o teste montou.
 */
class Connection
{
    /** @var array<string, mixed>|null Linha que o banco devolveria, ou null se o produto não existe. */
    public static $produto = null;
    /** @var array<int, mixed> Parâmetros da última consulta, para conferir a montagem. */
    public static $ultimosParams = [];

    public $linha = false;
    private $fila = [];

    public function __construct($db = 'RM') {}

    public function Consulta($sql = '', array $params = [])
    {
        self::$ultimosParams = $params;
        $this->fila = self::$produto === null ? [] : [self::$produto];
    }

    public function Resultado() { $this->linha = array_shift($this->fila); return $this->linha !== null; }
    public function manipula($sql = '', array $p = []) { return true; }
    public function manipulaContando($sql, array $p = []): int { return 0; }
    public function iniciarTransacao(): void {}
    public function confirmarTransacao(): void {}
    public function desfazerTransacao(): void {}
}

class EnvironmentManager
{
    public static function getCurrentKey() { return 'testes'; }
    public static function queryTimeout() { return 30; }
}

class SessionManager { public static function getUsername() { return 'TESTE'; } }

define('APP_ROOT', dirname(__DIR__));
define('DS', DIRECTORY_SEPARATOR);
require APP_ROOT . DS . 'src' . DS . 'Helpers' . DS . 'functions.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'LocaisEstoque.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'ZMDCODBARRAS.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'InventarioRM.php';

$falhas = 0;

function confere(string $rotulo, $obtido, $esperado): void
{
    global $falhas;
    $ok = ($obtido === $esperado);
    if (!$ok) {
        $falhas++;
    }
    printf("  %-52s %-7s %s\n", $rotulo, var_export($obtido, true),
        $ok ? 'ok' : '<-- esperava ' . var_export($esperado, true));
}

/**
 * Monta o cenário: onde o produto está e se ele usa lote.
 */
function cenario(bool $controlado, bool $noInventario, bool $noEstoque, bool $loteExiste, bool $inativo = false): void
{
    Connection::$produto = [
        'INATIVO'       => $inativo ? 1 : 0,
        'CONTROLADO'    => $controlado ? 1 : 0,
        'NOME'          => 'DIPIRONA 500MG',
        'NO_INVENTARIO' => $noInventario ? 1 : 0,
        'NO_ESTOQUE'    => $noEstoque ? 1 : 0,
        'LOTE_EXISTE'   => $loteExiste ? 1 : 0,
    ];
}

function pode(int $idlote = 0, bool $avulso = false): bool
{
    $r = InventarioRM::itemContavel('26.065.003', '065', 10231, $idlote, $avulso);
    return $r['ok'];
}

function motivo(int $idlote = 0, bool $avulso = false): string
{
    $r = InventarioRM::itemContavel('26.065.003', '065', 10231, $idlote, $avulso);
    return $r['error'];
}

echo "Produto NÃO controlado por lote: não se pede lote\n";
cenario(false, true, true, false);
confere('no inventário e no estoque',          pode(0), true);
cenario(false, false, true, false);
confere('só no estoque do local: agora conta', pode(0), true);
cenario(false, true, false, false);
confere('só no inventário: continua contando', pode(0), true);
cenario(false, false, false, false);
confere('em nenhum dos dois: recusa',          pode(0), false);
confere('e diz que não está em lugar nenhum',
    strpos(motivo(0), 'não está no inventário nem no estoque') !== false, true);

echo "\nProduto CONTROLADO por lote: o lote é obrigatório e precisa existir\n";
cenario(true, true, true, true);
confere('com lote cadastrado',            pode(4036), true);
cenario(true, true, true, false);
confere('lote informado não existe',      pode(4036), false);
confere('  e o motivo diz isso',
    strpos(motivo(4036), 'não está cadastrado no RM') !== false, true);
cenario(true, true, true, true);
confere('sem informar lote: recusa',      pode(0), false);
confere('  e diz que é controlado por lote',
    strpos(motivo(0), 'controlado por lote') !== false, true);

echo "\nO estoque do local vale mesmo para produto com lote\n";
cenario(true, false, true, true);
confere('só no estoque, com lote válido', pode(4036), true);

echo "\nContagem avulsa: só o estoque do local conta\n";
cenario(false, false, true, false);
confere('produto do estoque',                   pode(0, true), true);
cenario(false, true, false, false);
confere('produto só do inventário: recusa',     pode(0, true), false);

echo "\nProduto inativo não entra, esteja onde estiver\n";
cenario(false, true, true, false, true);
confere('inativo recusa',        pode(0), false);
confere('  e diz que está inativo', strpos(motivo(0), 'inativo') !== false, true);

echo "\nProduto que não existe no cadastro\n";
Connection::$produto = null;
confere('recusa',                   pode(0), false);
confere('  e diz que não existe',   strpos(motivo(0), 'não existe no cadastro') !== false, true);

echo "\nEntradas malformadas\n";
$r = InventarioRM::itemContavel('26.065.003', '065', 0, 0);
confere('produto zero',   $r['ok'], false);
confere('  motivo',       $r['error'], 'Produto inválido.');
$r = InventarioRM::itemContavel('26.065.003', '', 10231, 0);
confere('local vazio',    $r['ok'], false);

echo "\nA consulta recebe os parâmetros na ordem certa\n";
cenario(true, true, true, true);
InventarioRM::itemContavel('26.065.003', '065', 10231, 4036);
// coligada, inventario, loc, loc, loc, loc, coligada, idlote, idprd
confere('nove parâmetros', count(Connection::$ultimosParams), 9);
confere('inventário no 2º',  Connection::$ultimosParams[1], '26.065.003');
confere('lote no 8º',        Connection::$ultimosParams[7], 4036);
confere('produto no 9º',     Connection::$ultimosParams[8], 10231);

echo "\n" . ($falhas === 0 ? "TODAS AS REGRAS CONFEREM\n" : "{$falhas} PROBLEMA(S)\n");
exit($falhas === 0 ? 0 : 1);
