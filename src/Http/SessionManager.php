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
    private const KEY_LAST_INVENTARIO = 'rm_last_inventario';
    private const KEY_RECENT_INVENTARIOS = 'rm_recent_inventarios';
    private const KEY_SESSION_SCANS = 'rm_session_scans';
    private const MAX_RECENT = 5;

    public static function setLastInventario(string $codloc, string $codinventario, string $quantidade = '1'): void
    {
        $_SESSION[self::KEY_LAST_INVENTARIO] = [
            'codloc'        => $codloc,
            'codinventario' => $codinventario,
            'quantidade'    => $quantidade,
            'atualizado'    => time(),
        ];

        self::addRecentInventario($codloc, $codinventario, $quantidade);
    }

    public static function addRecentInventario(string $codloc, string $codinventario, string $quantidade = '1'): void
    {
        if ($codinventario === '') {
            return;
        }

        $recent = self::getRecentInventarios();
        $key = $codinventario . '|' . $codloc;
        $filtered = array_filter($recent, function ($item) use ($key) {
            return ($item['codinventario'] . '|' . $item['codloc']) !== $key;
        });

        array_unshift($filtered, [
            'codloc'        => $codloc,
            'codinventario' => $codinventario,
            'quantidade'    => $quantidade,
            'atualizado'    => time(),
        ]);

        $_SESSION[self::KEY_RECENT_INVENTARIOS] = array_slice(array_values($filtered), 0, self::MAX_RECENT);
    }

    /**
     * @return array<int, array{codloc: string, codinventario: string, quantidade: string, atualizado: int}>
     */
    public static function getRecentInventarios(): array
    {
        return $_SESSION[self::KEY_RECENT_INVENTARIOS] ?? [];
    }

    public static function incrementSessionScans(): void
    {
        $_SESSION[self::KEY_SESSION_SCANS] = self::getSessionScans() + 1;
    }

    public static function getSessionScans(): int
    {
        return (int) ($_SESSION[self::KEY_SESSION_SCANS] ?? 0);
    }

    public static function resetSessionScans(): void
    {
        $_SESSION[self::KEY_SESSION_SCANS] = 0;
    }

    /**
     * @return array{codloc: string, codinventario: string, quantidade: string, atualizado: int}|null
     */
    public static function getLastInventario(): ?array
    {
        return $_SESSION[self::KEY_LAST_INVENTARIO] ?? null;
    }

    public static function hasLastInventario(): bool
    {
        $last = self::getLastInventario();
        return $last !== null && ($last['codinventario'] ?? '') !== '';
    }

    public static function clearLastInventario(): void
    {
        unset($_SESSION[self::KEY_LAST_INVENTARIO]);
    }

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
