<?php

require __DIR__ . '/bootstrap.php';

// Modo inventário: se já conectado, vai direto para a leitura
if (SessionManager::isConnected() && !isset($_GET['config'])) {
    redirect_to('inventario.php');
}

$pageTitle = 'Inventário — Hospital 9 de Julho';
$bodyClass = 'page-login';

$environments = EnvironmentManager::all();
$selectedEnvironment = SessionManager::getEnvironment();

if ($selectedEnvironment === null && isset($_GET['ambiente']) && EnvironmentManager::exists($_GET['ambiente'])) {
    $selectedEnvironment = $_GET['ambiente'];
}

$defaultUsername = SessionManager::getUsername();
$lastTest = SessionManager::getLastConnectionTest();
$isConnected = SessionManager::isConnected();
$showNavbar = $isConnected;
$lastInventario = SessionManager::getLastInventario();
$recentInventarios = SessionManager::getRecentInventarios();

if ($isConnected && $recentInventarios !== []) {
    // Uma consulta para a lista toda. Uma por linha fazia a tela inicial abrir
    // cinco idas ao SQL Server só para escrever "N itens".
    $totais = ZMDCODBARRAS::contarPorInventarios(
        array_column($recentInventarios, 'codinventario')
    );

    foreach ($recentInventarios as $i => $item) {
        $recentInventarios[$i]['total'] = $totais[trim((string) ($item['codinventario'] ?? ''))] ?? 0;
    }
}

ob_start();
require __DIR__ . '/views/home.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
