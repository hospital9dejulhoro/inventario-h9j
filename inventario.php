<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

$codloc = isset($_GET['CODLOC']) ? (string) $_GET['CODLOC'] : '';
$codinventario = isset($_GET['CODINVENTARIO']) ? (string) $_GET['CODINVENTARIO'] : '';
$quantidade = isset($_GET['QUANTIDADE']) ? (string) $_GET['QUANTIDADE'] : '1';
$codigobarras = isset($_GET['CODIGOBARRAS']) ? (string) $_GET['CODIGOBARRAS'] : '';
$retomadoDaSessao = false;
$registros = [];
$mostrarTabela = false;

// Retomar último inventário se a URL não trouxer código
if ($codinventario === '' && SessionManager::hasLastInventario()) {
    $last = SessionManager::getLastInventario();
    $codloc = (string) ($last['codloc'] ?? '');
    $codinventario = (string) ($last['codinventario'] ?? '');
    $quantidade = (string) ($last['quantidade'] ?? '1');
    $retomadoDaSessao = true;
}

if ($codinventario !== '') {
    $mostrarTabela = true;

    if (isset($_GET['CODIGOBARRAS']) && $_GET['CODIGOBARRAS'] !== '') {
        $zmd = new ZMDCODBARRAS();
        $zmd->setCodigobarras($_GET['CODIGOBARRAS']);
        $zmd->setCodinventario($codinventario);
        $zmd->setQuantidade($_GET['QUANTIDADE'] ?? $quantidade);
        $zmd->setCodloc($_GET['CODLOC'] ?? $codloc);

        $redirectParams = [
            'CODLOC'        => $_GET['CODLOC'] ?? $codloc,
            'CODINVENTARIO' => $codinventario,
            'QUANTIDADE'    => $_GET['QUANTIDADE'] ?? $quantidade,
        ];

        if ($zmd->save()) {
            flash_set('success', 'Código de barras registrado com sucesso.');
        } else {
            flash_set('danger', 'Não foi possível salvar o registro. Verifique os dados e tente novamente.');
        }

        redirect_to('inventario.php?' . http_build_query($redirectParams));
    }

    SessionManager::setLastInventario($codloc, $codinventario, $quantidade);
    $registros = ZMDCODBARRAS::listarPorInventario($codinventario);
}

$pageTitle = 'Inventário RM — Leitura';
$bodyClass = 'page-inventory';
$showNavbar = true;

ob_start();
require __DIR__ . '/views/inventario.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
