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

csrf_exigir($redirectUrl);

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
    // A edição passa pelas mesmas regras do bipe. Antes ela só conferia o
    // local: dava para trocar o código por qualquer coisa, inclusive texto não
    // numérico — e aí o CONVERT(INT, SUBSTRING(CODIGOBARRAS,...)) usado por
    // todos os totais quebrava para o inventário inteiro, não só para a linha.
    $codigobarras = preg_replace('/\D/', '', (string) ($_POST['CODIGOBARRAS'] ?? ''));

    if ($id === '') {
        flash_set('danger', 'Registro não informado para edição.');
        redirect_to($redirectUrl);
    }

    if (strlen($codigobarras) !== 13) {
        flash_set('danger', 'O código de barras deve ter exatamente 13 dígitos.');
        redirect_to($redirectUrl);
    }

    $novaQuantidade = normalizar_quantidade($_POST['ITEM_QUANTIDADE'] ?? '');
    if ($novaQuantidade === null || $novaQuantidade <= 0) {
        flash_set('danger', 'Informe uma quantidade maior que zero (ex.: 1, 2 ou 1,5).');
        redirect_to($redirectUrl);
    }

    $itemCodloc = (string) ($_POST['ITEM_CODLOC'] ?? '');
    $localCheck = LocaisEstoque::validar($itemCodloc);
    if (!$localCheck['valid']) {
        flash_set('danger', $localCheck['error']);
        redirect_to($redirectUrl);
    }

    $validacao = ZMDCODBARRAS::validarCodigoBarras($codigobarras);
    if (!$validacao['valid']) {
        flash_set('danger', implode(' ', $validacao['errors']));
        redirect_to($redirectUrl);
    }

    // Mesma regra de pertencimento do bipe; contagem avulsa não tem itens
    // gerados no RM contra os quais conferir.
    $idprd = (int) $validacao['idprd'] > 0
        ? (int) $validacao['idprd']
        : ZMDCODBARRAS::idprdDoBarcode($codigobarras);

    if ($codinventario !== '' && !ZMDCODBARRAS::ehCodigoAvulso($codinventario)) {
        if (!InventarioRM::itemPertenceAoInventario($codinventario, $localCheck['codloc'], $idprd)) {
            $produtoLabel = $validacao['nome'] !== '' ? $validacao['nome'] : ('ID ' . $idprd);
            flash_set(
                'danger',
                "Produto {$produtoLabel} não faz parte do inventário {$codinventario} no local "
                . $localCheck['codloc'] . '. Alteração não gravada.'
            );
            redirect_to($redirectUrl);
        }
    }

    $zmd = new ZMDCODBARRAS();
    $zmd->setId($id);
    $zmd->setCodigobarras($codigobarras);
    $zmd->setCodinventario($codinventario);
    $zmd->setQuantidade(quantidade_para_banco($novaQuantidade));
    $zmd->setCodloc($localCheck['codloc']);

    if ($zmd->atualizar()) {
        flash_set('success', 'Registro atualizado com sucesso.');
    } else {
        flash_set('danger', ZMDCODBARRAS::$ultimoErro !== ''
            ? 'Não foi possível atualizar o registro: ' . ZMDCODBARRAS::$ultimoErro
            : 'Não foi possível atualizar o registro.');
    }

    redirect_to($redirectUrl);
}

flash_set('warning', 'Ação inválida.');
redirect_to($redirectUrl);
