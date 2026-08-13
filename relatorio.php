<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

$codinventario = trim((string) ($_GET['CODINVENTARIO'] ?? ''));
$export = strtolower(trim((string) ($_GET['export'] ?? '')));

if ($codinventario !== '') {
    $parsed = ZMDCODBARRAS::parseCodigoInventario($codinventario);
    if (!empty($parsed['valid'])) {
        $codinventario = $parsed['formatted'];
    } else {
        $codinventario = ZMDCODBARRAS::formatCodigoInventario($codinventario);
    }
}

$inventarios = ZMDCODBARRAS::listarInventariosComContagem();
$relatorio = [
    'totais' => ['bipagens' => 0, 'quantidade' => 0.0, 'produtos' => 0, 'lotes' => 0],
    'itens'  => [],
];
$nomeLocal = '';
$codloc = '';
$rmStatus = '';

if ($codinventario !== '') {
    $relatorio = ZMDCODBARRAS::relatorioContagem($codinventario);
    $parsed = ZMDCODBARRAS::parseCodigoInventario($codinventario);
    if (!empty($parsed['valid'])) {
        $codloc = $parsed['codloc'];
        $nomeLocal = $parsed['nome_local'];
    }
    $existe = InventarioRM::existeNoRm($codinventario);
    if ($existe['valid']) {
        $rmStatus = $existe['status'];
    }
}

if ($export === 'csv' && $codinventario !== '') {
    $filename = 'contagem-' . preg_replace('/[^0-9A-Za-z._-]/', '-', $codinventario) . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Inventário', $codinventario], ';');
    fputcsv($out, ['Local', $codloc . ($nomeLocal !== '' ? ' - ' . $nomeLocal : '')], ';');
    fputcsv($out, ['Bipagens', $relatorio['totais']['bipagens']], ';');
    fputcsv($out, ['Quantidade total', $relatorio['totais']['quantidade']], ';');
    fputcsv($out, ['Produtos', $relatorio['totais']['produtos']], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Produto', 'ID', 'Lote', 'Local', 'Und', 'Bipagens', 'Quantidade'], ';');
    foreach ($relatorio['itens'] as $item) {
        fputcsv($out, [
            $item['nome'],
            $item['idprd'],
            $item['lote'],
            $item['codloc'],
            $item['und'],
            $item['bipagens'],
            $item['quantidade'],
        ], ';');
    }
    fclose($out);
    exit;
}

$pageTitle = 'Relatório de contagem';
$showNavbar = true;
$envAtual = EnvironmentManager::getCurrent();

ob_start();
require APP_ROOT . DS . 'views' . DS . 'relatorio.php';
$content = ob_get_clean();
require APP_ROOT . DS . 'views' . DS . 'layout.php';
