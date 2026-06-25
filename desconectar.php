<?php

require __DIR__ . '/bootstrap.php';

SessionManager::clear();
flash_set('info', 'Desconectado do ambiente. Seu último inventário foi mantido — reconecte para continuar.');
redirect_to('index.php');
