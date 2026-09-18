<?php
/** @var string $codloc */
/** @var string $nomeLocal */
/** @var bool $localValido */
/** @var string $busca */
/** @var string $grupoContabil */
/** @var bool $somenteComSaldo */
/** @var bool $truncado */
/** @var array $linhas */
/** @var array<string, string> $grupos */
/** @var array $totais */
/** @var array|null $envAtual */

$moeda = function ($v) {
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
};
?>

<div class="page-wrap-wide inv-page rpt-page pos-page">
    <header class="inv-page-header">
        <h1 class="page-title">Posição de estoque</h1>
        <p class="page-subtitle">
            Saldo atual do local — com lote e sem lote —, com valor financeiro pelo
            custo médio. Sem data de referência: é a posição de agora.
            <?php if ($envAtual): ?>
                Ambiente: <strong><?= e($envAtual['label']) ?></strong>.
            <?php endif; ?>
        </p>
    </header>

    <section class="inv-section panel" aria-labelledby="secao-local">
        <div class="inv-section-head">
            <span class="inv-step">1</span>
            <div>
                <h2 id="secao-local" class="section-title">Local de estoque</h2>
                <p class="section-desc">Escolha o local e gere. Nada mais é necessário.</p>
            </div>
        </div>

        <form action="<?= e(url('posicao.php')) ?>" method="get" class="rpt-filter" autocomplete="off">
            <div class="pl-filtro-linha">
                <label class="pl-filtro-campo">
                    <span>Local</span>
                    <select name="CODLOC" class="form-control" required>
                        <option value="">Escolha o local...</option>
                        <?php foreach (LocaisEstoque::todos() as $cod => $descricao): ?>
                            <option value="<?= e($cod) ?>" <?= $cod === $codloc ? 'selected' : '' ?>>
                                <?= e($cod . ' — ' . $descricao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <?php if ($localValido && $grupos !== []): ?>
                <label class="pl-filtro-campo">
                    <span>Grupo contábil</span>
                    <select name="grupo" class="form-control">
                        <option value="">Todos os grupos</option>
                        <?php foreach ($grupos as $cod => $descricao): ?>
                            <option value="<?= e($cod) ?>" <?= $cod === $grupoContabil ? 'selected' : '' ?>>
                                <?= e($descricao !== '' ? $cod . ' — ' . $descricao : $cod) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>

                <label class="sl-check">
                    <input type="checkbox" name="todos" value="1" <?= $somenteComSaldo ? '' : 'checked' ?>>
                    Incluir lotes zerados
                </label>
            </div>

            <div class="pl-filtro-linha">
                <input type="search" name="q" class="form-control sl-busca" value="<?= e($busca) ?>"
                       autocomplete="off" placeholder="Filtrar por lote, produto ou código (opcional)">
            </div>

            <div class="rpt-filter-actions">
                <button type="submit" class="btn btn-primary">Gerar posição</button>
                <?php if ($localValido): ?>
                    <a class="btn btn-secondary" href="<?= e(url('posicao.php?' . http_build_query(array_filter([
                        'CODLOC' => $codloc,
                        'grupo'  => $grupoContabil,
                        'q'      => $busca,
                        'todos'  => $somenteComSaldo ? null : '1',
                        'export' => 'csv',
                    ], function ($v) { return $v !== null && $v !== ''; })))) ?>">Exportar CSV</a>
                    <a class="btn btn-primary" href="<?= e(url('posicao.php?' . http_build_query(array_filter([
                        'CODLOC' => $codloc,
                        'grupo'  => $grupoContabil,
                        'q'      => $busca,
                        'todos'  => $somenteComSaldo ? null : '1',
                        'export' => 'pdf',
                    ], function ($v) { return $v !== null && $v !== ''; })))) ?>">Imprimir PDF (A4)</a>
                    <button type="button" class="btn btn-ghost" onclick="window.print()">Imprimir</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <?php if ($codloc !== '' && !$localValido): ?>
        <section class="inv-section panel">
            <p class="empty">Local <?= e($codloc) ?> não está cadastrado na lista de locais do sistema.</p>
        </section>
    <?php elseif ($localValido): ?>

        <section class="inv-section panel rpt-summary" aria-labelledby="secao-totais">
            <div class="inv-section-head">
                <span class="inv-step">2</span>
                <div>
                    <h2 id="secao-totais" class="section-title">
                        Resumo · <?= e($codloc) ?><?= $nomeLocal !== '' ? ' — ' . e($nomeLocal) : '' ?>
                    </h2>
                    <p class="section-desc">
                        Gerado em <?= e(date('d/m/Y H:i')) ?>
                        <?php if ($grupoContabil !== ''): ?>
                            · grupo <?= e($grupoContabil) ?>
                        <?php endif; ?>
                        <?php if (!$somenteComSaldo): ?>
                            · incluindo lotes zerados
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="rpt-kpis">
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= (int) $totais['produtos'] ?></span>
                    <span class="rpt-kpi-label">Produtos</span>
                </div>
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= (int) $totais['lotes'] ?></span>
                    <span class="rpt-kpi-label">Lotes</span>
                </div>
                <?php /* Produto sem lote nao e lote nenhum, mas tambem esta no
                         local e conta no saldo — vale ver os dois numeros. */ ?>
                <?php if ((int) ($totais['sem_lote'] ?? 0) > 0): ?>
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= (int) $totais['sem_lote'] ?></span>
                    <span class="rpt-kpi-label">Sem lote</span>
                </div>
                <?php endif; ?>
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= e(formatar_quantidade($totais['quantidade'])) ?></span>
                    <span class="rpt-kpi-label">Quantidade</span>
                </div>
                <div class="rpt-kpi">
                    <span class="rpt-kpi-value"><?= e($moeda($totais['valor'])) ?></span>
                    <span class="rpt-kpi-label">Saldo financeiro</span>
                </div>
            </div>
        </section>

        <?php if ($truncado): ?>
            <p class="sl-hint is-err">
                A posição bateu o teto de <?= (int) (InventarioRM::LIMITE_LOTES + InventarioRM::LIMITE_ITENS) ?> linhas e foi cortada —
                os totais acima estão incompletos. Estreite por grupo contábil.
            </p>
        <?php endif; ?>

        <section class="inv-section panel panel-flush" aria-labelledby="secao-linhas">
            <div class="panel-header">
                <div class="inv-section-head inv-section-head--compact">
                    <span class="inv-step inv-step--muted">3</span>
                    <div>
                        <h2 id="secao-linhas" class="section-title">Posição por lote</h2>
                        <p class="section-desc">Ordenado por produto e lote. Produto sem lote aparece com &mdash; nas colunas de lote.</p>
                    </div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table pos-table">
                    <thead>
                    <tr>
                        <th>Produto</th>
                        <th>ID</th>
                        <th>Grupo</th>
                        <th>Lote</th>
                        <th>Validade</th>
                        <th>Und</th>
                        <th class="pos-num">Saldo</th>
                        <th class="pos-num">Custo médio</th>
                        <th class="pos-num">Valor</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($linhas === []): ?>
                        <tr><td colspan="9" class="empty">
                            <?= $busca !== '' || $grupoContabil !== ''
                                ? 'Nenhuma linha para esses filtros.'
                                : 'Nenhum lote com saldo neste local.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($linhas as $linha): ?>
                            <tr>
                                <td>
                                    <?= e($linha['nome'] !== '' ? $linha['nome'] : 'ID ' . $linha['idprd']) ?>
                                    <?php if (($linha['codigo'] ?? '') !== ''): ?>
                                        <span class="sl-cod mono"><?= e($linha['codigo']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="mono"><?= (int) $linha['idprd'] ?></td>
                                <td><?= e($linha['grupo_nome'] !== '' ? $linha['grupo_nome'] : ($linha['grupo_cod'] !== '' ? $linha['grupo_cod'] : '—')) ?></td>
                                <td class="mono"><?= e($linha['numlote'] !== '' ? $linha['numlote'] : '—') ?></td>
                                <td><?= e($linha['validade'] !== '' ? $linha['validade'] : '—') ?></td>
                                <td><?= e($linha['und']) ?></td>
                                <td class="pos-num"><?= e(formatar_quantidade($linha['saldo'])) ?></td>
                                <td class="pos-num"><?= e($moeda($linha['custo_medio'])) ?></td>
                                <td class="pos-num"><strong><?= e($moeda($linha['saldo_financeiro'])) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="pos-total">
                            <td colspan="6"><strong>Total</strong></td>
                            <td class="pos-num"><strong><?= e(formatar_quantidade($totais['quantidade'])) ?></strong></td>
                            <td class="pos-num"></td>
                            <td class="pos-num"><strong><?= e($moeda($totais['valor'])) ?></strong></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
