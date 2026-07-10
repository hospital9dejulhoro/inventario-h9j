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

        $sql = "SELECT TOP 1 CODUSUARIO, NOME, SENHA, STATUS
                FROM GUSUARIO
                WHERE CODUSUARIO = ?";
        $stmt = sqlsrv_query($conn, $sql, [$codusuario]);

        if ($stmt === false) {
            sqlsrv_close($conn);
            return [
                'success'    => false,
                'message'    => 'Falha ao consultar GUSUARIO.',
                'codusuario' => $codusuario,
                'nome'       => '',
            ];
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);

        if (!$row) {
            return $fail;
        }

        $status = strtoupper(trim((string) ($row['STATUS'] ?? '')));
        if ($status !== '' && $status !== 'A' && $status !== '1') {
            return [
                'success'    => false,
                'message'    => 'Usuário RM inativo ou bloqueado.',
                'codusuario' => $codusuario,
                'nome'       => '',
            ];
        }

        $hash = (string) ($row['SENHA'] ?? '');
        if (!self::verifyPassword($senha, $hash)) {
            return $fail;
        }

        $nome = encode_db_value($row['NOME'] ?? $codusuario);

        return [
            'success'    => true,
            'message'    => 'Autenticado com sucesso.',
            'codusuario' => encode_db_value($row['CODUSUARIO'] ?? $codusuario),
            'nome'       => $nome !== '' ? $nome : $codusuario,
        ];
    }

    public static function verifyPassword(string $plain, string $stored): bool
    {
        $stored = trim($stored);
        if ($stored === '') {
            return false;
        }

        // Formato PHC do RM moderno: ...#HA=Bcrypt#...#H=$2a$10$...
        if (preg_match('/#H=(\$2[ayb]?\$\d{2}\$[A-Za-z0-9\.\/]{53})/', $stored, $m)) {
            return password_verify($plain, $m[1]);
        }

        // Hash bcrypt puro
        if (preg_match('/^\$2[ayb]?\$\d{2}\$[A-Za-z0-9\.\/]{53}$/', $stored)) {
            return password_verify($plain, $stored);
        }

        // Legado RM (campo curto criptografado)
        $legacyVariants = [
            self::encryptLegacy($plain),
            self::encryptLegacy(strtoupper($plain)),
            self::encryptLegacy(strtolower($plain)),
        ];

        foreach ($legacyVariants as $enc) {
            if (hash_equals($stored, $enc)) {
                return true;
            }
        }

        // Comparação direta (ambientes raros / senha em claro)
        return hash_equals($stored, $plain);
    }

    /**
     * Algoritmo clássico de criptografia de senha do RM (até 8 caracteres).
     */
    public static function encryptLegacy(string $password): string
    {
        $password = substr($password . str_repeat(' ', 8), 0, 8);
        $out = '';

        for ($i = 0; $i < 8; $i++) {
            $asc = ord($password[$i]);
            $out .= chr($asc + ($i + 1));
        }

        return $out;
    }
}
