<?php
/**
 * Destino de uma contagem avulsa: escolha, não digitação.
 *
 * Vincular despeja a contagem inteira no inventário escolhido. Digitar o código
 * à mão aqui era o pior lugar para um erro de digitação: o comentário do
 * próprio vincular-contagem.php já dizia que um engano "criaria outra avulsa
 * silenciosamente e a contagem sumiria de vista".
 *
 * Só aparecem os abertos do mesmo local — é o único destino que o backend
 * aceita, porque misturar prateleiras de locais diferentes na mesma apuração
 * não faz sentido.
 *
 * @var array  $destinosVincular Vindo de InventarioRM::abertosDoLocal()
 * @var string $codloc
 */
$destinosVincular = $destinosVincular ?? [];
$codloc = (string) ($codloc ?? '');
?>
<?php if ($destinosVincular === []): ?>
    <p>Nenhum inventário em aberto no RM para o local <?= e($codloc) ?>.
       Peça a abertura no RM — a contagem fica guardada aqui até lá.</p>
<?php else: ?>
    <p>Escolha o inventário que o RM criou para o local <?= e($codloc) ?>.
       A contagem inteira passa para ele.</p>
    <select name="para" class="form-control mono" required>
        <option value="">— escolha o destino —</option>
        <?php foreach ($destinosVincular as $destino): ?>
            <option value="<?= e($destino['codinventario']) ?>">
                <?= e($destino['codinventario']) ?> · <?= (int) ($destino['itens'] ?? 0) ?> itens no RM
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Mover contagem</button>
<?php endif; ?>
