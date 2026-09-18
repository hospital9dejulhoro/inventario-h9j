<?php
/** @var string $codloc */
/** @var string $codinventario */
/** @var bool $modoLista */
/** @var bool $avulso */
/** @var bool $retomadoDaSessao */
/** @var bool $somenteComSaldo */
/** @var bool $listaTruncada */
/** @var array $itens */
/** @var array<int, float> $totaisProduto */
/** @var int $qtdItensRm */
/** @var array|null $envAtual */
/** @var array $recentInventarios */
/** @var string $nomeLocal */
/** @var string $statusInventarioRm */
/** @var array $inventariosAbertos */
$nomeLocal = $nomeLocal ?? '';
$qtdItensRm = (int) ($qtdItensRm ?? 0);
$itens = $itens ?? [];
$totaisProduto = $totaisProduto ?? [];
$inventariosAbertos = $inventariosAbertos ?? [];
$contagensAvulsas = $contagensAvulsas ?? [];
$statusInventarioRm = $statusInventarioRm ?? '';
$avulso = !empty($avulso);
$somenteComSaldo = !empty($somenteComSaldo);
$listaTruncada = !empty($listaTruncada);

$contados = 0;
$temSaldo = false;
foreach ($itens as $it) {
    if (($totaisProduto[(int) ($it['idprd'] ?? 0)] ?? 0) > 0) {
        $contados++;
    }
    if ((float) ($it['saldo'] ?? 0) != 0.0) {
        $temSaldo = true;
    }
}

$colunas = $temSaldo ? 7 : 6;
?>

<div class="page-wrap-wide inv-page sl-page">
    <header class="inv-page-header sl-header">
        <h1 class="page-title">Itens sem lote</h1>
        <p class="page-subtitle">
            Produtos sem cadastro de lote. Digite a quantidade e Enter para gravar.
        </p>
    </header>

    <?php if (!$modoLista): ?>
    <section class="inv-section panel inv-open-list" aria-labelledby="secao-abertos">
        <div class="inv-section-head">
            <span class="inv-step">0</span>
            <div>
                <h2 id="secao-abertos" class="section-title">Inventários em aberto</h2>
                <p class="section-desc">Escolha o inventário para listar os itens sem lote.</p>
            </div>
        </div>
        <?php if (empty($inventariosAbertos)): ?>
            <p class="inv-open-empty">Nenhum inventário em aberto no momento.</p>
        <?php else: ?>
            <ul class="inv-open-items">
                <?php foreach ($inventariosAbertos as $aberto): ?>
                    <li>
                        <a class="inv-open-item"
                           href="sem-lote.php?<?= e(http_build_query([
                               'CODINVENTARIO' => $aberto['codinventario'],
                               'CODLOC' => $aberto['codloc'],
                               'aplicar' => '1',
                           ])) ?>">
                            <span class="inv-open-main">
                                <strong class="mono"><?= e($aberto['codinventario']) ?></strong>
                                <span class="inv-open-badge">Aberto</span>
                            </span>
                            <span class="inv-open-meta">
                                <?php if ($aberto['codloc'] !== ''): ?>
                                    Local <?= e($aberto['codloc']) ?>
                                    <?php if ($aberto['local_nome'] !== ''): ?>
                                        — <?= e($aberto['local_nome']) ?>
                                    <?php endif; ?>
                                    ·
                                <?php endif; ?>
                                <?= (int) $aberto['itens'] ?> itens no RM
                            </span>
                            <span class="inv-open-action">Abrir</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="inv-section panel" aria-labelledby="secao-iniciar">
        <div class="inv-section-head">
            <span class="inv-step">1</span>
            <div>
                <h2 id="secao-iniciar" class="section-title">Código do inventário</h2>
                <p class="section-desc">
                    <?php if ($retomadoDaSessao): ?>
                        Último inventário carregado. Confirme e aplique.
                    <?php else: ?>
                        Informe AA.LLL.NNN. O local é preenchido automaticamente.
                        Contagens avulsas (faixa 9xx) também valem aqui.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <form action="sem-lote.php" method="get" autocomplete="off" class="inv-form">
            <div class="inv-setup-row inv-setup-row--inventario">
                <div class="form-group">
                    <label for="CODINVENTARIO" class="form-label">Código do inventário</label>
                    <input type="text" name="CODINVENTARIO" id="CODINVENTARIO" class="form-control mono"
                           inputmode="numeric" maxlength="10" placeholder="26.065.002"
                           pattern="\d{2}\.\d{3}\.\d{3}" data-inventario-mask
                           value="<?= e($codinventario) ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="CODLOC" class="form-label">Local</label>
                    <input type="text" name="CODLOC" id="CODLOC" class="form-control" maxlength="3"
                           value="<?= e($codloc) ?>" readonly>
                    <span class="form-hint"><?= e($nomeLocal !== '' ? $nomeLocal : 'Preenchido pelo código') ?></span>
                </div>
            </div>
            <button type="submit" name="aplicar" value="1" class="btn btn-primary">Listar itens sem lote</button>
        </form>
    </section>

    <?php
    $destinoAvulsa = 'sem-lote.php';
    require __DIR__ . '/_avulsas.php';
    ?>
    <?php else: ?>
    <div class="sl-toolbar" id="sl-toolbar">
        <div class="sl-toolbar-meta">
            <strong class="mono"><?= e($codinventario) ?></strong>
            <span><?= e($codloc) ?><?= $nomeLocal !== '' ? ' — ' . e($nomeLocal) : '' ?></span>
            <?php if ($envAtual): ?>
                <span><?= e($envAtual['label']) ?></span>
            <?php endif; ?>
            <?php /* Se o RM fechar o inventario no meio da contagem, quem esta
                     contando precisa ver. A tela de Leitura ja mostrava. */ ?>
            <?php if (($statusInventarioRm ?? '') !== ''): ?>
                <span title="STATUS em TINVENTARIO">Status RM <strong><?= e($statusInventarioRm) ?></strong></span>
            <?php endif; ?>
            <span id="sl-counts"><?= (int) $contados ?>/<?= (int) $qtdItensRm ?> contados</span>
            <button type="button" class="btn-ghost sl-atualizar" id="sl-atualizar"
                    title="Recarrega o que os outros operadores ja contaram">Atualizar totais</button>
            <?php if ($avulso): ?>
                <span class="pl-badge-avulsa" title="Código não existe em TINVENTARIO">Avulsa · fora do RM</span>
            <?php endif; ?>
        </div>
        <div class="sl-toolbar-actions">
            <input type="search" id="sl-busca" class="form-control sl-busca"
                   placeholder="Bipe a etiqueta, ou digite o produto…" autocomplete="off">
            <?php /* Mesmos dois modos da tela de lotes, com a mesma redação.
                     Volta sozinho para "Somar" depois de cada gravação. */ ?>
            <div class="sl-modo" role="radiogroup" aria-label="O que fazer com a quantidade">
                <label><input type="radio" name="sl-modo" value="somar" checked> Somar</label>
                <label><input type="radio" name="sl-modo" value="corrigir"> Corrigir o total</label>
            </div>
            <label class="sl-check">
                <input type="checkbox" id="sl-ocultar-contados"> Ocultar já contados
            </label>
            <?php if ($avulso): ?>
            <form action="sem-lote.php" method="get" class="sl-inline-form">
                <input type="hidden" name="CODINVENTARIO" value="<?= e($codinventario) ?>">
                <input type="hidden" name="CODLOC" value="<?= e($codloc) ?>">
                <label class="sl-check">
                    <input type="checkbox" name="todos" value="1" data-autosubmit
                           <?= $somenteComSaldo ? '' : 'checked' ?>> Incluir zerados
                </label>
            </form>
            <?php endif; ?>
            <a class="btn btn-ghost sl-link" href="inventario.php?<?= e(http_build_query(['CODINVENTARIO' => $codinventario, 'aplicar' => '1'])) ?>">Leitura (lote)</a>
            <a class="btn btn-ghost sl-link" href="por-lote.php?<?= e(http_build_query(['CODINVENTARIO' => $codinventario, 'aplicar' => '1'])) ?>">Por lote</a>
            <a class="btn btn-ghost sl-link" href="sem-lote.php">Trocar inventário</a>
        </div>
    </div>

    <p class="sl-hint" id="sl-status">Digite a quantidade e pressione Enter. <?= (int) $qtdItensRm ?> itens sem lote.</p>

    <?php if ($listaTruncada): ?>
    <p class="sl-hint is-err">
        A lista bateu o teto de <?= (int) InventarioRM::LIMITE_ITENS ?> produtos e foi cortada.
        Use o filtro para achar o item, ou conte por leitura de código de barras.
    </p>
    <?php endif; ?>

    <div class="sl-table-wrap">
        <table class="sl-table" id="sl-table">
            <thead>
            <tr>
                <th class="sl-col-n">#</th>
                <th>Produto</th>
                <th class="sl-col-und">Und</th>
                <?php if ($temSaldo): ?><th class="sl-col-saldo">Saldo</th><?php endif; ?>
                <th class="sl-col-ja">Já</th>
                <th class="sl-col-qtd">Qtd</th>
                <th class="sl-col-ok"></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($itens === []): ?>
                <tr><td colspan="<?= $colunas ?>" class="empty">
                    <?php if ($avulso && $somenteComSaldo): ?>
                        Nenhum produto sem lote com saldo neste local. Marque <strong>Incluir zerados</strong> para ver todos.
                    <?php else: ?>
                        Nenhum item sem lote neste inventário.
                    <?php endif; ?>
                </td></tr>
            <?php else: ?>
                <?php foreach ($itens as $i => $item):
                    $idprd = (int) $item['idprd'];
                    $ja = (float) ($totaisProduto[$idprd] ?? 0);
                    $saldo = (float) ($item['saldo'] ?? 0);
                    $termoBusca = mb_strtolower(
                        ($item['codigo'] ?? '') . ' ' . ($item['nome'] ?? '') . ' ' . $idprd,
                        'UTF-8'
                    );
                    ?>
                    <tr class="sl-row <?= $ja > 0 ? 'is-counted' : '' ?>"
                        data-idprd="<?= $idprd ?>"
                        data-search="<?= e($termoBusca) ?>"
                        data-counted="<?= $ja > 0 ? '1' : '0' ?>">
                        <td class="sl-col-n"><?= $i + 1 ?></td>
                        <td class="sl-nome">
                            <span class="sl-nome-main"><?= e($item['nome'] !== '' ? $item['nome'] : 'ID ' . $idprd) ?></span>
                            <?php if (($item['codigo'] ?? '') !== ''): ?>
                                <span class="sl-cod mono"><?= e($item['codigo']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="sl-col-und"><?= e($item['und']) ?></td>
                        <?php if ($temSaldo): ?>
                            <td class="sl-col-saldo"><?= $saldo != 0.0 ? e(formatar_quantidade($saldo)) : '—' ?></td>
                        <?php endif; ?>
                        <td class="sl-col-ja sl-ja"><?= $ja > 0 ? e(formatar_quantidade($ja)) : '—' ?></td>
                        <td class="sl-col-qtd">
                            <input type="text" class="sl-qtd" inputmode="decimal" autocomplete="off"
                                   aria-label="Quantidade <?= e($item['nome']) ?>">
                        </td>
                        <td class="sl-col-ok">
                            <button type="button" class="sl-btn" data-idprd="<?= $idprd ?>">OK</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
    window.SL_CFG = {
        saveUrl: <?= json_encode(url('sem-lote-salvar.php'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
        totaisUrl: <?= json_encode(url('contagem-totais.php'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
        inventario: <?= json_encode($codinventario, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
        codloc: <?= json_encode($codloc, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
        token: <?= json_encode(csrf_token(), JSON_HEX_TAG) ?>
    };
    </script>
    <script src="<?= e(url('assets/js/sem-lote.js')) ?>"></script>
    <?php endif; ?>
</div>
