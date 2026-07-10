<?php

/**
 * Autenticação de usuários TOTVS RM via tabela GUSUARIO.
 */
class RmAuth
{
    /**
     * @return array{success: bool, message: string, codusuario: string, nome: string}
     */
    public static function authenticate(string $envKey, string $codusuario, string $senha): array
    {
        $codusuario = trim($codusuario);
        $fail = [
            'success'    => false,
            'message'    => 'Usuário ou senha inválidos.',
            'codusuario' => $codusuario,
            'nome'       => '',
        ];

        if ($codusuario === '' || $senha === '') {
            $fail['message'] = 'Informe usuário e senha do RM.';
            return $fail;
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
        $conn = @sqlsrv_connect($env['host'], EnvironmentManager::buildConnectionInfo($env));

        if ($conn === false) {
            return [
                'success'    => false,
                'message'    => 'Não foi possível conectar ao banco para autenticar.',
                'codusuario' => $codusuario,
                'nome'       => '',
            ];
        }

        // Busca case-insensitive; SENHA pode ser longa (bcrypt PHC)
        $sql = "SELECT TOP 1
                    RTRIM(CODUSUARIO) AS CODUSUARIO,
                    NOME,
                    CAST(SENHA AS VARCHAR(1000)) AS SENHA,
                    STATUS
                FROM GUSUARIO
                WHERE UPPER(RTRIM(CODUSUARIO)) = UPPER(?)";
        $stmt = sqlsrv_query($conn, $sql, [$codusuario]);

        if ($stmt === false) {
            $errors = sqlsrv_errors();
            sqlsrv_close($conn);
            $detail = is_array($errors) && isset($errors[0]['message']) ? $errors[0]['message'] : '';
            return [
                'success'    => false,
                'message'    => 'Falha ao consultar GUSUARIO. ' . $detail,
                'codusuario' => $codusuario,
                'nome'       => '',
            ];
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);

        if (!$row) {
            return [
                'success'    => false,
                'message'    => 'Usuário não encontrado no RM (GUSUARIO).',
                'codusuario' => $codusuario,
                'nome'       => '',
            ];
        }

        $status = strtoupper(trim((string) ($row['STATUS'] ?? '')));
        // Só bloqueia status claramente inativos
        if (in_array($status, ['I', 'B', 'N', 'INATIVO'], true)) {
            return [
                'success'    => false,
                'message'    => 'Usuário RM inativo ou bloqueado.',
                'codusuario' => $codusuario,
                'nome'       => '',
            ];
        }

        $hash = (string) ($row['SENHA'] ?? '');
        if (!self::verifyPassword($senha, $hash)) {
            $formato = self::detectHashFormat($hash);
            return [
                'success'    => false,
                'message'    => 'Senha inválida para o usuário RM. (formato no banco: ' . $formato . ')',
                'codusuario' => $codusuario,
                'nome'       => '',
            ];
        }

        $nome = encode_db_value($row['NOME'] ?? $codusuario);

        return [
            'success'    => true,
            'message'    => 'Autenticado com sucesso.',
            'codusuario' => encode_db_value($row['CODUSUARIO'] ?? $codusuario),
            'nome'       => $nome !== '' ? $nome : $codusuario,
        ];
    }

    public static function detectHashFormat(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return 'vazio';
        }
        if (stripos($stored, '#HA=Bcrypt#') !== false || preg_match('/\$2[ayb]?\$/', $stored)) {
            return 'bcrypt';
        }
        if (strlen($stored) <= 16) {
            return 'legado(' . strlen($stored) . ' chars)';
        }
        return 'desconhecido(' . strlen($stored) . ' chars)';
    }

    public static function verifyPassword(string $plain, string $stored): bool
    {
        $stored = trim($stored);
        if ($stored === '') {
            return false;
        }

        // Formato PHC do RM: ...#HA=Bcrypt#...#H=$2a$10$...
        if (preg_match('/#H=(\$2[ayb]?\$[^\s#]+)/', $stored, $m)) {
            if (self::verifyBcrypt($plain, $m[1])) {
                return true;
            }
        }

        // Qualquer bcrypt embutido na string
        if (preg_match('/(\$2[ayb]?\$\d{2}\$[A-Za-z0-9\.\/]+)/', $stored, $m)) {
            if (self::verifyBcrypt($plain, $m[1])) {
                return true;
            }
        }

        // Hash bcrypt puro
        if (preg_match('/^\$2[ayb]?\$/', $stored)) {
            if (self::verifyBcrypt($plain, $stored)) {
                return true;
            }
        }

        // Algoritmos legados do RM
        foreach (self::legacyCandidates($plain) as $enc) {
            if (hash_equals($stored, $enc)) {
                return true;
            }
        }

        // Comparação binária sem trim (legado com espaços/nulos)
        foreach (self::legacyCandidates($plain) as $enc) {
            if ($stored === $enc || rtrim($stored) === rtrim($enc)) {
                return true;
            }
        }

        return hash_equals($stored, $plain)
            || hash_equals(strtoupper($stored), strtoupper($plain));
    }

    private static function verifyBcrypt(string $plain, string $hash): bool
    {
        $hash = trim($hash);
        if ($hash === '') {
            return false;
        }

        // PHP trata melhor $2y$; hashes do BCrypt.Net costumam vir como $2a$
        $variants = array_unique([
            $hash,
            preg_replace('/^\$2a\$/', '$2y$', $hash),
            preg_replace('/^\$2b\$/', '$2y$', $hash),
            preg_replace('/^\$2y\$/', '$2a$', $hash),
        ]);

        foreach ($variants as $candidate) {
            if (is_string($candidate) && password_verify($plain, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private static function legacyCandidates(string $password): array
    {
        $inputs = array_unique([
            $password,
            strtoupper($password),
            strtolower($password),
        ]);

        $out = [];
        foreach ($inputs as $p) {
            $out[] = self::encryptLegacyAdd($p);
            $out[] = self::encryptLegacyMul($p);
            $out[] = self::encryptLegacyAdd(substr($p, 0, 8));
            $out[] = self::encryptLegacyMul(substr($p, 0, 8));
        }

        return array_values(array_unique($out));
    }

    /** Legado: Ord + índice */
    public static function encryptLegacyAdd(string $password): string
    {
        $password = substr($password . str_repeat(' ', 8), 0, 8);
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= chr((ord($password[$i]) + ($i + 1)) % 256);
        }
        return $out;
    }

    /** Legado: (Ord * índice) mod 256 */
    public static function encryptLegacyMul(string $password): string
    {
        $password = substr($password . str_repeat(' ', 8), 0, 8);
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= chr((ord($password[$i]) * ($i + 1)) % 256);
        }
        return $out;
    }
}
