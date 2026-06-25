<?php
/** @var string $codloc */
/** @var string $codinventario */
/** @var string $quantidade */
/** @var string $codigobarras */
/** @var ZMDCODBARRAS[] $registros */
/** @var bool $mostrarTabela */
/** @var bool $retomadoDaSessao */
/** @var bool $modoLeitura */
/** @var bool $showInventorySummary */
/** @var array|null $envAtual */
/** @var int $leiturasSessao */
/** @var array $recentInventarios */
?>

<?php if ($showInventorySummary && $envAtual): ?>
<div class="inv-summary">
    <div class="inv-summary-inner">
        <div class="inv-summary-item">
            <span class="inv-summary-label">Inventário</span>
            <strong><?= e($codinventario) ?></strong>
        </div>
        <div class="inv-summary-item">
            <span class="inv-summary-label">Local</span>
            <strong><?= e($codloc) ?></strong>
        </div>
        <div class="inv-summary-item">
            <span class="inv-summary-label">Ambiente</span>
            <strong><?= e($envAtual['label']) ?></strong>
        </div>
        <div class="inv-summary-item">
            <span class="inv-summary-label">Lidos agora</span>
            <strong id="session-scan-count"><?= (int) $leiturasSessao ?></strong>
        </div>
        <div class="inv-summary-item">
            <span class="inv-summary-label">No banco</span>
            <strong><?= count($registros) ?></strong>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="page-wrap-wide">
    <?php if (!empty($recentInventarios) && count($recentInventarios) > 1): ?>
    <div class="recent-chips">
        <span class="recent-chips-label">Recentes:</span>
        <?php foreach ($recentInventarios as $item): ?>
            <a class="recent-chip <?= ($item['codinventario'] === $codinventario && $item['codloc'] === $codloc) ? 'is-active' : '' ?>"
               href="inventario.php?<?= e(http_build_query([
                   'CODLOC' => $item['codloc'],
                   'CODINVENTARIO' => $item['codinventario'],
                   'QUANTIDADE' => $item['quantidade'],
                   'aplicar' => '1',
               ])) ?>">
                <?= e($item['codinventario']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="inv-grid">
        <div class="panel">
            <div class="panel-header">
                <h2><?= $modoLeitura ? 'Modo leitura' : 'Iniciar inventário' ?></h2>
                <p>
                    <?php if ($modoLeitura): ?>
                        Escaneie o código de barras. Local, inventário e quantidade permanecem fixos.
                    <?php elseif ($retomadoDaSessao): ?>
                        Retomando inventário <strong><?= e($codinventario) ?></strong>.
                    <?php else: ?>
                        Informe os dados e clique em Aplicar inventário.
                    <?php endif; ?>
                </p>
            </div>

            <form action="inventario.php" method="get" autocomplete="off" id="inventory-form">
                <details class="inv-setup-details" <?= $modoLeitura ? '' : 'open' ?>>
                    <summary class="inv-setup-toggle">Local e inventário</summary>
                    <div class="inv-setup-fields">
                        <div class="form-group">
                            <label for="CODLOC" class="form-label">Local de estoque</label>
                            <input type="text" name="CODLOC" id="CODLOC" class="form-control"
                                   pattern=".{3,3}" maxlength="3"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                   value="<?= e($codloc) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="CODINVENTARIO" class="form-label">Código do inventário</label>
                            <input type="text" name="CODINVENTARIO" id="CODINVENTARIO" class="form-control"
                                   value="<?= e($codinventario) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="QUANTIDADE" class="form-label">Quantidade padrão</label>
                            <input type="text" name="QUANTIDADE" id="QUANTIDADE" class="form-control"
                                   value="<?= e($quantidade) ?>" required>
                        </div>
                        <button type="submit" name="aplicar" value="1" class="btn btn-secondary btn-block">
                            Aplicar inventário
                        </button>
                    </div>
                </details>

                <?php if ($modoLeitura): ?>
                <div class="form-group inv-barcode-group">
                    <label for="CODIGOBARRAS" class="form-label">Código de barras</label>
                    <input type="text" name="CODIGOBARRAS" id="CODIGOBARRAS" class="form-control inv-barcode-input"
                           maxlength="13" inputmode="numeric"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                           value="" placeholder="Escaneie aqui" autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="btn-registrar">Registrar leitura</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="panel panel-flush">
            <div class="panel-header">
                <div class="panel-header-row">
                    <div>
                        <h2>Registros</h2>
                        <p>
                            <?php if ($mostrarTabela): ?>
                                Inventário <?= e($codinventario) ?>
                            <?php else: ?>
                                Aplique um inventário para listar.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table" id="registros-table">
                    <thead>
                    <tr>
                        <th>Barras</th>
                        <th>Qtd</th>
                        <th>Local</th>
                        <th>Produto</th>
                        <th>Und</th>
                        <th>Lote</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($mostrarTabela && !empty($registros)): ?>
                        <?php foreach ($registros as $cod): ?>
                            <tr>
                                <td class="mono"><?= e($cod->getCodigobarras()) ?></td>
                                <td><?= e($cod->getQuantidade()) ?></td>
                                <td><?= e($cod->getCodloc()) ?></td>
                                <td><?= e($cod->getNome()) ?></td>
                                <td><?= e($cod->getUnd()) ?></td>
                                <td><?= e($cod->getNumlote()) ?></td>
                                <td class="actions-cell">
                                    <button type="button" class="btn-link btn-edit-item"
                                            data-id="<?= e($cod->getId()) ?>"
                                            data-barras="<?= e($cod->getCodigobarras()) ?>"
                                            data-qtd="<?= e($cod->getQuantidade()) ?>"
                                            data-loc="<?= e($cod->getCodloc()) ?>">Editar</button>
                                    <form action="inventario-item.php" method="post" class="inline-form"
                                          onsubmit="return confirm('Excluir este registro?');">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= e($cod->getId()) ?>">
                                        <input type="hidden" name="CODLOC" value="<?= e($codloc) ?>">
                                        <input type="hidden" name="CODINVENTARIO" value="<?= e($codinventario) ?>">
                                        <input type="hidden" name="QUANTIDADE" value="<?= e($quantidade) ?>">
                                        <button type="submit" class="btn-link btn-link-danger">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php elseif ($mostrarTabela): ?>
                        <tr><td colspan="7" class="empty">Nenhum registro neste inventário.</td></tr>
                    <?php else: ?>
                        <tr><td colspan="7" class="empty">Aplique um inventário para começar.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="edit-modal" class="modal hidden" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-panel" role="dialog" aria-labelledby="edit-modal-title">
        <h3 id="edit-modal-title" class="modal-title">Editar registro</h3>
        <form action="inventario-item.php" method="post" id="edit-form">
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="id" id="edit-id">
            <input type="hidden" name="CODLOC" value="<?= e($codloc) ?>">
            <input type="hidden" name="CODINVENTARIO" value="<?= e($codinventario) ?>">
            <input type="hidden" name="QUANTIDADE" value="<?= e($quantidade) ?>">
            <div class="form-group">
                <label for="edit-barras" class="form-label">Código de barras</label>
                <input type="text" name="CODIGOBARRAS" id="edit-barras" class="form-control"
                       maxlength="13" pattern=".{13,13}" required
                       oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            </div>
            <div class="form-group">
                <label for="edit-qtd" class="form-label">Quantidade</label>
                <input type="text" name="ITEM_QUANTIDADE" id="edit-qtd" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="edit-loc" class="form-label">Local de estoque</label>
                <input type="text" name="ITEM_CODLOC" id="edit-loc" class="form-control"
                       maxlength="3" pattern=".{3,3}" required
                       oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>
