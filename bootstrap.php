<?php

define('APP_ROOT', dirname(__FILE__));
define('DS', DIRECTORY_SEPARATOR);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

require APP_ROOT . DS . 'src' . DS . 'Helpers' . DS . 'functions.php';
require APP_ROOT . DS . 'src' . DS . 'Config' . DS . 'EnvironmentManager.php';
require APP_ROOT . DS . 'src' . DS . 'Http' . DS . 'SessionManager.php';
require APP_ROOT . DS . 'src' . DS . 'Database' . DS . 'Connection.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'LocaisEstoque.php';
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'ZMDCODBARRAS.php';

EnvironmentManager::boot(APP_ROOT . DS . 'config' . DS . 'environments.php');

// Compatibilidade se arquivos auxiliares não foram atualizados no servidor
if (!function_exists('base_path')) {
    function base_path(): string
    {
        global $appConfig;
        return rtrim((string) ($appConfig['base_path'] ?? ''), '/');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = base_path();
        $path = ltrim($path, '/');
        if ($path === '') {
            return $base === '' ? '/' : $base . '/';
        }
        return $base === '' ? $path : $base . '/' . $path;
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }
}
