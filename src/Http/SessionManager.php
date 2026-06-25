<?php

/**
 * Gerencia estado da sessão: ambiente, conexão e usuário.
 */
class SessionManager
{
    private const KEY_ENVIRONMENT = 'rm_environment';
    private const KEY_CONNECTED = 'rm_connected';
    private const KEY_USERNAME = 'rm_username';
    private const KEY_LAST_TEST = 'rm_last_connection_test';

    public static function setEnvironment(string $key): void
    {
        $_SESSION[self::KEY_ENVIRONMENT] = $key;
        EnvironmentManager::setCurrent($key);
    }

    public static function getEnvironment(): ?string
    {
        $key = $_SESSION[self::KEY_ENVIRONMENT] ?? null;

        if ($key !== null && EnvironmentManager::exists($key)) {
            EnvironmentManager::setCurrent($key);
        }

        return $key;
    }

    public static function setConnected(bool $connected): void
    {
        $_SESSION[self::KEY_CONNECTED] = $connected;
    }

    public static function isConnected(): bool
    {
        return !empty($_SESSION[self::KEY_CONNECTED]) && self::getEnvironment() !== null;
    }

    public static function setUsername(string $username): void
    {
        $_SESSION[self::KEY_USERNAME] = trim($username);
    }

    public static function getUsername(): string
    {
        return (string) ($_SESSION[self::KEY_USERNAME] ?? '');
    }

    public static function setLastConnectionTest(array $result): void
    {
        $_SESSION[self::KEY_LAST_TEST] = $result;
    }

    public static function getLastConnectionTest(): ?array
    {
        return $_SESSION[self::KEY_LAST_TEST] ?? null;
    }

    public static function clear(): void
    {
        unset(
            $_SESSION[self::KEY_ENVIRONMENT],
            $_SESSION[self::KEY_CONNECTED],
            $_SESSION[self::KEY_USERNAME],
            $_SESSION[self::KEY_LAST_TEST]
        );

        EnvironmentManager::setCurrent(null);
    }

    public static function requireConnection(): void
    {
        if (!self::isConnected()) {
            flash_set('warning', 'Selecione um ambiente e conecte-se antes de acessar o inventário.');
            header('Location: ' . url('index.php'));
            exit;
        }
    }
}
