<?php

/**
 * Quem pode apagar contagem.
 *
 * O perfil vem do RM (GUSRPERFIL), não de um cadastro próprio: a regra do
 * escopo é não criar um sistema paralelo de permissões. Quem administra o RM
 * continua administrando o acesso, e o inventário só lê.
 *
 * Só a exclusão é restrita. Contar e corrigir o total seguem livres — recontar
 * errado é rotina de quem conta, e transformar isso em chamado para a
 * coordenação faria o pessoal parar de corrigir.
 */
class Permissoes
{
    /** @var string Ação de apagar um lançamento ou uma contagem inteira. */
    public const EXCLUIR = 'excluir';

    /**
     * Perfis que mandam, como estão em config/app.php.
     *
     * Aceita 'FARM.COORD' (qualquer sistema do RM) ou 'O:FARM.COORD' (só o
     * sistema O), porque o mesmo CODPERFIL pode existir em mais de um sistema —
     * 'Geral' e 'SOLICITACAO' existem em dois cada um neste banco.
     *
     * @return array<int, string>
     */
    public static function perfisSupervisor(): array
    {
        $lista = $GLOBALS['appConfig']['perfis_supervisor'] ?? [];

        if (!is_array($lista)) {
            return [];
        }

        $limpos = [];
        foreach ($lista as $item) {
            $item = strtoupper(trim((string) $item));
            if ($item !== '') {
                $limpos[] = $item;
            }
        }

        return $limpos;
    }

    /**
     * Lista vazia = restrição desligada, e o sistema se comporta como antes
     * dela existir.
     *
     * É uma escolha, não um esquecimento: ligar a trava sem saber quais perfis
     * a coordenação usa travaria o inventário no meio de uma contagem. Enquanto
     * está desligada, a exclusão continua registrada no log de auditoria.
     */
    public static function restricaoAtiva(): bool
    {
        return self::perfisSupervisor() !== [];
    }

    /**
     * O usuário da sessão tem algum dos perfis que mandam?
     */
    public static function ehSupervisor(): bool
    {
        $exigidos = self::perfisSupervisor();
        if ($exigidos === []) {
            return true;
        }

        foreach (SessionManager::getPerfis() as $perfil) {
            $codigo = strtoupper(trim((string) ($perfil['codperfil'] ?? '')));
            $sistema = strtoupper(trim((string) ($perfil['codsistema'] ?? '')));

            if ($codigo === '') {
                continue;
            }

            if (in_array($codigo, $exigidos, true)
                || in_array($sistema . ':' . $codigo, $exigidos, true)) {
                return true;
            }
        }

        return false;
    }

    public static function podeExcluir(): bool
    {
        return self::ehSupervisor();
    }

    /**
     * Motivo pronto para a tela. '' quando pode.
     */
    public static function motivoNaoPodeExcluir(): string
    {
        if (self::podeExcluir()) {
            return '';
        }

        return 'Apagar contagem é permitido apenas para os perfis de supervisão do RM. '
            . 'Seu usuário não tem esse perfil — peça à coordenação, ou corrija o total '
            . 'em vez de apagar.';
    }
}
