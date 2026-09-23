<?php

require __DIR__ . '/bootstrap.php';

app_modo_json();
header('Content-Type: application/json; charset=utf-8');

function pl_falha(string $mensagem, int $status = 200): void
{
    if ($status !== 200) {
        http_response_code($status);
    }
    echo json_encode(['ok' => false, 'message' => $mensagem]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pl_falha('Use POST.', 405);
}

if (!SessionManager::isConnected()) {
    pl_falha('Sessão expirada. Entre novamente.', 401);
}

csrf_exigir();

$idprd = (int) ($_POST['idprd'] ?? 0);
$idlote = (int) ($_POST['idlote'] ?? 0);
$quantidadeNum = normalizar_quantidade($_POST['quantidade'] ?? '');
$codinventario = trim((string) ($_POST['CODINVENTARIO'] ?? ''));
$codloc = trim((string) ($_POST['CODLOC'] ?? ''));
// somar: acrescenta uma leitura. corrigir: troca o total acumulado pelo valor
// informado (zero apaga a contagem do lote).
$modo = ($_POST['modo'] ?? 'somar') === 'corrigir' ? 'corrigir' : 'somar';

if ($idprd <= 0) {
    pl_falha('Produto inválido.');
}

if ($idlote <= 0) {
    pl_falha('Lote inválido. Use a tela "Sem lote" para produtos sem controle de lote.');
}

if ($quantidadeNum === null) {
    pl_falha('Informe uma quantidade válida.');
}

// Zero so faz sentido corrigindo, e ai quer dizer "apaga a contagem deste lote".
if ($modo === 'corrigir' ? $quantidadeNum < 0 : $quantidadeNum <= 0) {
    pl_falha($modo === 'corrigir'
        ? 'Quantidade não pode ser negativa. Use zero para apagar a contagem deste lote.'
        : 'Informe uma quantidade maior que zero.');
}

$quantidade = quantidade_para_banco($quantidadeNum);

$parsed = ZMDCODBARRAS::parseCodigoInventario($codinventario);
if (empty($parsed['valid'])) {
    pl_falha($parsed['error'] ?: 'Inventário inválido.');
}

$codinventario = $parsed['formatted'];
$codloc = $parsed['codloc'] !== '' ? $parsed['codloc'] : LocaisEstoque::normalizar($codloc);

// Contagem avulsa nao tem inventario no RM para validar contra; o local ja foi
// conferido pela mascara do codigo.
if (!ZMDCODBARRAS::ehCodigoAvulso($codinventario)) {
    $rmCheck = InventarioRM::validarParaUso($codinventario, $codloc);
    if (!$rmCheck['valid']) {
        pl_falha($rmCheck['error']);
    }

    // Inventário encerrado no RM já foi apurado: contar nele altera um
    // resultado fechado sem o RM saber. O status vem da validação acima, sem
    // consulta extra.
    if (!$rmCheck['pode_gravar']) {
        pl_falha($rmCheck['motivo_bloqueio']);
    }

    // Pertencimento no RM é por produto: o lote vem do cadastro do próprio produto.
    if (!InventarioRM::itemPertenceAoInventario($codinventario, $codloc, $idprd)) {
        pl_falha('Este produto não faz parte do inventário neste local.');
    }
}

$codigobarras = ZMDCODBARRAS::barcodeComLote($idprd, $idlote);
if ($codigobarras === '') {
    pl_falha('Produto ' . $idprd . ' / lote ' . $idlote . ' não cabe no código de 13 dígitos.');
}

$apagados = 0;

if ($modo === 'corrigir') {
    $correcao = ZMDCODBARRAS::corrigirTotalProdutoLote(
        $codinventario,
        $idprd,
        $idlote,
        $quantidadeNum,
        $codloc
    );

    if (!$correcao['ok']) {
        pl_falha($correcao['error']);
    }

    $apagados = (int) $correcao['apagados'];
} else {
    $zmd = new ZMDCODBARRAS();
    $zmd->setCodigobarras($codigobarras);
    $zmd->setCodinventario($codinventario);
    $zmd->setQuantidade($quantidade);
    $zmd->setCodloc($codloc);

    if (!$zmd->save()) {
        pl_falha(ZMDCODBARRAS::mensagemDaFalha('Não foi possível gravar. Tente novamente.'));
    }

    SessionManager::incrementSessionScans();
}

$totais = ZMDCODBARRAS::totaisPorProdutoLote($codinventario);
$total = $totais[$idprd . ':' . $idlote] ?? $quantidadeNum;

// resumoDoCodigo compara por produto+lote, então enxerga também o que veio
// bipado da etiqueta nesta mesma contagem.
$resumo = ZMDCODBARRAS::resumoDoCodigo($codinventario, $codigobarras);

// Corrigir substitui o total, então o que ficou gravado tem de ser exatamente
// o que foi pedido. Se não for, alguém contou este mesmo lote entre o momento
// em que esta tela mostrou o número e o clique em Corrigir — a transação
// manteve o banco íntegro, mas a decisão foi tomada sobre um número velho.
$aviso = '';
if ($modo === 'corrigir' && abs($total - $quantidadeNum) > 0.0001) {
    $aviso = 'Outro operador mexeu neste lote agora há pouco. O total gravado é '
        . formatar_quantidade($total) . ', não ' . formatar_quantidade($quantidadeNum)
        . '. Confira antes de corrigir de novo.';
    log_erro('correcao concorrente', "inv={$codinventario} idprd={$idprd} idlote={$idlote} pedido={$quantidadeNum} final={$total}");
}

echo json_encode([
    'ok'         => true,
    'modo'       => $modo,
    'message'    => $modo === 'corrigir' ? 'Total corrigido' : 'Registrado',
    'idprd'      => $idprd,
    'idlote'     => $idlote,
    'quantidade' => $quantidadeNum,
    'total'      => $total,
    'apagados'   => $apagados,
    'aviso'      => $aviso,
    // Corrigindo sobra uma linha so; nao faz sentido alertar releitura.
    'leituras'   => $modo === 'corrigir' ? 1 : (int) $resumo['leituras'],
]);
