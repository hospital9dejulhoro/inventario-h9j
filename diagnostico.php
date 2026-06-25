<?php
/**
 * Diagnóstico rápido — acesse /diagnostico.php e remova após corrigir.
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo '<pre>';
echo "PHP: " . PHP_VERSION . "\n\n";

$files = [
    'bootstrap.php',
    'src/Helpers/functions.php',
    'src/Config/EnvironmentManager.php',
    'views/layout.php',
    'views/home.php',
    'assets/css/app.css',
];

foreach ($files as $f) {
    echo (file_exists(__DIR__ . '/' . $f) ? '[OK] ' : '[FALTA] ') . $f . "\n";
}

echo "\nCarregando bootstrap...\n";
require __DIR__ . '/bootstrap.php';

echo "base_path: " . (function_exists('base_path') ? 'OK' : 'AUSENTE') . "\n";
echo "buildConnectionInfo: " . (method_exists('EnvironmentManager', 'buildConnectionInfo') ? 'OK' : 'AUSENTE') . "\n";
echo "Ambientes: " . count(EnvironmentManager::all()) . "\n";
echo "\nBootstrap OK.\n";
echo '</pre>';
