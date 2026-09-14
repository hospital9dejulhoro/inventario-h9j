<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

$codloc = isset($_GET['CODLOC']) ? (string) $_GET['CODLOC'] : '';
$codinventario = isset($_GET['CODINVENTARIO']) ? (string) $_GET['CODINVENTARIO'] : '';
$retomadoDaSessao = false;
$modoLista = false;
$itens = [];
$totaisProduto = [];
$qtdItensRm = 0;
$envAtual = EnvironmentManager::getCurrent();
$recentInventarios = SessionManager::getRecentInventarios();
$statusInventarioRm = '';

$deveValidarAcao = isset($_GET['aplicar']);
$veioDaUrl = isset($_GET['CODINVENTARIO']) && trim((string) $_GET['CODINVENTARIO']) !== '';

if ($codinventario === '' && SessionManager::hasLastInventario()) {
    $last = SessionManager::getLastInventario();
    $codloc = (string) ($last['codloc'] ?? '');
    $codinventario = (string) ($last['codinventario'] ?? '');
    $retomadoDaSessao = true;
}

$redirectParams = function () use (&$codloc, &$codinventario) {
    return [
        'CODLOC'        => $codloc,
        'CODINVENTARIO' => $codinventario,
    ];
};

$parsedInv = null;
if ($codinventario !== '') {
    $parsedInv = ZMDCODBARRAS::parseCodigoInventario($codinventario);
    if ($parsedInv['valid']) {
        $codinventario = $parsedInv['formatted'];
        $codloc = $parsedInv['codloc'];
    } elseif ($deveValidarAcao) {
        flash_set('danger', $parsedInv['error']);
        redirect_to('sem-lote.php?' . http_build_query($redirectParams()));
    } else {
        $codinventario = ZMDCODBARRAS::formatCodigoInventario($codinventario);
        if (strlen(preg_replace('/\D/', '', $codinventario)) >= 5) {
            $codloc = substr(preg_replace('/\D/', '', $codinventario), 2, 3);
        }
        $parsedInv = ZMDCODBARRAS::parseCodigoInventario($codinventario);
    }
}

if ($codloc !== '') {
    $localCheck = LocaisEstoque::validar($codloc);
    if ($localCheck['valid']) {
        $codloc = $localCheck['codloc'];
    } elseif ($deveValidarAcao) {
        flash_set('danger', $localCheck['error']);
        redirect_to('sem-lote.php?' . http_build_query($redirectParams()));
    }
}

$mascaraOk = is_array($parsedInv) && !empty($parsedInv['valid']);
$rmOk = false;

if ($mascaraOk && $codloc !== '') {
    $rmCheck = InventarioRM::validarParaUso($codinventario, $codloc);
    if ($rmCheck['valid']) {
        $statusInventarioRm = $rmCheck['status'];
        if ($deveValidarAcao || $veioDaUrl) {
            $rmOk = true;
        }
    } elseif ($deveValidarAcao) {
        flash_set('danger', $rmCheck['error']);
        redirect_to('sem-lote.php?' . http_build_query($redirectParams()));
    }
}

$nomeLocal = LocaisEstoque::nome($codloc);
// Só a tela de seleção usa a lista (view: if (!$modoLista), e $modoLista === $rmOk).
$inventariosAbertos = $rmOk ? [] : InventarioRM::listarAbertos();

if ($rmOk) {
    $modoLista = true;
    if (isset($_GET['aplicar'])) {
        SessionManager::setLastInventario($codloc, $codinventario, '1');
        redirect_to('sem-lote.php?' . http_build_query($redirectParams()));
    }
    SessionManager::setLastInventario($codloc, $codinventario, '1');
    $itens = InventarioRM::listarItensSemLote($codinventario, $codloc);
    $totaisProduto = ZMDCODBARRAS::totaisPorProduto($codinventario);
    $qtdItensRm = count($itens);
}

$pageTitle = 'Inventário — Sem lote';
$bodyClass = 'page-inventory page-sem-lote';
$showNavbar = true;

ob_start();
require __DIR__ . '/views/sem-lote.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
