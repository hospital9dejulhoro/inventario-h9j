<?php

/**
 * Gerencia ambientes de conexão TOTVS RM (Produção, Homologação, futuros).
 */
class EnvironmentManager
{
    /** @var array<string, array> */
    private static $environments = [];

    /** @var string|null */
    private static $currentKey = null;

    public static function boot(string $configPath): void
    {
        if (!file_exists($configPath)) {
            throw new RuntimeException('Arquivo de ambientes não encontrado: ' . $configPath);
        }

        $config = include $configPath;

        if (!is_array($config) || empty($config)) {
            throw new RuntimeException('Configuração de ambientes inválida.');
        }

        self::$environments = $config;
    }

    /**
     * @return array<string, array>
     */
    public static function all(): array
    {
        return self::$environments;
    }

    public static function exists(string $key): bool
    {
        return isset(self::$environments[$key]);
    }

    /**
     * @return array{label: string, host: string, database: string, usuario: string, senha: string}
     */
    public static function get(string $key): array
    {
        if (!self::exists($key)) {
            throw new InvalidArgumentException('Ambiente não configurado: ' . $key);
        }

        return self::$environments[$key];
    }

    public static function setCurrent(?string $key): void
    {
        if ($key !== null && !self::exists($key)) {
            throw new InvalidArgumentException('Ambiente não configurado: ' . $key);
        }

        self::$currentKey = $key;
    }

    public static function getCurrentKey(): ?string
    {
        return self::$currentKey;
    }

    /**
     * @return array|null
     */
    public static function getCurrent(): ?array
    {
        if (self::$currentKey === null) {
            return null;
        }

        return self::get(self::$currentKey);
    }

    /**
     * Opções de conexão sqlsrv (ODBC Driver 18+ exige configuração SSL).
     *
     * @param array $env
     * @return array<string, mixed>
     */
    public static function buildConnectionInfo(array $env): array
    {
        $options = [
            'Database' => $env['database'],
            'UID'      => $env['usuario'],
            'PWD'      => $env['senha'],
        ];

        // ODBC 18: certificado autoassinado em ambientes internos RM/TOTVS
        $trustCert = $env['trust_server_certificate'] ?? true;
        if ($trustCert) {
            $options['TrustServerCertificate'] = true;
        }

        if (isset($env['encrypt'])) {
            $options['Encrypt'] = (bool) $env['encrypt'];
        }

        return $options;
    }

    /**
     * Testa conectividade com o ambiente informado sem alterar a sessão.
     *
     * @return array{success: bool, message: string}
     */
    public static function testConnection(string $key): array
    {
        $env = self::get($key);

        if (!function_exists('sqlsrv_connect')) {
            return [
                'success' => false,
                'message' => 'Extensão PHP sqlsrv não está instalada ou habilitada.',
            ];
        }

        $connectionInfo = self::buildConnectionInfo($env);

        $connection = @sqlsrv_connect($env['host'], $connectionInfo);

        if ($connection === false) {
            $errors = sqlsrv_errors();
            $detail = '';

            if (is_array($errors) && isset($errors[0]['message'])) {
                $detail = $errors[0]['message'];
            }

            return [
                'success' => false,
                'message' => 'Não foi possível conectar ao ambiente ' . $env['label'] . '. ' . $detail,
            ];
        }

        sqlsrv_close($connection);

        return [
            'success' => true,
            'message' => 'Conexão com ' . $env['label'] . ' (' . $env['host'] . ') estabelecida com sucesso.',
        ];
    }
}
