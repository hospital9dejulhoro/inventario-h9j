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
$modoContagem = $modoContagem ?? 'somar';

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
    <?php if (!$modoLeitura): ?>
    <header class="inv-page-header">
        <h1 class="page-title">Leitura de inventário</h1>
        <p class="page-subtitle">Escolha um inventário em aberto ou informe o código para começar.</p>
    </header>
    <?php endif; ?>

    <?php if (!$modoLeitura): ?>
    <section class="inv-section panel inv-open-list" aria-labelledby="secao-abertos">
        <div class="inv-section-head">
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

    <?php /*
        Barra de contexto: qual inventário, qual local, quanto já foi contado.

        No lugar do título da página, da barra de estatísticas e da seção
        "Inventário e local" inteira — que ocupava 666px num celular, mais que a
        própria área de bipagem, para algo que se usa uma vez no começo da
        contagem. Trocar de inventário virou um detalhe recolhido.

        Saiu o ambiente, que já está na barra de navegação, e saíram os links
        para Por lote, Sem lote e Relatório, que já estão lá também.
    */ ?>
    <?php if ($modoLeitura): ?>
    <header class="inv-barra" aria-label="Inventário em contagem">
        <div class="inv-barra-topo">
            <div class="inv-barra-quem">
                <strong class="mono inv-barra-codigo"><?= e($codinventario) ?></strong>
                <span class="inv-barra-local"><?= e($codloc) ?><?= $nomeLocal !== '' ? ' · ' . e($nomeLocal) : '' ?></span>
            </div>
            <?php if ($avulso): ?>
                <span class="pl-badge-avulsa" title="Código não existe em TINVENTARIO">Avulsa</span>
            <?php elseif ($statusInventarioRm !== ''): ?>
                <span class="inv-barra-status" title="STATUS em TINVENTARIO"><?= e(InventarioRM::rotuloStatus($statusInventarioRm)) ?></span>
            <?php endif; ?>
        </div>

        <p class="inv-barra-numeros">
            <?php /* Separador de milhar: "30581" obriga a contar as casas. */ ?>
            <span><strong id="session-scan-count"><?= (int) $leiturasSessao ?></strong> nesta sessão</span>
            <span><strong><?= number_format((int) $totalBipagens, 0, ',', '.') ?></strong> no total</span>
            <?php if (!$avulso): ?>
                <span><strong><?= number_format((int) $qtdItensRm, 0, ',', '.') ?></strong> itens no RM</span>
            <?php endif; ?>
        </p>

        <details class="inv-trocar">
            <summary class="inv-trocar-abrir">Trocar inventário ou local</summary>
            <div class="inv-trocar-corpo">
                <form action="inventario.php" method="get" autocomplete="off" id="inventario-escolher" class="inv-form">
                    <div class="inv-setup-row inv-setup-row--inventario">
                        <?php require __DIR__ . '/_seletor-inventario.php'; ?>
                    </div>
                    <?php /* Quantidade e modo viajavam junto quando escolher e bipar
                             eram a mesma forma. Agora sao duas: sem isto, trocar de
                             inventario no meio da contagem zerava os dois. */ ?>
                    <input type="hidden" name="QUANTIDADE" value="<?= e($quantidade !== '' ? $quantidade : '1') ?>">
                    <input type="hidden" name="modo" value="<?= e($modoContagem) ?>">
                    <button type="submit" name="aplicar" value="1" class="btn btn-primary" id="btn-aplicar">Aplicar</button>
                </form>

                <?php if (!empty($recentInventarios) && count($recentInventarios) > 1): ?>
                <div class="inv-status-recent">
                    <span class="inv-status-recent-label">Recentes</span>
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
        </details>
    </header>
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

    <?php /* Escolher inventario e navegacao (GET, endereco compartilhavel);
             gravar leitura e escrita (POST com token). A forma de escolher
             mora na barra de contexto, la em cima. */ ?>
    <?php if ($modoLeitura): ?>

    <section class="inv-section inv-section--scan" aria-labelledby="secao-leitura">
        <div class="inv-section-head">
            <div>
                <h2 id="secao-leitura" class="section-title">Ler código de barras</h2>
                <p class="section-desc">Escaneie os 13 dígitos, ou use a câmera. Enter grava.</p>
            </div>
        </div>

    <form action="inventario.php" method="post" autocomplete="off" id="inventory-form" class="inv-form">
        <?= csrf_field() ?>
        <?php /* O inventario e o local nao estao na URL desta gravacao: viajam
                 no proprio envio, ja resolvidos pelo servidor. */ ?>
        <input type="hidden" name="CODINVENTARIO" value="<?= e($codinventario) ?>">
        <input type="hidden" name="CODLOC" value="<?= e($codloc) ?>">

            <?php /* Mesma escolha das telas de lote, e pelo mesmo motivo: quem
                     recontou uma posicao quer dizer quanto HA, nao quanto somar.
                     O modo viaja na URL para nao voltar a somar a cada bipe. */ ?>
            <div class="pl-modo" role="radiogroup" aria-label="O que fazer com a quantidade">
                <label>
                    <input type="radio" name="modo" value="somar" <?= $modoContagem !== 'corrigir' ? 'checked' : '' ?>>
                    Somar ao contado
                </label>
                <label>
                    <input type="radio" name="modo" value="corrigir" <?= $modoContagem === 'corrigir' ? 'checked' : '' ?>>
                    Corrigir o total
                </label>
            </div>
            <?php if ($modoContagem === 'corrigir'): ?>
                <p class="form-hint pl-modo-aviso">
                    Cada leitura substitui tudo que já foi contado do produto e lote. Zero apaga a contagem do item.
                </p>
            <?php endif; ?>
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

            <?php /* Depois do campo e antes de gravar: a camera e mais um jeito
                     de preencher o codigo, nao um fluxo separado. */ ?>
            <?php require __DIR__ . '/_camera-bipe.php'; ?>

            <button type="submit" class="btn btn-primary" id="btn-registrar">Registrar leitura</button>
    </form>

        <?php /* Bipar, fotografar e procurar pelo nome sao tres jeitos de dizer
                 QUAL item. Ficavam em cartoes separados, como se fossem coisas
                 diferentes; agora dividem o mesmo. A busca fica fora da forma
                 de proposito: um campo de texto dentro dela faria Enter gravar
                 a leitura em vez de procurar. */ ?>
        <?php require __DIR__ . '/_busca-item.php'; ?>
    </section>

    <?php else: ?>
    <form action="inventario.php" method="get" autocomplete="off" id="inventario-escolher" class="inv-form">
        <section class="inv-section inv-section--setup panel" aria-labelledby="secao-iniciar">
            <div class="inv-section-head">
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
    </form>
    <?php endif; ?>

    <section class="inv-section inv-section--records panel panel-flush" aria-labelledby="secao-registros">
        <div class="panel-header">
            <div class="inv-section-head inv-section-head--compact inv-section-head--with-action">

                <div class="inv-section-head-text">
                    <h2 id="secao-registros" class="section-title">Itens bipados</h2>
                    <?php /* Inventario, total e itens no RM ja estao na barra de
                             contexto. So sobra o que e excecao - e quando nao ha
                             excecao a linha inteira some, em vez de deixar um
                             paragrafo vazio empurrando a lista para baixo. */ ?>
                    <?php if (!$mostrarTabela): ?>
                    <p class="section-desc">A lista de bipagens aparece depois de aplicar um inventário.</p>
                    <?php elseif ($listaTruncada): ?>
                    <p class="section-desc">
                        <strong>A tabela mostra só as <?= (int) ZMDCODBARRAS::LIMITE_LISTAGEM ?> leituras mais recentes</strong> — use o relatório para a contagem completa.
                    </p>
                    <?php endif; ?>
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
                    <button type="submit" class="btn-link btn-link-danger">Excluir contagem deste inventário</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table" id="registros-table">
                <thead>
                <tr>
                    <th class="reg-col-barras">Código de barras</th>
                    <th class="reg-col-qtd">Qtd</th>
                    <th class="reg-col-local">Local</th>
                    <th class="reg-col-produto">Produto</th>
                    <th class="reg-col-und">Und</th>
                    <th class="reg-col-lote">Lote</th>
                    <th class="reg-col-acoes"></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($mostrarTabela && !empty($registros)): ?>
                    <?php foreach ($registros as $cod): ?>
                        <tr class="reg-row">
                            <td class="mono reg-col-barras">
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
                            <td class="reg-col-qtd" data-rotulo="Qtd"><?= e($cod->getQuantidade()) ?></td>
                            <td class="reg-col-local" data-rotulo="Local"><?= e($cod->getCodloc()) ?></td>
                            <td class="reg-col-produto"><?= e($cod->getNome()) ?></td>
                            <td class="reg-col-und"><?= e($cod->getUnd()) ?></td>
                            <td class="reg-col-lote" data-rotulo="Lote"><?= e($cod->getNumlote()) ?></td>
                            <td class="actions-cell reg-col-acoes">
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
