<?php
/** @var string $codloc */
/** @var string $codinventario */
/** @var string $quantidade */
/** @var string $codigobarras */
/** @var ZMDCODBARRAS[] $registros */
/** @var int $totalBipagens */
/** @var bool $listaTruncada */
/** @var int $qtdItensRm */
/** @var bool $mostrarTabela */
/** @var bool $retomadoDaSessao */
/** @var bool $modoLeitura */
/** @var array|null $envAtual */
/** @var int $leiturasSessao */
/** @var array $recentInventarios */
/** @var string $nomeLocal */
/** @var string $locaisEstoqueJson */
/** @var string $statusInventarioRm */
/** @var array $inventariosAbertos */
$nomeLocal = $nomeLocal ?? '';
$locaisEstoqueJson = $locaisEstoqueJson ?? '{}';
$qtdItensRm = (int) ($qtdItensRm ?? 0);
$totalBipagens = (int) ($totalBipagens ?? 0);
$listaTruncada = !empty($listaTruncada);
$statusInventarioRm = $statusInventarioRm ?? '';

// Quem nao pode apagar nao ve os botoes de apagar. Quem recusa de verdade e o
// backend; isto e para nao oferecer o que vai ser negado.
$podeExcluir = Permissoes::podeExcluir();
$inventariosAbertos = $inventariosAbertos ?? [];
$contagensAvulsas = $contagensAvulsas ?? [];
$avulso = !empty($avulso);
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
                Escolha um inventário em aberto ou informe o código para começar.
            <?php endif; ?>
        </p>
    </header>

    <?php if (!$modoLeitura): ?>
    <section class="inv-section panel inv-open-list" aria-labelledby="secao-abertos">
        <div class="inv-section-head">
            <span class="inv-step">0</span>
            <div>
                <h2 id="secao-abertos" class="section-title">Inventários em aberto</h2>
                <p class="section-desc">Status <strong>A</strong> no RM — toque para iniciar a leitura.</p>
            </div>
        </div>

        <?php if (empty($inventariosAbertos)): ?>
            <p class="inv-open-empty">Nenhum inventário em aberto no momento.</p>
        <?php else: ?>
            <ul class="inv-open-items">
                <?php foreach ($inventariosAbertos as $aberto): ?>
                    <li>
                        <a class="inv-open-item"
                           href="inventario.php?<?= e(http_build_query([
                               'CODINVENTARIO' => $aberto['codinventario'],
                               'CODLOC' => $aberto['codloc'],
                               'QUANTIDADE' => '1',
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
                                <?php if ($aberto['data'] !== ''): ?>
                                    · <?= e($aberto['data']) ?>
                                <?php endif; ?>
                            </span>
                            <span class="inv-open-action">Abrir</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php
    $destinoAvulsa = 'inventario.php';
    require __DIR__ . '/_avulsas.php';
    ?>
    <?php endif; ?>

    <?php if ($modoLeitura && $envAtual): ?>
    <div class="inv-stats-bar" aria-label="Resumo da sessão">
        <span><strong>Ambiente:</strong> <?= e($envAtual['label']) ?></span>
        <span><strong>Bipados agora:</strong> <span id="session-scan-count"><?= (int) $leiturasSessao ?></span></span>
        <span><strong>Bipados (total):</strong> <?= $totalBipagens ?></span>
        <?php if ($avulso): ?>
        <span class="pl-badge-avulsa" title="Código não existe em TINVENTARIO">Avulsa · fora do RM</span>
        <?php else: ?>
        <span><strong>Itens no RM:</strong> <?= (int) $qtdItensRm ?></span>
        <?php endif; ?>
        <?php if ($statusInventarioRm !== ''): ?>
        <span><strong>Status RM:</strong> <?= e(InventarioRM::rotuloStatus($statusInventarioRm)) ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php if (($somenteLeitura ?? false) && ($motivoBloqueio ?? '') !== ''): ?>
    <?php /* O bloqueio de verdade e no backend; isto e para nao deixar a pessoa
             contar uma prateleira inteira antes de descobrir. */ ?>
    <div class="flash-wrap">
        <div class="flash flash-warning" role="status">
            <strong>Somente leitura.</strong> <?= e($motivoBloqueio) ?>
        </div>
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
                <?php require __DIR__ . '/_seletor-inventario.php'; ?>
            </div>
            <div class="inv-config-actions">
                <button type="submit" name="aplicar" value="1" class="btn btn-secondary" id="btn-aplicar">Aplicar inventário</button>
                <a class="btn btn-ghost" href="<?= e(url('por-lote.php?' . http_build_query(['CODINVENTARIO' => $codinventario, 'aplicar' => '1']))) ?>">Contagem por lote</a>
                <a class="btn btn-ghost" href="<?= e(url('sem-lote.php?' . http_build_query(['CODINVENTARIO' => $codinventario, 'aplicar' => '1']))) ?>">Itens sem lote</a>
                <a class="btn btn-ghost" href="<?= e(url('relatorio.php?' . http_build_query(['CODINVENTARIO' => $codinventario]))) ?>">Relatório de contagem</a>
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
                           inputmode="numeric" value="<?= e($quantidade) ?>" required
                           autocomplete="off" tabindex="2">
                </div>
                <div class="form-group inv-barcode-group">
                    <label for="CODIGOBARRAS" class="form-label">Código de barras</label>
                    <input type="text" name="CODIGOBARRAS" id="CODIGOBARRAS" class="form-control inv-barcode-input"
                           maxlength="13" inputmode="numeric" autocomplete="off" tabindex="1"
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
                    <h2 id="secao-iniciar" class="section-title">Escolher inventário</h2>
                    <p class="section-desc">
                        <?php if ($retomadoDaSessao): ?>
                            Dados do último inventário carregados. Confirme ou altere e clique em Aplicar.
                        <?php else: ?>
                            Escolha entre os inventários que o RM tem em aberto. O local vem junto.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="inv-setup-row inv-setup-row--inventario">
                <?php require __DIR__ . '/_seletor-inventario.php'; ?>
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
                    <h2 id="secao-registros" class="section-title">Itens bipados</h2>
                    <p class="section-desc">
                        <?php if ($mostrarTabela): ?>
                            Leituras do inventário <strong><?= e($codinventario) ?></strong>
                            <?php if (!$avulso): ?>· <?= (int) $qtdItensRm ?> itens no RM<?php endif; ?>
                            · <?= $totalBipagens ?> <?= $totalBipagens === 1 ? 'bipado' : 'bipados' ?>.
                            <?php if ($listaTruncada): ?>
                                <strong>A tabela mostra só as <?= (int) ZMDCODBARRAS::LIMITE_LISTAGEM ?> leituras mais recentes</strong> — use o relatório para a contagem completa.
                            <?php endif; ?>
                        <?php else: ?>
                            A lista de bipagens aparece depois de aplicar um inventário.
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($mostrarTabela && $codinventario !== '' && $podeExcluir): ?>
                <?php /* Confirmacao por digitacao: numa contagem com varias
                         pessoas, este botao apaga o trabalho de todas, e um OK
                         de um clique so e leve demais para isso. */ ?>
                <form action="inventario-item.php" method="post" class="inv-delete-inventario-form"
                      data-confirmar-codigo="<?= e($codinventario) ?>"
                      data-confirmar-total="<?= (int) $totalBipagens ?>">
                    <?= csrf_field() ?>
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
                            <td class="mono">
                                <?= e($cod->getCodigobarras()) ?>
                                <?php /* Linha secundaria em vez de coluna nova: a tabela ja
                                         tem sete colunas e o celular e o aparelho da bipagem.
                                         Lancamento anterior a esta versao nao tem autoria
                                         gravada e simplesmente nao mostra nada. */ ?>
                                <?php $autoria = trim($cod->getCriadoPor() . ' · ' . $cod->getCriadoEm(), " ·"); ?>
                                <?php if ($autoria !== ''): ?>
                                    <span class="inv-autoria"><?= e($autoria) ?></span>
                                <?php endif; ?>
                            </td>
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
                                <?php if ($podeExcluir): ?>
                                <form action="inventario-item.php" method="post" class="inline-form"
                                      onsubmit="return confirm('Excluir este registro?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= e($cod->getId()) ?>">
                                    <input type="hidden" name="CODLOC" value="<?= e($codloc) ?>">
                                    <input type="hidden" name="CODINVENTARIO" value="<?= e($codinventario) ?>">
                                    <input type="hidden" name="QUANTIDADE" value="<?= e($quantidade) ?>">
                                    <button type="submit" class="btn-link btn-link-danger">Excluir</button>
                                </form>
                                <?php endif; ?>
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
            <?= csrf_field() ?>
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
