<?php

require __DIR__ . '/bootstrap.php';

/**
 * Totais já contados de um inventário, para as telas atualizarem a coluna
 * "Já contado" sem recarregar a página inteira.
 *
 * Com várias pessoas contando o mesmo inventário, esse número envelhece no
 * instante em que a tela abre: o operador decide "corrigir para 8" olhando um
 * total que um colega já mudou. A gravação é atômica e não corrompe nada, mas
 * a decisão foi tomada sobre um número velho.
 *
 * É só leitura — sem CSRF, sem efeito colateral.
 */

app_modo_json();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!SessionManager::isConnected()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sessão expirada. Entre novamente.']);
    exit;
}

$codinventario = trim((string) ($_GET['CODINVENTARIO'] ?? ''));
$modo = ($_GET['modo'] ?? 'lote') === 'produto' ? 'produto' : 'lote';

$parsed = ZMDCODBARRAS::parseCodigoInventario($codinventario);
if (empty($parsed['valid'])) {
    echo json_encode(['ok' => false, 'message' => $parsed['error'] ?: 'Inventário inválido.']);
    exit;
}

$codinventario = $parsed['formatted'];

// Chave "idprd:idlote" na tela de lotes, "idprd" na tela sem lote.
$totais = $modo === 'produto'
    ? ZMDCODBARRAS::totaisPorProduto($codinventario)
    : ZMDCODBARRAS::totaisPorProdutoLote($codinventario);

// Chaves como texto: em JSON, "123" viraria índice de array e a ordem/tipo
// mudaria conforme o conteúdo.
$saida = [];
foreach ($totais as $chave => $qtd) {
    $saida[(string) $chave] = (float) $qtd;
}

// Uma consulta só: as telas recalculam "N de M contados" a partir do mapa, e
// um COUNT extra aqui sairia caro sendo chamado por 10 telas.
echo json_encode([
    'ok'         => true,
    'totais'     => (object) $saida,
    'atualizado' => date('H:i:s'),
]);
