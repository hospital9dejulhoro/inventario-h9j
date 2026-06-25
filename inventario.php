<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

$codloc = isset($_GET['CODLOC']) ? (string) $_GET['CODLOC'] : '';
$codinventario = isset($_GET['CODINVENTARIO']) ? (string) $_GET['CODINVENTARIO'] : '';
$quantidade = isset($_GET['QUANTIDADE']) ? (string) $_GET['QUANTIDADE'] : '1';
$codigobarras = isset($_GET['CODIGOBARRAS']) ? (string) $_GET['CODIGOBARRAS'] : '';
$registros = [];
$mostrarTabela = false;

if (isset($_GET['CODINVENTARIO']) && $_GET['CODINVENTARIO'] !== '') {
    $mostrarTabela = true;

    if (isset($_GET['CODIGOBARRAS']) && $_GET['CODIGOBARRAS'] !== '') {
        $zmd = new ZMDCODBARRAS();
        $zmd->setCodigobarras($_GET['CODIGOBARRAS']);
        $zmd->setCodinventario($_GET['CODINVENTARIO']);
        $zmd->setQuantidade($_GET['QUANTIDADE'] ?? '1');
        $zmd->setCodloc($_GET['CODLOC'] ?? '');

        $redirectParams = [
            'CODLOC'        => $_GET['CODLOC'] ?? '',
            'CODINVENTARIO' => $_GET['CODINVENTARIO'],
            'QUANTIDADE'    => $_GET['QUANTIDADE'] ?? '1',
        ];

        if ($zmd->save()) {
            flash_set('success', 'Código de barras registrado com sucesso.');
        } else {
            flash_set('danger', 'Não foi possível salvar o registro. Verifique os dados e tente novamente.');
        }

        // PRG: evita duplicar registro ao atualizar a página (F5)
        redirect_to('inventario.php?' . http_build_query($redirectParams));
    }

    $registros = ZMDCODBARRAS::listarPorInventario($_GET['CODINVENTARIO']);
}

$pageTitle = 'Inventário RM — Leitura';
$bodyClass = 'page-inventory';
$showNavbar = true;

ob_start();
require __DIR__ . '/views/inventario.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
