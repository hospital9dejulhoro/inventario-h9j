<?php
/** @var string $codloc */
/** @var string $codinventario */
/** @var string $quantidade */
/** @var string $codigobarras */
/** @var ZMDCODBARRAS[] $registros */
/** @var bool $mostrarTabela */
/** @var bool $retomadoDaSessao */
?>

<div class="page-wrap-wide">
    <div class="inv-grid">
        <div class="panel">
            <div class="panel-header">
                <h2>Leitura</h2>
                <p>
                    <?php if ($retomadoDaSessao && $codinventario): ?>
                        Retomando inventário <strong><?= e($codinventario) ?></strong>.
                    <?php else: ?>
                        Escaneie o código de barras após preencher os campos.
                    <?php endif; ?>
                </p>
            </div>

            <form action="inventario.php" method="get" autocomplete="off" id="inventory-form">
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
                    <label for="QUANTIDADE" class="form-label">Quantidade</label>
                    <input type="text" name="QUANTIDADE" id="QUANTIDADE" class="form-control"
                           value="<?= e($quantidade) ?>" required>
                </div>

                <div class="form-group">
                    <label for="CODIGOBARRAS" class="form-label">Código de barras</label>
                    <input type="text" name="CODIGOBARRAS" id="CODIGOBARRAS" class="form-control"
                           pattern=".{13,13}" maxlength="13"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                           value="<?= e($codigobarras) ?>" required autofocus>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Registrar</button>
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
                                Informe o inventário para listar.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($mostrarTabela): ?>
                        <span class="count-label"><?= count($registros) ?> registro(s)</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table">
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
                                            data-loc="<?= e($cod->getCodloc()) ?>">
                                        Editar
                                    </button>
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
                        <tr>
                            <td colspan="7" class="empty">Nenhum registro neste inventário.</td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty">Aguardando inventário.</td>
                        </tr>
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
