<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

$busca = trim((string) ($_GET['q'] ?? ''));
$grupoContabil = trim((string) ($_GET['grupo'] ?? ''));

// Padrao "so com saldo" espelha a consulta de posicao da farmacia; desmarcar
// mostra tambem o lote zerado, que e onde aparece divergencia de prateleira.
$somenteComSaldo = !isset($_GET['todos']);

$paramsFiltro = [];
if ($busca !== '') {
    $paramsFiltro['q'] = $busca;
}
if ($grupoContabil !== '') {
    $paramsFiltro['grupo'] = $grupoContabil;
}
if (!$somenteComSaldo) {
    $paramsFiltro['todos'] = '1';
}

$ctx = ContextoInventario::resolver('por-lote.php', $paramsFiltro);

$codloc = $ctx->codloc;
$codinventario = $ctx->codinventario;
$nomeLocal = $ctx->nomeLocal;
$statusInventarioRm = $ctx->statusRm;
$retomadoDaSessao = $ctx->retomadoDaSessao;
$modoLista = $ctx->ativo;

$linhas = [];
$grupos = [];
$totaisProdutoLote = [];
$noInventario = [];
$listaTruncada = false;
$foraDoInventario = 0;
$valorTotal = 0.0;
$envAtual = EnvironmentManager::getCurrent();
$inventariosAbertos = $modoLista ? [] : InventarioRM::listarAbertos();

if ($modoLista) {
    SessionManager::setLastInventario($codloc, $codinventario, '1');

    if (isset($_GET['aplicar'])) {
        redirect_to('por-lote.php?' . http_build_query($ctx->params($paramsFiltro)));
    }

    $linhas = InventarioRM::listarPosicaoPorLote($codloc, $busca, $grupoContabil, $somenteComSaldo);
    $grupos = InventarioRM::gruposContabeisDoLocal($codloc, $somenteComSaldo);
    $noInventario = InventarioRM::idprdsDoInventario($codinventario, $codloc);
    $totaisProdutoLote = ZMDCODBARRAS::totaisPorProdutoLote($codinventario);
    $listaTruncada = count($linhas) >= InventarioRM::LIMITE_LOTES;

    foreach ($linhas as $linha) {
        $valorTotal += (float) $linha['saldo_financeiro'];
        if (empty($noInventario[(int) $linha['idprd']])) {
            $foraDoInventario++;
        }
    }
}

$pageTitle = 'Inventário — Por lote';
$bodyClass = 'page-inventory page-por-lote';
$showNavbar = true;

ob_start();
require __DIR__ . '/views/por-lote.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
