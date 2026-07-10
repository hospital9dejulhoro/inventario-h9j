<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}

$ambiente = $_POST['ambiente'] ?? '';
$usuario = trim($_POST['usuario'] ?? '');
$senha = (string) ($_POST['senha'] ?? '');
$acao = $_POST['acao'] ?? 'conectar';

if (!EnvironmentManager::exists($ambiente)) {
    flash_set('danger', 'Ambiente inválido. Selecione Produção, Homologação ou Testes.');
    redirect_to('index.php');
}

if ($usuario === '') {
    flash_set('danger', 'Informe o usuário do RM (CODUSUARIO).');
    redirect_to('index.php');
}

if ($senha === '') {
    flash_set('danger', 'Informe a senha do RM.');
    redirect_to('index.php');
}

SessionManager::setUsername($usuario);
SessionManager::setEnvironment($ambiente);

$result = EnvironmentManager::testConnection($ambiente);
SessionManager::setLastConnectionTest($result);

if (!$result['success']) {
    SessionManager::setConnected(false);
    flash_set('danger', $result['message']);
    redirect_to('index.php');
}

$auth = RmAuth::authenticate($ambiente, $usuario, $senha);

if (!$auth['success']) {
    SessionManager::setConnected(false);
    flash_set('danger', $auth['message']);
    redirect_to('index.php');
}

SessionManager::setUsername($auth['codusuario']);
SessionManager::setDisplayName($auth['nome']);

if ($acao === 'testar') {
    SessionManager::setConnected(false);
    flash_set('success', 'Usuário e senha válidos no RM · ' . $result['message']);
    redirect_to('index.php');
}

SessionManager::setConnected(true);
flash_set(
    'success',
    'Conectado como ' . $auth['nome'] . ' (' . $auth['codusuario'] . ') · '
    . EnvironmentManager::get($ambiente)['label']
);
redirect_to('inventario.php');
