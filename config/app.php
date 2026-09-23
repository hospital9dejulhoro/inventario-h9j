<?php

return [
    // Vazio = raiz (http://IP:9080/). Use '/inventario' se acessar via Docker na porta 80.
    'base_path' => '',

    /*
     * Perfis do RM (GUSRPERFIL.CODPERFIL) que podem APAGAR contagem — um
     * lançamento ou um inventário inteiro. Contar e corrigir o total seguem
     * livres para todo mundo.
     *
     * ENQUANTO ESTA LISTA ESTIVER VAZIA, A TRAVA ESTÁ DESLIGADA: qualquer
     * usuário conectado apaga, como era antes. A exclusão continua registrada
     * no log de auditoria de qualquer forma.
     *
     * Use 'FARM.COORD' para valer em qualquer sistema do RM, ou 'O:FARM.COORD'
     * para restringir ao sistema O — o mesmo CODPERFIL existe em mais de um
     * sistema neste banco.
     *
     * Candidatos que existem hoje, com o número de usuários:
     *   'FARM.COORD'       Coordenador da Farmacia       33
     *   'COORDFARMA'       COORD. FARMA                  48
     *   'ESTOQUE_NIVEL_1'  ESTOQUE_NIVEL_1               31
     *   'CDR_FARM'         ALMOXARIFADO FARMACIA          6
     */
    'perfis_supervisor' => [],
];
