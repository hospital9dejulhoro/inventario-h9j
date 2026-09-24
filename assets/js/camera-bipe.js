(function () {
    'use strict';

    var cfg = window.CAM_CFG;
    var abrir = document.getElementById('cam-abrir');
    var painel = document.getElementById('cam-painel');
    var caixa = document.getElementById('cam-video-caixa');
    var video = document.getElementById('cam-video');
    var status = document.getElementById('cam-status');
    var fechar = document.getElementById('cam-fechar');
    var dicaGirar = document.getElementById('cam-girar');
    var dicaToque = document.getElementById('cam-toque');
    var botaoLanterna = document.getElementById('cam-lanterna');
    var areaZoom = document.getElementById('cam-zoom-area');
    var controleZoom = document.getElementById('cam-zoom');
    var botaoTrocar = document.getElementById('cam-trocar');

    if (!cfg || !abrir || !painel || !video || !status) {
        return;
    }

    // Formatos que a etiqueta do RM pode sair. upc_a fica na lista de proposito:
    // um EAN-13 comecando com zero e FISICAMENTE identico a um UPC-A, e sem ele
    // alguns aparelhos simplesmente nao leem a etiqueta. O zero perdido e
    // reposto em normalizar().
    var FORMATOS = ['ean_13', 'code_128', 'itf', 'code_39', 'codabar', 'upc_a'];

    var DIGITOS = 13;

    var stream = null;
    var faixa = null;
    var leitorNativo = null;
    var leitorZxing = null;
    var rodando = false;
    var ultimoCodigo = '';
    var ultimoEm = 0;
    var lanternaAcesa = false;
    var cameras = [];
    var cameraAtual = 0;

    function aviso(texto, classe) {
        status.textContent = texto;
        status.className = 'form-hint cam-status' + (classe ? ' ' + classe : '');
    }

    function temLeitorNativo() {
        return typeof window.BarcodeDetector === 'function';
    }

    /**
     * Repoe o zero que o leitor come.
     *
     * Os codigos daqui sao IDPRD com seis digitos preenchidos com zero, entao
     * todo produto com IDPRD abaixo de 100000 gera um codigo que comeca com
     * zero - a grande maioria. Nessa faixa, a etiqueta EAN-13 e indistinguivel
     * de um UPC-A, e o leitor devolve 12 digitos com o zero da frente fora.
     *
     * UPC-A e EAN-13 com zero na frente, entao repor o zero reconstroi o codigo
     * exato, nao chuta nada.
     */
    function normalizar(texto) {
        var digitos = String(texto || '').replace(/\D/g, '');

        if (digitos.length === DIGITOS - 1) {
            digitos = '0' + digitos;
        }

        return digitos;
    }

    function carregarZxing() {
        if (window.ZXing) {
            return Promise.resolve();
        }

        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = cfg.zxingUrl;
            s.onload = function () { resolve(); };
            s.onerror = function () { reject(new Error('Nao foi possivel carregar o leitor.')); };
            document.head.appendChild(s);
        });
    }

    /* ----------------------------------------------------------------------
     * Controles do aparelho
     *
     * Tudo aqui e opcional e varia muito entre celulares. Cada controle so
     * aparece depois de confirmar na propria camera que ela oferece aquilo:
     * botao que nao faz nada e pior que botao nenhum.
     * -------------------------------------------------------------------- */

    function capacidades() {
        if (!faixa || !faixa.getCapabilities) {
            return {};
        }
        try {
            return faixa.getCapabilities() || {};
        } catch (e) {
            return {};
        }
    }

    function aplicar(avancado) {
        if (!faixa || !faixa.applyConstraints || !avancado.length) {
            return Promise.resolve(false);
        }
        return faixa.applyConstraints({ advanced: avancado })
            .then(function () { return true; })
            .catch(function () { return false; });
    }

    /**
     * Lanterna.
     *
     * Prateleira de farmacia e mal iluminada e a etiqueta costuma estar na
     * sombra da propria prateleira de cima. E o unico controle daqui que muda o
     * resultado sozinho, sem a pessoa precisar mirar melhor.
     *
     * So o Android oferece: o Safari nao expoe a lanterna pela camera da pagina.
     */
    function prepararLanterna() {
        if (!botaoLanterna) {
            return;
        }

        var cap = capacidades();
        var tem = !!cap.torch;

        botaoLanterna.hidden = !tem;
        lanternaAcesa = false;
        botaoLanterna.setAttribute('aria-pressed', 'false');
        botaoLanterna.classList.remove('is-ligado');
    }

    function alternarLanterna() {
        aplicar([{ torch: !lanternaAcesa }]).then(function (deu) {
            if (!deu) {
                aviso('Este aparelho nao deixa ligar a lanterna pela pagina.', 'is-err');
                botaoLanterna.hidden = true;
                return;
            }
            lanternaAcesa = !lanternaAcesa;
            botaoLanterna.setAttribute('aria-pressed', lanternaAcesa ? 'true' : 'false');
            botaoLanterna.classList.toggle('is-ligado', lanternaAcesa);
        });
    }

    /**
     * Aproximar.
     *
     * Vale mais que recortar a imagem por software: o zoom da camera entrega
     * pixels de verdade sobre as barras, em vez de esticar os que ja tem.
     *
     * Antes isto era um valor fixo que eu escolhi. Quem esta com a etiqueta na
     * mao sabe melhor do que eu a que distancia esta contando.
     */
    function prepararZoom() {
        if (!areaZoom || !controleZoom) {
            return;
        }

        var cap = capacidades();
        var tem = cap.zoom && cap.zoom.max > cap.zoom.min;

        areaZoom.hidden = !tem;
        if (!tem) {
            return;
        }

        controleZoom.min = cap.zoom.min;
        controleZoom.max = cap.zoom.max;
        controleZoom.step = cap.zoom.step || 0.1;

        // Comeca um pouco aproximado: e quase sempre melhor que o padrao para
        // etiqueta, e quem quiser volta no controle.
        var inicial = Math.min(cap.zoom.min + (cap.zoom.max - cap.zoom.min) * 0.3, cap.zoom.max);
        controleZoom.value = inicial;
        aplicar([{ zoom: inicial }]);
    }

    /**
     * Trocar camera.
     *
     * Celular moderno tem mais de uma lente atras, e a grande-angular - que
     * varios escolhem por padrao - nao foca de perto: e exatamente o caso de
     * ler etiqueta a um palmo. Qual lente serve varia por aparelho, entao em
     * vez de adivinhar pelo nome, deixa escolher.
     */
    function prepararTroca() {
        if (!botaoTrocar || !navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            return;
        }

        navigator.mediaDevices.enumerateDevices().then(function (lista) {
            cameras = lista.filter(function (d) { return d.kind === 'videoinput'; });
            botaoTrocar.hidden = cameras.length < 2;
        }).catch(function () {
            botaoTrocar.hidden = true;
        });
    }

    function trocarCamera() {
        if (cameras.length < 2) {
            return;
        }
        cameraAtual = (cameraAtual + 1) % cameras.length;
        aviso('Trocando de câmera…');
        reabrir();
    }

    /**
     * Focar onde a pessoa tocou.
     *
     * Em prateleira cheia o foco automatico fica caçando entre a etiqueta e a
     * caixa atras dela. Apontar resolve.
     */
    function focarNoToque(evento) {
        var cap = capacidades();
        if (!cap.focusMode || cap.focusMode.indexOf('single-shot') === -1) {
            return;
        }

        var r = video.getBoundingClientRect();
        var toque = evento.touches ? evento.touches[0] : evento;
        var x = (toque.clientX - r.left) / r.width;
        var y = (toque.clientY - r.top) / r.height;

        if (x < 0 || x > 1 || y < 0 || y > 1) {
            return;
        }

        var pedido = [{ focusMode: 'single-shot' }];
        if (cap.pointsOfInterest) {
            pedido[0].pointsOfInterest = [{ x: x, y: y }];
        }

        aplicar(pedido).then(function (deu) {
            if (deu && dicaToque) {
                dicaToque.classList.add('is-piscando');
                window.setTimeout(function () { dicaToque.classList.remove('is-piscando'); }, 600);
            }
        });
    }

    function prepararFoco() {
        var cap = capacidades();
        var avancado = [];

        if (cap.focusMode && cap.focusMode.indexOf('continuous') !== -1) {
            avancado.push({ focusMode: 'continuous' });
        }
        // Foco perto: e o que se pede a uma camera para ler etiqueta a um palmo.
        if (cap.focusDistance && typeof cap.focusDistance.min === 'number') {
            avancado.push({ focusDistance: cap.focusDistance.min });
        }

        aplicar(avancado);

        if (dicaToque) {
            dicaToque.hidden = !(cap.focusMode && cap.focusMode.indexOf('single-shot') !== -1);
        }
    }

    /* ----------------------------------------------------------------------
     * Tela cheia
     * -------------------------------------------------------------------- */

    function entrarEmTelaCheia() {
        painel.classList.add('is-cheio');
        document.body.classList.add('cam-travado');

        if (painel.requestFullscreen) {
            painel.requestFullscreen().then(travarHorizontal, function () {});
        } else {
            atualizarDicaDeGirar();
        }
    }

    function travarHorizontal() {
        var orient = window.screen && window.screen.orientation;

        if (orient && orient.lock) {
            orient.lock('landscape').catch(atualizarDicaDeGirar);
        } else {
            atualizarDicaDeGirar();
        }
    }

    function sairDaTelaCheia() {
        painel.classList.remove('is-cheio');
        document.body.classList.remove('cam-travado');

        var orient = window.screen && window.screen.orientation;
        if (orient && orient.unlock) {
            try { orient.unlock(); } catch (e) { /* nem todo aparelho deixa */ }
        }

        if (document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen().catch(function () {});
        }
    }

    function atualizarDicaDeGirar() {
        if (!dicaGirar) {
            return;
        }
        dicaGirar.hidden = !rodando || window.innerWidth >= window.innerHeight;
    }

    /* ---------------------------------------------------------------------- */

    function pararCamera(manterPainel) {
        rodando = false;
        lanternaAcesa = false;

        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        faixa = null;

        if (leitorZxing && leitorZxing.reset) {
            leitorZxing.reset();
        }

        video.srcObject = null;

        if (!manterPainel) {
            painel.hidden = true;
            abrir.setAttribute('aria-expanded', 'false');
            sairDaTelaCheia();
        }
    }

    function achou(texto) {
        var digitos = normalizar(texto);

        if (digitos.length !== DIGITOS) {
            aviso('Codigo lido tem ' + digitos.length + ' digitos; o do inventario tem ' + DIGITOS
                + '. Aproxime e tente de novo.', 'is-err');
            return;
        }

        var agora = Date.now();
        if (digitos === ultimoCodigo && (agora - ultimoEm) < 2000) {
            return;
        }
        ultimoCodigo = digitos;
        ultimoEm = agora;

        var campoCodigo = document.getElementById('CODIGOBARRAS');
        var campoQtd = document.getElementById('QUANTIDADE');

        pararCamera();

        if (campoCodigo) {
            campoCodigo.value = digitos;
        }

        aviso('Lido: ' + digitos + '. Informe a quantidade e grave.', 'is-ok');

        if (campoQtd) {
            campoQtd.focus();
            campoQtd.select();
            var r = campoQtd.getBoundingClientRect();
            if ((r.top < 0 || r.bottom > (window.innerHeight || 0)) && campoQtd.scrollIntoView) {
                campoQtd.scrollIntoView({ block: 'center' });
            }
        }
    }

    function lacoNativo() {
        if (!rodando) {
            return;
        }

        leitorNativo.detect(video)
            .then(function (codigos) {
                if (codigos && codigos.length > 0) {
                    achou(codigos[0].rawValue);
                }
            })
            .catch(function () {
                // Quadro ruim acontece o tempo todo; nao e erro que valha aviso.
            })
            .then(function () {
                if (rodando) {
                    // 120ms em vez de 200: mais tentativas por segundo, e a mao
                    // treme menos do que parece entre um quadro e outro.
                    window.setTimeout(lacoNativo, 120);
                }
            });
    }

    function comecarLeitura() {
        if (temLeitorNativo()) {
            leitorNativo = new window.BarcodeDetector({ formats: FORMATOS });
            aviso('Aponte para o codigo de barras.');
            lacoNativo();
            return Promise.resolve();
        }

        aviso('Preparando o leitor...');

        return carregarZxing().then(function () {
            var dicas = new Map();
            dicas.set(window.ZXing.DecodeHintType.POSSIBLE_FORMATS, [
                window.ZXing.BarcodeFormat.EAN_13,
                window.ZXing.BarcodeFormat.CODE_128,
                window.ZXing.BarcodeFormat.ITF,
                window.ZXing.BarcodeFormat.CODE_39,
                window.ZXing.BarcodeFormat.CODABAR,
                window.ZXing.BarcodeFormat.UPC_A
            ]);
            // Mais tempo por quadro: etiqueta amassada ou meio borrada so sai com
            // a varredura mais cuidadosa.
            dicas.set(window.ZXing.DecodeHintType.TRY_HARDER, true);

            leitorZxing = new window.ZXing.BrowserMultiFormatReader(dicas);
            leitorZxing.timeBetweenDecodingAttempts = 120;
            aviso('Aponte para o codigo de barras.');

            leitorZxing.decodeFromStream(stream, video, function (resultado) {
                if (resultado && rodando) {
                    achou(resultado.getText());
                }
            });
        });
    }

    function restricoes() {
        var video = {
            // Resolucao alta e o que separa as barras finas. Em 640x480 a
            // etiqueta sai borrada e o leitor nao decide.
            width: { ideal: 2560 },
            height: { ideal: 1440 },
            // Sem redimensionar: o navegador entregaria um recorte esticado de
            // uma resolucao menor, que e o oposto do que se quer aqui.
            resizeMode: 'none'
        };

        if (cameras.length > 1 && cameras[cameraAtual]) {
            video.deviceId = { exact: cameras[cameraAtual].deviceId };
        } else {
            // A traseira e a que enxerga a prateleira; sem isto o celular abre a
            // frontal e a pessoa filma o proprio rosto.
            video.facingMode = { ideal: 'environment' };
        }

        return { video: video, audio: false };
    }

    function ligar() {
        return navigator.mediaDevices.getUserMedia(restricoes()).then(function (s) {
            stream = s;
            faixa = s.getVideoTracks()[0] || null;
            video.srcObject = s;
            rodando = true;

            prepararFoco();
            prepararZoom();
            prepararLanterna();
            prepararTroca();

            return video.play().then(comecarLeitura);
        });
    }

    function reabrir() {
        pararCamera(true);
        ligar().catch(function () {
            aviso('Nao foi possivel usar esta camera. Toque em Trocar camera de novo.', 'is-err');
        });
    }

    function falhou(erro) {
        pararCamera();
        painel.hidden = false;

        var motivo = 'Nao foi possivel abrir a camera.';
        if (erro && (erro.name === 'NotAllowedError' || erro.name === 'SecurityError')) {
            motivo = 'Acesso a camera negado. Libere nas permissoes do navegador, ou use a busca por nome.';
        } else if (erro && erro.name === 'NotFoundError') {
            motivo = 'Nenhuma camera encontrada neste aparelho.';
        }

        aviso(motivo, 'is-err');
    }

    function abrirCamera() {
        if (!window.isSecureContext) {
            aviso('A camera so funciona em HTTPS. Use a busca por nome logo abaixo.', 'is-err');
            painel.hidden = false;
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            aviso('Este navegador nao da acesso a camera. Use a busca por nome logo abaixo.', 'is-err');
            painel.hidden = false;
            return;
        }

        painel.hidden = false;
        abrir.setAttribute('aria-expanded', 'true');
        aviso('Pedindo acesso a camera...');

        ligar()
            .then(entrarEmTelaCheia)
            .then(atualizarDicaDeGirar)
            .catch(falhou);
    }

    abrir.addEventListener('click', function () {
        if (painel.hidden) {
            abrirCamera();
        } else {
            pararCamera();
        }
    });

    if (fechar) {
        fechar.addEventListener('click', function () { pararCamera(); });
    }
    if (botaoLanterna) {
        botaoLanterna.addEventListener('click', alternarLanterna);
    }
    if (controleZoom) {
        controleZoom.addEventListener('input', function () {
            aplicar([{ zoom: parseFloat(controleZoom.value) }]);
        });
    }
    if (botaoTrocar) {
        botaoTrocar.addEventListener('click', trocarCamera);
    }
    if (caixa) {
        caixa.addEventListener('click', focarNoToque);
    }

    window.addEventListener('resize', atualizarDicaDeGirar);
    window.addEventListener('orientationchange', function () {
        window.setTimeout(atualizarDicaDeGirar, 200);
    });

    // Sair da tela cheia pelo gesto do sistema (ou pelo Esc) tem de fechar a
    // camera tambem - senao ela fica ligada atras da pagina.
    document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement && rodando && painel.classList.contains('is-cheio')) {
            pararCamera();
        }
    });

    // Sair da tela com a camera ligada deixaria a luz acesa e a bateria indo
    // embora no bolso de quem esta contando.
    window.addEventListener('pagehide', function () { pararCamera(); });
    document.addEventListener('visibilitychange', function () {
        if (document.hidden && rodando) {
            pararCamera();
            aviso('Camera desligada ao sair da tela.');
        }
    });
}());
