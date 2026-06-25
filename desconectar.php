<?php

require __DIR__ . '/bootstrap.php';

SessionManager::clear();
flash_set('info', 'Sessão encerrada. Selecione o ambiente novamente para continuar.');
redirect_to('index.php');
