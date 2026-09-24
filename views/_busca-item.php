<?php
/**
 * Resultados da busca, logo abaixo do campo de leitura.
 *
 * O campo é um só. Treze dígitos gravam a leitura; qualquer outra coisa —
 * nome, código do produto, lote — procura o item. Antes eram dois campos em
 * dois cartões: o de bipar em cima e, recolhido embaixo, o de procurar. Quem
 * chegava com a etiqueta rasgada na mão precisava descobrir que existia o
 * segundo, abrir o painel e digitar de novo.
 *
 * Aqui ficam só a lista e o aviso. O campo que alimenta os dois caminhos é o
 * CODIGOBARRAS da própria forma de gravação — por isso escolher um item da
 * lista já deixa a leitura pronta para gravar.
 *
 * @var string $codinventario
 * @var string $codloc
 */
?>
<div class="bi-resultados" id="bi-caixa">
    <p class="form-hint bi-status" id="bi-status" role="status" aria-live="polite" hidden></p>
    <ul class="bi-itens" id="bi-itens"></ul>
</div>

<script>
window.BI_CFG = {
    buscaUrl: <?= json_encode(url('buscar-item.php'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
    inventario: <?= json_encode($codinventario, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
    codloc: <?= json_encode($codloc, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
};
</script>
<script src="<?= e(url('assets/js/busca-item.js')) ?>"></script>
