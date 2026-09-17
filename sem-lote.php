<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

// Numa avulsa a folha vem da posição do local, e lá "com saldo" é o padrão
// útil; o operador marca a caixa quando quiser ver também o que está zerado.
$somenteComSaldo = !isset($_GET['todos']);

$paramsFiltro = [];
if (!$somenteComSaldo) {
    $paramsFiltro['todos'] = '1';
}

$ctx = ContextoInventario::resolver('sem-lote.php', $paramsFiltro);

$codloc = $ctx->codloc;
$codinventario = $ctx->codinventario;
$nomeLocal = $ctx->nomeLocal;
$statusInventarioRm = $ctx->statusRm;
$retomadoDaSessao = $ctx->retomadoDaSessao;
$modoLista = $ctx->ativo;
$avulso = $ctx->avulso;

$itens = [];
$totaisProduto = [];
$qtdItensRm = 0;
$listaTruncada = false;
$envAtual = EnvironmentManager::getCurrent();
$recentInventarios = SessionManager::getRecentInventarios();
$inventariosAbertos = $modoLista ? [] : InventarioRM::listarAbertos();
$contagensAvulsas = $modoLista ? [] : ZMDCODBARRAS::listarAvulsos();

if ($modoLista) {
    // A folha de um local grande é pesada de montar; o teto padrão do PHP
    // derrubava a tela no meio.
    app_operacao_demorada();

    SessionManager::setLastInventario($codloc, $codinventario, '1');

    if (isset($_GET['aplicar'])) {
        redirect_to('sem-lote.php?' . http_build_query($ctx->params($paramsFiltro)));
    }

    $itens = InventarioRM::listarItensSemLote($codinventario, $codloc, $avulso, $somenteComSaldo);
    $totaisProduto = ZMDCODBARRAS::totaisPorProduto($codinventario);
    $qtdItensRm = count($itens);
    $listaTruncada = $qtdItensRm >= InventarioRM::LIMITE_ITENS;
}

$pageTitle = 'Inventário — Sem lote';
$bodyClass = 'page-inventory page-sem-lote';
$showNavbar = true;

ob_start();
require __DIR__ . '/views/sem-lote.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
