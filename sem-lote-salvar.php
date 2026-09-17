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

if ($idprd <= 0) {
    sl_falha('Produto inválido.');
}

if ($quantidadeNum === null || $quantidadeNum <= 0) {
    sl_falha('Informe uma quantidade maior que zero.');
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
$totais = ZMDCODBARRAS::totaisPorProduto($codinventario);
$total = $totais[$idprd] ?? $quantidadeNum;

echo json_encode([
    'ok'         => true,
    'message'    => 'Registrado',
    'idprd'      => $idprd,
    'quantidade' => $quantidadeNum,
    'total'      => $total,
]);
