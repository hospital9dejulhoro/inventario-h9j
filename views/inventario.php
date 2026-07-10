<?php
/** @var string $codloc */
/** @var string $codinventario */
/** @var string $quantidade */
/** @var string $codigobarras */
/** @var ZMDCODBARRAS[] $registros */
/** @var bool $mostrarTabela */
/** @var bool $retomadoDaSessao */
/** @var bool $modoLeitura */
/** @var array|null $envAtual */
/** @var int $leiturasSessao */
/** @var array $recentInventarios */
/** @var string $nomeLocal */
/** @var string $locaisEstoqueJson */
$nomeLocal = $nomeLocal ?? '';
$locaisEstoqueJson = $locaisEstoqueJson ?? '{}';
?>

<script type="application/json" id="locais-estoque-data"><?= $locaisEstoqueJson ?></script>
<datalist id="locais-estoque-list">
    <?php foreach (LocaisEstoque::todos() as $codigo => $descricao): ?>
        <option value="<?= e($codigo) ?>"><?= e($codigo . ' — ' . $descricao) ?></option>
    <?php endforeach; ?>
</datalist>

<div class="page-wrap-wide inv-page">
    <header class="inv-page-header">
        <h1 class="page-title">Leitura de inventário</h1>
        <p class="page-subtitle">
            <?php if ($modoLeitura): ?>
                Altere o inventário na etapa 1 se precisar. Quantidade e leitura ficam na etapa 2.
            <?php else: ?>
                Informe o inventário abaixo para começar a leitura.
            <?php endif; ?>
        </p>
    </header>

    <?php if ($modoLeitura && $envAtual): ?>
    <div class="inv-stats-bar" aria-label="Resumo da sessão">
        <span><strong>Ambiente:</strong> <?= e($envAtual['label']) ?></span>
        <span><strong>Lidos agora:</strong> <span id="session-scan-count"><?= (int) $leiturasSessao ?></span></span>
        <span><strong>Total gravado:</strong> <?= count($registros) ?></span>
    </div>
    <?php endif; ?>

    <form action="inventario.php" method="get" autocomplete="off" id="inventory-form" class="inv-form">

        <?php if ($modoLeitura): ?>
        <section class="inv-section inv-section--config" aria-labelledby="secao-config">
            <div class="inv-section-head">
                <span class="inv-step">1</span>
                <div>
                    <h2 id="secao-config" class="section-title">Inventário e local</h2>
                    <p class="section-desc">Edite o código do inventário quando precisar trocar. Clique em <strong>Aplicar</strong> para confirmar.</p>
                </div>
            </div>
            <div class="inv-setup-row inv-setup-row--inventario">
                <div class="form-group">
                    <label for="CODINVENTARIO" class="form-label">Código do inventário</label>
                    <input type="text" name="CODINVENTARIO" id="CODINVENTARIO" class="form-control mono"
                           inputmode="numeric" maxlength="10" placeholder="26.065.002"
                           pattern="\d{2}\.\d{3}\.\d{3}"
                           title="Formato: AA.LLL.NNN — ano, local de estoque válido e número"
                           data-inventario-mask
                           value="<?= e($codinventario) ?>" required>
                    <span class="form-hint" id="inventario-mask-hint">Formato AA.LLL.NNN (ano.local.número)</span>
                </div>
                <div class="form-group">
                    <label for="CODLOC" class="form-label">Local de estoque</label>
                    <input type="text" name="CODLOC" id="CODLOC" class="form-control"
                           pattern=".{3,3}" maxlength="3" inputmode="numeric" readonly
                           title="Preenchido automaticamente pelo código do inventário (AA.LLL.NNN)"
                           value="<?= e($codloc) ?>" required>
                    <span class="form-hint" id="codloc-nome" data-codloc-nome><?= e($nomeLocal !== '' ? $nomeLocal : 'Informe o local no código do inventário') ?></span>
                </div>
            </div>
            <div class="inv-config-actions">
                <button type="submit" name="aplicar" value="1" class="btn btn-secondary" id="btn-aplicar">Aplicar inventário</button>
                <?php if (!empty($recentInventarios) && count($recentInventarios) > 1): ?>
                <div class="inv-status-recent inv-status-recent--inline">
                    <span class="inv-status-recent-label">Recentes:</span>
                    <?php foreach ($recentInventarios as $item): ?>
                        <a class="inv-status-recent-link <?= ($item['codinventario'] === $codinventario && $item['codloc'] === $codloc) ? 'is-active' : '' ?>"
                           href="inventario.php?<?= e(http_build_query([
                               'CODLOC' => $item['codloc'],
                               'CODINVENTARIO' => $item['codinventario'],
                               'QUANTIDADE' => $item['quantidade'],
                               'aplicar' => '1',
                           ])) ?>">
                            <?= e($item['codinventario']) ?>
                            <span class="inv-status-recent-meta">local <?= e($item['codloc']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="inv-section inv-section--scan" aria-labelledby="secao-leitura">
            <div class="inv-section-head">
                <span class="inv-step">2</span>
                <div>
                    <h2 id="secao-leitura" class="section-title">Ler código de barras</h2>
                    <p class="section-desc">Ajuste a quantidade se precisar e escaneie os 13 dígitos (Enter para gravar).</p>
                </div>
            </div>
            <div class="inv-scan-row">
                <div class="form-group inv-qtd-group">
                    <label for="QUANTIDADE" class="form-label">Quantidade</label>
                    <input type="text" name="QUANTIDADE" id="QUANTIDADE" class="form-control inv-qtd-input"
                           inputmode="numeric" value="<?= e($quantidade) ?>" required>
                </div>
                <div class="form-group inv-barcode-group">
                    <label for="CODIGOBARRAS" class="form-label">Código de barras</label>
                    <input type="text" name="CODIGOBARRAS" id="CODIGOBARRAS" class="form-control inv-barcode-input"
                           maxlength="13" inputmode="numeric"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                           value="" placeholder="0000000000000" autofocus>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" id="btn-registrar">Registrar leitura</button>
        </section>

        <?php else: ?>
        <section class="inv-section inv-section--setup panel" aria-labelledby="secao-iniciar">
            <div class="inv-section-head">
                <span class="inv-step">1</span>
                <div>
                    <h2 id="secao-iniciar" class="section-title">Iniciar inventário</h2>
                    <p class="section-desc">
                        <?php if ($retomadoDaSessao): ?>
                            Dados do último inventário carregados. Confirme ou altere e clique em Aplicar.
                        <?php else: ?>
                            Informe o código do inventário (AA.LLL.NNN). O local é preenchido automaticamente.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="inv-setup-row inv-setup-row--inventario">
                <div class="form-group">
                    <label for="CODINVENTARIO" class="form-label">Código do inventário</label>
                    <input type="text" name="CODINVENTARIO" id="CODINVENTARIO" class="form-control mono"
                           inputmode="numeric" maxlength="10" placeholder="26.065.002"
                           pattern="\d{2}\.\d{3}\.\d{3}"
                           title="Formato: AA.LLL.NNN — ano, local de estoque válido e número"
                           data-inventario-mask
                           value="<?= e($codinventario) ?>" required autofocus>
                    <span class="form-hint" id="inventario-mask-hint">Formato AA.LLL.NNN (ano.local.número)</span>
                </div>
                <div class="form-group">
                    <label for="CODLOC" class="form-label">Local de estoque</label>
                    <input type="text" name="CODLOC" id="CODLOC" class="form-control"
                           pattern=".{3,3}" maxlength="3" inputmode="numeric" readonly
                           title="Preenchido automaticamente pelo código do inventário (AA.LLL.NNN)"
                           value="<?= e($codloc) ?>" required>
                    <span class="form-hint" id="codloc-nome" data-codloc-nome><?= e($nomeLocal !== '' ? $nomeLocal : 'Informe o local no código do inventário') ?></span>
                </div>
            </div>
            <input type="hidden" name="QUANTIDADE" value="<?= e($quantidade !== '' ? $quantidade : '1') ?>">
            <button type="submit" name="aplicar" value="1" class="btn btn-primary">Aplicar e começar leitura</button>
        </section>
        <?php endif; ?>
    </form>

    <section class="inv-section inv-section--records panel panel-flush" aria-labelledby="secao-registros">
        <div class="panel-header">
            <div class="inv-section-head inv-section-head--compact inv-section-head--with-action">
                <span class="inv-step inv-step--muted"><?= $modoLeitura ? '3' : '2' ?></span>
                <div class="inv-section-head-text">
                    <h2 id="secao-registros" class="section-title">Itens gravados</h2>
                    <p class="section-desc">
                        <?php if ($mostrarTabela): ?>
                            Registros do inventário <strong><?= e($codinventario) ?></strong> no banco
                            (<?= count($registros) ?> <?= count($registros) === 1 ? 'item' : 'itens' ?>).
                        <?php else: ?>
                            A lista aparece depois de aplicar um inventário.
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($mostrarTabela && $codinventario !== ''): ?>
                <form action="inventario-item.php" method="post" class="inv-delete-inventario-form"
                      onsubmit="return confirm('Excluir o inventário <?= e($codinventario) ?> e todos os <?= count($registros) ?> itens gravados?\n\nEsta ação não pode ser desfeita.');">
                    <input type="hidden" name="acao" value="excluir_inventario">
                    <input type="hidden" name="CODINVENTARIO" value="<?= e($codinventario) ?>">
                    <input type="hidden" name="CODLOC" value="<?= e($codloc) ?>">
                    <input type="hidden" name="QUANTIDADE" value="<?= e($quantidade) ?>">
                    <button type="submit" class="btn btn-danger">Excluir inventário</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table" id="registros-table">
                <thead>
                <tr>
                    <th>Código de barras</th>
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
                    <tr><td colspan="7" class="empty">Nenhum item gravado ainda neste inventário.</td></tr>
                <?php else: ?>
                    <tr><td colspan="7" class="empty">Aplique um inventário para ver os itens aqui.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
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
                       maxlength="3" pattern=".{3,3}" required inputmode="numeric"
                       list="locais-estoque-list"
                       oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                <span class="form-hint" id="edit-loc-nome"></span>
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>
