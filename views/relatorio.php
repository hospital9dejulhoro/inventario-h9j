<?php
/** @var string $codinventario */
/** @var string $codloc */
/** @var string $nomeLocal */
/** @var string $rmStatus */
/** @var array $relatorio */
/** @var array $inventarios */
/** @var bool $verConferencia */
/** @var array $conferencia */
/** @var array|null $envAtual */
$totais = $relatorio['totais'] ?? ['bipagens' => 0, 'quantidade' => 0, 'produtos' => 0, 'lotes' => 0];
$itens = $relatorio['itens'] ?? [];
$verConferencia = !empty($verConferencia);
$conf = $conferencia['itens'] ?? [];
$confTotais = $conferencia['totais'] ?? [];
$rotuloSituacao = ['contado' => 'Contado', 'nao_contado' => 'Não contado', 'sobra' => 'Sobra'];
$fmtQ = function ($v) { return rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ','); };
$fmtMoeda = function ($v) { return 'R$ ' . number_format((float) $v, 2, ',', '.'); };
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
                    <span class="rpt-actions-sep" aria-hidden="true"></span>
                    <a class="btn btn-secondary<?= $verConferencia ? ' is-nav-on' : '' ?>" href="<?= e(url('relatorio.php?' . http_build_query(['CODINVENTARIO' => $codinventario, 'conferencia' => '1']))) ?>">Conferência do local</a>
                    <?php if ($verConferencia): ?>
                        <a class="btn btn-secondary" href="<?= e(url('relatorio.php?' . http_build_query(['CODINVENTARIO' => $codinventario, 'export' => 'conferencia']))) ?>">CSV da conferência</a>
                    <?php endif; ?>
                    <span class="rpt-actions-sep" aria-hidden="true"></span>
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
                        <p class="section-desc">Agrupado por produto, lote e local. A quantidade soma todos os lançamentos do item.</p>
                    </div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th class="rpt-col-min">Código</th>
                        <th>Produto</th>
                        <th class="rpt-col-min">ID</th>
                        <th class="rpt-col-min">Lote</th>
                        <th class="rpt-col-min">Local</th>
                        <th class="rpt-col-min">Und</th>
                        <th class="rpt-col-min num">Quantidade</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($itens === []): ?>
                        <tr><td colspan="7" class="empty">Nenhuma bipagem neste inventário.</td></tr>
                    <?php else: ?>
                        <?php foreach ($itens as $item): ?>
                            <tr>
                                <td class="mono rpt-col-min"><?= e(($item['codigo'] ?? '') !== '' ? $item['codigo'] : '—') ?></td>
                                <td class="rpt-col-desc"><?= e($item['nome'] !== '' ? $item['nome'] : '—') ?></td>
                                <td class="mono rpt-col-min"><?= (int) $item['idprd'] ?></td>
                                <td class="mono rpt-col-min"><?= e($item['lote'] !== '' ? $item['lote'] : '—') ?></td>
                                <td class="rpt-col-min"><?= e($item['codloc']) ?></td>
                                <td class="rpt-col-min"><?= e($item['und']) ?></td>
                                <td class="rpt-col-min num"><strong><?= e(rtrim(rtrim(number_format((float) $item['quantidade'], 3, ',', '.'), '0'), ',')) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($verConferencia): ?>
        <section class="inv-section panel panel-flush rpt-conf" aria-labelledby="secao-conferencia">
            <div class="panel-header">
                <div class="inv-section-head inv-section-head--compact">
                    <span class="inv-step inv-step--muted">4</span>
                    <div>
                        <h2 id="secao-conferencia" class="section-title">Conferência do local <?= e($codloc) ?></h2>
                        <p class="section-desc">
                            Posição de estoque do local contra o que foi contado. Pendências primeiro.
                            Lotes zerados no sistema não entram como pendência — se algum foi contado, aparece como sobra.
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($codloc === ''): ?>
                <p class="empty">Informe um inventário no formato AA.LLL.NNN para saber o local.</p>
            <?php else: ?>

            <div class="rpt-kpis">
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= (int) ($confTotais['esperados'] ?? 0) ?></span>
                    <span class="rpt-kpi-label">Esperados no local</span>
                </div>
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= (int) ($confTotais['contados'] ?? 0) ?></span>
                    <span class="rpt-kpi-label">Contados</span>
                </div>
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value rpt-kpi-alerta"><?= (int) ($confTotais['nao_contados'] ?? 0) ?></span>
                    <span class="rpt-kpi-label">Não contados</span>
                </div>
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= (int) ($confTotais['sobras'] ?? 0) ?></span>
                    <span class="rpt-kpi-label">Sobras</span>
                </div>
                <?php $valorDif = (float) ($confTotais['valor_diferenca'] ?? 0); ?>
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value <?= $valorDif < 0 ? 'rpt-dif-neg' : ($valorDif > 0 ? 'rpt-dif-pos' : '') ?>">
                        <?= e($fmtMoeda($valorDif)) ?>
                    </span>
                    <span class="rpt-kpi-label">Valor da diferença</span>
                </div>
            </div>

            <?php if (!empty($conferencia['truncado'])): ?>
                <p class="sl-hint is-err">
                    A posição do local bateu o teto de <?= (int) InventarioRM::LIMITE_LOTES ?> linhas com lote
                    ou <?= (int) InventarioRM::LIMITE_ITENS ?> sem lote e foi cortada —
                    a lista de não contados pode estar incompleta.
                </p>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th class="rpt-col-min">Situação</th>
                        <th>Produto</th>
                        <th class="rpt-col-min">Lote</th>
                        <th class="rpt-col-min">Validade</th>
                        <th class="rpt-col-min">Grupo</th>
                        <th class="rpt-col-min">Und</th>
                        <th class="rpt-col-min num">Saldo</th>
                        <th class="rpt-col-min num">Contado</th>
                        <th class="rpt-col-min num">Diferença</th>
                        <th class="rpt-col-min num">Valor dif.</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($conf === []): ?>
                        <tr><td colspan="10" class="empty">Nada a conferir: o local não tem saldo e nada foi contado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($conf as $item):
                            $dif = (float) $item['diferenca'];
                            $classeDif = $dif < 0 ? 'rpt-dif-neg' : ($dif > 0 ? 'rpt-dif-pos' : '');
                            ?>
                            <tr>
                                <td><span class="rpt-sit rpt-sit-<?= e($item['situacao']) ?>"><?= e($rotuloSituacao[$item['situacao']] ?? $item['situacao']) ?></span></td>
                                <td>
                                    <?= e($item['nome'] !== '' ? $item['nome'] : 'ID ' . $item['idprd']) ?>
                                    <?php if (($item['codigo'] ?? '') !== ''): ?>
                                        <span class="sl-cod mono"><?= e($item['codigo']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="mono"><?= e($item['numlote'] !== '' ? $item['numlote'] : '—') ?></td>
                                <td><?= e($item['validade'] !== '' ? $item['validade'] : '—') ?></td>
                                <td><?= e($item['grupo'] !== '' ? $item['grupo'] : '—') ?></td>
                                <td><?= e($item['und']) ?></td>
                                <td class="num"><?= e($fmtQ($item['saldo'])) ?></td>
                                <td class="num"><?= $item['situacao'] === 'nao_contado' ? '—' : e($fmtQ($item['contado'])) ?></td>
                                <td class="num <?= $classeDif ?>">
                                    <?= $item['situacao'] === 'nao_contado' ? '—' : ($dif > 0 ? '+' : '') . e($fmtQ($dif)) ?>
                                </td>
                                <td class="num <?= $classeDif ?>">
                                    <?php $vd = (float) $item['valor_diferenca']; ?>
                                    <?= $item['situacao'] === 'contado' && $vd != 0.0 ? e($fmtMoeda($vd)) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

    <?php endif; ?>
</div>
