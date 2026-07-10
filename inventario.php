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
$qtdItensRm = 0;
$mostrarTabela = false;
$envAtual = EnvironmentManager::getCurrent();
$leiturasSessao = SessionManager::getSessionScans();
$recentInventarios = SessionManager::getRecentInventarios();
$statusInventarioRm = '';

$deveValidarAcao = isset($_GET['aplicar'])
    || (isset($_GET['CODIGOBARRAS']) && trim((string) $_GET['CODIGOBARRAS']) !== '');

$veioDaUrl = isset($_GET['CODINVENTARIO']) && trim((string) $_GET['CODINVENTARIO']) !== '';

// Retomar último inventário (só preenche; não entra em leitura sem Aplicar)
if ($codinventario === '' && SessionManager::hasLastInventario()) {
    $last = SessionManager::getLastInventario();
    $codloc = (string) ($last['codloc'] ?? '');
    $codinventario = (string) ($last['codinventario'] ?? '');
    $quantidade = (string) ($last['quantidade'] ?? '1');
    $retomadoDaSessao = true;
}

$redirectParams = function () use (&$codloc, &$codinventario, &$quantidade) {
    return ZMDCODBARRAS::inventarioQueryParams($codloc, $codinventario, $quantidade);
};

// Normaliza máscara AA.LLL.NNN e sincroniza CODLOC
$parsedInv = null;
if ($codinventario !== '') {
    $parsedInv = ZMDCODBARRAS::parseCodigoInventario($codinventario);
    if ($parsedInv['valid']) {
        $codinventario = $parsedInv['formatted'];
        $codloc = $parsedInv['codloc'];
    } elseif ($deveValidarAcao) {
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
        $parsedInv = ZMDCODBARRAS::parseCodigoInventario($codinventario);
    }
}

if ($codloc !== '') {
    $localCheck = LocaisEstoque::validar($codloc);
    if ($localCheck['valid']) {
        $codloc = $localCheck['codloc'];
    } elseif ($deveValidarAcao) {
        flash_set('danger', $localCheck['error']);
        redirect_to('inventario.php?' . http_build_query($redirectParams()));
    }
}

$mascaraOk = is_array($parsedInv) && !empty($parsedInv['valid']);
$rmOk = false;

if ($mascaraOk && $codloc !== '') {
    $rmCheck = InventarioRM::validarParaUso($codinventario, $codloc);
    if ($rmCheck['valid']) {
        $statusInventarioRm = $rmCheck['status'];
        // Sessão sozinha só pré-preenche; leitura exige URL/Aplicar/bipagem
        if ($deveValidarAcao || $veioDaUrl) {
            $rmOk = true;
        }
    } elseif ($deveValidarAcao) {
        flash_set('danger', $rmCheck['error']);
        redirect_to('inventario.php?' . http_build_query($redirectParams()));
    }
}

$nomeLocal = LocaisEstoque::nome($codloc);
$locaisEstoqueJson = json_encode(LocaisEstoque::todos(), JSON_UNESCAPED_UNICODE);
$inventariosAbertos = InventarioRM::listarAbertos();

if ($rmOk) {
    $mostrarTabela = true;
    $modoLeitura = true;

    $barcodeInformado = isset($_GET['CODIGOBARRAS']) && trim((string) $_GET['CODIGOBARRAS']) !== '';

    if ($barcodeInformado) {
        $codigobarras = preg_replace('/\D/', '', $_GET['CODIGOBARRAS']);
        $codloc = (string) ($_GET['CODLOC'] ?? $codloc);
        $quantidade = (string) ($_GET['QUANTIDADE'] ?? $quantidade);

        $validacao = ZMDCODBARRAS::validarCodigoBarras($codigobarras);

        if (!$validacao['valid']) {
            flash_set('danger', implode(' ', $validacao['errors']));
            redirect_to('inventario.php?' . http_build_query($redirectParams()));
        }

        $idprd = (int) ($validacao['idprd'] ?? InventarioRM::idprdDoBarcode($codigobarras));
        if (!InventarioRM::itemPertenceAoInventario($codinventario, $codloc, $idprd)) {
            $produtoLabel = $validacao['nome'] !== '' ? $validacao['nome'] : ('ID ' . $idprd);
            flash_set(
                'danger',
                "Produto {$produtoLabel} não faz parte do inventário {$codinventario} no local {$codloc}. Item não gravado."
            );
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

    if (isset($_GET['aplicar'])) {
        SessionManager::resetSessionScans();
        SessionManager::setLastInventario($codloc, $codinventario, $quantidade);
        $qtdItens = InventarioRM::contarItensInventario($codinventario, $codloc);
        flash_set(
            'info',
            'Inventário ' . $codinventario . ' ativo (' . $qtdItens . ' itens no RM). Escaneie o código de barras.'
        );
        redirect_to('inventario.php?' . http_build_query($redirectParams()));
    }

    SessionManager::setLastInventario($codloc, $codinventario, $quantidade);
    $registros = ZMDCODBARRAS::listarPorInventario($codinventario);
    $qtdItensRm = InventarioRM::contarItensInventario($codinventario, $codloc);
    $leiturasSessao = SessionManager::getSessionScans();
} elseif ($mascaraOk && $codinventario !== '' && !$deveValidarAcao && !$retomadoDaSessao) {
    // Código na URL sem ação: avisa se não existir no RM, sem entrar em modo leitura
    $rmCheck = InventarioRM::validarParaUso($codinventario, $codloc);
    if (!$rmCheck['valid']) {
        flash_set('warning', $rmCheck['error']);
    }
}

$pageTitle = 'Inventário RM — Leitura';
$bodyClass = 'page-inventory';
$showNavbar = true;

ob_start();
require __DIR__ . '/views/inventario.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
