<?php

/**
 * Copie este arquivo para environments.php e ajuste os valores.
 *   cp config/environments.example.php config/environments.php
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
    ],
    'homologacao' => [
        'label'                    => 'Homologação',
        'host'                     => '172.20.0.15',
        'database'                 => 'HomologaRM',
        'usuario'                  => 'rm',
        'senha'                    => 'SUA_SENHA',
        'badge_class'              => 'bg-warning text-dark',
        'trust_server_certificate' => true,
    ],
];
