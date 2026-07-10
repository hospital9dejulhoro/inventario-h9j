<?php

/**
 * Autenticação TOTVS RM.
 *
 * Ordem:
 * 1) API oficial POST /api/connect/token (api_url / ws_url)
 * 2) SOAP wsDataServer (fallback)
 * 3) GUSUARIO (bcrypt / legado) — só quando a API não estiver disponível
 *
 * STATUS na GUSUARIO é SMALLINT: 1 = ativo.
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

        // 1) API REST oficial do RM Host
        $rest = self::authenticateViaRest($env, $codusuario, $senha);
        if ($rest['success']) {
            $perfil = self::loadUsuario($env, $codusuario);
            if ($perfil['found'] && !$perfil['ativo']) {
                return [
                    'success'    => false,
                    'message'    => 'Usuário RM inativo neste ambiente (STATUS <> 1).',
                    'codusuario' => $codusuario,
                    'nome'       => '',
                ];
            }
            return [
                'success'    => true,
                'message'    => 'Autenticado via RM API.',
                'codusuario' => $perfil['codusuario'] ?: $codusuario,
                'nome'       => ($perfil['nome'] !== '' ? $perfil['nome'] : $codusuario),
            ];
        }

        // Credencial rejeitada pela API — não usar fallback legado (algoritmo incerto)
        if (!empty($rest['rejected'])) {
            return [
                'success'    => false,
                'message'    => 'Senha inválida (rejeitada pela API do RM).',
                'codusuario' => $codusuario,
                'nome'       => '',
            ];
        }

        // 2) SOAP (versões antigas / sem REST)
        $soap = self::authenticateViaSoap($env, $codusuario, $senha);
        if ($soap['success']) {
            $perfil = self::loadUsuario($env, $codusuario);
            return [
                'success'    => true,
                'message'    => 'Autenticado via RM WebService.',
                'codusuario' => $perfil['codusuario'] ?: $codusuario,
                'nome'       => ($perfil['nome'] !== '' ? $perfil['nome'] : $codusuario),
            ];
        }

        // 3) Fallback local GUSUARIO (bcrypt / legado) se a API estiver indisponível
        $perfil = self::loadUsuario($env, $codusuario);
        if (!$perfil['found']) {
            $hint = (!$rest['tried'] && !$soap['tried'])
                ? ' Configure api_url do RM Host em environments.php (ex.: http://IP:8051).'
                : '';
            return [
                'success'    => false,
                'message'    => 'Usuário não encontrado no RM (GUSUARIO).' . $hint,
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
        $apiHint = $rest['tried']
            ? ''
            : ' Dica: configure api_url do RM no environments.php (ex.: http://172.20.0.21:8051).';

        return [
            'success'    => false,
            'message'    => 'Senha inválida para o usuário RM. (formato no banco: ' . $formato . ')' . $apiHint,
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
     * Valida usuário/senha via POST /api/connect/token (RM Host).
     *
     * @return array{tried: bool, success: bool, rejected: bool, message: string}
     */
    private static function authenticateViaRest(array $env, string $usuario, string $senha): array
    {
        $fail = ['tried' => false, 'success' => false, 'rejected' => false, 'message' => ''];

        if (!function_exists('curl_init')) {
            $fail['message'] = 'cURL indisponível';
            return $fail;
        }

        $bases = self::apiBases($env);
        if ($bases === []) {
            $fail['message'] = 'Sem api_url';
            return $fail;
        }

        $payload = json_encode([
            'grant_type' => 'password',
            'username'   => $usuario,
            'password'   => $senha,
        ], JSON_UNESCAPED_UNICODE);

        $lastErr = '';
        $reached = false;

        foreach ($bases as $base) {
            $url = rtrim($base, '/') . '/api/connect/token';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($body === false || $code === 0) {
                $lastErr = $err !== '' ? $err : 'sem resposta';
                continue;
            }

            $reached = true;

            if ($code >= 200 && $code < 300) {
                $json = json_decode((string) $body, true);
                if (is_array($json) && !empty($json['access_token'])) {
                    return [
                        'tried'    => true,
                        'success'  => true,
                        'rejected' => false,
                        'message'  => $url,
                    ];
                }
            }

            // Credencial rejeitada — não tentar outro host
            if ($code === 400 || $code === 401 || $code === 403) {
                return [
                    'tried'    => true,
                    'success'  => false,
                    'rejected' => true,
                    'message'  => "HTTP $code",
                ];
            }

            $lastErr = "HTTP $code";
        }

        return [
            'tried'    => $reached,
            'success'  => false,
            'rejected' => false,
            'message'  => $lastErr !== '' ? $lastErr : 'API falhou',
        ];
    }

    /**
     * @return string[]
     */
    private static function apiBases(array $env): array
    {
        $bases = [];
        foreach (['api_url', 'ws_url'] as $key) {
            if (empty($env[$key]) || !is_string($env[$key])) {
                continue;
            }
            $raw = trim($env[$key]);
            // Aceita base (http://host:8051) ou WSDL completo
            if (preg_match('#^https?://[^/]+#i', $raw, $m)) {
                $bases[] = $m[0];
            }
        }

        // Descoberta padrão na rede do hospital (RM Host)
        $bases[] = 'http://172.20.0.21:8051';

        return array_values(array_unique(array_filter($bases)));
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
        foreach (self::apiBases($env) as $base) {
            $candidates[] = rtrim($base, '/') . '/wsDataServer/MEX?wsdl';
            $candidates[] = rtrim($base, '/') . '/WSDataServer/MEX?wsdl';
        }
        if (!empty($env['ws_url']) && is_string($env['ws_url'])) {
            array_unshift($candidates, $env['ws_url']);
        }

        $candidates = array_values(array_unique(array_filter($candidates)));
        if ($candidates === []) {
            return ['tried' => false, 'success' => false, 'message' => 'Sem URL SOAP'];
        }

        foreach ($candidates as $wsdl) {
            try {
                $client = @new SoapClient($wsdl, [
                    'login'              => $usuario,
                    'password'           => $senha,
                    'authentication'     => SOAP_AUTHENTICATION_BASIC,
                    'connection_timeout' => 5,
                    'exceptions'         => true,
                    'trace'              => false,
                    'cache_wsdl'         => WSDL_CACHE_MEMORY,
                ]);

                if ($client instanceof SoapClient) {
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
                            // método inexistente ou senha inválida — tenta próximo
                        }
                    }
                    // WSDL aberto sem método de autenticação não prova a senha
                }
            } catch (Throwable $e) {
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

        if (preg_match('/#H=(\$2[ayb]?\$[^\s#]+)/', $storedTrim, $m) && self::verifyBcrypt($plain, $m[1])) {
            return true;
        }
        if (preg_match('/(\$2[ayb]?\$\d{2}\$[A-Za-z0-9\.\/]+)/', $storedTrim, $m) && self::verifyBcrypt($plain, $m[1])) {
            return true;
        }
        if (preg_match('/^\$2[ayb]?\$/', $storedTrim) && self::verifyBcrypt($plain, $storedTrim)) {
            return true;
        }

        foreach (self::legacyCandidates($plain) as $enc) {
            if ($stored === $enc || $storedTrim === $enc || rtrim($stored) === rtrim($enc)) {
                return true;
            }
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
