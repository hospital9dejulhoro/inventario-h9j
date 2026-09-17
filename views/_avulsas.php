<?php
/**
 * Lista de contagens avulsas em aberto.
 *
 * @var array  $contagensAvulsas
 * @var string $destinoAvulsa  Tela que retoma a contagem (inventario.php, por-lote.php)
 */
$contagensAvulsas = $contagensAvulsas ?? [];
$destinoAvulsa = $destinoAvulsa ?? 'por-lote.php';

if ($contagensAvulsas === []) {
    return;
}
?>
<section class="inv-section panel inv-open-list" aria-labelledby="secao-avulsas">
    <div class="inv-section-head">
        <span class="inv-step inv-step--muted">·</span>
        <div>
            <h2 id="secao-avulsas" class="section-title">
                Contagens avulsas
                <span class="pl-badge-avulsa">fora do RM</span>
            </h2>
            <p class="section-desc">
                Abertas sem inventário cadastrado. Continue de onde parou, ou use
                <strong>Vincular ao RM</strong> na tela de lote quando o inventário for criado.
            </p>
        </div>
    </div>
    <ul class="inv-open-items">
        <?php foreach ($contagensAvulsas as $avulsa): ?>
            <li class="inv-open-row">
                <a class="inv-open-item"
                   href="<?= e(url($destinoAvulsa . '?' . http_build_query([
                       'CODINVENTARIO' => $avulsa['codinventario'],
                       'CODLOC'        => $avulsa['codloc'],
                       'aplicar'       => '1',
                   ]))) ?>">
                    <span class="inv-open-main">
                        <strong class="mono"><?= e($avulsa['codinventario']) ?></strong>
                        <span class="pl-badge-avulsa">Avulsa</span>
                    </span>
                    <span class="inv-open-meta">
                        <?php if ($avulsa['codloc'] !== ''): ?>
                            Local <?= e($avulsa['codloc']) ?>
                            <?php if ($avulsa['local_nome'] !== ''): ?>
                                — <?= e($avulsa['local_nome']) ?>
                            <?php endif; ?>
                            ·
                        <?php endif; ?>
                        <?= (int) $avulsa['bipagens'] ?> <?= (int) $avulsa['bipagens'] === 1 ? 'item' : 'itens' ?>
                        · qtd <?= e(formatar_quantidade($avulsa['quantidade'])) ?>
                    </span>
                    <span class="inv-open-action">Continuar</span>
                </a>
                <?php /* Avulsa é rascunho: aberta no local errado ou por
                         engano, precisa poder ser jogada fora daqui mesmo, sem
                         ter de entrar nela pela tela de Leitura. A confirmação
                         por digitação é a mesma da exclusão de inventário — o
                         que se apaga é a contagem de todo mundo. */ ?>
                <form action="<?= e(url('inventario-item.php')) ?>" method="post"
                      class="inv-open-descartar"
                      data-confirmar-codigo="<?= e($avulsa['codinventario']) ?>"
                      data-confirmar-total="<?= (int) $avulsa['bipagens'] ?>"
                      data-confirmar-rotulo="a contagem avulsa">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="excluir_avulsa">
                    <input type="hidden" name="CODINVENTARIO" value="<?= e($avulsa['codinventario']) ?>">
                    <input type="hidden" name="voltar" value="<?= e($destinoAvulsa) ?>">
                    <button type="submit" class="btn-link btn-link-danger"
                            title="Descartar a contagem avulsa <?= e($avulsa['codinventario']) ?>">Descartar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
