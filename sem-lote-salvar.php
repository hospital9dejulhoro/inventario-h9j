<?php

require __DIR__ . '/bootstrap.php';

app_modo_json();
header('Content-Type: application/json; charset=utf-8');

function sl_falha(string $mensagem, int $status = 200): void
{
    if ($status !== 200) {
        http_response_code($status);
    }
    echo json_encode(['ok' => false, 'message' => $mensagem]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_falha('Use POST.', 405);
}

if (!SessionManager::isConnected()) {
    sl_falha('Sessão expirada. Entre novamente.', 401);
}

csrf_exigir();

$idprd = (int) ($_POST['idprd'] ?? 0);
$quantidadeNum = normalizar_quantidade($_POST['quantidade'] ?? '');
$codinventario = trim((string) ($_POST['CODINVENTARIO'] ?? ''));
$codloc = trim((string) ($_POST['CODLOC'] ?? ''));
// Mesmos dois modos da tela de lotes. Sem "corrigir", quem digitasse 10 no
// lugar de 1 não tinha como desfazer daqui: teria de caçar o lançamento na
// tela de leitura para excluir.
$modo = ($_POST['modo'] ?? 'somar') === 'corrigir' ? 'corrigir' : 'somar';

if ($idprd <= 0) {
    sl_falha('Produto inválido.');
}

if ($quantidadeNum === null) {
    sl_falha('Informe uma quantidade válida.');
}

// Zero só faz sentido corrigindo, e aí quer dizer "apaga a contagem deste item".
if ($modo === 'corrigir' ? $quantidadeNum < 0 : $quantidadeNum <= 0) {
    sl_falha($modo === 'corrigir'
        ? 'Quantidade não pode ser negativa. Use zero para apagar a contagem deste item.'
        : 'Informe uma quantidade maior que zero.');
}

$parsed = ZMDCODBARRAS::parseCodigoInventario($codinventario);
if (empty($parsed['valid'])) {
    sl_falha($parsed['error'] ?: 'Inventário inválido.');
}

$codinventario = $parsed['formatted'];
$codloc = $parsed['codloc'] !== '' ? $parsed['codloc'] : LocaisEstoque::normalizar($codloc);

// Contagem avulsa não tem inventário no RM para validar contra; o local já foi
// conferido pela máscara do código.
if (!ZMDCODBARRAS::ehCodigoAvulso($codinventario)) {
    $rmCheck = InventarioRM::validarParaUso($codinventario, $codloc);
    if (!$rmCheck['valid']) {
        sl_falha($rmCheck['error']);
    }

    if (!InventarioRM::itemPertenceAoInventario($codinventario, $codloc, $idprd)) {
        sl_falha('Este produto não faz parte do inventário neste local.');
    }
}

$codigobarras = ZMDCODBARRAS::barcodeSemLote($idprd);
if ($codigobarras === '') {
    sl_falha('Produto ' . $idprd . ' não cabe no código de 13 dígitos (IDPRD acima de 999999). '
        . 'Conte este item pela leitura de código de barras.');
}

$apagados = 0;

if ($modo === 'corrigir') {
    // Item sem lote é IDLOTE 0 no layout do código — a mesma rotina
    // transacional da tela de lotes serve, e com ela a correção simultânea
    // do mesmo item também é segura aqui.
    $correcao = ZMDCODBARRAS::corrigirTotalProdutoLote($codinventario, $idprd, 0, $quantidadeNum, $codloc);

    if (!$correcao['ok']) {
        sl_falha($correcao['error']);
    }

    $apagados = (int) $correcao['apagados'];
} else {
    $zmd = new ZMDCODBARRAS();
    $zmd->setCodigobarras($codigobarras);
    $zmd->setCodinventario($codinventario);
    $zmd->setQuantidade(quantidade_para_banco($quantidadeNum));
    $zmd->setCodloc($codloc);

    if (!$zmd->save()) {
        sl_falha(ZMDCODBARRAS::$ultimoErro !== '' && !empty($GLOBALS['appConfig']['debug'])
            ? 'Não foi possível gravar: ' . ZMDCODBARRAS::$ultimoErro
            : 'Não foi possível gravar. Tente novamente.');
    }

    SessionManager::incrementSessionScans();
}

$totais = ZMDCODBARRAS::totaisPorProduto($codinventario);
$total = $totais[$idprd] ?? ($modo === 'corrigir' ? $quantidadeNum : 0.0);

// Mesma checagem da tela de lotes: corrigir substitui, então o que ficou
// gravado tem de ser exatamente o que foi pedido. Se não for, alguém contou
// este item entre a leitura da tela e o clique.
$aviso = '';
if ($modo === 'corrigir' && abs($total - $quantidadeNum) > 0.0001) {
    $aviso = 'Outro operador mexeu neste item agora há pouco. O total gravado é '
        . formatar_quantidade($total) . ', não ' . formatar_quantidade($quantidadeNum)
        . '. Confira antes de corrigir de novo.';
    log_erro('correcao concorrente', "inv={$codinventario} idprd={$idprd} sem lote pedido={$quantidadeNum} final={$total}");
}

echo json_encode([
    'ok'         => true,
    'modo'       => $modo,
    'message'    => $modo === 'corrigir' ? 'Total corrigido' : 'Registrado',
    'idprd'      => $idprd,
    'quantidade' => $quantidadeNum,
    'total'      => $total,
    'apagados'   => $apagados,
    'aviso'      => $aviso,
]);
