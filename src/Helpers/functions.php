<?php

/**
 * Escapa saída HTML preservando o conteúdo exibido para dados normais.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Normaliza texto vindo do banco para UTF-8.
 *
 * A conversão é condicional de propósito. O driver sqlsrv devolve Latin-1 ou
 * UTF-8 conforme o sistema e a configuração — no Windows e no Linux o padrão
 * difere. Convertendo sempre, um texto que já veio em UTF-8 é codificado duas
 * vezes e "LÍNGUA" chega na tela como "LÃNGUA": os bytes C3 8D do Í passam a
 * ser lidos como "Ã" mais um caractere de controle invisível.
 *
 * Texto ASCII puro já é UTF-8 válido e passa intacto.
 */
function encode_db_value($value)
{
    if ($value === null) {
        return '';
    }

    $texto = (string) $value;

    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_check_encoding') && mb_check_encoding($texto, 'UTF-8')) {
        return $texto;
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
    }

    return utf8_encode($texto);
}

/**
 * Formata quantidade no padrão pt-BR sem zeros à direita: 12 · 1,5 · 1.250.
 */
function formatar_quantidade(float $quantidade): string
{
    // number_format sempre emite 3 casas, então sempre há vírgula para aparar.
    $formatado = number_format($quantidade, 3, ',', '.');

    return rtrim(rtrim($formatado, '0'), ',');
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

    $ignored = ['www-data', 'apache', 'nginx', 'nobody', 'daemon'];

    foreach ($candidates as $candidate) {
        if (!empty($candidate)) {
            $name = (string) $candidate;
            if (strpos($name, '\\') !== false) {
                $parts = explode('\\', $name);
                $name = end($parts);
            }
            if (!in_array(strtolower($name), $ignored, true)) {
                return $name;
            }
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
