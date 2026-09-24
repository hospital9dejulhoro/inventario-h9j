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
 *
 * Os controles (lanterna, aproximar, trocar câmera) nascem escondidos e só
 * aparecem se o aparelho oferecer. Botão que não faz nada é pior que botão
 * nenhum — e o que cada celular permite varia muito.
 */
?>
<div class="cam-bloco">
    <button type="button" class="btn btn-secondary cam-abrir" id="cam-abrir"
            aria-expanded="false" aria-controls="cam-painel">
        Bipar com a câmera
    </button>

    <div class="cam-painel" id="cam-painel" hidden>
        <div class="cam-video-caixa" id="cam-video-caixa">
            <?php /* playsinline impede o iPhone de jogar o vídeo em tela cheia,
                     que tiraria da vista o campo de quantidade. muted é o que
                     permite o autoplay sem toque. */ ?>
            <video id="cam-video" class="cam-video" playsinline muted autoplay></video>
            <div class="cam-mira" aria-hidden="true"></div>
            <?php /* Tocar na imagem manda a câmera focar ali. Em prateleira
                     cheia o foco automático fica caçando entre a etiqueta e a
                     caixa de trás. */ ?>
            <p class="cam-toque" id="cam-toque" aria-hidden="true">Toque para focar</p>
        </div>

        <div class="cam-controles" id="cam-controles">
            <button type="button" class="cam-botao" id="cam-lanterna" hidden
                    aria-pressed="false">Lanterna</button>

            <label class="cam-zoom" id="cam-zoom-area" hidden>
                <span class="cam-zoom-rotulo">Aproximar</span>
                <input type="range" id="cam-zoom" min="1" max="5" step="0.1" value="1"
                       aria-label="Aproximar a câmera">
            </label>

            <button type="button" class="cam-botao" id="cam-trocar" hidden>Trocar câmera</button>
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
