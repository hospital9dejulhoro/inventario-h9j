<?php

/**
 * Conferência: o que casa, o que falta e o que sobra — e quanto vale.
 *
 * Rode com:  php tests/conferencia.php
 *
 * reconciliar() é pura de propósito: recebe a posição do local e a contagem, e
 * devolve o cruzamento. É aqui que mora a regra, e é aqui que ela precisa de
 * teste.
 *
 * O caso que este teste guarda custava caro no relatório: item em SOBRA — o que
 * foi contado e não tinha linha na posição — entrava valendo zero, porque o
 * custo vem junto com a linha da posição e sobra não tem linha nenhuma. Num
 * inventário real do hospital isso escondia 55.720 unidades: o relatório
 * apontava R$ 268,60 de divergência quando o valor era R$ 13.710.662,12.
 */

class LocaisEstoque
{
    public static function normalizar(string $c): string { return trim($c); }
    public static function nome(string $c): string { return ''; }
}

class DatabaseException extends Exception {}
class Connection
{
    public $linha = false;
    public function __construct($db = 'RM') {}
    public function Consulta($sql = '', array $p = []) {}
    public function Resultado() { return false; }
}
class EnvironmentManager
{
    public static function getCurrentKey() { return 'testes'; }
    public static function queryTimeout() { return 30; }
}
class SessionManager { public static function getUsername() { return 'TESTE'; } }
class ZMDCODBARRAS { public static function parseCodigoInventario($c) { return ['valid' => false]; } }

define('APP_ROOT', dirname(__DIR__));
define('DS', DIRECTORY_SEPARATOR);
require APP_ROOT . DS . 'src' . DS . 'Helpers' . DS . 'functions.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'InventarioRM.php';

$falhas = 0;

function confere(string $rotulo, $obtido, $esperado): void
{
    global $falhas;
    $ok = ($obtido === $esperado);
    if (!$ok) {
        $falhas++;
    }
    printf("  %-50s %-12s %s\n", $rotulo, var_export($obtido, true),
        $ok ? 'ok' : '<-- esperava ' . var_export($esperado, true));
}

function linhaPosicao(int $idprd, int $idlote, float $saldo, float $custo): array
{
    return [
        'idprd' => $idprd, 'idlote' => $idlote, 'numlote' => 'L' . $idlote,
        'codigo' => '00' . $idprd, 'nome' => 'PRODUTO ' . $idprd, 'und' => 'UN',
        'validade' => '', 'grupo_nome' => 'GRUPO', 'grupo_cod' => '1',
        'saldo' => $saldo, 'custo_medio' => $custo,
    ];
}

function linhaContagem(int $idprd, int $idlote, float $qtd): array
{
    return [
        'idprd' => $idprd, 'idlote' => $idlote, 'nome' => 'PRODUTO ' . $idprd,
        'und' => 'UN', 'numlote' => 'L' . $idlote, 'bipagens' => 1, 'quantidade' => $qtd,
    ];
}

// Posicao: dois itens, um com saldo 10 (custo 2,00) e outro com saldo 5 (custo 3,00).
$posicao = [linhaPosicao(100, 1, 10.0, 2.0), linhaPosicao(200, 2, 5.0, 3.0)];

echo "Item contado igual ao saldo: sem diferença\n";
$r = InventarioRM::reconciliar($posicao, ['100:1' => linhaContagem(100, 1, 10.0)]);
confere('contados',        $r['totais']['contados'], 1);
confere('não contados',    $r['totais']['nao_contados'], 1);
confere('sobras',          $r['totais']['sobras'], 0);
confere('diferença',       $r['totais']['diferenca'], 0.0);
confere('valor',           $r['totais']['valor_diferenca'], 0.0);

echo "\nItem contado a mais: a diferença vale o custo da posição\n";
$r = InventarioRM::reconciliar($posicao, ['100:1' => linhaContagem(100, 1, 13.0)]);
confere('diferença de 3 unidades', $r['totais']['diferenca'], 3.0);
confere('valor 3 x R$ 2,00',       $r['totais']['valor_diferenca'], 6.0);

echo "\nSOBRA: contado o que não estava na posição\n";
// Sem os custos, a sobra entrava valendo zero — o bug.
$soSobra = ['900:9' => linhaContagem(900, 9, 40.0)];
$r = InventarioRM::reconciliar($posicao, $soSobra);
confere('conta como sobra',            $r['totais']['sobras'], 1);
confere('quantidade entra na diferença', $r['totais']['diferenca'], 40.0);
confere('sem custo conhecido, vale 0',  $r['totais']['valor_diferenca'], 0.0);

// Com o custo, a mesma sobra passa a valer.
$r = InventarioRM::reconciliar($posicao, $soSobra, false, [900 => 1.5]);
confere('com custo, 40 x R$ 1,50',      $r['totais']['valor_diferenca'], 60.0);
$sobra = null;
foreach ($r['itens'] as $i) {
    if ($i['situacao'] === 'sobra') { $sobra = $i; }
}
confere('a linha da sobra também mostra o valor', $sobra['valor_diferenca'], 60.0);

echo "\nCusto de produto que não está em sobra não vaza para o total\n";
// 100 esta na posicao e usa o custo dela (2,00), nao o do mapa.
$r = InventarioRM::reconciliar($posicao, ['100:1' => linhaContagem(100, 1, 13.0)], false, [100 => 999.0]);
confere('usa o custo da posição, não o do mapa', $r['totais']['valor_diferenca'], 6.0);

echo "\nSobra e falta somadas\n";
$r = InventarioRM::reconciliar(
    $posicao,
    ['100:1' => linhaContagem(100, 1, 8.0), '900:9' => linhaContagem(900, 9, 40.0)],
    false,
    [900 => 1.5]
);
confere('contados',   $r['totais']['contados'], 1);
confere('sobras',     $r['totais']['sobras'], 1);
// 8 - 10 = -2 unidades a R$ 2,00 = -4,00 ; mais 40 a R$ 1,50 = 60,00
confere('valor total', $r['totais']['valor_diferenca'], 56.0);

echo "\nOrdem: pendências primeiro\n";
$r = InventarioRM::reconciliar($posicao, ['900:9' => linhaContagem(900, 9, 1.0)], false, [900 => 1.0]);
$situacoes = array_map(function ($i) { return $i['situacao']; }, $r['itens']);
confere('não contados antes da sobra', $situacoes[0], 'nao_contado');
confere('sobra antes do contado',      in_array('sobra', array_slice($situacoes, 0, 3), true), true);

echo "\n" . ($falhas === 0 ? "TODAS AS REGRAS CONFEREM\n" : "{$falhas} PROBLEMA(S)\n");
exit($falhas === 0 ? 0 : 1);
