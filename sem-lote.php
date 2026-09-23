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

// Abrir contagem avulsa direto daqui: num local sem lote nenhum, criar pela
// tela de lotes só para voltar para cá era desvio sem motivo.
if (isset($_GET['avulsa'])) {
    ContextoInventario::abrirAvulsa('sem-lote.php');
}

$ctx = ContextoInventario::resolver('sem-lote.php', $paramsFiltro);

$codloc = $ctx->codloc;
$codinventario = $ctx->codinventario;
$nomeLocal = $ctx->nomeLocal;
$statusInventarioRm = $ctx->statusRm;
$somenteLeitura = $ctx->somenteLeitura;
$motivoBloqueio = $ctx->motivoBloqueio;
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

    // Só na avulsa, e só porque a confirmação de descarte precisa dizer
    // quantos lançamentos vão embora.
    $bipagensAvulsa = $avulso ? ZMDCODBARRAS::contarPorInventario($codinventario) : 0;

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
