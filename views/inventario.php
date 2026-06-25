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
                    <input type="text"
                           name="CODLOC"
                           id="CODLOC"
                           class="form-control"
                           pattern=".{3,3}"
                           maxlength="3"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                           value="<?= e($codloc) ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="CODINVENTARIO" class="form-label">Código do inventário</label>
                    <input type="text"
                           name="CODINVENTARIO"
                           id="CODINVENTARIO"
                           class="form-control"
                           value="<?= e($codinventario) ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="QUANTIDADE" class="form-label">Quantidade</label>
                    <input type="text"
                           name="QUANTIDADE"
                           id="QUANTIDADE"
                           class="form-control"
                           value="<?= e($quantidade) ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="CODIGOBARRAS" class="form-label">Código de barras</label>
                    <input type="text"
                           name="CODIGOBARRAS"
                           id="CODIGOBARRAS"
                           class="form-control"
                           pattern=".{13,13}"
                           maxlength="13"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                           value="<?= e($codigobarras) ?>"
                           required
                           autofocus>
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
                        <th>ID</th>
                        <th>Inventário</th>
                        <th>Barras</th>
                        <th>Qtd</th>
                        <th>Local</th>
                        <th>Produto</th>
                        <th>Und</th>
                        <th>Lote</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($mostrarTabela && !empty($registros)): ?>
                        <?php foreach ($registros as $cod): ?>
                            <tr>
                                <td><?= e($cod->getId()) ?></td>
                                <td><?= e($cod->getCodinventario()) ?></td>
                                <td class="mono"><?= e($cod->getCodigobarras()) ?></td>
                                <td><?= e($cod->getQuantidade()) ?></td>
                                <td><?= e($cod->getCodloc()) ?></td>
                                <td><?= e($cod->getNome()) ?></td>
                                <td><?= e($cod->getUnd()) ?></td>
                                <td><?= e($cod->getNumlote()) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php elseif ($mostrarTabela): ?>
                        <tr>
                            <td colspan="8" class="empty">Nenhum registro neste inventário.</td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty">Aguardando inventário.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
