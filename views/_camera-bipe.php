<?php
/**
 * Bipar com a câmera do celular.
 *
 * Nem todo posto tem leitor. O celular que a pessoa já leva no bolso resolve,
 * desde que a página esteja em HTTPS — a câmera só é liberada em contexto
 * seguro, e é por isso que o botão avisa em vez de simplesmente não funcionar.
 *
 * O Android tem leitor de código de barras embutido no navegador; o iPhone não.
 * Para o iPhone existe a biblioteca em assets/vendor/zxing, carregada só quando
 * falta o leitor nativo — no Android aqueles 336KB nunca são baixados.
 *
 * Quando lê, para a câmera e devolve o foco para a quantidade: o mesmo caminho
 * da busca por nome, porque quem bipou ainda precisa dizer quantos são.
 */
?>
<div class="cam-bloco">
    <button type="button" class="btn btn-secondary cam-abrir" id="cam-abrir"
            aria-expanded="false" aria-controls="cam-painel">
        Bipar com a câmera
    </button>

    <div class="cam-painel" id="cam-painel" hidden>
        <div class="cam-video-caixa">
            <?php /* playsinline impede o iPhone de jogar o vídeo em tela cheia,
                     que tiraria da vista o campo de quantidade. muted é o que
                     permite o autoplay sem toque. */ ?>
            <video id="cam-video" class="cam-video" playsinline muted autoplay></video>
            <div class="cam-mira" aria-hidden="true"></div>
        </div>
        <?php /* O iPhone nao deixa travar a orientacao; ali so resta pedir. */ ?>
        <p class="cam-girar" id="cam-girar" hidden>Gire o celular na horizontal — o código de barras é deitado e cabe melhor.</p>
        <p class="form-hint cam-status" id="cam-status" role="status" aria-live="polite"></p>
        <button type="button" class="btn btn-ghost cam-fechar" id="cam-fechar">Fechar câmera</button>
    </div>
</div>

<script>
window.CAM_CFG = {
    zxingUrl: <?= json_encode(url('assets/vendor/zxing/zxing.min.js'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
};
</script>
<script src="<?= e(url('assets/js/camera-bipe.js')) ?>"></script>
