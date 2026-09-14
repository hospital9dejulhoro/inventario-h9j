<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

$busca = trim((string) ($_GET['q'] ?? ''));
$paramsBusca = $busca !== '' ? ['q' => $busca] : [];

$ctx = ContextoInventario::resolver('por-lote.php', $paramsBusca);

$codloc = $ctx->codloc;
$codinventario = $ctx->codinventario;
$nomeLocal = $ctx->nomeLocal;
$statusInventarioRm = $ctx->statusRm;
$retomadoDaSessao = $ctx->retomadoDaSessao;
$modoLista = $ctx->ativo;

$lotes = [];
$totaisProdutoLote = [];
$listaTruncada = false;
$envAtual = EnvironmentManager::getCurrent();
$inventariosAbertos = $modoLista ? [] : InventarioRM::listarAbertos();

if ($modoLista) {
    SessionManager::setLastInventario($codloc, $codinventario, '1');

    if (isset($_GET['aplicar'])) {
        redirect_to('por-lote.php?' . http_build_query($ctx->params($paramsBusca)));
    }

    $lotes = InventarioRM::listarLotesDoInventario($codinventario, $codloc, $busca);
    $totaisProdutoLote = ZMDCODBARRAS::totaisPorProdutoLote($codinventario);
    $listaTruncada = count($lotes) >= InventarioRM::LIMITE_LOTES;
}

$pageTitle = 'Inventário — Por lote';
$bodyClass = 'page-inventory page-por-lote';
$showNavbar = true;

ob_start();
require __DIR__ . '/views/por-lote.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
