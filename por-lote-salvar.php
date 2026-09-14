<?php

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function pl_falha(string $mensagem, int $status = 200): void
{
    if ($status !== 200) {
        http_response_code($status);
    }
    echo json_encode(['ok' => false, 'message' => $mensagem]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pl_falha('Use POST.', 405);
}

if (!SessionManager::isConnected()) {
    pl_falha('Sessão expirada. Entre novamente.', 401);
}

$idprd = (int) ($_POST['idprd'] ?? 0);
$idlote = (int) ($_POST['idlote'] ?? 0);
$quantidadeRaw = str_replace([' ', ','], ['', '.'], trim((string) ($_POST['quantidade'] ?? '')));
$codinventario = trim((string) ($_POST['CODINVENTARIO'] ?? ''));
$codloc = trim((string) ($_POST['CODLOC'] ?? ''));

if ($idprd <= 0) {
    pl_falha('Produto inválido.');
}

if ($idlote <= 0) {
    pl_falha('Lote inválido. Use a tela "Sem lote" para produtos sem controle de lote.');
}

if ($quantidadeRaw === '' || !is_numeric($quantidadeRaw) || (float) $quantidadeRaw <= 0) {
    pl_falha('Informe uma quantidade maior que zero.');
}

$quantidade = (string) (0 + (float) $quantidadeRaw);

$parsed = ZMDCODBARRAS::parseCodigoInventario($codinventario);
if (empty($parsed['valid'])) {
    pl_falha($parsed['error'] ?: 'Inventário inválido.');
}

$codinventario = $parsed['formatted'];
$codloc = $parsed['codloc'] !== '' ? $parsed['codloc'] : LocaisEstoque::normalizar($codloc);

// Contagem avulsa nao tem inventario no RM para validar contra; o local ja foi
// conferido pela mascara do codigo.
if (!ZMDCODBARRAS::ehCodigoAvulso($codinventario)) {
    $rmCheck = InventarioRM::validarParaUso($codinventario, $codloc);
    if (!$rmCheck['valid']) {
        pl_falha($rmCheck['error']);
    }

    // Pertencimento no RM é por produto: o lote vem do cadastro do próprio produto.
    if (!InventarioRM::itemPertenceAoInventario($codinventario, $codloc, $idprd)) {
        pl_falha('Este produto não faz parte do inventário neste local.');
    }
}

$codigobarras = ZMDCODBARRAS::barcodeComLote($idprd, $idlote);
if ($codigobarras === '') {
    pl_falha('Produto ' . $idprd . ' / lote ' . $idlote . ' não cabe no código de 13 dígitos.');
}

$zmd = new ZMDCODBARRAS();
$zmd->setCodigobarras($codigobarras);
$zmd->setCodinventario($codinventario);
$zmd->setQuantidade($quantidade);
$zmd->setCodloc($codloc);

if (!$zmd->save()) {
    pl_falha('Não foi possível gravar. Tente novamente.');
}

SessionManager::incrementSessionScans();

$totais = ZMDCODBARRAS::totaisPorProdutoLote($codinventario);
$total = $totais[$idprd . ':' . $idlote] ?? (float) $quantidade;

// resumoDoCodigo compara por produto+lote, então enxerga também o que veio
// bipado da etiqueta nesta mesma contagem.
$resumo = ZMDCODBARRAS::resumoDoCodigo($codinventario, $codigobarras);

echo json_encode([
    'ok'         => true,
    'message'    => 'Registrado',
    'idprd'      => $idprd,
    'idlote'     => $idlote,
    'quantidade' => (float) $quantidade,
    'total'      => $total,
    'leituras'   => (int) $resumo['leituras'],
]);
