<?php

require __DIR__ . '/bootstrap.php';

SessionManager::requireConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('inventario.php');
}

$acao = $_POST['acao'] ?? '';
$id = $_POST['id'] ?? '';
$codloc = (string) ($_POST['CODLOC'] ?? '');
$codinventario = (string) ($_POST['CODINVENTARIO'] ?? '');
$quantidade = (string) ($_POST['QUANTIDADE'] ?? '1');

$redirectParams = ZMDCODBARRAS::inventarioQueryParams($codloc, $codinventario, $quantidade);
$redirectUrl = 'inventario.php?' . http_build_query($redirectParams);

csrf_exigir($redirectUrl);

/*
 * Inventário encerrado no RM não aceita mais nenhuma escrita — nem incluir,
 * nem corrigir, nem apagar. Apagar é o mais perigoso dos três: sumiria com a
 * contagem que sustenta uma apuração que o RM já fechou.
 *
 * Vale para editar, excluir e excluir_inventario. Fora ficam excluir_avulsa,
 * que por definição não tem linha no RM, e os códigos órfãos de antes da
 * máscara, que precisam continuar podendo ser limpos — motivoNaoPodeGravar()
 * só bloqueia o que existe no RM e não está aberto.
 */
$acoesQueEscrevem = ['excluir', 'excluir_inventario', 'editar'];
if (in_array($acao, $acoesQueEscrevem, true)) {
    $bloqueio = InventarioRM::motivoNaoPodeGravar($codinventario);
    if ($bloqueio !== '') {
        flash_set('danger', $bloqueio);
        redirect_to($acao === 'excluir_inventario' ? 'inventario.php' : $redirectUrl);
    }
}

/*
 * Apagar exige perfil de supervisão no RM. Contar e corrigir não: recontar
 * errado é rotina de quem conta, e virar chamado para a coordenação faria o
 * pessoal parar de corrigir.
 *
 * excluir_avulsa fica de fora: é o rascunho da própria pessoa, que nunca
 * entrou em apuração nenhuma do RM.
 *
 * Esconder o botão na tela não basta — este POST é forjável por quem souber o
 * nome do campo.
 */
$acoesQueApagam = ['excluir', 'excluir_inventario'];
if (in_array($acao, $acoesQueApagam, true)) {
    $semPermissao = Permissoes::motivoNaoPodeExcluir();
    if ($semPermissao !== '') {
        log_auditoria('exclusao negada', "acao={$acao} inv={$codinventario} id={$id}");
        flash_set('danger', $semPermissao);
        redirect_to($acao === 'excluir_inventario' ? 'inventario.php' : $redirectUrl);
    }
}

if ($acao === 'excluir') {
    if ($id === '') {
        flash_set('danger', 'Registro não informado para exclusão.');
        redirect_to($redirectUrl);
    }

    if (ZMDCODBARRAS::excluirPorId($id)) {
        flash_set('success', 'Registro excluído com sucesso.');
    } else {
        flash_set('danger', 'Não foi possível excluir o registro.');
    }

    redirect_to($redirectUrl);
}

/**
 * Descarta uma contagem avulsa inteira.
 *
 * Separado de excluir_inventario porque recusa qualquer código que NÃO seja da
 * faixa avulsa. O botão aparece na lista de avulsas, ao lado de contagens que
 * são rascunho por natureza; sem essa trava, um POST forjado dali apagaria um
 * inventário de verdade do RM.
 */
if ($acao === 'excluir_avulsa') {
    // Volta para a tela de onde veio. Whitelist porque destino de redirect
    // vindo de POST é entrada do usuário como qualquer outra.
    $telas = ['inventario.php', 'por-lote.php', 'sem-lote.php'];
    $voltar = (string) ($_POST['voltar'] ?? 'por-lote.php');
    if (!in_array($voltar, $telas, true)) {
        $voltar = 'por-lote.php';
    }

    if ($codinventario === '') {
        flash_set('danger', 'Código da contagem avulsa não informado.');
        redirect_to($voltar);
    }

    if (!ZMDCODBARRAS::ehCodigoAvulso($codinventario)) {
        flash_set(
            'danger',
            "{$codinventario} não é uma contagem avulsa. Inventário cadastrado no RM "
            . 'só pode ser excluído pela tela de Leitura.'
        );
        redirect_to($voltar);
    }

    $total = ZMDCODBARRAS::contarPorInventario($codinventario);

    if (ZMDCODBARRAS::excluirPorInventario($codinventario)) {
        SessionManager::removeRecentInventario($codinventario);
        $last = SessionManager::getLastInventario();
        if ($last !== null && ($last['codinventario'] ?? '') === $codinventario) {
            SessionManager::clearLastInventario();
            SessionManager::resetSessionScans();
        }
        flash_set('success', $total > 0
            ? "Contagem avulsa {$codinventario} descartada ({$total} itens removidos)."
            : "Contagem avulsa {$codinventario} descartada (não havia itens contados).");
    } else {
        flash_set('danger', 'Não foi possível descartar a contagem avulsa.');
    }

    redirect_to($voltar);
}

if ($acao === 'excluir_inventario') {
    if ($codinventario === '') {
        flash_set('danger', 'Código do inventário não informado.');
        redirect_to('inventario.php');
    }

    $total = ZMDCODBARRAS::contarPorInventario($codinventario);

    if (ZMDCODBARRAS::excluirPorInventario($codinventario)) {
        SessionManager::removeRecentInventario($codinventario);
        $last = SessionManager::getLastInventario();
        if ($last !== null && ($last['codinventario'] ?? '') === $codinventario) {
            SessionManager::clearLastInventario();
        }
        SessionManager::resetSessionScans();
        $msg = $total > 0
            ? "Inventário {$codinventario} excluído ({$total} itens removidos)."
            : "Inventário {$codinventario} excluído (nenhum item no banco).";
        flash_set('success', $msg);
    } else {
        flash_set('danger', 'Não foi possível excluir o inventário.');
    }

    redirect_to('inventario.php');
}

if ($acao === 'editar') {
    // A edição passa pelas mesmas regras do bipe. Antes ela só conferia o
    // local: dava para trocar o código por qualquer coisa, inclusive texto não
    // numérico — e aí o CONVERT(INT, SUBSTRING(CODIGOBARRAS,...)) usado por
    // todos os totais quebrava para o inventário inteiro, não só para a linha.
    $codigobarras = preg_replace('/\D/', '', (string) ($_POST['CODIGOBARRAS'] ?? ''));

    if ($id === '') {
        flash_set('danger', 'Registro não informado para edição.');
        redirect_to($redirectUrl);
    }

    if (strlen($codigobarras) !== 13) {
        flash_set('danger', 'O código de barras deve ter exatamente 13 dígitos.');
        redirect_to($redirectUrl);
    }

    $novaQuantidade = normalizar_quantidade($_POST['ITEM_QUANTIDADE'] ?? '');
    if ($novaQuantidade === null || $novaQuantidade <= 0) {
        flash_set('danger', 'Informe uma quantidade maior que zero (ex.: 1, 2 ou 1,5).');
        redirect_to($redirectUrl);
    }

    $itemCodloc = (string) ($_POST['ITEM_CODLOC'] ?? '');
    $localCheck = LocaisEstoque::validar($itemCodloc);
    if (!$localCheck['valid']) {
        flash_set('danger', $localCheck['error']);
        redirect_to($redirectUrl);
    }

    $validacao = ZMDCODBARRAS::validarCodigoBarras($codigobarras);
    if (!$validacao['valid']) {
        flash_set('danger', implode(' ', $validacao['errors']));
        redirect_to($redirectUrl);
    }

    // Mesma regra de pertencimento do bipe; contagem avulsa não tem itens
    // gerados no RM contra os quais conferir.
    $idprd = (int) $validacao['idprd'] > 0
        ? (int) $validacao['idprd']
        : ZMDCODBARRAS::idprdDoBarcode($codigobarras);

    if ($codinventario !== '' && !ZMDCODBARRAS::ehCodigoAvulso($codinventario)) {
        if (!InventarioRM::itemPertenceAoInventario($codinventario, $localCheck['codloc'], $idprd)) {
            $produtoLabel = $validacao['nome'] !== '' ? $validacao['nome'] : ('ID ' . $idprd);
            flash_set(
                'danger',
                "Produto {$produtoLabel} não faz parte do inventário {$codinventario} no local "
                . $localCheck['codloc'] . '. Alteração não gravada.'
            );
            redirect_to($redirectUrl);
        }
    }

    $zmd = new ZMDCODBARRAS();
    $zmd->setId($id);
    $zmd->setCodigobarras($codigobarras);
    $zmd->setCodinventario($codinventario);
    $zmd->setQuantidade(quantidade_para_banco($novaQuantidade));
    $zmd->setCodloc($localCheck['codloc']);

    if ($zmd->atualizar()) {
        flash_set('success', 'Registro atualizado com sucesso.');
    } else {
        flash_set('danger', ZMDCODBARRAS::mensagemDaFalha('Não foi possível atualizar o registro.'));
    }

    redirect_to($redirectUrl);
}

flash_set('warning', 'Ação inválida.');
redirect_to($redirectUrl);
