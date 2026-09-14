<?php
/** @var string $codloc */
/** @var string $codinventario */
/** @var string $busca */
/** @var string $grupoContabil */
/** @var bool $somenteComSaldo */
/** @var bool $modoLista */
/** @var bool $avulso */
/** @var bool $retomadoDaSessao */
/** @var bool $listaTruncada */
/** @var array $linhas */
/** @var array<string, string> $grupos */
/** @var array<int, bool> $noInventario */
/** @var array<string, float> $totaisProdutoLote */
/** @var int $foraDoInventario */
/** @var float $valorTotal */
/** @var array|null $envAtual */
/** @var string $nomeLocal */
/** @var array $inventariosAbertos */
$nomeLocal = $nomeLocal ?? '';
$busca = (string) ($busca ?? '');
$grupoContabil = (string) ($grupoContabil ?? '');
$linhas = $linhas ?? [];
$grupos = $grupos ?? [];
$noInventario = $noInventario ?? [];
$totaisProdutoLote = $totaisProdutoLote ?? [];
$inventariosAbertos = $inventariosAbertos ?? [];
$listaTruncada = !empty($listaTruncada);
$somenteComSaldo = !empty($somenteComSaldo);
$avulso = !empty($avulso);
$foraDoInventario = (int) ($foraDoInventario ?? 0);
$valorTotal = (float) ($valorTotal ?? 0);

$contados = 0;
foreach ($linhas as $linha) {
    if (($totaisProdutoLote[$linha['idprd'] . ':' . $linha['idlote']] ?? 0) > 0) {
        $contados++;
    }
}

$moeda = function ($v) {
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
};
?>

<div class="page-wrap-wide inv-page sl-page pl-page">
    <?php if (!$modoLista): ?>
    <header class="inv-page-header sl-header">
        <h1 class="page-title">Contagem por lote</h1>
        <p class="page-subtitle">Escolha o inventário para ver a posição de estoque do local.</p>
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
            <button type="submit" name="aplicar" value="1" class="btn btn-primary">Ver posição do local</button>
        </form>
    </section>

    <section class="inv-section panel" aria-labelledby="secao-avulsa">
        <div class="inv-section-head">
            <span class="inv-step">2</span>
            <div>
                <h2 id="secao-avulsa" class="section-title">Ou conte sem inventário cadastrado</h2>
                <p class="section-desc">
                    Abre uma contagem avulsa do local, sem esperar o inventário existir no RM.
                    Quando ele for criado, você move a contagem para o código dele em um clique.
                </p>
            </div>
        </div>
        <form action="por-lote.php" method="get" autocomplete="off" class="pl-filtro-linha">
            <input type="hidden" name="avulsa" value="1">
            <label class="pl-filtro-campo">
                <span>Local</span>
                <select name="CODLOC" class="form-control" required>
                    <option value="">Escolha o local...</option>
                    <?php foreach (LocaisEstoque::todos() as $cod => $descricao): ?>
                        <option value="<?= e($cod) ?>"><?= e($cod . ' — ' . $descricao) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn btn-secondary">Abrir contagem avulsa</button>
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
            <span id="pl-counts"><?= (int) $contados ?>/<?= count($linhas) ?> contados</span>
            <?php if ($avulso): ?>
                <span class="pl-badge-avulsa" title="Código não existe em TINVENTARIO">Avulsa · fora do RM</span>
            <?php endif; ?>
            <span class="pl-valor-total"><?= e($moeda($valorTotal)) ?> em estoque</span>
        </div>
        <div class="sl-toolbar-actions">
            <a class="btn btn-ghost sl-link" href="inventario.php?<?= e(http_build_query(['CODINVENTARIO' => $codinventario, 'aplicar' => '1'])) ?>">Leitura</a>
            <a class="btn btn-ghost sl-link" href="sem-lote.php?<?= e(http_build_query(['CODINVENTARIO' => $codinventario, 'aplicar' => '1'])) ?>">Sem lote</a>
            <?php if ($avulso): ?>
            <details class="pl-vincular">
                <summary class="btn btn-ghost sl-link">Vincular ao RM</summary>
                <form action="<?= e(url('vincular-contagem.php')) ?>" method="post" class="pl-vincular-form"
                      onsubmit="return confirm('Mover toda a contagem de <?= e($codinventario) ?> para o código informado?');">
                    <input type="hidden" name="de" value="<?= e($codinventario) ?>">
                    <p>Informe o inventário que o RM criou para o local <?= e($codloc) ?>. A contagem inteira passa para ele.</p>
                    <input type="text" name="para" class="form-control mono" required
                           inputmode="numeric" maxlength="10" placeholder="<?= e(substr($codinventario, 0, 7)) ?>001"
                           pattern="\d{2}\.\d{3}\.\d{3}" data-inventario-mask>
                    <button type="submit" class="btn btn-primary">Mover contagem</button>
                </form>
            </details>
            <?php endif; ?>
            <a class="btn btn-ghost sl-link" href="por-lote.php">Trocar inventário</a>
        </div>
    </div>

    <div class="pl-split">
        <section class="pl-pane pl-pane-lista panel" aria-labelledby="pl-lista-titulo">
            <form action="por-lote.php" method="get" class="pl-filtros" id="pl-busca-form">
                <input type="hidden" name="CODINVENTARIO" value="<?= e($codinventario) ?>">
                <input type="hidden" name="CODLOC" value="<?= e($codloc) ?>">

                <h2 id="pl-lista-titulo" class="pl-pane-title">Posição do local <?= e($codloc) ?></h2>

                <div class="pl-filtro-linha">
                    <input type="search" id="pl-busca" name="q" class="form-control sl-busca"
                           value="<?= e($busca) ?>" autocomplete="off"
                           placeholder="Lote, produto ou código — Enter pega o primeiro">
                    <button type="submit" class="btn btn-secondary pl-busca-btn" id="pl-busca-rm">Buscar no RM</button>
                </div>

                <div class="pl-filtro-linha">
                    <label class="pl-filtro-campo">
                        <span>Grupo contábil</span>
                        <select name="grupo" class="form-control" id="pl-grupo" data-autosubmit>
                            <option value="">Todos os grupos</option>
                            <?php foreach ($grupos as $cod => $descricao): ?>
                                <option value="<?= e($cod) ?>" <?= $cod === $grupoContabil ? 'selected' : '' ?>>
                                    <?= e($descricao !== '' ? $cod . ' — ' . $descricao : $cod) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="sl-check">
                        <input type="checkbox" name="todos" value="1" id="pl-todos" data-autosubmit
                               <?= $somenteComSaldo ? '' : 'checked' ?>> Incluir lotes zerados
                    </label>
                    <label class="sl-check">
                        <input type="checkbox" id="pl-ocultar-contados"> Ocultar já contados
                    </label>
                </div>
            </form>

            <?php if ($listaTruncada): ?>
            <p class="sl-hint is-err">
                A lista bateu o teto de <?= (int) InventarioRM::LIMITE_LOTES ?> linhas e foi cortada.
                Estreite por grupo contábil ou use <strong>Buscar no RM</strong>.
            </p>
            <?php endif; ?>

            <?php if ($foraDoInventario > 0): ?>
            <p class="sl-hint is-err">
                <?php if ($foraDoInventario === 1): ?>
                    <strong>1 linha</strong> tem saldo no local mas não entrou no inventário
                    <?= e($codinventario) ?> — aparece marcada e não aceita contagem.
                <?php else: ?>
                    <strong><?= (int) $foraDoInventario ?> linhas</strong> têm saldo no local mas não entraram
                    no inventário <?= e($codinventario) ?> — aparecem marcadas e não aceitam contagem.
                <?php endif; ?>
            </p>
            <?php endif; ?>

            <div class="sl-table-wrap pl-table-wrap">
                <table class="sl-table pl-table" id="pl-table">
                    <thead>
                    <tr>
                        <th>Produto</th>
                        <th class="pl-col-grupo">Grupo</th>
                        <th class="pl-col-lote">Lote</th>
                        <th class="pl-col-validade">Validade</th>
                        <th class="pl-col-saldo">Saldo</th>
                        <th class="pl-col-valor">Valor</th>
                        <th class="sl-col-ja">Já</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($linhas === []): ?>
                        <tr><td colspan="7" class="empty">
                            <?= $busca !== '' || $grupoContabil !== ''
                                ? 'Nenhuma linha para esses filtros.'
                                : 'Nenhum lote com saldo neste local.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($linhas as $linha):
                            $idprd = (int) $linha['idprd'];
                            $idlote = (int) $linha['idlote'];
                            $ja = (float) ($totaisProdutoLote[$idprd . ':' . $idlote] ?? 0);
                            $saldo = (float) $linha['saldo'];
                            // Avulsa nao tem itens gerados no RM: lista vazia significa
                            // "tudo contavel", nao "nada contavel".
                            $dentro = $avulso || !empty($noInventario[$idprd]);
                            $nomeProduto = $linha['nome'] !== '' ? $linha['nome'] : 'ID ' . $idprd;
                            $numlote = $linha['numlote'] !== '' ? $linha['numlote'] : '—';
                            $validade = $linha['validade'] !== '' ? $linha['validade'] : '—';
                            $grupoLabel = $linha['grupo_nome'] !== '' ? $linha['grupo_nome'] : $linha['grupo_cod'];
                            $termoBusca = mb_strtolower(
                                ($linha['numlote'] ?? '') . ' ' . ($linha['codigo'] ?? '') . ' '
                                . ($linha['nome'] ?? '') . ' ' . $idprd . ' ' . ($linha['grupo_nome'] ?? ''),
                                'UTF-8'
                            );
                            ?>
                            <tr class="sl-row pl-row <?= $ja > 0 ? 'is-counted' : '' ?> <?= $dentro ? '' : 'is-fora' ?>"
                                tabindex="0" role="button"
                                aria-label="Selecionar <?= e($nomeProduto) ?> lote <?= e($numlote) ?>"
                                data-idprd="<?= $idprd ?>"
                                data-idlote="<?= $idlote ?>"
                                data-nome="<?= e($nomeProduto) ?>"
                                data-codigo="<?= e($linha['codigo']) ?>"
                                data-grupo="<?= e($grupoLabel) ?>"
                                data-lote="<?= e($numlote) ?>"
                                data-validade="<?= e($validade) ?>"
                                data-und="<?= e($linha['und']) ?>"
                                data-saldo="<?= e(formatar_quantidade($saldo)) ?>"
                                data-valor="<?= e($moeda($linha['saldo_financeiro'])) ?>"
                                data-ja="<?= e(formatar_quantidade($ja)) ?>"
                                data-dentro="<?= $dentro ? '1' : '0' ?>"
                                data-search="<?= e($termoBusca) ?>"
                                data-counted="<?= $ja > 0 ? '1' : '0' ?>">
                                <td class="sl-nome">
                                    <span class="sl-nome-main"><?= e($nomeProduto) ?></span>
                                    <?php if (($linha['codigo'] ?? '') !== ''): ?>
                                        <span class="sl-cod mono"><?= e($linha['codigo']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!$dentro): ?>
                                        <span class="pl-badge-fora">fora do inventário</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pl-col-grupo"><?= e($grupoLabel !== '' ? $grupoLabel : '—') ?></td>
                                <td class="pl-col-lote mono"><?= e($numlote) ?></td>
                                <td class="pl-col-validade"><?= e($validade) ?></td>
                                <td class="pl-col-saldo"><?= e(formatar_quantidade($saldo)) ?></td>
                                <td class="pl-col-valor"><?= e($moeda($linha['saldo_financeiro'])) ?></td>
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
                Clique numa linha da lista — ou digite na busca e pressione
                <kbd>Enter</kbd> para pegar o primeiro resultado.
            </p>

            <form id="pl-form" hidden autocomplete="off" data-ajax>
                <dl class="pl-dados">
                    <dt>Produto</dt><dd id="pl-f-nome" class="pl-f-nome"></dd>
                    <dt>Grupo</dt><dd id="pl-f-grupo"></dd>
                    <dt>Lote</dt><dd id="pl-f-lote" class="mono"></dd>
                    <dt>Validade</dt><dd id="pl-f-validade"></dd>
                    <dt>Saldo</dt><dd id="pl-f-saldo"></dd>
                    <dt>Valor</dt><dd id="pl-f-valor"></dd>
                    <dt>Já contado</dt><dd id="pl-f-ja"></dd>
                </dl>

                <p class="pl-bloqueio" id="pl-bloqueio" hidden>
                    Este produto tem saldo no local mas não faz parte do inventário
                    <?= e($codinventario) ?> no RM, então a contagem não pode ser gravada.
                    Gere o item no inventário pelo RM para poder contá-lo.
                </p>

                <div class="form-group" id="pl-grupo-qtd">
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
