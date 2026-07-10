<?php

/**
 * Autenticação TOTVS RM via GUSUARIO + SOAP (mesma validação do RM).
 *
 * STATUS na GUSUARIO é SMALLINT: 1 = ativo, 0 = inativo.
 * SENHA legada tem 8 chars (algoritmo proprietário); senha moderna usa bcrypt PHC.
 * Quando possível, valida via WebService do RM (ws_url / porta 8051).
 */
class RmAuth
{
    /**
     * @return array{success: bool, message: string, codusuario: string, nome: string}
     */
    public static function authenticate(string $envKey, string $codusuario, string $senha): array
    {
        $codusuario = trim($codusuario);
        $empty = [
            'success'    => false,
            'message'    => 'Usuário ou senha inválidos.',
            'codusuario' => $codusuario,
            'nome'       => '',
        ];

        if ($codusuario === '' || $senha === '') {
            $empty['message'] = 'Informe usuário e senha do RM.';
            return $empty;
        }

        if (!function_exists('sqlsrv_connect')) {
            return [
                'success'    => false,
                'message'    => 'Extensão PHP sqlsrv não está instalada.',
                'codusuario' => $codusuario,
                'nome'       => '',
            ];
        }

        $env = EnvironmentManager::get($envKey);

        // 1) Preferir autenticação oficial do RM via SOAP (usa a mesma regra do TOTVS)
        $soap = self::authenticateViaSoap($env, $codusuario, $senha);
        if ($soap['tried'] && $soap['success']) {
            $perfil = self::loadUsuario($env, $codusuario);
            return [
                'success'    => true,
                'message'    => 'Autenticado via RM WebService.',
                'codusuario' => $perfil['codusuario'] ?: $codusuario,
                'nome'       => $perfil['nome'] ?: $codusuario,
            ];
        }

        // 2) Fallback: validação direta em GUSUARIO
        $perfil = self::loadUsuario($env, $codusuario);
        if (!$perfil['found']) {
            return [
                'success'    => false,
                'message'    => 'Usuário não encontrado no RM (GUSUARIO).',
                'codusuario' => $codusuario,
                'nome'       => '',
            ];
        }

        if (!$perfil['ativo']) {
            return [
                'success'    => false,
                'message'    => 'Usuário RM inativo (STATUS <> 1).',
                'codusuario' => $codusuario,
                'nome'       => '',
            ];
        }

        $hash = $perfil['senha'];
        $interno = $perfil['interno1'];

        if (self::verifyPassword($senha, $hash) || self::verifyPassword($senha, $interno)) {
            return [
                'success'    => true,
                'message'    => 'Autenticado com sucesso.',
                'codusuario' => $perfil['codusuario'],
                'nome'       => $perfil['nome'] !== '' ? $perfil['nome'] : $perfil['codusuario'],
            ];
        }

        $formato = self::detectHashFormat($hash);
        $soapHint = $soap['tried']
            ? ' SOAP também falhou.'
            : ' Dica: configure ws_url do RM no environments.php para validar pela API oficial.';

        return [
            'success'    => false,
            'message'    => 'Senha inválida para o usuário RM. (formato no banco: ' . $formato . ')' . $soapHint,
            'codusuario' => $codusuario,
            'nome'       => '',
        ];
    }

    /**
     * @return array{found: bool, ativo: bool, codusuario: string, nome: string, senha: string, interno1: string}
     */
    private static function loadUsuario(array $env, string $codusuario): array
    {
        $empty = [
            'found'      => false,
            'ativo'      => false,
            'codusuario' => $codusuario,
            'nome'       => '',
            'senha'      => '',
            'interno1'   => '',
        ];

        $conn = @sqlsrv_connect($env['host'], EnvironmentManager::buildConnectionInfo($env));
        if ($conn === false) {
            return $empty;
        }

        $sql = "SELECT TOP 1
                    RTRIM(CODUSUARIO) AS CODUSUARIO,
                    NOME,
                    STATUS,
                    CAST(SENHA AS VARCHAR(1000)) AS SENHA,
                    CAST(INTERNO1 AS VARCHAR(1000)) AS INTERNO1
                FROM GUSUARIO
                WHERE UPPER(RTRIM(CODUSUARIO)) = UPPER(?)";
        $stmt = sqlsrv_query($conn, $sql, [$codusuario]);
        if ($stmt === false) {
            sqlsrv_close($conn);
            return $empty;
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);

        if (!$row) {
            return $empty;
        }

        // STATUS SMALLINT: 1 = ativo (padrão Totvs / script mestre)
        $status = (int) ($row['STATUS'] ?? 0);

        return [
            'found'      => true,
            'ativo'      => ($status === 1),
            'codusuario' => encode_db_value($row['CODUSUARIO'] ?? $codusuario),
            'nome'       => encode_db_value($row['NOME'] ?? ''),
            'senha'      => (string) ($row['SENHA'] ?? ''),
            'interno1'   => (string) ($row['INTERNO1'] ?? ''),
        ];
    }

    /**
     * @return array{tried: bool, success: bool, message: string}
     */
    private static function authenticateViaSoap(array $env, string $usuario, string $senha): array
    {
        if (!class_exists('SoapClient')) {
            return ['tried' => false, 'success' => false, 'message' => 'SOAP não disponível'];
        }

        $candidates = [];
        if (!empty($env['ws_url'])) {
            $candidates[] = $env['ws_url'];
        }
        $host = $env['host'] ?? '';
        if ($host !== '') {
            $candidates[] = "http://{$host}:8051/wsDataServer/MEX?wsdl";
            $candidates[] = "http://{$host}:8051/WSDataServer/MEX?wsdl";
        }

        $candidates = array_values(array_unique(array_filter($candidates)));
        if ($candidates === []) {
            return ['tried' => false, 'success' => false, 'message' => 'Sem URL SOAP'];
        }

        foreach ($candidates as $wsdl) {
            try {
                $client = @new SoapClient($wsdl, [
                    'login'          => $usuario,
                    'password'       => $senha,
                    'authentication' => SOAP_AUTHENTICATION_BASIC,
                    'connection_timeout' => 5,
                    'exceptions'     => true,
                    'trace'          => false,
                    'cache_wsdl'     => WSDL_CACHE_MEMORY,
                ]);

                // Se o WSDL abriu com basic auth, o IIS/RM já validou usuário/senha
                if ($client instanceof SoapClient) {
                    // Tenta método explícito quando existir
                    foreach (['AutenticaAcessoAuth', 'AutenticaAcesso'] as $method) {
                        try {
                            if (!is_callable([$client, $method])) {
                                continue;
                            }
                            $client->__soapCall($method, [[
                                'codUsuario' => $usuario,
                                'senha'      => $senha,
                            ]]);
                            return ['tried' => true, 'success' => true, 'message' => $method];
                        } catch (Throwable $e) {
                            // continua; basic auth do WSDL já pode ter bastado
                        }
                    }
                    return ['tried' => true, 'success' => true, 'message' => 'SOAP basic auth OK'];
                }
            } catch (Throwable $e) {
                // tenta próxima URL
                continue;
            }
        }

        return ['tried' => true, 'success' => false, 'message' => 'SOAP falhou'];
    }

    public static function detectHashFormat(string $stored): string
    {
        $stored = rtrim($stored);
        if ($stored === '') {
            return 'vazio';
        }
        if (stripos($stored, '#HA=Bcrypt#') !== false || preg_match('/\$2[ayb]?\$/', $stored)) {
            return 'bcrypt';
        }
        $len = strlen($stored);
        if ($len <= 16) {
            return 'legado(' . $len . ' chars)';
        }
        return 'desconhecido(' . $len . ' chars)';
    }

    public static function verifyPassword(string $plain, string $stored): bool
    {
        if ($stored === null || $stored === '') {
            return false;
        }

        $storedTrim = rtrim($stored);

        // bcrypt PHC / puro
        if (preg_match('/#H=(\$2[ayb]?\$[^\s#]+)/', $storedTrim, $m) && self::verifyBcrypt($plain, $m[1])) {
            return true;
        }
        if (preg_match('/(\$2[ayb]?\$\d{2}\$[A-Za-z0-9\.\/]+)/', $storedTrim, $m) && self::verifyBcrypt($plain, $m[1])) {
            return true;
        }
        if (preg_match('/^\$2[ayb]?\$/', $storedTrim) && self::verifyBcrypt($plain, $storedTrim)) {
            return true;
        }

        // Legado: não usar trim no meio (pode ter espaços significativos)
        foreach (self::legacyCandidates($plain) as $enc) {
            if ($stored === $enc || $storedTrim === $enc || rtrim($stored) === rtrim($enc)) {
                return true;
            }
            // comparação binária segura
            if (strlen($stored) === strlen($enc) && hash_equals($stored, $enc)) {
                return true;
            }
        }

        return hash_equals($storedTrim, $plain);
    }

    private static function verifyBcrypt(string $plain, string $hash): bool
    {
        $hash = trim($hash);
        $variants = array_unique([
            $hash,
            preg_replace('/^\$2a\$/', '$2y$', $hash),
            preg_replace('/^\$2b\$/', '$2y$', $hash),
            preg_replace('/^\$2y\$/', '$2a$', $hash),
        ]);
        foreach ($variants as $candidate) {
            if (is_string($candidate) && $candidate !== '' && @password_verify($plain, $candidate)) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] */
    private static function legacyCandidates(string $password): array
    {
        $inputs = array_unique([$password, strtoupper($password), strtolower($password)]);
        $out = [];
        foreach ($inputs as $p) {
            $out[] = self::encryptLegacyAdd($p, ' ');
            $out[] = self::encryptLegacyAdd($p, "\0");
            $out[] = self::encryptLegacyMul($p, ' ');
            $out[] = self::encryptLegacyMul($p, "\0");
            $out[] = self::encryptLegacyXor($p);
            $out[] = self::encryptLegacyMod95($p);
        }
        return array_values(array_unique($out));
    }

    public static function encryptLegacyAdd(string $password, string $pad = ' '): string
    {
        $password = substr($password . str_repeat($pad, 8), 0, 8);
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= chr((ord($password[$i]) + $i + 1) & 0xFF);
        }
        return $out;
    }

    public static function encryptLegacyMul(string $password, string $pad = ' '): string
    {
        $password = substr($password . str_repeat($pad, 8), 0, 8);
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= chr((ord($password[$i]) * ($i + 1)) & 0xFF);
        }
        return $out;
    }

    public static function encryptLegacyXor(string $password): string
    {
        $password = substr($password . str_repeat(' ', 8), 0, 8);
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= chr(ord($password[$i]) ^ (($i + 1) * 7));
        }
        return $out;
    }

    public static function encryptLegacyMod95(string $password): string
    {
        $password = substr($password . str_repeat(' ', 8), 0, 8);
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= chr(((ord($password[$i]) * ($i + 1)) % 95) + 32);
        }
        return $out;
    }
}
