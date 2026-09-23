<?php
/**
 * Achar o item quando a etiqueta não lê.
 *
 * Etiqueta rasgada, impressão apagada, embalagem amassada: a contagem não pode
 * parar por causa disso, e sem esta saída a pessoa larga o item de lado ou
 * digita 13 dígitos que não consegue enxergar.
 *
 * Fica fora da forma de gravação de propósito — um campo de texto dentro dela
 * faria Enter gravar a leitura em vez de buscar.
 *
 * Vem fechado: quem está bipando não precisa dele na frente o tempo todo, e
 * aberto empurraria o campo de leitura para fora da tela no celular.
 *
 * @var string $codinventario
 * @var string $codloc
 */
?>
<section class="inv-section inv-section--busca panel" aria-labelledby="secao-busca">
    <details class="bi-caixa" id="bi-caixa">
        <summary class="bi-abrir">
            <span class="bi-abrir-titulo" id="secao-busca">Etiqueta não lê? Procurar o item</span>
            <span class="bi-abrir-dica">Por nome, código do produto, lote ou código de barras</span>
        </summary>

        <div class="bi-corpo">
            <label for="bi-termo" class="form-label">O que você está procurando</label>
            <div class="bi-linha">
                <input type="search" id="bi-termo" class="form-control bi-termo"
                       autocomplete="off" enterkeyhint="search"
                       placeholder="Ex.: dipirona, 007439, lote 2401">
                <button type="button" class="btn btn-secondary bi-limpar" id="bi-limpar">Limpar</button>
            </div>
            <p class="form-hint bi-status" id="bi-status" role="status" aria-live="polite">
                Digite pelo menos 2 caracteres.
            </p>

            <ul class="bi-itens" id="bi-itens"></ul>
        </div>
    </details>
</section>

<script>
window.BI_CFG = {
    buscaUrl: <?= json_encode(url('buscar-item.php'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
    inventario: <?= json_encode($codinventario, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
    codloc: <?= json_encode($codloc, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
};
</script>
<script src="<?= e(url('assets/js/busca-item.js')) ?>"></script>
