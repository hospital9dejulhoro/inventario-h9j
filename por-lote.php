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

// Abrir contagem avulsa: gera o proximo codigo livre do local e entra nele.
if (isset($_GET['avulsa'])) {
    $novo = ZMDCODBARRAS::proximoCodigoAvulso((string) ($_GET['CODLOC'] ?? ''));
    if ($novo['error'] !== '') {
        flash_set('danger', $novo['error']);
        redirect_to('por-lote.php');
    }
    flash_set(
        'info',
        'Contagem avulsa ' . $novo['codinventario'] . ' aberta. Ela ainda nao existe no RM — '
        . 'quando o inventario for criado, use "Vincular ao RM" para mover a contagem.'
    );
    redirect_to('por-lote.php?' . http_build_query([
        'CODINVENTARIO' => $novo['codinventario'],
        'CODLOC'        => LocaisEstoque::normalizar((string) ($_GET['CODLOC'] ?? '')),
        'aplicar'       => '1',
    ]));
}

$ctx = ContextoInventario::resolver('por-lote.php', $paramsFiltro);

$codloc = $ctx->codloc;
$codinventario = $ctx->codinventario;
$nomeLocal = $ctx->nomeLocal;
$statusInventarioRm = $ctx->statusRm;
$retomadoDaSessao = $ctx->retomadoDaSessao;
$modoLista = $ctx->ativo;
$avulso = $ctx->avulso;

$linhas = [];
$grupos = [];
$totaisProdutoLote = [];
$noInventario = [];
$listaTruncada = false;
$foraDoInventario = 0;
$valorTotal = 0.0;
$envAtual = EnvironmentManager::getCurrent();
$inventariosAbertos = $modoLista ? [] : InventarioRM::listarAbertos();
$contagensAvulsas = $modoLista ? [] : ZMDCODBARRAS::listarAvulsos();

if ($modoLista) {
    SessionManager::setLastInventario($codloc, $codinventario, '1');

    if (isset($_GET['aplicar'])) {
        redirect_to('por-lote.php?' . http_build_query($ctx->params($paramsFiltro)));
    }

    $linhas = InventarioRM::listarPosicaoPorLote($codloc, $busca, $grupoContabil, $somenteComSaldo);
    $grupos = InventarioRM::gruposContabeisDoLocal($codloc, $somenteComSaldo);
    // Avulsa nao tem itens gerados no RM para comparar: tudo que tem posicao no
    // local pode ser contado.
    $noInventario = $avulso ? [] : InventarioRM::idprdsDoInventario($codinventario, $codloc);
    $totaisProdutoLote = ZMDCODBARRAS::totaisPorProdutoLote($codinventario);
    $listaTruncada = count($linhas) >= InventarioRM::LIMITE_LOTES;

    foreach ($linhas as $linha) {
        $valorTotal += (float) $linha['saldo_financeiro'];
        if (!$avulso && empty($noInventario[(int) $linha['idprd']])) {
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
