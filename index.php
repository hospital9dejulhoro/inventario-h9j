<?php

require __DIR__ . '/bootstrap.php';

// Modo inventário: se já conectado, vai direto para a leitura
if (SessionManager::isConnected() && !isset($_GET['config'])) {
    redirect_to('inventario.php');
}

$pageTitle = 'Inventário RM — Seleção de Ambiente';
$bodyClass = 'page-home';
$showNavbar = true;

$environments = EnvironmentManager::all();
$selectedEnvironment = SessionManager::getEnvironment();

if ($selectedEnvironment === null && isset($_GET['ambiente']) && EnvironmentManager::exists($_GET['ambiente'])) {
    $selectedEnvironment = $_GET['ambiente'];
}
$defaultUsername = SessionManager::getUsername() ?: detect_os_username();
$lastTest = SessionManager::getLastConnectionTest();
$isConnected = SessionManager::isConnected();
$lastInventario = SessionManager::getLastInventario();
$recentInventarios = SessionManager::getRecentInventarios();

if ($isConnected) {
    foreach ($recentInventarios as $i => $item) {
        $recentInventarios[$i]['total'] = ZMDCODBARRAS::contarPorInventario($item['codinventario'] ?? '');
    }
}

ob_start();
require __DIR__ . '/views/home.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
