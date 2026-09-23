<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

$codigobarras = isset($_GET['CODIGOBARRAS']) ? (string) $_GET['CODIGOBARRAS'] : '';
$barcodeInformado = trim($codigobarras) !== '';

// A quantidade acompanha a tela inteira: entra na URL, volta nos redirects e é
// o que será gravado. Recusar aqui o que não é número evita gravar um texto que
// o TRY_CAST dos totais descartaria depois, em silêncio.
$quantidadeBruta = isset($_GET['QUANTIDADE']) ? (string) $_GET['QUANTIDADE'] : '1';
$quantidadeNum = normalizar_quantidade($quantidadeBruta);
$quantidadeInvalida = $quantidadeBruta !== '' && $quantidadeNum === null;
$quantidade = ($quantidadeNum !== null && $quantidadeNum > 0)
    ? quantidade_para_banco($quantidadeNum)
    : '1';

// Bipe também é ação do usuário: precisa reclamar se o inventário não servir,
// não só pré-preencher a tela.
$ctx = ContextoInventario::resolver(
    'inventario.php',
    ['QUANTIDADE' => $quantidade],
    $barcodeInformado
);

$codloc = $ctx->codloc;
$codinventario = $ctx->codinventario;
$nomeLocal = $ctx->nomeLocal;
$statusInventarioRm = $ctx->statusRm;
$somenteLeitura = $ctx->somenteLeitura;
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
