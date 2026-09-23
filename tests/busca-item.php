<?php

/**
 * Por quais campos a busca de item procura.
 *
 * Rode com:  php tests/busca-item.php
 *
 * Quando a etiqueta não lê — rasgada, apagada, amassada — a pessoa precisa
 * achar o item de outro jeito: pelo nome, pelo código do produto, pelo lote ou
 * digitando o código de barras. Os quatro caminhos passam por aqui.
 *
 * O caso que deu trabalho: código de barras SEM lote. O código carrega IDLOTE
 * zero, e lote zero não existe em TLOTEPRDLOC — procurar por ele devolvia nada,
 * justamente para quem digitou o código de uma etiqueta ilegível. Agora cai
 * para o produto inteiro e a pessoa escolhe o lote.
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
    public static $sql = '';
    /** @var array<int, mixed> */
    public static $params = [];

    public $linha = false;

    public function __construct($db = 'RM') {}

    public function Consulta($sql = '', array $params = [])
    {
        self::$sql = preg_replace('/\s+/', ' ', (string) $sql);
        self::$params = $params;
    }

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
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'InventarioRM.php';

$falhas = 0;

function confere(string $rotulo, $obtido, $esperado): void
{
    global $falhas;
    $ok = ($obtido === $esperado);
    if (!$ok) {
        $falhas++;
    }
    printf("  %-52s %-11s %s\n", $rotulo, var_export($obtido, true),
        $ok ? 'ok' : '<-- esperava ' . var_export($esperado, true));
}

function busca(string $termo): void
{
    Connection::$sql = '';
    Connection::$params = [];
    InventarioRM::listarPosicaoPorLote('028', $termo, '', false, 30);
}

function sqlTem(string $trecho): bool
{
    return strpos(Connection::$sql, $trecho) !== false;
}

echo "Texto procura em lote, nome e código do produto\n";
busca('dipirona');
confere('casa o número do lote',       sqlTem('LOT.NUMLOTE LIKE'), true);
confere('casa o nome do produto',      sqlTem('PRD.NOMEFANTASIA LIKE'), true);
confere('casa o código do produto',    sqlTem('PRD.CODIGOPRD LIKE'), true);
confere('e o termo entra no LIKE',     sqlTem("'%dipirona%'"), true);
confere('sem filtrar por ID',          sqlTem('PRD.IDPRD = ?'), false);

echo "\nCódigo do produto é texto, não número mágico\n";
busca('007439');
confere("'007439' procura como texto", sqlTem("'%007439%'"), true);

echo "\nCódigo de barras de 13 dígitos vira produto + lote\n";
// 0008570403600 = produto 857, lote 40360.
busca('0008570403600');
confere('filtra pelo produto',  sqlTem('PRD.IDPRD = ?'), true);
confere('e pelo lote',          sqlTem('LOTLOC.IDLOTE = ?'), true);
// O local aparece duas vezes: condicaoCodloc() compara o CODLOC como esta e
// preenchido com zeros a esquerda, porque no RM ele aparece dos dois jeitos.
confere('local (2x), produto e lote', Connection::$params, ['028', '028', 857, 40360]);
confere('sem LIKE nenhum',      sqlTem('LIKE'), false);

echo "\nCódigo de barras SEM lote cai para o produto inteiro\n";
// 0008570000000 = produto 857, lote 0. Lote zero nao existe em TLOTEPRDLOC:
// filtrar por ele devolvia nada a quem digitou uma etiqueta ilegivel.
busca('0008570000000');
confere('filtra pelo produto',        sqlTem('PRD.IDPRD = ?'), true);
confere('e NÃO filtra por lote zero', sqlTem('LOTLOC.IDLOTE = ?'), false);
confere('local (2x) e produto, sem lote', Connection::$params, ['028', '028', 857]);

echo "\nCuringa digitado não vira curinga de busca\n";
// O LIKE e montado no SQL, entao % e _ digitados precisam sair escapados -
// senao '%' sozinho listaria o local inteiro.
busca('50%');
confere('% escapado',  sqlTem('[%]') || sqlTem('50[%]'), true);
busca('a_b');
confere('_ escapado',  sqlTem('[_]'), true);

echo "\nSem termo, sem filtro de busca\n";
busca('');
confere('nenhum LIKE',        sqlTem('LIKE'), false);
confere('nenhum filtro de ID', sqlTem('PRD.IDPRD = ?'), false);

echo "\nO teto de resultados é respeitado\n";
busca('dipirona');
confere('TOP 30 na consulta', sqlTem('SELECT TOP 30'), true);

echo "\n" . ($falhas === 0 ? "TODAS AS REGRAS CONFEREM\n" : "{$falhas} PROBLEMA(S)\n");
exit($falhas === 0 ? 0 : 1);
