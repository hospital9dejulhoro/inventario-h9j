(function () {
    'use strict';

    var cfg = window.CAM_CFG;
    var abrir = document.getElementById('cam-abrir');
    var painel = document.getElementById('cam-painel');
    var video = document.getElementById('cam-video');
    var status = document.getElementById('cam-status');
    var fechar = document.getElementById('cam-fechar');
    var dicaGirar = document.getElementById('cam-girar');

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
    var leitorNativo = null;
    var leitorZxing = null;
    var rodando = false;
    var ultimoCodigo = '';
    var ultimoEm = 0;

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
     * Tela cheia
     *
     * O codigo de barras e largo e baixo. Numa janelinha no meio da pagina e
     * preciso afastar o celular ate a etiqueta inteira caber, e ai ela fica
     * pequena demais para o leitor resolver as barras finas. Ocupando a tela,
     * da para chegar perto.
     *
     * A sobreposicao e feita com CSS, nao com a Fullscreen API: no iPhone a API
     * so funciona no proprio <video>, e ai o Safari poe os controles nativos por
     * cima e esconde a mira. O CSS funciona igual nos dois.
     *
     * Por cima disso, quando o aparelho deixa, pede tela cheia de verdade (some
     * a barra do navegador) e trava na horizontal. O iPhone nao permite travar a
     * orientacao - por isso existe a dica de girar.
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
            orient.lock('landscape').catch(function () {
                // iPhone, e Android com rotacao bloqueada no sistema.
                atualizarDicaDeGirar();
            });
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
        // Em pe, a etiqueta cabe em menos pixels de largura - que e a dimensao
        // que importa para separar as barras.
        dicaGirar.hidden = !rodando || window.innerWidth >= window.innerHeight;
    }

    /* ----------------------------------------------------------------------
     * Foco e resolucao
     * -------------------------------------------------------------------- */

    /**
     * Sem isto a camera abre em 640x480 e focada longe: a etiqueta sai borrada e
     * o leitor nao resolve as barras finas. Pedir resolucao alta da pixels
     * suficientes, e o foco continuo faz a camera reajustar quando a mao mexe.
     *
     * Sao ajustes opcionais: aparelho que nao suporta ignora, e a camera abre
     * assim mesmo.
     */
    function melhorarFoco(faixa) {
        if (!faixa || !faixa.applyConstraints) {
            return;
        }

        var capacidades = faixa.getCapabilities ? faixa.getCapabilities() : {};
        var avancado = [];

        if (capacidades.focusMode && capacidades.focusMode.indexOf('continuous') !== -1) {
            avancado.push({ focusMode: 'continuous' });
        }

        // Um pouco de zoom optico aproxima sem precisar encostar na etiqueta.
        if (capacidades.zoom && capacidades.zoom.max > capacidades.zoom.min) {
            var alvo = Math.min(capacidades.zoom.min + (capacidades.zoom.max - capacidades.zoom.min) * 0.3,
                capacidades.zoom.max);
            avancado.push({ zoom: alvo });
        }

        if (avancado.length === 0) {
            return;
        }

        faixa.applyConstraints({ advanced: avancado }).catch(function () {
            // Restricao avancada e opcional: se o aparelho recusa, segue sem.
        });
    }

    /* ---------------------------------------------------------------------- */

    function pararCamera() {
        rodando = false;

        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }

        if (leitorZxing && leitorZxing.reset) {
            leitorZxing.reset();
        }

        video.srcObject = null;
        painel.hidden = true;
        abrir.setAttribute('aria-expanded', 'false');
        sairDaTelaCheia();
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
                    window.setTimeout(lacoNativo, 200);
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
            aviso('Aponte para o codigo de barras.');

            leitorZxing.decodeFromStream(stream, video, function (resultado) {
                if (resultado && rodando) {
                    achou(resultado.getText());
                }
            });
        });
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

        navigator.mediaDevices.getUserMedia({
            video: {
                // A traseira e a que enxerga a prateleira; sem isto o celular
                // abre a frontal e a pessoa filma o proprio rosto.
                facingMode: { ideal: 'environment' },
                // Resolucao alta e o que separa as barras finas. Em 640x480 a
                // etiqueta sai borrada e o leitor nao decide.
                width: { ideal: 1920 },
                height: { ideal: 1080 }
            },
            audio: false
        })
            .then(function (s) {
                stream = s;
                video.srcObject = s;
                rodando = true;
                melhorarFoco(s.getVideoTracks()[0]);
                entrarEmTelaCheia();
                return video.play().then(comecarLeitura);
            })
            .then(atualizarDicaDeGirar)
            .catch(function (erro) {
                pararCamera();
                painel.hidden = false;

                var motivo = 'Nao foi possivel abrir a camera.';
                if (erro && (erro.name === 'NotAllowedError' || erro.name === 'SecurityError')) {
                    motivo = 'Acesso a camera negado. Libere nas permissoes do navegador, ou use a busca por nome.';
                } else if (erro && erro.name === 'NotFoundError') {
                    motivo = 'Nenhuma camera encontrada neste aparelho.';
                }

                aviso(motivo, 'is-err');
            });
    }

    abrir.addEventListener('click', function () {
        if (painel.hidden) {
            abrirCamera();
        } else {
            pararCamera();
        }
    });

    if (fechar) {
        fechar.addEventListener('click', pararCamera);
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
    window.addEventListener('pagehide', pararCamera);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden && rodando) {
            pararCamera();
            aviso('Camera desligada ao sair da tela.');
        }
    });
}());
