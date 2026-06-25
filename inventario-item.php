<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('inventario.php');
}

$acao = $_POST['acao'] ?? '';
$id = $_POST['id'] ?? '';
$codloc = (string) ($_POST['CODLOC'] ?? '');
$codinventario = (string) ($_POST['CODINVENTARIO'] ?? '');
$quantidade = (string) ($_POST['QUANTIDADE'] ?? '1');

$redirectParams = ZMDCODBARRAS::inventarioQueryParams($codloc, $codinventario, $quantidade);
$redirectUrl = 'inventario.php?' . http_build_query($redirectParams);

if ($acao === 'excluir') {
    if ($id === '') {
        flash_set('danger', 'Registro não informado para exclusão.');
        redirect_to($redirectUrl);
    }

    if (ZMDCODBARRAS::excluirPorId($id)) {
        flash_set('success', 'Registro excluído com sucesso.');
    } else {
        flash_set('danger', 'Não foi possível excluir o registro.');
    }

    redirect_to($redirectUrl);
}

if ($acao === 'excluir_inventario') {
    if ($codinventario === '') {
        flash_set('danger', 'Código do inventário não informado.');
        redirect_to('inventario.php');
    }

    $total = ZMDCODBARRAS::contarPorInventario($codinventario);

    if (ZMDCODBARRAS::excluirPorInventario($codinventario)) {
        SessionManager::removeRecentInventario($codinventario);
        $last = SessionManager::getLastInventario();
        if ($last !== null && ($last['codinventario'] ?? '') === $codinventario) {
            SessionManager::clearLastInventario();
        }
        SessionManager::resetSessionScans();
        $msg = $total > 0
            ? "Inventário {$codinventario} excluído ({$total} itens removidos)."
            : "Inventário {$codinventario} excluído (nenhum item no banco).";
        flash_set('success', $msg);
    } else {
        flash_set('danger', 'Não foi possível excluir o inventário.');
    }

    redirect_to('inventario.php');
}

if ($acao === 'editar') {
    $codigobarras = (string) ($_POST['CODIGOBARRAS'] ?? '');

    if ($id === '' || $codigobarras === '') {
        flash_set('danger', 'Dados incompletos para edição.');
        redirect_to($redirectUrl);
    }

    $zmd = new ZMDCODBARRAS();
    $zmd->setId($id);
    $zmd->setCodigobarras($codigobarras);
    $zmd->setQuantidade($_POST['ITEM_QUANTIDADE'] ?? '1');
    $zmd->setCodloc($_POST['ITEM_CODLOC'] ?? '');

    if ($zmd->atualizar()) {
        flash_set('success', 'Registro atualizado com sucesso.');
    } else {
        flash_set('danger', 'Não foi possível atualizar o registro.');
    }

    redirect_to($redirectUrl);
}

flash_set('warning', 'Ação inválida.');
redirect_to($redirectUrl);
