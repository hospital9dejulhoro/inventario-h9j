<?php

/**
 * O código de barras de 13 dígitos: IDPRD nos 1-6, IDLOTE nos 8-12.
 *
 * Rode com:  php tests/barcode.php
 *
 * Este layout é contrato com os leitores já em uso no hospital, e três coisas
 * dependem dele estar exato:
 *
 *  - o aviso de bipagem repetida, que agrupa por produto + lote;
 *  - a correção de total, que APAGA o que já foi contado daquele produto e
 *    lote antes de gravar o novo — errar o lote aqui apaga o lote errado;
 *  - a leitura do que veio da etiqueta impressa pelo RM.
 *
 * Um bug já corrigido mora aqui: barcodeSemLote() escrevia o IDPRD em sete
 * dígitos, enquanto todos os leitores usam seis. Os códigos gerados não batiam
 * com os lidos, e o mesmo produto contava como dois.
 */

class DatabaseException extends Exception
{
    public function __construct($m = '', $d = '') { parent::__construct($m); }
    public function detalhe(): string { return ''; }
    public static function formatarErros($e): string { return ''; }
    public static function ehTimeout($e): bool { return false; }
}

class Connection
{
    public $linha = false;
    public function __construct($db = 'RM') {}
    public function Consulta($sql = '', array $p = []) {}
    public function Resultado() { return false; }
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

class SessionManager
{
    public static function getUsername() { return 'TESTE'; }
}

define('APP_ROOT', dirname(__DIR__));
define('DS', DIRECTORY_SEPARATOR);
require APP_ROOT . DS . 'src' . DS . 'Helpers' . DS . 'functions.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'LocaisEstoque.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'ZMDCODBARRAS.php';

$falhas = 0;

function confere(string $rotulo, $obtido, $esperado): void
{
    global $falhas;
    $ok = ($obtido === $esperado);
    if (!$ok) {
        $falhas++;
    }
    printf("  %-50s %-17s %s\n", $rotulo, var_export($obtido, true),
        $ok ? 'ok' : '<-- esperava ' . var_export($esperado, true));
}

echo "Posição de cada campo nos 13 dígitos\n";
$bc = ZMDCODBARRAS::barcodeComLote(10231, 51);
confere('produto 10231 lote 51', $bc, '0102310000510');
confere('  tem 13 dígitos', strlen($bc), 13);
confere('  IDPRD são os 6 primeiros', substr($bc, 0, 6), '010231');
confere('  IDLOTE são os dígitos 8 a 12', substr($bc, 7, 5), '00051');

echo "\nSem lote é o mesmo formato com lote zero\n";
confere('barcodeSemLote(10231)', ZMDCODBARRAS::barcodeSemLote(10231), '0102310000000');
confere('  é igual a barcodeComLote(10231, 0)',
    ZMDCODBARRAS::barcodeSemLote(10231), ZMDCODBARRAS::barcodeComLote(10231, 0));
// O bug antigo: sete digitos para o produto. Guardado para nao voltar.
confere('  IDPRD em 6 dígitos, não 7', substr(ZMDCODBARRAS::barcodeSemLote(10231), 0, 7), '0102310');

echo "\nIda e volta: gerar e ler devolvem o mesmo par\n";
$casos = [
    [1, 0], [24, 0], [10231, 51], [999999, 99999], [857, 1], [123456, 12345],
];
foreach ($casos as [$idprd, $idlote]) {
    $codigo = ZMDCODBARRAS::barcodeComLote($idprd, $idlote);
    $volta = [ZMDCODBARRAS::idprdDoBarcode($codigo), ZMDCODBARRAS::idloteDoBarcode($codigo)];
    confere("produto {$idprd} lote {$idlote} → {$codigo}", $volta, [$idprd, $idlote]);
}

echo "\nO que não cabe não vira código errado\n";
confere('IDPRD acima de 999999 recusa', ZMDCODBARRAS::barcodeComLote(1000000, 0), '');
confere('IDLOTE acima de 99999 recusa', ZMDCODBARRAS::barcodeComLote(10231, 100000), '');
confere('IDPRD zero recusa',            ZMDCODBARRAS::barcodeComLote(0, 0), '');

echo "\nLeitura de código incompleto não inventa produto nem lote\n";
confere('vazio',          [ZMDCODBARRAS::idprdDoBarcode(''), ZMDCODBARRAS::idloteDoBarcode('')], [0, 0]);
confere('5 dígitos',      ZMDCODBARRAS::idprdDoBarcode('01023'), 0);
confere('6 dígitos: produto sim, lote não',
    [ZMDCODBARRAS::idprdDoBarcode('010231'), ZMDCODBARRAS::idloteDoBarcode('010231')], [10231, 0]);

echo "\nLeitura aceita o código com separadores, como alguns leitores mandam\n";
confere('com hífens', ZMDCODBARRAS::idprdDoBarcode('010231-0-00051-0'), 10231);
confere('com espaços', ZMDCODBARRAS::idloteDoBarcode('010231 0 00051 0'), 51);

echo "\n" . ($falhas === 0 ? "TODAS AS REGRAS CONFEREM\n" : "{$falhas} PROBLEMA(S)\n");
exit($falhas === 0 ? 0 : 1);
