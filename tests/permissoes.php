<?php

/**
 * Quem pode apagar contagem.
 *
 * Rode com:  php tests/permissoes.php
 *
 * O perfil vem do RM (GUSRPERFIL), não de cadastro próprio. Dois casos moram
 * aqui e são os que fazem a diferença entre proteger e atrapalhar:
 *
 *  - lista vazia significa trava DESLIGADA, não "ninguém pode". Ligar sem
 *    saber quais perfis a coordenação usa travaria o inventário no meio de
 *    uma contagem;
 *  - sessão sem perfil nenhum (banco fora na hora do login) NEGA a exclusão,
 *    que é o lado seguro do erro.
 */

class SessionManager
{
    /** @var array<int, array{codsistema: string, codperfil: string, nome: string}> */
    public static $perfis = [];
    public static function getPerfis(): array { return self::$perfis; }
    public static function getUsername() { return 'TESTE'; }
}

define('APP_ROOT', dirname(__DIR__));
define('DS', DIRECTORY_SEPARATOR);
require APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'Permissoes.php';

$falhas = 0;

function confere(string $rotulo, $obtido, $esperado): void
{
    global $falhas;
    $ok = ($obtido === $esperado);
    if (!$ok) {
        $falhas++;
    }
    printf("  %-54s %-7s %s\n", $rotulo, var_export($obtido, true),
        $ok ? 'ok' : '<-- esperava ' . var_export($esperado, true));
}

function cenario(array $config, array $perfis): void
{
    $GLOBALS['appConfig'] = ['perfis_supervisor' => $config];
    SessionManager::$perfis = array_map(function ($p) {
        [$sis, $cod] = explode(':', $p);
        return ['codsistema' => $sis, 'codperfil' => $cod, 'nome' => ''];
    }, $perfis);
}

echo "Lista vazia: trava desligada, sistema como era antes\n";
cenario([], []);
confere('restrição não está ativa', Permissoes::restricaoAtiva(), false);
confere('sem perfil nenhum, ainda apaga', Permissoes::podeExcluir(), true);
confere('sem motivo de recusa', Permissoes::motivoNaoPodeExcluir(), '');

echo "\nLista preenchida: só quem tem o perfil\n";
cenario(['FARM.COORD', 'COORDFARMA'], ['O:FARM.COORD']);
confere('restrição ativa', Permissoes::restricaoAtiva(), true);
confere('tem o perfil, apaga', Permissoes::podeExcluir(), true);

cenario(['FARM.COORD', 'COORDFARMA'], ['O:FARM.USU', 'T:SOLICITACAO']);
confere('não tem o perfil, não apaga', Permissoes::podeExcluir(), false);
confere('recusa explica o que fazer',
    strpos(Permissoes::motivoNaoPodeExcluir(), 'corrija o total') !== false, true);

cenario(['FARM.COORD'], ['O:FARM.USU', 'T:COORDFARMA', 'O:FARM.COORD']);
confere('basta um perfil da lista entre vários', Permissoes::podeExcluir(), true);

echo "\nCaixa e espaço não podem decidir permissão\n";
cenario(['farm.coord'], ['O:FARM.COORD']);
confere('config minúscula casa', Permissoes::podeExcluir(), true);
cenario([' FARM.COORD '], ['O:FARM.COORD']);
confere('config com espaço casa', Permissoes::podeExcluir(), true);
cenario(['FARM.COORD'], ['O:farm.coord']);
confere('perfil minúsculo no RM casa', Permissoes::podeExcluir(), true);
cenario(['', '   '], ['O:FARM.USU']);
confere('lista só de vazios = desligada', Permissoes::restricaoAtiva(), false);

echo "\nQualificar por sistema, quando o CODPERFIL se repete\n";
// 'SOLICITACAO' e 'Geral' existem em dois sistemas neste banco.
cenario(['O:SOLICITACAO'], ['O:SOLICITACAO']);
confere('sistema certo casa', Permissoes::podeExcluir(), true);
cenario(['O:SOLICITACAO'], ['T:SOLICITACAO']);
confere('sistema errado NÃO casa', Permissoes::podeExcluir(), false);
cenario(['SOLICITACAO'], ['T:SOLICITACAO']);
confere('sem qualificar, qualquer sistema casa', Permissoes::podeExcluir(), true);

echo "\nO lado seguro do erro\n";
// PerfisRM devolve [] quando o banco falha no login. Com a trava ligada,
// isso tem de negar - nunca liberar.
cenario(['FARM.COORD'], []);
confere('sessão sem perfil (banco fora) nega', Permissoes::podeExcluir(), false);

$GLOBALS['appConfig'] = [];
confere('config sem a chave = desligada', Permissoes::restricaoAtiva(), false);
$GLOBALS['appConfig'] = ['perfis_supervisor' => 'FARM.COORD'];
confere('config com texto no lugar de lista = desligada', Permissoes::restricaoAtiva(), false);

echo "\n" . ($falhas === 0 ? "TODAS AS REGRAS CONFEREM\n" : "{$falhas} PROBLEMA(S)\n");
exit($falhas === 0 ? 0 : 1);
