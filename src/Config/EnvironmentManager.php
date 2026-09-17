<?php

/**
 * Gerencia ambientes de conexão TOTVS RM (Produção, Homologação, Testes).
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
            throw new AppException(
                'A aplicação ainda não foi configurada: falta o arquivo config/environments.php. '
                . 'Copie config/environments.example.php para config/environments.php e preencha os dados de conexão.',
                'arquivo de ambientes ausente: ' . $configPath
            );
        }

        $config = include $configPath;

        if (!is_array($config) || empty($config)) {
            throw new AppException(
                'O arquivo config/environments.php não devolveu nenhum ambiente. Confira o conteúdo dele.',
                'configuracao de ambientes invalida em ' . $configPath
            );
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
            // Explícito porque a conexão é reaproveitada na requisição inteira:
            // permite abrir um statement novo com o result set anterior ainda vivo.
            'MultipleActiveResultSets' => true,
            // Sem teto, um host fora do ar deixa a tela de login pendurada até o
            // PHP-FPM derrubar a requisição, sem nenhuma mensagem.
            'LoginTimeout' => self::loginTimeout($env),
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
     * Segundos que uma consulta pode levar antes de o SQL Server abortá-la.
     *
     * Ajustável por ambiente (`query_timeout`). O objetivo não é deixar a
     * consulta rodar para sempre — é que ela estoure ANTES do PHP-FPM, para
     * que o erro chegue na tela com uma instrução útil em vez de um 504.
     */
    public static function queryTimeout(): int
    {
        $env = self::getCurrent() ?? [];
        $segundos = (int) ($env['query_timeout'] ?? 120);

        return max(10, min(600, $segundos));
    }

    private static function loginTimeout(array $env): int
    {
        $segundos = (int) ($env['login_timeout'] ?? 10);

        return max(3, min(60, $segundos));
    }

    /**
     * Hosts alternativos do RM Host para autenticação, configurados por
     * ambiente (`api_fallbacks`).
     *
     * Vazio por padrão de propósito: com uma lista fixa no código, um Host de
     * outro ambiente podia validar a senha de quem escolheu Produção.
     *
     * @return string[]
     */
    public static function apiFallbacks(array $env): array
    {
        $lista = $env['api_fallbacks'] ?? [];

        if (!is_array($lista)) {
            return [];
        }

        $out = [];
        foreach ($lista as $base) {
            if (is_string($base) && preg_match('#^https?://[^/]+#i', trim($base), $m)) {
                $out[] = $m[0];
            }
        }

        return array_values(array_unique($out));
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
