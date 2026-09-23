<?php

require __DIR__ . '/bootstrap.php';

/**
 * Procura um item do local para contar quando a etiqueta não lê.
 *
 * Etiqueta rasgada, impressão apagada, embalagem amassada — a contagem não
 * pode parar por causa disso. Aqui a pessoa acha o item pelo nome, pelo código
 * do produto, pelo lote ou digitando o código de barras, e conta igual.
 *
 * A regra de busca é a mesma da tela de contagem por lote
 * (InventarioRM::listarPosicaoPorLote), de propósito: duas buscas com critérios
 * diferentes para a mesma coisa confundiriam quem usa as duas telas.
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

/** Teto de resultados: é busca ao vivo, não listagem. */
const BUSCA_LIMITE = 30;

/** Abaixo disto a consulta varre o local inteiro para nada. */
const BUSCA_MINIMO = 2;

$termo = trim((string) ($_GET['q'] ?? ''));
$codinventario = trim((string) ($_GET['CODINVENTARIO'] ?? ''));
$codloc = LocaisEstoque::normalizar((string) ($_GET['CODLOC'] ?? ''));

$parsed = ZMDCODBARRAS::parseCodigoInventario($codinventario);
if (empty($parsed['valid'])) {
    echo json_encode(['ok' => false, 'message' => $parsed['error'] ?: 'Inventário inválido.']);
    exit;
}

$codinventario = $parsed['formatted'];
if ($codloc === '') {
    $codloc = (string) $parsed['codloc'];
}

if (mb_strlen($termo) < BUSCA_MINIMO) {
    echo json_encode([
        'ok'      => true,
        'itens'   => [],
        'message' => 'Digite pelo menos ' . BUSCA_MINIMO . ' caracteres.',
    ]);
    exit;
}

// A consulta de posição é a mais cara do sistema; esta é chamada a cada tecla.
app_operacao_demorada(60);

try {
    // Sem filtro de saldo: item que o sistema julga zerado é justamente o que
    // aparece na prateleira e precisa ser contado.
    $linhas = InventarioRM::listarPosicaoPorLote($codloc, $termo, '', false, BUSCA_LIMITE);
} catch (Throwable $e) {
    log_erro('busca item', $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Não foi possível buscar agora. Tente de novo.']);
    exit;
}

$avulso = ZMDCODBARRAS::ehCodigoAvulso($codinventario);

// Avulsa não tem itens gerados no RM contra os quais conferir.
$noInventario = $avulso ? [] : InventarioRM::idprdsDoInventario($codinventario, $codloc);
$jaContado = ZMDCODBARRAS::totaisPorProdutoLote($codinventario);

$itens = [];
foreach ($linhas as $linha) {
    $idprd = (int) $linha['idprd'];
    $idlote = (int) $linha['idlote'];
    $codigobarras = ZMDCODBARRAS::barcodeComLote($idprd, $idlote);

    // Sem código de 13 dígitos não há como gravar: mostrar o item seria
    // oferecer um botão que não funciona.
    if ($codigobarras === '') {
        continue;
    }

    $itens[] = [
        'idprd'        => $idprd,
        'idlote'       => $idlote,
        'codigobarras' => $codigobarras,
        'codigo'       => $linha['codigo'],
        'nome'         => $linha['nome'],
        'und'          => $linha['und'],
        'lote'         => $linha['numlote'],
        'validade'     => $linha['validade'],
        'saldo'        => (float) $linha['saldo'],
        'ja'           => (float) ($jaContado[$idprd . ':' . $idlote] ?? 0),
        // Fora do inventário o servidor recusa a gravação; a tela avisa antes.
        'contavel'     => $avulso || !empty($noInventario[$idprd]),
    ];
}

echo json_encode([
    'ok'        => true,
    'itens'     => $itens,
    'truncado'  => count($linhas) >= BUSCA_LIMITE,
    'termo'     => $termo,
]);
