<?php
/** @var string $codinventario */
/** @var string $codloc */
/** @var string $nomeLocal */
/** @var string $rmStatus */
/** @var array $relatorio */
/** @var array $inventarios */
/** @var array|null $envAtual */
$totais = $relatorio['totais'] ?? ['bipagens' => 0, 'quantidade' => 0, 'produtos' => 0, 'lotes' => 0];
$itens = $relatorio['itens'] ?? [];
?>

<div class="page-wrap-wide inv-page rpt-page">
    <header class="inv-page-header">
        <h1 class="page-title">Relatório de contagem</h1>
        <p class="page-subtitle">
            Totais do que já foi bipado neste inventário (tabela ZMDCODBARRAS).
            <?php if ($envAtual): ?>
                Ambiente: <strong><?= e($envAtual['label']) ?></strong>.
            <?php endif; ?>
        </p>
    </header>

    <section class="inv-section panel" aria-labelledby="secao-filtro">
        <div class="inv-section-head">
            <span class="inv-step">1</span>
            <div>
                <h2 id="secao-filtro" class="section-title">Inventário</h2>
                <p class="section-desc">Escolha um inventário com bipagens ou informe o código AA.LLL.NNN.</p>
            </div>
        </div>

        <form action="<?= e(url('relatorio.php')) ?>" method="get" class="rpt-filter" autocomplete="off">
            <div class="inv-setup-row inv-setup-row--inventario">
                <div class="form-group">
                    <label for="CODINVENTARIO" class="form-label">Código do inventário</label>
                    <input type="text" name="CODINVENTARIO" id="CODINVENTARIO" class="form-control mono"
                           inputmode="numeric" maxlength="10" placeholder="26.065.002"
                           pattern="\d{2}\.\d{3}\.\d{3}"
                           data-inventario-mask
                           value="<?= e($codinventario) ?>" required>
                </div>
            </div>
            <div class="rpt-filter-actions">
                <button type="submit" class="btn btn-primary">Gerar relatório</button>
                <?php if ($codinventario !== ''): ?>
                    <a class="btn btn-primary" href="<?= e(url('relatorio.php?' . http_build_query(['CODINVENTARIO' => $codinventario, 'export' => 'pdf']))) ?>">Exportar PDF (A4)</a>
                    <a class="btn btn-secondary" href="<?= e(url('relatorio.php?' . http_build_query(['CODINVENTARIO' => $codinventario, 'export' => 'csv']))) ?>">Exportar CSV</a>
                    <button type="button" class="btn btn-ghost" onclick="window.print()">Imprimir</button>
                    <a class="btn btn-ghost" href="<?= e(url('inventario.php?' . http_build_query(['CODINVENTARIO' => $codinventario, 'aplicar' => '1']))) ?>">Ir para leitura</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($inventarios !== []): ?>
            <p class="section-desc rpt-pick-label">Inventários com contagem:</p>
            <ul class="inv-open-items rpt-pick">
                <?php foreach ($inventarios as $inv): ?>
                    <li>
                        <a class="inv-open-item <?= ($inv['codinventario'] === $codinventario) ? 'is-active' : '' ?>"
                           href="<?= e(url('relatorio.php?' . http_build_query(['CODINVENTARIO' => $inv['codinventario']]))) ?>">
                            <span class="inv-open-main">
                                <strong class="mono"><?= e($inv['codinventario']) ?></strong>
                            </span>
                            <span class="inv-open-meta">
                                <?php if ($inv['codloc'] !== ''): ?>
                                    Local <?= e($inv['codloc']) ?>
                                    <?php if ($inv['local_nome'] !== ''): ?>
                                        — <?= e($inv['local_nome']) ?>
                                    <?php endif; ?>
                                    ·
                                <?php endif; ?>
                                <?= (int) $inv['bipagens'] ?> bipagens
                                · qtd <?= e(rtrim(rtrim(number_format((float) $inv['quantidade'], 3, ',', '.'), '0'), ',')) ?>
                            </span>
                            <span class="inv-open-action">Ver</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($codinventario !== ''): ?>
        <section class="inv-section panel rpt-summary" aria-labelledby="secao-totais">
            <div class="inv-section-head">
                <span class="inv-step">2</span>
                <div>
                    <h2 id="secao-totais" class="section-title">Resumo · <?= e($codinventario) ?></h2>
                    <p class="section-desc">
                        <?php if ($codloc !== ''): ?>
                            Local <?= e($codloc) ?><?= $nomeLocal !== '' ? ' — ' . e($nomeLocal) : '' ?>
                        <?php endif; ?>
                        <?php if ($rmStatus !== ''): ?>
                            · Status RM: <?= e($rmStatus) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="rpt-kpis">
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= (int) $totais['bipagens'] ?></span>
                    <span class="rpt-kpi-label">Bipagens</span>
                </div>
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= e(rtrim(rtrim(number_format((float) $totais['quantidade'], 3, ',', '.'), '0'), ',')) ?></span>
                    <span class="rpt-kpi-label">Quantidade total</span>
                </div>
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= (int) $totais['produtos'] ?></span>
                    <span class="rpt-kpi-label">Produtos</span>
                </div>
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= (int) $totais['lotes'] ?></span>
                    <span class="rpt-kpi-label">Lotes</span>
                </div>
            </div>
        </section>

        <section class="inv-section panel panel-flush" aria-labelledby="secao-itens">
            <div class="panel-header">
                <div class="inv-section-head inv-section-head--compact">
                    <span class="inv-step inv-step--muted">3</span>
                    <div>
                        <h2 id="secao-itens" class="section-title">Itens contados</h2>
                        <p class="section-desc">Agrupado por produto, lote e local. Quantidade é a soma das bipagens.</p>
                    </div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Produto</th>
                        <th>ID</th>
                        <th>Lote</th>
                        <th>Local</th>
                        <th>Und</th>
                        <th>Bipagens</th>
                        <th>Quantidade</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($itens === []): ?>
                        <tr><td colspan="7" class="empty">Nenhuma bipagem neste inventário.</td></tr>
                    <?php else: ?>
                        <?php foreach ($itens as $item): ?>
                            <tr>
                                <td><?= e($item['nome'] !== '' ? $item['nome'] : '—') ?></td>
                                <td class="mono"><?= (int) $item['idprd'] ?></td>
                                <td class="mono"><?= e($item['lote'] !== '' ? $item['lote'] : '—') ?></td>
                                <td><?= e($item['codloc']) ?></td>
                                <td><?= e($item['und']) ?></td>
                                <td><?= (int) $item['bipagens'] ?></td>
                                <td><strong><?= e(rtrim(rtrim(number_format((float) $item['quantidade'], 3, ',', '.'), '0'), ',')) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
