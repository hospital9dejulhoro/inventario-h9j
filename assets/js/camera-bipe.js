(function () {
    'use strict';

    var cfg = window.CAM_CFG;
    var abrir = document.getElementById('cam-abrir');
    var painel = document.getElementById('cam-painel');
    var video = document.getElementById('cam-video');
    var status = document.getElementById('cam-status');
    var fechar = document.getElementById('cam-fechar');

    if (!cfg || !abrir || !painel || !video || !status) {
        return;
    }

    // Formatos que a etiqueta do RM pode sair: 13 digitos numericos cabem em
    // qualquer um destes, e o hospital tem etiqueta antiga e nova misturada.
    var FORMATOS = ['ean_13', 'code_128', 'itf', 'code_39', 'codabar', 'upc_a'];

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
     * Carrega a biblioteca so quando nao ha leitor nativo. No Android o arquivo
     * de 336KB nunca e baixado.
     */
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
    }

    /**
     * Achou um codigo: para a camera, preenche o campo e devolve o foco para a
     * quantidade. Mesmo caminho da busca por nome - quem bipou ainda precisa
     * dizer quantos sao.
     */
    function achou(texto) {
        var digitos = String(texto || '').replace(/\D/g, '');

        if (digitos.length !== 13) {
            // Nao interrompe: a camera segue lendo, so avisa.
            aviso('Codigo lido tem ' + digitos.length + ' digitos; o do inventario tem 13. Aproxime e tente de novo.', 'is-err');
            return;
        }

        // A camera le o mesmo codigo varias vezes por segundo; sem isto a tela
        // piscaria a confirmacao em loop.
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

        // A traseira e a que enxerga a prateleira; sem isto o celular abre a
        // frontal e a pessoa filma o proprio rosto.
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false
        })
            .then(function (s) {
                stream = s;
                video.srcObject = s;
                rodando = true;
                return video.play().then(comecarLeitura);
            })
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
