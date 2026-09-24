<?php

require __DIR__ . '/bootstrap.php';

/**
 * Procura um item do local para contar quando a etiqueta não lê.
 *
 * Etiqueta rasgada, impressão apagada, embalagem amassada — a contagem não
 * pode parar por causa disso. Aqui a pessoa acha o item pelo nome, pelo código
 * do produto, pelo lote ou digitando o código de barras, e conta igual.
 *
 * A regra de busca é a mesma da posição do local
 * (InventarioRM::listarPosicaoDoLocal), de propósito: duas buscas com critérios
 * diferentes para a mesma coisa confundiriam quem usa as duas telas.
 *
 * As duas metades, e não só a dos lotes: produto sem controle de lote não tem
 * linha de lote nenhuma, e procurar só pela metade com lote o deixava invisível
 * — "BLOCO DE RECEITUÁRIO" não aparecia por nome nem por código.
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
    $linhas = InventarioRM::listarPosicaoDoLocal($codloc, $termo, '', false);
    $linhas = array_slice($linhas, 0, BUSCA_LIMITE);
} catch (Throwable $e) {
    log_erro('busca item', $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Não foi possível buscar agora. Tente de novo.']);
    exit;
}

$avulso = ZMDCODBARRAS::ehCodigoAvulso($codinventario);

/*
 * Tudo que a busca devolve está no estoque do local, e o estoque do local
 * basta para contar — a trava de "só o que o RM gerou no inventário" saiu.
 *
 * O mapa do inventário continua sendo lido, mas agora só para o resultado
 * poder dizer quais itens estão fora do que o RM gerou. É informação útil para
 * quem confere depois; não impede mais de contar.
 */
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
        // Está no estoque do local, então conta. A consulta já descarta produto
        // inativo nas duas metades.
        'contavel'     => true,
        // Só para a tela poder marcar: está no estoque mas o RM não gerou este
        // item no inventário.
        'fora_do_rm'   => !$avulso && empty($noInventario[$idprd]),
    ];
}

echo json_encode([
    'ok'        => true,
    'itens'     => $itens,
    'truncado'  => count($linhas) >= BUSCA_LIMITE,
    'termo'     => $termo,
]);
