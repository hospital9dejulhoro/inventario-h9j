<?php

/**
 * O seletor de inventário: o que a tela oferece.
 *
 * Rode com:  php tests/seletor-inventario.php
 *
 * Quem cria inventário é o RM, então a tela escolhe em vez de aceitar
 * digitação. O que este teste guarda é o caso em que trocar campo por lista
 * estraga tudo: o inventário aberto agora pode não estar entre os abertos do
 * RM — encerrado que se está consultando, ou contagem avulsa. Se a lista não
 * o incluir, abrir a tela apaga a escolha da pessoa e ela cai fora da contagem.
 */

class LocaisEstoque
{
    public static function normalizar(string $c): string { return trim($c); }
    public static function nome(string $c): string { return $c === '065' ? 'FARMACIA' : ''; }
}

define('APP_ROOT', dirname(__DIR__));
define('DS', DIRECTORY_SEPARATOR);

function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

class ZMDCODBARRAS
{
    public static function ehCodigoAvulso(string $cod): bool
    {
        return (bool) preg_match('/^\d{2}\.\d{3}\.9\d{2}$/', $cod);
    }
}

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
 * Renderiza a parcial e devolve o HTML.
 */
function render(array $abertos, string $codinventario, string $codloc = '065'): string
{
    $inventariosAbertos = $abertos;
    $nomeLocal = 'FARMACIA';

    ob_start();
    require APP_ROOT . DS . 'views' . DS . '_seletor-inventario.php';
    return ob_get_clean();
}

/** Valor do <option selected>, ou '' se nenhum. */
function selecionado(string $html): string
{
    return preg_match('/<option value="([^"]*)"[^>]*\bselected\b/', $html, $m) ? $m[1] : '';
}

function contaOpcoes(string $html): int
{
    return preg_match_all('/<option /', $html);
}

$abertos = [
    ['codinventario' => '26.065.003', 'codloc' => '065', 'local_nome' => 'FARMACIA', 'itens' => 120],
    ['codinventario' => '26.028.001', 'codloc' => '028', 'local_nome' => 'ALMOX',    'itens' => 90],
];

echo "A tela oferece, não aceita digitação\n";
$html = render($abertos, '');
confere('é um select',            strpos($html, '<select') !== false, true);
confere('não é campo de texto',   strpos($html, 'type="text" name="CODINVENTARIO"') !== false, false);
confere('nome do campo preservado', strpos($html, 'name="CODINVENTARIO"') !== false, true);
confere('placeholder + os 2 abertos', contaOpcoes($html), 3);
confere('nada selecionado sem escolha', selecionado($html), '');
confere('mostra o local de cada um', strpos($html, '065 — FARMACIA') !== false, true);
confere('mostra quantos itens', strpos($html, '120 itens') !== false, true);

echo "\nA escolha da pessoa sobrevive a abrir a tela\n";
$html = render($abertos, '26.028.001');
confere('aberto do RM continua selecionado', selecionado($html), '26.028.001');
confere('sem opção duplicada', contaOpcoes($html), 3);

// O caso que quebraria: encerrado em consulta nao esta entre os abertos.
$html = render($abertos, '25.065.007');
confere('encerrado em consulta é mantido', selecionado($html), '25.065.007');
confere('entra como opção a mais', contaOpcoes($html), 4);
confere('rotulado como fora dos abertos', strpos($html, 'Fora dos abertos') !== false, true);

$html = render($abertos, '26.065.901');
confere('contagem avulsa é mantida', selecionado($html), '26.065.901');
confere('rotulada como avulsa', strpos($html, 'Contagem avulsa') !== false, true);

echo "\nQuando o RM não tem nada aberto\n";
$html = render([], '');
confere('só o placeholder',      contaOpcoes($html), 1);
confere('diz o que fazer',       strpos($html, 'use a contagem avulsa') !== false, true);

$html = render([], '26.065.901');
confere('a avulsa em curso aparece', selecionado($html), '26.065.901');
confere('e some o aviso de vazio',   strpos($html, 'use a contagem avulsa') !== false, false);

echo "\nO local continua saindo do inventário\n";
$html = render($abertos, '26.065.003');
confere('campo de local existe', strpos($html, 'id="CODLOC"') !== false, true);
confere('local é só leitura',    strpos($html, 'readonly') !== false, true);
confere('valor do local veio junto', strpos($html, 'value="065"') !== false, true);

echo "\n" . ($falhas === 0 ? "TODAS AS REGRAS CONFEREM\n" : "{$falhas} PROBLEMA(S)\n");
exit($falhas === 0 ? 0 : 1);
