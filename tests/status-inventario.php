<?php

/**
 * Inventário encerrado no RM não aceita mais contagem.
 *
 * Rode com:  php tests/status-inventario.php
 *
 * TINVENTARIO.STATUS é varchar(1). No banco do hospital aparecem 'A' (aberto),
 * 'E' (encerrado) e 'P' (em processamento, só em registros de 2017 a 2020).
 * Contar num encerrado altera uma apuração que o RM já fechou, sem o RM saber.
 *
 * O caso que este teste guarda é o oposto, e é o que quase passou batido:
 * 142 códigos com contagem gravada não têm linha nenhuma em TINVENTARIO —
 * lixo de antes da máscara, como "0013710175975" (um código de barras digitado
 * no campo do inventário) e "23021,001". Se a trava tratasse "sem status" como
 * bloqueio, ninguém mais conseguiria limpar isso.
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
    /** @var array<string, string|null> CODINVENTARIO => STATUS, ou null para inexistente. */
    public static $inventarios = [];
    /** @var bool Resposta de localPertenceAoInventario. */
    public static $localPertence = true;

    public $linha = false;
    private $fila = [];

    public function __construct($db = 'RM') {}

    public function Consulta($sql = '', array $params = [])
    {
        $this->fila = [];

        if (strpos($sql, 'FROM TINVENTARIO') !== false) {
            $cod = (string) ($params[1] ?? '');
            $status = self::$inventarios[$cod] ?? null;
            if ($status !== null) {
                $this->fila = [['CODINVENTARIO' => $cod, 'STATUS' => $status]];
            }
            return;
        }

        if (strpos($sql, 'FROM TITMINVENTARIO') !== false && self::$localPertence) {
            $this->fila = [['OK' => 1]];
        }
    }

    public function Resultado()
    {
        $this->linha = array_shift($this->fila);
        return $this->linha !== null;
    }

    public function manipula($sql = '', array $params = []) { return true; }
    public function manipulaContando($sql, array $params = []): int { return 0; }
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
    printf("  %-52s %-8s %s\n", $rotulo, var_export($obtido, true),
        $ok ? 'ok' : '<-- esperava ' . var_export($esperado, true));
}

echo "Que status aceita gravação\n";
confere("'A' aberto grava",            InventarioRM::statusPermiteGravar('A'), true);
confere("'E' encerrado não grava",     InventarioRM::statusPermiteGravar('E'), false);
confere("'P' processamento não grava", InventarioRM::statusPermiteGravar('P'), false);
confere("minúsculo conta igual",       InventarioRM::statusPermiteGravar('a'), true);
confere("com espaço conta igual",      InventarioRM::statusPermiteGravar(' E '), false);
confere("sem status não é bloqueio",   InventarioRM::statusPermiteGravar(''), true);

echo "\nO motivo que vai para a tela\n";
confere('aberto não tem motivo', InventarioRM::motivoStatusBloqueia('26.028.001', 'A'), '');
$m = InventarioRM::motivoStatusBloqueia('26.028.001', 'E');
confere('encerrado diz qual inventário', strpos($m, '26.028.001') !== false, true);
confere('encerrado diz o estado por extenso', strpos($m, 'Encerrado') !== false, true);
confere('encerrado diz o que fazer', strpos($m, 'reabra o inventário no RM') !== false, true);

echo "\nvalidarParaUso: abrir e gravar são perguntas diferentes\n";
Connection::$inventarios = ['26.028.001' => 'A', '25.028.007' => 'E'];

$aberto = InventarioRM::validarParaUso('26.028.001', '028');
confere('aberto: valid',       $aberto['valid'], true);
confere('aberto: pode_gravar', $aberto['pode_gravar'], true);

$encerrado = InventarioRM::validarParaUso('25.028.007', '028');
confere('encerrado ainda abre para consulta', $encerrado['valid'], true);
confere('encerrado não grava',                $encerrado['pode_gravar'], false);
confere('encerrado traz o motivo',            $encerrado['motivo_bloqueio'] !== '', true);
confere('encerrado não é erro de abertura',   $encerrado['error'], '');

$inexistente = InventarioRM::validarParaUso('30.028.001', '028');
confere('inexistente: não abre',  $inexistente['valid'], false);
confere('inexistente: não grava', $inexistente['pode_gravar'], false);

echo "\nmotivoNaoPodeGravar: a trava da edição e da exclusão\n";
confere('encerrado bloqueia',
    InventarioRM::motivoNaoPodeGravar('25.028.007') !== '', true);
confere('aberto libera',
    InventarioRM::motivoNaoPodeGravar('26.028.001'), '');
confere('avulsa (9xx) não tem status a consultar',
    InventarioRM::motivoNaoPodeGravar('26.028.901'), '');
confere('vazio libera',
    InventarioRM::motivoNaoPodeGravar(''), '');

// Sem estes dois, a limpeza do lixo historico ficaria impossivel.
confere('código órfão continua podendo ser limpo',
    InventarioRM::motivoNaoPodeGravar('21.003.001'), '');
confere('código de barras digitado no campo também',
    InventarioRM::motivoNaoPodeGravar('0013710175975'), '');

echo "\nRótulos\n";
confere("'A'", InventarioRM::rotuloStatus('A'), 'Aberto');
confere("'E'", InventarioRM::rotuloStatus('E'), 'Encerrado');
confere("'P'", InventarioRM::rotuloStatus('P'), 'Em processamento');
confere("''",  InventarioRM::rotuloStatus(''), 'desconhecido');
confere("'X' desconhecido mostra a letra", InventarioRM::rotuloStatus('X'), 'X');

echo "\n A lista de avulsas e o botão de descartar concordam\n";
// A lista sai de uma consulta SQL; o descarte, de ehCodigoAvulso(). Enquanto as
// duas definições divergiam, a tela oferecia o que o botão recusava: o código
// de barras '0013710175975' termina em 975, passava no >= 900 da consulta e
// aparecia como avulsa com 482 lançamentos — mas descartar negava, e mandava
// para a tela de Leitura, que também recusa. Ficavam à vista e presos.
$aceitaNaConsulta = function (string $cod): bool {
    $ultimos = substr($cod, -3);
    return ctype_digit($ultimos)
        && (int) $ultimos >= 900
        && (bool) preg_match('/^\d{2}\.\d{3}\.\d{3}$/', $cod);
};

$casos = [
    '26.028.900'    => true,   // avulsa de verdade
    '26.028.999'    => true,
    '26.028.899'    => false,  // abaixo da faixa
    '0013710175975' => false,  // código de barras no campo do inventário
    '23021,001'     => false,  // vírgula no lugar do ponto
    '21033001'      => false,  // sem os pontos
];
foreach ($casos as $cod => $esperado) {
    $noBotao = ZMDCODBARRAS::ehCodigoAvulso($cod);
    confere("'{$cod}': lista e botão concordam", $aceitaNaConsulta($cod) === $noBotao, true);
    confere("'{$cod}': é avulsa?", $noBotao, $esperado);
}

echo "\n" . ($falhas === 0 ? "TODAS AS REGRAS CONFEREM\n" : "{$falhas} PROBLEMA(S)\n");
exit($falhas === 0 ? 0 : 1);
