<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

// Relatorio de posicao: depende so do local. Nao tem inventario nem data de
// referencia - e sempre o saldo de agora, direto de TPRDLOC/TLOTEPRDLOC.
$codloc = LocaisEstoque::normalizar((string) ($_GET['CODLOC'] ?? ''));
$busca = trim((string) ($_GET['q'] ?? ''));
$grupoContabil = trim((string) ($_GET['grupo'] ?? ''));
$somenteComSaldo = !isset($_GET['todos']);
$export = strtolower(trim((string) ($_GET['export'] ?? '')));

$localValido = $codloc !== '' && LocaisEstoque::existe($codloc);
$nomeLocal = $localValido ? LocaisEstoque::nome($codloc) : '';
$envAtual = EnvironmentManager::getCurrent();

$linhas = [];
$grupos = [];
$truncado = false;
$totais = ['linhas' => 0, 'produtos' => 0, 'lotes' => 0, 'quantidade' => 0.0, 'valor' => 0.0];

if ($localValido) {
    $linhas = InventarioRM::listarPosicaoPorLote($codloc, $busca, $grupoContabil, $somenteComSaldo);
    $grupos = InventarioRM::gruposContabeisDoLocal($codloc, $somenteComSaldo);
    $truncado = count($linhas) >= InventarioRM::LIMITE_LOTES;

    $produtos = [];
    foreach ($linhas as $linha) {
        $totais['linhas']++;
        $produtos[(int) $linha['idprd']] = true;
        $totais['quantidade'] += (float) $linha['saldo'];
        $totais['valor'] += (float) $linha['saldo_financeiro'];
    }
    $totais['produtos'] = count($produtos);
    $totais['lotes'] = $totais['linhas'];
}

if ($export === 'pdf' && $localValido) {
    $grupoLabel = '';
    if ($grupoContabil !== '') {
        $grupoLabel = isset($grupos[$grupoContabil]) && $grupos[$grupoContabil] !== ''
            ? $grupoContabil . ' - ' . $grupos[$grupoContabil]
            : $grupoContabil;
    }

    PosicaoEstoquePdf::gerar([
        'codloc'      => $codloc,
        'local_label' => $codloc . ($nomeLocal !== '' ? ' - ' . $nomeLocal : ''),
        'grupo_label' => $grupoLabel,
        'ambiente'    => (string) ($envAtual['label'] ?? ''),
        'operador'    => SessionManager::getDisplayName() ?: SessionManager::getUsername(),
        'totais'      => $totais,
        'itens'       => $linhas,
    ]);
    exit;
}

if ($export === 'csv' && $localValido) {
    $filename = 'posicao-' . $codloc . '-' . date('Ymd-Hi') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Local', $codloc . ($nomeLocal !== '' ? ' - ' . $nomeLocal : '')], ';');
    fputcsv($out, ['Ambiente', (string) ($envAtual['label'] ?? '')], ';');
    fputcsv($out, ['Gerado em', date('d/m/Y H:i')], ';');
    if ($grupoContabil !== '') {
        fputcsv($out, ['Grupo contabil', $grupoContabil], ';');
    }
    fputcsv($out, ['Lotes', $totais['lotes']], ';');
    fputcsv($out, ['Produtos', $totais['produtos']], ';');
    fputcsv($out, ['Saldo financeiro', round($totais['valor'], 2)], ';');
    fputcsv($out, [], ';');

    // Cabecalho com os mesmos nomes da consulta de posicao do RM
    fputcsv($out, ['IDPRD', 'NOMEFANTASIA', 'CODGRUPOCONTABIL', 'GRUPOCONTABIL',
                   'CODLOCALESTOQUE', 'LOCALESTOQUE', 'IDLOTE', 'NUMLOTE',
                   'DATAVALIDADE', 'SALDO', 'CUSTOMEDIO', 'SALDOFINANCEIRO'], ';');

    foreach ($linhas as $linha) {
        fputcsv($out, [
            $linha['idprd'],
            $linha['nome'],
            $linha['grupo_cod'],
            $linha['grupo_nome'],
            $linha['codloc'],
            $linha['local_nome'],
            $linha['idlote'],
            $linha['numlote'],
            $linha['validade'],
            $linha['saldo'],
            round((float) $linha['custo_medio'], 4),
            round((float) $linha['saldo_financeiro'], 2),
        ], ';');
    }
    fclose($out);
    exit;
}

$pageTitle = 'Posição de estoque';
$bodyClass = 'page-posicao';
$showNavbar = true;

ob_start();
require __DIR__ . '/views/posicao.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
