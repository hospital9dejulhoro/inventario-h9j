<?php

/**
 * Copie este arquivo para environments.php e ajuste os valores.
 *   cp config/environments.example.php config/environments.php
 *
 * Chaves opcionais de cada ambiente:
 *
 *   query_timeout   Segundos que uma consulta pode levar antes de o SQL Server
 *                   abortá-la (padrão 120). O objetivo é ela estourar ANTES do
 *                   PHP-FPM, para o erro chegar na tela com uma instrução útil
 *                   em vez de um 504 em branco. Local muito grande, aumente.
 *
 *   login_timeout   Segundos para abrir a conexão (padrão 10). Sem teto, um
 *                   host fora do ar deixa a tela pendurada sem mensagem.
 *
 *   api_fallbacks   Hosts alternativos do RM Host para autenticação, usados
 *                   só quando o api_url deste ambiente não responde. Deixe
 *                   vazio a menos que precise: um Host de OUTRO ambiente
 *                   validando a senha significa que a tela diz um ambiente e
 *                   quem confere a credencial é outro.
 */
return [
    'producao' => [
        'label'                    => 'Produção',
        'host'                     => '172.20.0.10',
        'database'                 => 'CorporeRM',
        'usuario'                  => 'rm',
        'senha'                    => 'SUA_SENHA',
        'badge_class'              => 'bg-danger',
        'trust_server_certificate' => true,
        // RM Host (porta 8051) — valida usuário/senha pela API oficial
        'api_url'                  => 'https://172.20.0.20:8051',
        'api_fallbacks'            => [],
        'query_timeout'            => 120,
        'login_timeout'            => 10,
    ],
    'homologacao' => [
        'label'                    => 'Homologação',
        'host'                     => '172.20.0.15',
        'database'                 => 'HomologaRM',
        'usuario'                  => 'rm',
        'senha'                    => 'SUA_SENHA',
        'badge_class'              => 'bg-warning text-dark',
        'trust_server_certificate' => true,
        'api_url'                  => 'https://172.20.0.20:8051',
        'api_fallbacks'            => [],
        'query_timeout'            => 120,
        'login_timeout'            => 10,
    ],
    'testes' => [
        'label'                    => 'Testes',
        'host'                     => '172.20.0.15',
        'database'                 => 'ontemrm',
        'usuario'                  => 'rm',
        'senha'                    => 'SUA_SENHA',
        'badge_class'              => 'bg-info text-dark',
        'trust_server_certificate' => true,
        'api_url'                  => 'https://172.20.0.20:8051',
        'api_fallbacks'            => [],
        'query_timeout'            => 120,
        'login_timeout'            => 10,
    ],
];
