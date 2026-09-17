<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

// A conferência cruza a posição inteira do local com a contagem; nas
// exportações isso ainda vira CSV ou PDF. É o caminho mais demorado do app.
app_operacao_demorada();

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

// Conferencia sob demanda: ela dispara a consulta de posicao do local, cara
// demais para rodar em toda abertura do relatorio normal.
$verConferencia = isset($_GET['conferencia']) || $export === 'conferencia';
$conferencia = [
    'itens'  => [],
    'totais' => [
        'esperados' => 0, 'contados' => 0, 'nao_contados' => 0, 'sobras' => 0,
        'saldo' => 0.0, 'contado' => 0.0, 'diferenca' => 0.0, 'valor_diferenca' => 0.0,
    ],
    'truncado' => false,
];

if ($verConferencia && $codinventario !== '' && $codloc !== '') {
    $conferencia = InventarioRM::conferenciaDoLocal($codinventario, $codloc);
}

if ($export === 'conferencia' && $codinventario !== '') {
    $filename = 'conferencia-' . preg_replace('/[^0-9A-Za-z._-]/', '-', $codinventario) . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    $rotulos = ['contado' => 'Contado', 'nao_contado' => 'NAO CONTADO', 'sobra' => 'SOBRA'];
    $tc = $conferencia['totais'];

    fputcsv($out, ['Inventário', $codinventario], ';');
    fputcsv($out, ['Local', $codloc . ($nomeLocal !== '' ? ' - ' . $nomeLocal : '')], ';');
    fputcsv($out, ['Esperados no local', $tc['esperados']], ';');
    fputcsv($out, ['Contados', $tc['contados']], ';');
    fputcsv($out, ['Nao contados', $tc['nao_contados']], ';');
    fputcsv($out, ['Sobras (contado fora da posicao)', $tc['sobras']], ';');
    fputcsv($out, ['Diferenca total', $tc['diferenca']], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Situacao', 'Produto', 'ID', 'Lote', 'Validade', 'Grupo', 'Und',
                   'Saldo sistema', 'Contado', 'Diferenca', 'Valor diferenca', 'Bipagens'], ';');

    foreach ($conferencia['itens'] as $item) {
        fputcsv($out, [
            $rotulos[$item['situacao']] ?? $item['situacao'],
            $item['nome'],
            $item['idprd'],
            $item['numlote'],
            $item['validade'],
            $item['grupo'],
            $item['und'],
            $item['saldo'],
            $item['contado'],
            $item['diferenca'],
            round((float) $item['valor_diferenca'], 2),
            $item['bipagens'],
        ], ';');
    }
    fclose($out);
    exit;
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

if ($export === 'pdf' && $codinventario !== '') {
    carregar_pdf('RelatorioContagemPdf');
    $envAtual = EnvironmentManager::getCurrent();
    $localLabel = $codloc;
    if ($nomeLocal !== '') {
        $localLabel = $codloc !== '' ? ($codloc . ' — ' . $nomeLocal) : $nomeLocal;
    }
    RelatorioContagemPdf::gerar([
        'codinventario' => $codinventario,
        'local_label'   => $localLabel,
        'ambiente'      => (string) ($envAtual['label'] ?? ''),
        'operador'      => SessionManager::getDisplayName() ?: SessionManager::getUsername(),
        'status_rm'     => $rmStatus,
        'totais'        => $relatorio['totais'],
        'itens'         => $relatorio['itens'],
    ]);
    exit;
}

$pageTitle = 'Relatório de contagem';
$showNavbar = true;
$envAtual = EnvironmentManager::getCurrent();

ob_start();
require APP_ROOT . DS . 'views' . DS . 'relatorio.php';
$content = ob_get_clean();
require APP_ROOT . DS . 'views' . DS . 'layout.php';
