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
            <li>
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
            </li>
        <?php endforeach; ?>
    </ul>
</section>
