<?php

/**
 * Copie este arquivo para environments.php e ajuste os valores.
 *   cp config/environments.example.php config/environments.php
 */
return [
    'producao' => [
        'label'                    => 'ProduÃ§Ã£o',
        'host'                     => '172.20.0.10',
        'database'                 => 'CorporeRM',
        'usuario'                  => 'rm',
        'senha'                    => 'SUA_SENHA',
        'badge_class'              => 'bg-danger',
        'trust_server_certificate' => true,
        // RM Host (porta 8051) â€” valida usuÃ¡rio/senha pela API oficial
        'api_url'                  => 'https://172.20.0.20:8051',
    ],
    'homologacao' => [
        'label'                    => 'HomologaÃ§Ã£o',
        'host'                     => '172.20.0.15',
        'database'                 => 'HomologaRM',
        'usuario'                  => 'rm',
        'senha'                    => 'SUA_SENHA',
        'badge_class'              => 'bg-warning text-dark',
        'trust_server_certificate' => true,
        'api_url'                  => 'https://172.20.0.20:8051',
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
    ],
];
