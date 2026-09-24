<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

/*
 * Gravar leitura é POST; ver a tela é GET.
 *
 * A bipagem inteira vinha pela URL, e uma contagem é escrita: bastava alguém
 * mandar um link inventario.php?CODIGOBARRAS=...&CODINVENTARIO=... para que
 * qualquer pessoa logada que clicasse gravasse contagem sem saber. Escrita não
 * pode caber num link — e agora exige o token da sessão.
 *
 * O inventário e o local chegam no mesmo envio da bipagem, não na URL, então a
 * origem dos dados muda junto com o método.
 */
$ehGravacao = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$entrada = $ehGravacao ? $_POST : $_GET;

$codigobarras = isset($entrada['CODIGOBARRAS']) ? (string) $entrada['CODIGOBARRAS'] : '';

// Código na URL não grava mais nada: em GET a tela só mostra.
$barcodeInformado = $ehGravacao && trim($codigobarras) !== '';

/*
 * Página aberta antes desta versão, ainda enviando por GET.
 *
 * Quem estava contando quando a atualização subiu continua com o formulário
 * antigo na tela. O bipe chegaria por GET, seria ignorado, e a pessoa seguiria
 * bipando a prateleira inteira sem gravar nada — sem erro, sem aviso, sem
 * nenhum item novo na lista. Melhor dizer o que houve.
 */
$telaDesatualizada = !$ehGravacao && trim($codigobarras) !== '';

// Somar é o normal: cada bipe acrescenta. Corrigir substitui o total do
// produto/lote — é para quem recontou uma posição e quer dizer quanto há, não
// quanto acrescentar. Acompanha a tela pela URL junto com a quantidade, senão
// voltaria a somar a cada leitura e a correção seguinte viraria soma.
$modo = (isset($entrada['modo']) && $entrada['modo'] === 'corrigir') ? 'corrigir' : 'somar';

// A quantidade acompanha a tela inteira: entra na URL, volta nos redirects e é
// o que será gravado. Recusar aqui o que não é número evita gravar um texto que
// o TRY_CAST dos totais descartaria depois, em silêncio.
$quantidadeBruta = isset($entrada['QUANTIDADE']) ? (string) $entrada['QUANTIDADE'] : '1';
$quantidadeNum = normalizar_quantidade($quantidadeBruta);
$quantidadeInvalida = $quantidadeBruta !== '' && $quantidadeNum === null;

// Somando, zero não quer dizer nada e o padrão 1 é o que o bipe espera.
// Corrigindo, zero é uma resposta legítima: "não há nada nesta posição", e
// apaga a contagem do item. Forçar 1 aqui tornaria isso impossível.
$minimoAceito = $modo === 'corrigir' ? 0.0 : 0.000001;
$quantidade = ($quantidadeNum !== null && $quantidadeNum >= $minimoAceito)
    ? quantidade_para_banco($quantidadeNum)
    : '1';

// Bipe também é ação do usuário: precisa reclamar se o inventário não servir,
// não só pré-preencher a tela.
$ctx = ContextoInventario::resolver(
    'inventario.php',
    ['QUANTIDADE' => $quantidade, 'modo' => $modo],
    $barcodeInformado,
    $entrada
);

// O token confere antes de qualquer decisão sobre gravar. Volta para a mesma
// tela: sessão que expirou no meio da contagem não pode jogar a pessoa para a
// seleção de inventário.
if ($ehGravacao) {
    csrf_exigir('inventario.php?' . http_build_query(
        ZMDCODBARRAS::inventarioQueryParams($ctx->codloc, $ctx->codinventario, $quantidade)
    ));
}

$codloc = $ctx->codloc;
$codinventario = $ctx->codinventario;
$nomeLocal = $ctx->nomeLocal;
$statusInventarioRm = $ctx->statusRm;
$somenteLeitura = $ctx->somenteLeitura;
$modoContagem = $modo;
$motivoBloqueio = $ctx->motivoBloqueio;
$retomadoDaSessao = $ctx->retomadoDaSessao;
$modoLeitura = $ctx->ativo;
$avulso = $ctx->avulso;

$registros = [];
$totalBipagens = 0;
$listaTruncada = false;
$qtdItensRm = 0;
$mostrarTabela = false;
$envAtual = EnvironmentManager::getCurrent();
$leiturasSessao = SessionManager::getSessionScans();
$recentInventarios = SessionManager::getRecentInventarios();
$locaisEstoqueJson = json_encode(LocaisEstoque::todos(), JSON_UNESCAPED_UNICODE);

// Só a tela de seleção usa as listas (view: if (!$modoLeitura)). Em modo leitura
// o resultado era descartado, mas custava consultas a cada bipagem.
$inventariosAbertos = $modoLeitura ? [] : InventarioRM::listarAbertos();

// Destinos possiveis para vincular a avulsa: so os abertos do mesmo local, que
// e o unico caso que vincular-contagem.php aceita. Consulta so quando ha uma
// avulsa aberta na tela.
$destinosVincular = $avulso ? InventarioRM::abertosDoLocal($codloc) : [];
$contagensAvulsas = $modoLeitura ? [] : ZMDCODBARRAS::listarAvulsos();

$redirectUrl = 'inventario.php?' . http_build_query($ctx->params(['QUANTIDADE' => $quantidade]));

if ($telaDesatualizada) {
    flash_set(
        'warning',
        'Esta tela foi atualizada enquanto você contava e a leitura NÃO foi gravada. '
        . 'Recarregue a página (F5) e bipe este item de novo. As leituras anteriores estão salvas.'
    );
    redirect_to($redirectUrl);
}

if ($quantidadeInvalida) {
    flash_set('danger', 'Quantidade inválida: use um número, como 1, 2 ou 1,5.');
    redirect_to($redirectUrl);
}

if ($modoLeitura) {
    $mostrarTabela = true;

    if ($barcodeInformado) {
        // Esconder o campo não basta: o bipe chega por POST e um reload da
        // página repete o último. A recusa fica aqui, antes de qualquer coisa.
        if ($ctx->somenteLeitura) {
            flash_set('danger', $ctx->motivoBloqueio);
            redirect_to($redirectUrl);
        }

        $codigobarras = preg_replace('/\D/', '', $codigobarras);

        $validacao = ZMDCODBARRAS::validarCodigoBarras($codigobarras);

        if (!$validacao['valid']) {
            flash_set('danger', implode(' ', $validacao['errors']));
            redirect_to($redirectUrl);
        }

        $idprd = (int) $validacao['idprd'] > 0
            ? (int) $validacao['idprd']
            : ZMDCODBARRAS::idprdDoBarcode($codigobarras);

        // Avulsa nao tem itens gerados no RM contra os quais conferir.
        if (!$avulso && !InventarioRM::itemPertenceAoInventario($codinventario, $codloc, $idprd)) {
            $produtoLabel = $validacao['nome'] !== '' ? $validacao['nome'] : ('ID ' . $idprd);
            flash_set(
                'danger',
                "Produto {$produtoLabel} não faz parte do inventário {$codinventario} no local {$codloc}. Item não gravado."
            );
            redirect_to($redirectUrl);
        }

        if ($modo === 'corrigir') {
            // Mesma operação das telas de lote: troca o total do produto/lote
            // por este valor, em transação. Zero apaga a contagem do item.
            $idlote = ZMDCODBARRAS::idloteDoBarcode($codigobarras);
            $correcao = ZMDCODBARRAS::corrigirTotalProdutoLote(
                $codinventario,
                $idprd,
                $idlote,
                (float) $quantidade,
                $codloc
            );

            if ($correcao['error'] !== '') {
                flash_set('danger', $correcao['error']);
            } else {
                $rotulo = $validacao['nome'] !== '' ? $validacao['nome'] : ('ID ' . $idprd);
                $msg = 'Total corrigido: ' . $rotulo . ' · agora ' . formatar_quantidade((float) $quantidade)
                    . ' ' . $validacao['und'];
                if (!empty($validacao['lote'])) {
                    $msg .= ' · Lote ' . $validacao['lote'];
                }
                if ($correcao['apagados'] > 0) {
                    $msg .= ' (substituiu ' . $correcao['apagados']
                        . ($correcao['apagados'] === 1 ? ' leitura' : ' leituras') . ')';
                }
                flash_set('success', $msg);
            }

            redirect_to($redirectUrl);
        }

        $zmd = new ZMDCODBARRAS();
        $zmd->setCodigobarras($codigobarras);
        $zmd->setCodinventario($codinventario);
        $zmd->setQuantidade($quantidade);
        $zmd->setCodloc($codloc);

        if ($zmd->save()) {
            SessionManager::incrementSessionScans();
            $msg = 'Registrado: ' . $validacao['nome'] . ' · Qtd ' . $quantidade . ' ' . $validacao['und'];
            if (!empty($validacao['lote'])) {
                $msg .= ' · Lote ' . $validacao['lote'];
            }
            if (!empty($validacao['warnings'])) {
                $msg .= ' (' . implode(' ', $validacao['warnings']) . ')';
            }

            // Releitura do mesmo produto/lote: grava mesmo assim (contar duas
            // caixas do mesmo lote é legítimo), mas avisa em vez de confirmar em
            // silêncio — bipagem repetida por engano é o erro mais comum aqui.
            $resumo = ZMDCODBARRAS::resumoDoCodigo($codinventario, $codigobarras);

            if ($resumo['leituras'] > 1) {
                $item = $validacao['nome'];
                if (!empty($validacao['lote'])) {
                    $item .= ' · Lote ' . $validacao['lote'];
                }

                $und = trim((string) $validacao['und']);
                $acumulado = formatar_quantidade($resumo['quantidade']) . ($und !== '' ? ' ' . $und : '');

                // Pode ser repetição sua ou a contagem de um colega no mesmo
                // inventário. Com várias pessoas contando, as duas coisas
                // acontecem, e a mensagem não pode acusar só a primeira.
                flash_set(
                    'warning',
                    $resumo['leituras'] . 'ª leitura de ' . $item . ' — acumulado ' . $acumulado
                    . '. Se você não bipou antes, foi outro operador; se foi repetição sua,'
                    . ' use Excluir na lista abaixo.'
                );
            } else {
                flash_set('success', $msg);
            }
        } else {
            // Antes esta tela engolia o motivo: recusa de quantidade ou de
            // local virava sempre "tente novamente", sem dizer o que corrigir.
            flash_set('danger', ZMDCODBARRAS::mensagemDaFalha('Não foi possível salvar o registro. Tente novamente.'));
        }

        redirect_to($redirectUrl);
    }

    if (isset($_GET['aplicar'])) {
        SessionManager::resetSessionScans();
        SessionManager::setLastInventario($codloc, $codinventario, $quantidade);
        $qtdItens = $avulso ? 0 : InventarioRM::contarItensInventario($codinventario, $codloc);
        flash_set(
            'info',
            $avulso
                ? 'Contagem avulsa ' . $codinventario . ' ativa. Escaneie o código de barras.'
                : 'Inventário ' . $codinventario . ' ativo (' . $qtdItens . ' itens no RM). Escaneie o código de barras.'
        );
        redirect_to($redirectUrl);
    }

    SessionManager::setLastInventario($codloc, $codinventario, $quantidade);
    $registros = ZMDCODBARRAS::listarPorInventario($codinventario);
    // count($registros) para no teto da listagem — o total real vem daqui.
    $totalBipagens = ZMDCODBARRAS::totalBipagens($codinventario, $registros);
    $listaTruncada = $totalBipagens > count($registros);
    $qtdItensRm = $avulso ? 0 : InventarioRM::contarItensInventario($codinventario, $codloc);
    $leiturasSessao = SessionManager::getSessionScans();
}

$pageTitle = 'Inventário RM — Leitura';
$bodyClass = 'page-inventory';
$showNavbar = true;

ob_start();
require __DIR__ . '/views/inventario.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
