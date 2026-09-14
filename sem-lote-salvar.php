<?php

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Use POST.']);
    exit;
}

if (!SessionManager::isConnected()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sessão expirada. Entre novamente.']);
    exit;
}

$idprd = (int) ($_POST['idprd'] ?? 0);
$quantidadeRaw = str_replace([' ', ','], ['', '.'], trim((string) ($_POST['quantidade'] ?? '')));
$codinventario = trim((string) ($_POST['CODINVENTARIO'] ?? ''));
$codloc = trim((string) ($_POST['CODLOC'] ?? ''));

if ($idprd <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Produto inválido.']);
    exit;
}

if ($quantidadeRaw === '' || !is_numeric($quantidadeRaw) || (float) $quantidadeRaw <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Informe uma quantidade maior que zero.']);
    exit;
}

$quantidade = (string) (0 + (float) $quantidadeRaw);

$parsed = ZMDCODBARRAS::parseCodigoInventario($codinventario);
if (empty($parsed['valid'])) {
    echo json_encode(['ok' => false, 'message' => $parsed['error'] ?: 'Inventário inválido.']);
    exit;
}

$codinventario = $parsed['formatted'];
$codloc = $parsed['codloc'] !== '' ? $parsed['codloc'] : LocaisEstoque::normalizar($codloc);

$rmCheck = InventarioRM::validarParaUso($codinventario, $codloc);
if (!$rmCheck['valid']) {
    echo json_encode(['ok' => false, 'message' => $rmCheck['error']]);
    exit;
}

if (!InventarioRM::itemPertenceAoInventario($codinventario, $codloc, $idprd)) {
    echo json_encode(['ok' => false, 'message' => 'Este produto não faz parte do inventário neste local.']);
    exit;
}

$codigobarras = ZMDCODBARRAS::barcodeSemLote($idprd);
if ($codigobarras === '') {
    echo json_encode([
        'ok'      => false,
        'message' => 'Produto ' . $idprd . ' não cabe no código de 13 dígitos (IDPRD acima de 999999). Conte este item pela leitura de código de barras.',
    ]);
    exit;
}

$zmd = new ZMDCODBARRAS();
$zmd->setCodigobarras($codigobarras);
$zmd->setCodinventario($codinventario);
$zmd->setQuantidade($quantidade);
$zmd->setCodloc($codloc);

if (!$zmd->save()) {
    echo json_encode(['ok' => false, 'message' => 'Não foi possível gravar. Tente novamente.']);
    exit;
}

SessionManager::incrementSessionScans();
$totais = ZMDCODBARRAS::totaisPorProduto($codinventario);
$total = $totais[$idprd] ?? (float) $quantidade;

echo json_encode([
    'ok'          => true,
    'message'     => 'Registrado',
    'idprd'       => $idprd,
    'quantidade'  => (float) $quantidade,
    'total'       => $total,
]);
