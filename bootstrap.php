<?php

define('APP_ROOT', dirname(__FILE__));
define('DS', DIRECTORY_SEPARATOR);

$appConfig = array_merge([
    'debug' => false,
    'app_name' => 'Inventário RM',
    'base_path' => '',
], file_exists(APP_ROOT . DS . 'config' . DS . 'app.php')
    ? include APP_ROOT . DS . 'config' . DS . 'app.php'
    : []);

if ($appConfig['debug']) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// O cookie de sessão é a única credencial depois do login: fora do alcance do
// JavaScript, preso ao próprio site e marcado como seguro quando há HTTPS.
if (session_status() === PHP_SESSION_NONE) {
    $emHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => rtrim((string) $appConfig['base_path'], '/') . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $emHttps,
    ]);

    session_start();
}

require APP_ROOT . DS . 'src' . DS . 'Support' . DS . 'AppException.php';
require APP_ROOT . DS . 'src' . DS . 'Helpers' . DS . 'functions.php';
require APP_ROOT . DS . 'src' . DS . 'Config' . DS . 'EnvironmentManager.php';
require APP_ROOT . DS . 'src' . DS . 'Http' . DS . 'SessionManager.php';
require APP_ROOT . DS . 'src' . DS . 'Database' . DS . 'DatabaseException.php';
require APP_ROOT . DS . 'src' . DS . 'Database' . DS . 'Connection.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'LocaisEstoque.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'InventarioRM.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'RmAuth.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'PerfisRM.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'Permissoes.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'ZMDCODBARRAS.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'ContextoInventario.php';

// As classes de PDF arrastam o FPDF inteiro junto. Carregar aqui custava esse
// parse em toda requisição — inclusive em cada bipe. Quem exporta chama
// carregar_pdf() na hora.
if (!function_exists('carregar_pdf')) {
    function carregar_pdf(string $classe): void
    {
        require_once APP_ROOT . DS . 'src' . DS . 'Domain' . DS . $classe . '.php';
    }
}

set_exception_handler('app_tratar_excecao');

EnvironmentManager::boot(APP_ROOT . DS . 'config' . DS . 'environments.php');
