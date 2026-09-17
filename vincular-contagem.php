<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

// Move a contagem de um codigo avulso para o inventario que o RM criou depois.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('por-lote.php');
}

$de = trim((string) ($_POST['de'] ?? ''));
$paraBruto = trim((string) ($_POST['para'] ?? ''));

$voltar = 'por-lote.php?' . http_build_query(['CODINVENTARIO' => $de, 'aplicar' => '1']);

csrf_exigir($voltar);

$origem = ZMDCODBARRAS::parseCodigoInventario($de);
if (empty($origem['valid'])) {
    flash_set('danger', 'Código de origem inválido.');
    redirect_to('por-lote.php');
}

$destino = ZMDCODBARRAS::parseCodigoInventario($paraBruto);
if (empty($destino['valid'])) {
    flash_set('danger', $destino['error'] ?: 'Código de destino inválido.');
    redirect_to($voltar);
}

$para = $destino['formatted'];

// O destino tem de existir no RM - a razao de vincular e justamente parar de
// ser avulsa. Sem essa checagem, um erro de digitacao criaria outra avulsa
// silenciosamente e a contagem sumiria de vista.
$existe = InventarioRM::existeNoRm($para);
if (!$existe['valid']) {
    flash_set('danger', $existe['error']);
    redirect_to($voltar);
}

// Mesmo local: mover contagem para o inventario de outro local misturaria
// prateleiras diferentes na mesma apuracao.
if ($origem['codloc'] !== $destino['codloc']) {
    flash_set(
        'danger',
        "A contagem é do local {$origem['codloc']} e o inventário {$para} é do local {$destino['codloc']}."
    );
    redirect_to($voltar);
}

$jaTem = ZMDCODBARRAS::contarPorInventario($para);
$resultado = ZMDCODBARRAS::renomearInventario($de, $para);

if (!$resultado['ok']) {
    flash_set('danger', $resultado['error']);
    redirect_to($voltar);
}

// A sessão inteira passa a apontar para o código novo. Só tirar dos recentes
// deixava o "último inventário" apontando para o avulso recém-esvaziado, e a
// próxima visita sem parâmetros retomava uma contagem que não existe mais.
SessionManager::trocarCodigoInventario($de, $para, $destino['codloc']);

$msg = "Contagem movida de {$de} para {$para} ({$resultado['movidos']} "
    . ($resultado['movidos'] === 1 ? 'item' : 'itens') . ').';
if ($jaTem > 0) {
    $msg .= " Atenção: {$para} já tinha {$jaTem} "
        . ($jaTem === 1 ? 'item contado' : 'itens contados') . ' — as duas contagens agora somam.';
}

flash_set($jaTem > 0 ? 'warning' : 'success', $msg);
redirect_to('por-lote.php?' . http_build_query(['CODINVENTARIO' => $para, 'aplicar' => '1']));
