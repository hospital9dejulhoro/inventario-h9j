<?php

/**
 * Escapa saída HTML preservando o conteúdo exibido para dados normais.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Compatível com a codificação usada nas versões anteriores do sistema.
 */
function encode_db_value($value)
{
    if ($value === null) {
        return '';
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding((string) $value, 'UTF-8', 'ISO-8859-1');
    }

    return utf8_encode((string) $value);
}

/**
 * Obtém o nome do usuário do sistema operacional / servidor web.
 */
function detect_os_username(): string
{
    $candidates = [
        $_SERVER['AUTH_USER'] ?? null,
        $_SERVER['REMOTE_USER'] ?? null,
        $_SERVER['LOGON_USER'] ?? null,
        getenv('USERNAME'),
        getenv('USER'),
    ];

    foreach ($candidates as $candidate) {
        if (!empty($candidate)) {
            $name = (string) $candidate;
            if (strpos($name, '\\') !== false) {
                $parts = explode('\\', $name);
                $name = end($parts);
            }
            return $name;
        }
    }

    return '';
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function base_path(): string
{
    global $appConfig;
    return rtrim((string) ($appConfig['base_path'] ?? ''), '/');
}

function url(string $path = ''): string
{
    $base = base_path();
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base === '' ? $path : $base . '/' . $path;
}

function redirect_to(string $path): void
{
    header('Location: ' . url($path));
    exit;
}
