<?php

/**
 * Perfis do usuário no RM (GUSRPERFIL).
 *
 * GUSRPERFIL liga CODUSUARIO a CODPERFIL dentro de um CODSISTEMA, e uma pessoa
 * costuma ter vários: no banco do hospital há quem tenha perfil de farmácia,
 * de compras e de portal ao mesmo tempo.
 *
 * Lido uma vez, no login, e guardado na sessão. Consultar a cada clique
 * custaria uma ida ao banco em cada bipe.
 */
class PerfisRM
{
    /** Teto de segurança: ninguém tem centenas de perfis, e a sessão não é lugar de lista longa. */
    private const LIMITE = 60;

    /**
     * @return array<int, array{codsistema: string, codperfil: string, nome: string}>
     */
    public static function doUsuario(string $codusuario): array
    {
        $codusuario = trim($codusuario);
        if ($codusuario === '') {
            return [];
        }

        $perfis = [];

        try {
            $c = new Connection('RM');
            $c->Consulta(
                'SELECT TOP ' . self::LIMITE . '
                    RTRIM(U.CODSISTEMA) AS CODSISTEMA,
                    RTRIM(U.CODPERFIL)  AS CODPERFIL,
                    RTRIM(ISNULL(P.NOME, \'\')) AS NOME
                 FROM GUSRPERFIL U
                 LEFT JOIN GPERFIL P
                        ON P.CODPERFIL = U.CODPERFIL
                       AND P.CODSISTEMA = U.CODSISTEMA
                 WHERE UPPER(RTRIM(U.CODUSUARIO)) = UPPER(?)
                 ORDER BY U.CODSISTEMA, U.CODPERFIL',
                [$codusuario]
            );

            while ($c->Resultado()) {
                $perfis[] = [
                    'codsistema' => encode_db_value((string) ($c->linha['CODSISTEMA'] ?? '')),
                    'codperfil'  => encode_db_value((string) ($c->linha['CODPERFIL'] ?? '')),
                    'nome'       => encode_db_value((string) ($c->linha['NOME'] ?? '')),
                ];
            }
        } catch (Throwable $e) {
            // Banco fora não pode impedir o login: quem já entrou continua
            // contando. Sem perfil na sessão, a trava de exclusão nega — que é
            // o lado seguro do erro.
            log_erro('perfis', 'falha ao ler GUSRPERFIL de ' . $codusuario . ': ' . $e->getMessage());
            return [];
        }

        return $perfis;
    }

    /**
     * Texto curto para a tela: 'FARM.COORD, COORDFARMA'.
     *
     * @param array<int, array{codsistema: string, codperfil: string, nome: string}> $perfis
     */
    public static function resumo(array $perfis, int $maximo = 4): string
    {
        $codigos = [];
        foreach ($perfis as $p) {
            $cod = trim((string) ($p['codperfil'] ?? ''));
            if ($cod !== '' && !in_array($cod, $codigos, true)) {
                $codigos[] = $cod;
            }
        }

        if ($codigos === []) {
            return '';
        }

        $sobra = count($codigos) - $maximo;
        $mostra = array_slice($codigos, 0, $maximo);

        return implode(', ', $mostra) . ($sobra > 0 ? " +{$sobra}" : '');
    }
}
