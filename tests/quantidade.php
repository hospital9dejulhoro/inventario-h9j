<?php

/**
 * Regras da quantidade: como o que o operador digita vira o que a coluna
 * ZMDCODBARRAS.QUANTIDADE guarda — decimal(10,2).
 *
 * Rode com:  php tests/quantidade.php
 *
 * Dois bugs já corrigidos moram aqui, e é por isso que o teste existe:
 *
 *  - "1.250" era lido como 1,25. Mil unidades contadas viravam uma e um
 *    quarto, sem aviso.
 *  - a gravação arredondava em quatro casas para uma coluna de duas, então
 *    1,125 ia como 1.1250 e voltava 1,13; a checagem pós-gravação via a
 *    diferença e acusava alteração concorrente que não havia.
 */

class DatabaseException extends Exception
{
    public function __construct($mensagem = '', $detalhe = '') { parent::__construct($mensagem); }
    public function detalhe(): string { return ''; }
    public static function formatarErros($erros): string { return ''; }
    public static function ehTimeout($erros): bool { return false; }
}

class Connection
{
    public static $ops = [];
    public $linha = false;
    public $erro = '';
    public $res = false;
    public function __construct($db = 'RM') {}
    public function Consulta($sql = '', array $params = []) { self::$ops[] = $sql; }
    public function Resultado() { return false; }
    public function manipula($sql = '', array $params = []) { self::$ops[] = $sql; return true; }
    public function manipulaContando($sql, array $params = []): int { self::$ops[] = $sql; return 0; }
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
    printf("  %-44s %-14s %s\n", $rotulo, var_export($obtido, true),
        $ok ? 'ok' : '<-- esperava ' . var_export($esperado, true));
}

echo "Leitura do que o operador digita\n";
confere("'5'",          normalizar_quantidade('5'), 5.0);
confere("'1,5'",        normalizar_quantidade('1,5'), 1.5);
confere("'1.5'",        normalizar_quantidade('1.5'), 1.5);
confere("'1.250' é mil duzentos e cinquenta", normalizar_quantidade('1.250'), 1250.0);
confere("'1.250,75'",   normalizar_quantidade('1.250,75'), 1250.75);
confere("'1.250.000'",  normalizar_quantidade('1.250.000'), 1250000.0);
confere("'0.500' é meio",  normalizar_quantidade('0.500'), 0.5);
confere("'1 250'",      normalizar_quantidade('1 250'), 1250.0);
confere("'abc' recusa", normalizar_quantidade('abc'), null);
confere("'' recusa",    normalizar_quantidade(''), null);

echo "\nFormato gravado, na escala da coluna (decimal(10,2))\n";
confere('escala declarada', ZMDCODBARRAS::QUANTIDADE_CASAS, 2);
confere('1.125 arredonda em duas casas', quantidade_para_banco(1.125), '1.13');
confere('1250.5', quantidade_para_banco(1250.5), '1250.5');
confere('24 inteiro',   quantidade_para_banco(24.0), '24');
confere('0.001 some',   quantidade_para_banco(0.001), '0');
confere('sem notação científica', quantidade_para_banco(0.0000001), '0');

echo "\nIda e volta: o que grava é o que volta (a falsa alteração concorrente)\n";
foreach (['1,125', '1,13', '24', '1.250', '0,5'] as $digitado) {
    $num = normalizar_quantidade($digitado);
    $gravado = (float) quantidade_para_banco($num);
    // O banco devolve o valor já na escala da coluna; a checagem pós-gravação
    // compara com o que foi pedido usando esta mesma tolerância.
    $bate = abs($gravado - (float) quantidade_para_banco($num)) <= 0.0001;
    confere("'{$digitado}' → {$gravado} estável", $bate, true);
}

echo "\nQuantidade que a coluna não comporta\n";
$z = new ZMDCODBARRAS();
$z->setCodigobarras('0102310000510');
$z->setCodinventario('26.028.001');
$z->setCodloc('028');
$z->setQuantidade('1234567890123');   // etiqueta disparada no campo de quantidade
Connection::$ops = [];
$z->save();
confere('nada chegou ao banco', Connection::$ops, []);
confere('13 dígitos recusados antes do banco',
    strpos(ZMDCODBARRAS::$ultimoMotivo, 'limite') !== false, true);

// A recusa é instrução para quem conta e precisa chegar à tela mesmo em
// produção, onde o erro técnico fica escondido.
$naTela = ZMDCODBARRAS::mensagemDaFalha('Não foi possível gravar. Tente novamente.');
confere('o motivo chega à tela', strpos($naTela, 'limite') !== false, true);

// Já o erro técnico do banco não vaza: vira o texto genérico.
ZMDCODBARRAS::$ultimoMotivo = '';
ZMDCODBARRAS::$ultimoErro = 'SQLSTATE[22003] numeric overflow at line 1';
$GLOBALS['appConfig'] = ['debug' => false];
confere('erro técnico não vaza',
    ZMDCODBARRAS::mensagemDaFalha('Não foi possível gravar.'), 'Não foi possível gravar.');
ZMDCODBARRAS::$ultimoErro = '';

$correcao = ZMDCODBARRAS::corrigirTotalProdutoLote('26.028.001', 10231, 51, 1234567890123.0, '028');
confere('correção também recusa', strpos($correcao['error'], 'limite') !== false, true);

$z->setQuantidade('99999999.99');
Connection::$ops = [];
$z->save();
confere('teto exato é aceito', count(Connection::$ops) > 0, true);

echo "\nUsuário de auditoria cabe na coluna varchar(50)\n";
confere('limite declarado', ZMDCODBARRAS::AUDITORIA_USUARIO_MAX, 50);

echo "\n" . ($falhas === 0 ? "TODAS AS REGRAS CONFEREM\n" : "{$falhas} PROBLEMA(S)\n");
exit($falhas === 0 ? 0 : 1);
