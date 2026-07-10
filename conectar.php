<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}

$ambiente = $_POST['ambiente'] ?? '';
$usuario = trim($_POST['usuario'] ?? '');
$acao = $_POST['acao'] ?? 'conectar';

if (!EnvironmentManager::exists($ambiente)) {
    flash_set('danger', 'Ambiente inválido. Selecione Produção, Homologação ou Testes.');
    redirect_to('index.php');
}

if ($usuario === '') {
    flash_set('danger', 'Informe o nome do usuário para continuar.');
    redirect_to('index.php');
}

SessionManager::setUsername($usuario);
SessionManager::setEnvironment($ambiente);

$result = EnvironmentManager::testConnection($ambiente);
SessionManager::setLastConnectionTest($result);

if ($acao === 'testar') {
    if (!$result['success']) {
        SessionManager::setConnected(false);
    }
    flash_set($result['success'] ? 'success' : 'danger', $result['message']);
    redirect_to('index.php');
}

if (!$result['success']) {
    SessionManager::setConnected(false);
    flash_set('danger', $result['message']);
    redirect_to('index.php');
}

SessionManager::setConnected(true);
flash_set('success', 'Conectado com sucesso! Ambiente: ' . EnvironmentManager::get($ambiente)['label']);
redirect_to('inventario.php');
