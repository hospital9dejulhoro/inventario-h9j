<?php

/**
 * Falha de banco com duas mensagens separadas de propósito.
 *
 * getMessage() é o que o operador lê na tela — sem nome de tabela, nem de
 * servidor, nem rastro de driver. detalhe() é o que vai para o log do PHP,
 * onde a TI precisa do texto do SQL Server inteiro.
 *
 * Antes isso era um die(print_r(sqlsrv_errors(), true)): o operador via o
 * despejo do driver e ninguém via nada no log.
 */
class DatabaseException extends AppException
{
    /**
     * Achata o array de sqlsrv_errors() numa linha só para o log.
     *
     * @param array|false $errors
     */
    public static function formatarErros($errors): string
    {
        if (!is_array($errors) || $errors === []) {
            return 'sqlsrv nao informou o erro.';
        }

        $partes = [];
        foreach ($errors as $erro) {
            $partes[] = trim(sprintf(
                'SQLSTATE %s / codigo %s: %s',
                (string) ($erro['SQLSTATE'] ?? '?'),
                (string) ($erro['code'] ?? '?'),
                (string) ($erro['message'] ?? '')
            ));
        }

        return implode(' | ', $partes);
    }

    /**
     * O SQL Server devolve códigos próprios para "estourou o tempo". Vale
     * separar: a resposta para o operador não é "tente de novo", é "estreite
     * o filtro" — repetir a mesma consulta pesada estoura de novo.
     *
     * @param array|false $errors
     */
    public static function ehTimeout($errors): bool
    {
        if (!is_array($errors)) {
            return false;
        }

        foreach ($errors as $erro) {
            $codigo = (string) ($erro['code'] ?? '');
            $estado = (string) ($erro['SQLSTATE'] ?? '');
            if ($codigo === '-2' || $estado === 'HYT00' || $estado === 'HYT01') {
                return true;
            }
            if (stripos((string) ($erro['message'] ?? ''), 'timeout') !== false) {
                return true;
            }
        }

        return false;
    }
}
