<?php
/** @var string $codloc */
/** @var string $codinventario */
/** @var string $busca */
/** @var bool $modoLista */
/** @var bool $retomadoDaSessao */
/** @var bool $listaTruncada */
/** @var array $lotes */
/** @var array<string, float> $totaisProdutoLote */
/** @var array|null $envAtual */
/** @var string $nomeLocal */
/** @var string $statusInventarioRm */
/** @var array $inventariosAbertos */
$nomeLocal = $nomeLocal ?? '';
$busca = (string) ($busca ?? '');
$lotes = $lotes ?? [];
$totaisProdutoLote = $totaisProdutoLote ?? [];
$inventariosAbertos = $inventariosAbertos ?? [];
$listaTruncada = !empty($listaTruncada);

$contados = 0;
foreach ($lotes as $lote) {
    if (($totaisProdutoLote[$lote['idprd'] . ':' . $lote['idlote']] ?? 0) > 0) {
        $contados++;
    }
}
?>

<div class="page-wrap-wide inv-page sl-page pl-page">
    <?php if (!$modoLista): ?>
    <header class="inv-page-header sl-header">
        <h1 class="page-title">Contagem por lote</h1>
        <p class="page-subtitle">Escolha o inventário para listar os lotes do local.</p>
    </header>

    <section class="inv-section panel inv-open-list" aria-labelledby="secao-abertos">
        <div class="inv-section-head">
            <span class="inv-step">0</span>
            <div>
                <h2 id="secao-abertos" class="section-title">Inventários em aberto</h2>
                <p class="section-desc">Escolha o inventário para listar os lotes.</p>
            </div>
        </div>
        <?php if (empty($inventariosAbertos)): ?>
            <p class="inv-open-empty">Nenhum inventário em aberto no momento.</p>
        <?php else: ?>
            <ul class="inv-open-items">
                <?php foreach ($inventariosAbertos as $aberto): ?>
                    <li>
                        <a class="inv-open-item"
                           href="por-lote.php?<?= e(http_build_query([
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
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <form action="por-lote.php" method="get" autocomplete="off" class="inv-form">
            <div class="inv-setup-row inv-setup-row--inventario">
                <div class="form-group">
                    <label for="CODINVENTARIO" class="form-label">Código do inventário</label>
                    <input type="text" name="CODINVENTARIO" id="CODINVENTARIO" class="form-control mono"
                           inputmode="numeric" maxlength="10" placeholder="26.028.001"
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
            <button type="submit" name="aplicar" value="1" class="btn btn-primary">Listar lotes</button>
        </form>
    </section>

    <?php else: ?>

    <div class="sl-toolbar pl-toolbar">
        <div class="sl-toolbar-meta">
            <strong class="mono"><?= e($codinventario) ?></strong>
            <span><?= e($codloc) ?><?= $nomeLocal !== '' ? ' — ' . e($nomeLocal) : '' ?></span>
            <?php if ($envAtual): ?>
                <span><?= e($envAtual['label']) ?></span>
            <?php endif; ?>
            <span id="pl-counts"><?= (int) $contados ?>/<?= count($lotes) ?> contados</span>
        </div>
        <div class="sl-toolbar-actions">
            <a class="btn btn-ghost sl-link" href="inventario.php?<?= e(http_build_query(['CODINVENTARIO' => $codinventario, 'aplicar' => '1'])) ?>">Leitura</a>
            <a class="btn btn-ghost sl-link" href="sem-lote.php?<?= e(http_build_query(['CODINVENTARIO' => $codinventario, 'aplicar' => '1'])) ?>">Sem lote</a>
            <a class="btn btn-ghost sl-link" href="por-lote.php">Trocar inventário</a>
        </div>
    </div>

    <div class="pl-split">
        <section class="pl-pane pl-pane-lista panel" aria-labelledby="pl-lista-titulo">
            <div class="pl-pane-head">
                <h2 id="pl-lista-titulo" class="pl-pane-title">Lotes no local <?= e($codloc) ?></h2>
                <form action="por-lote.php" method="get" class="pl-busca-form js-no-loading" id="pl-busca-form">
                    <input type="hidden" name="CODINVENTARIO" value="<?= e($codinventario) ?>">
                    <input type="hidden" name="CODLOC" value="<?= e($codloc) ?>">
                    <input type="search" id="pl-busca" name="q" class="form-control sl-busca"
                           value="<?= e($busca) ?>" autocomplete="off"
                           placeholder="Lote, produto ou código — Enter pega o primeiro">
                    <button type="submit" class="btn btn-secondary pl-busca-btn" id="pl-busca-rm">Buscar no RM</button>
                </form>
                <label class="sl-check">
                    <input type="checkbox" id="pl-ocultar-contados"> Ocultar já contados
                </label>
            </div>

            <?php if ($listaTruncada): ?>
            <p class="sl-hint is-err">
                A lista bateu o teto de <?= (int) InventarioRM::LIMITE_LOTES ?> lotes e foi cortada.
                O filtro só alcança o que está na tela — use <strong>Buscar no RM</strong> para o inventário inteiro.
            </p>
            <?php endif; ?>

            <div class="sl-table-wrap pl-table-wrap">
                <table class="sl-table pl-table" id="pl-table">
                    <thead>
                    <tr>
                        <th>Produto</th>
                        <th class="pl-col-lote">Lote</th>
                        <th class="pl-col-validade">Validade</th>
                        <th class="pl-col-saldo">Saldo</th>
                        <th class="sl-col-ja">Já</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($lotes === []): ?>
                        <tr><td colspan="5" class="empty">
                            <?= $busca !== '' ? 'Nenhum lote encontrado para essa busca.' : 'Nenhum lote com registro neste local.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($lotes as $lote):
                            $idprd = (int) $lote['idprd'];
                            $idlote = (int) $lote['idlote'];
                            $ja = (float) ($totaisProdutoLote[$idprd . ':' . $idlote] ?? 0);
                            $saldo = (float) ($lote['saldo'] ?? 0);
                            $nomeProduto = $lote['nome'] !== '' ? $lote['nome'] : 'ID ' . $idprd;
                            $numlote = $lote['numlote'] !== '' ? $lote['numlote'] : '—';
                            $validade = $lote['validade'] !== '' ? $lote['validade'] : '—';
                            $termoBusca = mb_strtolower(
                                ($lote['numlote'] ?? '') . ' ' . ($lote['codigo'] ?? '') . ' ' . ($lote['nome'] ?? '') . ' ' . $idprd,
                                'UTF-8'
                            );
                            ?>
                            <tr class="sl-row pl-row <?= $ja > 0 ? 'is-counted' : '' ?>"
                                tabindex="0" role="button"
                                aria-label="Selecionar <?= e($nomeProduto) ?> lote <?= e($numlote) ?>"
                                data-idprd="<?= $idprd ?>"
                                data-idlote="<?= $idlote ?>"
                                data-nome="<?= e($nomeProduto) ?>"
                                data-codigo="<?= e($lote['codigo']) ?>"
                                data-lote="<?= e($numlote) ?>"
                                data-validade="<?= e($validade) ?>"
                                data-und="<?= e($lote['und']) ?>"
                                data-saldo="<?= e(formatar_quantidade($saldo)) ?>"
                                data-ja="<?= e(formatar_quantidade($ja)) ?>"
                                data-search="<?= e($termoBusca) ?>"
                                data-counted="<?= $ja > 0 ? '1' : '0' ?>">
                                <td class="sl-nome">
                                    <span class="sl-nome-main"><?= e($nomeProduto) ?></span>
                                    <?php if (($lote['codigo'] ?? '') !== ''): ?>
                                        <span class="sl-cod mono"><?= e($lote['codigo']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="pl-col-lote mono"><?= e($numlote) ?></td>
                                <td class="pl-col-validade"><?= e($validade) ?></td>
                                <td class="pl-col-saldo"><?= e(formatar_quantidade($saldo)) ?></td>
                                <td class="sl-col-ja pl-ja"><?= $ja > 0 ? e(formatar_quantidade($ja)) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="pl-pane pl-pane-form panel" aria-labelledby="pl-form-titulo">
            <h2 id="pl-form-titulo" class="pl-pane-title">Incluir</h2>

            <p class="pl-form-vazio" id="pl-form-vazio">
                Clique num lote da lista — ou digite no campo de busca e pressione
                <kbd>Enter</kbd> para pegar o primeiro resultado.
            </p>

            <form id="pl-form" hidden autocomplete="off">
                <dl class="pl-dados">
                    <dt>Produto</dt><dd id="pl-f-nome" class="pl-f-nome"></dd>
                    <dt>Lote</dt><dd id="pl-f-lote" class="mono"></dd>
                    <dt>Validade</dt><dd id="pl-f-validade"></dd>
                    <dt>Saldo</dt><dd id="pl-f-saldo"></dd>
                    <dt>Já contado</dt><dd id="pl-f-ja"></dd>
                </dl>

                <div class="form-group">
                    <label for="pl-f-qtd" class="form-label">Quantidade contada</label>
                    <input type="text" id="pl-f-qtd" class="form-control pl-f-qtd"
                           inputmode="decimal" autocomplete="off" placeholder="0">
                </div>

                <div class="pl-form-acoes">
                    <button type="submit" class="btn btn-primary" id="pl-f-gravar">Gravar</button>
                    <button type="button" class="btn btn-ghost" id="pl-f-cancelar">Cancelar</button>
                </div>
            </form>

            <p class="sl-hint pl-status" id="pl-status" role="status" aria-live="polite"></p>
        </aside>
    </div>

    <script>
    window.PL_CFG = {
        saveUrl: <?= json_encode(url('por-lote-salvar.php'), JSON_UNESCAPED_UNICODE) ?>,
        inventario: <?= json_encode($codinventario, JSON_UNESCAPED_UNICODE) ?>,
        codloc: <?= json_encode($codloc, JSON_UNESCAPED_UNICODE) ?>
    };
    </script>
    <script src="<?= e(url('assets/js/por-lote.js')) ?>"></script>
    <?php endif; ?>
</div>
