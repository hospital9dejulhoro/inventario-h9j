<?php

/**
 * De onde ContextoInventario lê o inventário e o local.
 *
 * Rode com:  php tests/contexto-entrada.php
 *
 * A tela de leitura passou a gravar por POST: antes, a bipagem inteira vinha
 * pela URL, e um link inventario.php?CODIGOBARRAS=...&CODINVENTARIO=... fazia
 * qualquer pessoa logada que clicasse gravar contagem sem saber.
 *
 * O inventário e o local da bipagem chegam no mesmo envio, não na URL. Se
 * resolver() voltar a ler $_GET, a gravação por POST passa a usar o que estiver
 * no endereço — e a trava vira enfeite. É isso que este teste guarda.
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
    /** @var array<string, string> CODINVENTARIO => STATUS */
    public static $inventarios = [];
    public $linha = false;
    private $fila = [];

    public function __construct($db = 'RM') {}

    public function Consulta($sql = '', array $params = [])
    {
        $this->fila = [];
        if (strpos($sql, 'FROM TINVENTARIO') !== false) {
            $cod = (string) ($params[1] ?? '');
            if (isset(self::$inventarios[$cod])) {
                $this->fila = [['CODINVENTARIO' => $cod, 'STATUS' => self::$inventarios[$cod]]];
            }
            return;
        }
        if (strpos($sql, 'FROM TITMINVENTARIO') !== false) {
            $this->fila = [['OK' => 1]];
        }
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

class SessionManager
{
    public static $ultimo = null;
    public static function getUsername() { return 'TESTE'; }
    public static function hasLastInventario(): bool { return self::$ultimo !== null; }
    public static function getLastInventario() { return self::$ultimo; }
    public static function setLastInventario($a, $b, $c) {}
}

define('APP_ROOT', dirname(__DIR__));
define('DS', DIRECTORY_SEPARATOR);
require APP_ROOT . DS . 'src' . DS . 'Helpers' . DS . 'functions.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'LocaisEstoque.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'ZMDCODBARRAS.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'InventarioRM.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'ContextoInventario.php';

$falhas = 0;

function confere(string $rotulo, $obtido, $esperado): void
{
    global $falhas;
    $ok = ($obtido === $esperado);
    if (!$ok) {
        $falhas++;
    }
    printf("  %-50s %-16s %s\n", $rotulo, var_export($obtido, true),
        $ok ? 'ok' : '<-- esperava ' . var_export($esperado, true));
}

/**
 * Todos os casos abaixo usam código e local válidos que existem no RM
 * simulado, de propósito: assim nenhum cai num redirect. redirect_to() termina
 * o processo, então um desvio inesperado aqui faz a saída parar antes da última
 * linha — que é sinal suficiente de que algo mudou.
 */
function resolve(?array $entrada, array $get = []): ContextoInventario
{
    $_GET = $get;
    $_SESSION = [];
    SessionManager::$ultimo = null;

    return ContextoInventario::resolver('inventario.php', [], true, $entrada);
}

// Dois inventarios abertos em locais diferentes, para nao haver como confundir.
Connection::$inventarios = ['26.028.001' => 'A', '26.065.003' => 'A'];

echo "Sem origem explícita, continua lendo a URL — é o que as outras telas usam\n";
$ctx = resolve(null, ['CODINVENTARIO' => '26.028.001', 'CODLOC' => '028']);
confere('inventário da URL', $ctx->codinventario, '26.028.001');
confere('local da URL',      $ctx->codloc, '028');
confere('entrou na contagem', $ctx->ativo, true);

echo "\nCom origem explícita, é ela que manda\n";
$ctx = resolve(['CODINVENTARIO' => '26.065.003', 'CODLOC' => '065']);
confere('inventário do envio', $ctx->codinventario, '26.065.003');
confere('local do envio',      $ctx->codloc, '065');

echo "\nO caso que importa: URL e envio discordando\n";
// Simula a bipagem por POST com um endereco carregando outro inventario. Se o
// resolver voltar a olhar $_GET, o 028 ganha - e a contagem vai para o
// inventario errado, escolhido por quem montou o link.
$ctx = resolve(
    ['CODINVENTARIO' => '26.065.003', 'CODLOC' => '065'],
    ['CODINVENTARIO' => '26.028.001', 'CODLOC' => '028']
);
confere('o envio vence a URL', $ctx->codinventario, '26.065.003');
confere('e o local vem dele',  $ctx->codloc, '065');

echo "\nEnvio vazio não herda a URL por descuido\n";
$ctx = resolve([], ['CODINVENTARIO' => '26.028.001', 'CODLOC' => '028']);
confere('nada do envio, nada de inventário', $ctx->codinventario, '');
confere('nem local',                          $ctx->codloc, '');
confere('e não entra na contagem',            $ctx->ativo, false);

echo "\n" . ($falhas === 0 ? "TODAS AS REGRAS CONFEREM\n" : "{$falhas} PROBLEMA(S)\n");
exit($falhas === 0 ? 0 : 1);
