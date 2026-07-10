<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

$codloc = isset($_GET['CODLOC']) ? (string) $_GET['CODLOC'] : '';
$codinventario = isset($_GET['CODINVENTARIO']) ? (string) $_GET['CODINVENTARIO'] : '';
$quantidade = isset($_GET['QUANTIDADE']) ? (string) $_GET['QUANTIDADE'] : '1';
$codigobarras = isset($_GET['CODIGOBARRAS']) ? (string) $_GET['CODIGOBARRAS'] : '';
$retomadoDaSessao = false;
$modoLeitura = false;
$registros = [];
$mostrarTabela = false;
$envAtual = EnvironmentManager::getCurrent();
$leiturasSessao = SessionManager::getSessionScans();
$recentInventarios = SessionManager::getRecentInventarios();

// Retomar último inventário
if ($codinventario === '' && SessionManager::hasLastInventario()) {
    $last = SessionManager::getLastInventario();
    $codloc = (string) ($last['codloc'] ?? '');
    $codinventario = (string) ($last['codinventario'] ?? '');
    $quantidade = (string) ($last['quantidade'] ?? '1');
    $retomadoDaSessao = true;
}

// Normaliza máscara AA.LLL.NNN, valida local e sincroniza CODLOC
if ($codinventario !== '') {
    $parsedInv = ZMDCODBARRAS::parseCodigoInventario($codinventario);
    if ($parsedInv['valid']) {
        $codinventario = $parsedInv['formatted'];
        $codloc = $parsedInv['codloc'];
    } elseif (isset($_GET['aplicar']) || (isset($_GET['CODIGOBARRAS']) && trim((string) $_GET['CODIGOBARRAS']) !== '')) {
        flash_set('danger', $parsedInv['error']);
        redirect_to('inventario.php?' . http_build_query(ZMDCODBARRAS::inventarioQueryParams(
            $codloc,
            ZMDCODBARRAS::formatCodigoInventario($codinventario),
            $quantidade
        )));
    } else {
        $codinventario = ZMDCODBARRAS::formatCodigoInventario($codinventario);
        if (strlen(preg_replace('/\D/', '', $codinventario)) >= 5) {
            $codloc = substr(preg_replace('/\D/', '', $codinventario), 2, 3);
        }
    }
}

if ($codloc !== '') {
    $localCheck = LocaisEstoque::validar($codloc);
    if ($localCheck['valid']) {
        $codloc = $localCheck['codloc'];
    } elseif (isset($_GET['aplicar']) || (isset($_GET['CODIGOBARRAS']) && trim((string) $_GET['CODIGOBARRAS']) !== '')) {
        flash_set('danger', $localCheck['error']);
        redirect_to('inventario.php?' . http_build_query(ZMDCODBARRAS::inventarioQueryParams(
            $codloc,
            ZMDCODBARRAS::formatCodigoInventario($codinventario),
            $quantidade
        )));
    }
}

$nomeLocal = LocaisEstoque::nome($codloc);
$locaisEstoqueJson = json_encode(LocaisEstoque::todos(), JSON_UNESCAPED_UNICODE);

$redirectParams = function () use (&$codloc, &$codinventario, &$quantidade) {
    return ZMDCODBARRAS::inventarioQueryParams($codloc, $codinventario, $quantidade);
};

if ($codinventario !== '') {
    $mostrarTabela = true;
    $modoLeitura = true;

    $barcodeInformado = isset($_GET['CODIGOBARRAS']) && trim($_GET['CODIGOBARRAS']) !== '';

    if ($barcodeInformado) {
        $codigobarras = preg_replace('/\D/', '', $_GET['CODIGOBARRAS']);
        $codloc = (string) ($_GET['CODLOC'] ?? $codloc);
        $quantidade = (string) ($_GET['QUANTIDADE'] ?? $quantidade);

        $validacao = ZMDCODBARRAS::validarCodigoBarras($codigobarras);

        if (!$validacao['valid']) {
            flash_set('danger', implode(' ', $validacao['errors']));
            redirect_to('inventario.php?' . http_build_query($redirectParams()));
        }

        $zmd = new ZMDCODBARRAS();
        $zmd->setCodigobarras($codigobarras);
        $zmd->setCodinventario($codinventario);
        $zmd->setQuantidade($quantidade);
        $zmd->setCodloc($codloc);

        if ($zmd->save()) {
            SessionManager::incrementSessionScans();
            $msg = 'Registrado: ' . $validacao['nome'] . ' · Qtd ' . $quantidade . ' ' . $validacao['und'];
            if (!empty($validacao['lote'])) {
                $msg .= ' · Lote ' . $validacao['lote'];
            }
            if (!empty($validacao['warnings'])) {
                $msg .= ' (' . implode(' ', $validacao['warnings']) . ')';
            }
            flash_set('success', $msg);
        } else {
            flash_set('danger', 'Não foi possível salvar o registro. Tente novamente.');
        }

        redirect_to('inventario.php?' . http_build_query($redirectParams()));
    }

    // Aplicar / trocar inventário (sem código de barras)
    if (isset($_GET['aplicar'])) {
        SessionManager::resetSessionScans();
        SessionManager::setLastInventario($codloc, $codinventario, $quantidade);
        flash_set('info', 'Inventário ' . $codinventario . ' ativo. Escaneie o código de barras.');
        redirect_to('inventario.php?' . http_build_query($redirectParams()));
    }

    SessionManager::setLastInventario($codloc, $codinventario, $quantidade);
    $registros = ZMDCODBARRAS::listarPorInventario($codinventario);
    $leiturasSessao = SessionManager::getSessionScans();
}

$pageTitle = 'Inventário RM — Leitura';
$bodyClass = 'page-inventory';
$showNavbar = true;

ob_start();
require __DIR__ . '/views/inventario.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
