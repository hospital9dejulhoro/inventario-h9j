(function () {
    'use strict';

    var cfg = window.BI_CFG;
    var caixa = document.getElementById('bi-caixa');
    var termo = document.getElementById('bi-termo');
    var status = document.getElementById('bi-status');
    var lista = document.getElementById('bi-itens');
    var limpar = document.getElementById('bi-limpar');

    if (!cfg || !caixa || !termo || !status || !lista) {
        return;
    }

    var MINIMO = 2;
    var ESPERA = 300;

    var timer = null;
    var emVoo = null;
    var ultimoTermo = '';

    function aviso(texto, classe) {
        status.textContent = texto;
        status.className = 'form-hint bi-status' + (classe ? ' ' + classe : '');
    }

    function fmt(n) {
        var v = Number(n) || 0;
        return (Math.round(v * 100) / 100).toLocaleString('pt-BR');
    }

    /**
     * Preenche o campo de leitura com o item escolhido e devolve o foco para a
     * quantidade — que e o proximo passo real, nao o codigo de barras: quem
     * chegou ate aqui nao vai bipar, vai digitar quanto tem.
     */
    function usarItem(item) {
        var campoCodigo = document.getElementById('CODIGOBARRAS');
        var campoQtd = document.getElementById('QUANTIDADE');

        if (!campoCodigo) {
            return;
        }

        campoCodigo.value = item.codigobarras;
        caixa.open = false;

        var rotulo = item.nome + (item.lote ? ' · lote ' + item.lote : '');
        aviso('Selecionado: ' + rotulo + '. Informe a quantidade e grave.', 'is-ok');

        if (campoQtd) {
            campoQtd.focus();
            campoQtd.select();
            // O painel fechou e a pagina encolheu: sem isto o campo pode ficar
            // fora da tela no celular, com o foco nele.
            if (campoQtd.scrollIntoView) {
                var r = campoQtd.getBoundingClientRect();
                if (r.top < 0 || r.bottom > (window.innerHeight || 0)) {
                    campoQtd.scrollIntoView({ block: 'center' });
                }
            }
        }
    }

    function linhaDoItem(item) {
        var li = document.createElement('li');
        li.className = 'bi-item' + (item.contavel ? '' : ' is-fora');

        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'bi-item-botao';
        botao.disabled = !item.contavel;

        var nome = document.createElement('span');
        nome.className = 'bi-item-nome';
        nome.textContent = item.nome || ('Produto ' + item.idprd);
        botao.appendChild(nome);

        var meta = document.createElement('span');
        meta.className = 'bi-item-meta';
        var partes = [];
        if (item.codigo) { partes.push('Cód. ' + item.codigo); }
        if (item.lote) { partes.push('Lote ' + item.lote); }
        if (item.validade) { partes.push('Val. ' + item.validade); }
        partes.push('Saldo ' + fmt(item.saldo) + (item.und ? ' ' + item.und : ''));
        if (item.ja > 0) { partes.push('já contado ' + fmt(item.ja)); }
        meta.textContent = partes.join(' · ');
        botao.appendChild(meta);

        var acao = document.createElement('span');
        acao.className = 'bi-item-acao';
        acao.textContent = item.contavel ? 'Contar este' : 'Fora do inventário';
        botao.appendChild(acao);

        if (!item.contavel) {
            botao.title = 'Este produto não faz parte do inventário neste local. '
                + 'Gere o item no inventário pelo RM para poder contá-lo.';
        }

        botao.addEventListener('click', function () { usarItem(item); });
        li.appendChild(botao);

        return li;
    }

    function mostrar(dados) {
        lista.textContent = '';

        if (!dados.itens || dados.itens.length === 0) {
            aviso('Nada encontrado para "' + ultimoTermo + '". Tente parte do nome, o código do produto ou o lote.');
            return;
        }

        var frag = document.createDocumentFragment();
        dados.itens.forEach(function (item) { frag.appendChild(linhaDoItem(item)); });
        lista.appendChild(frag);

        var quantos = dados.itens.length;
        aviso(quantos === 1
            ? '1 item encontrado.'
            : quantos + ' itens encontrados.' + (dados.truncado ? ' Refine a busca para ver o resto.' : ''));
    }

    function buscar() {
        var valor = termo.value.trim();
        ultimoTermo = valor;

        if (valor.length < MINIMO) {
            lista.textContent = '';
            aviso('Digite pelo menos ' + MINIMO + ' caracteres.');
            return;
        }

        // A consulta de posicao e cara: uma busca em voo vira lixo assim que a
        // pessoa digita a proxima letra.
        if (emVoo) {
            emVoo.abort();
        }
        emVoo = new AbortController();

        aviso('Procurando…');

        var url = cfg.buscaUrl
            + (cfg.buscaUrl.indexOf('?') === -1 ? '?' : '&')
            + 'q=' + encodeURIComponent(valor)
            + '&CODINVENTARIO=' + encodeURIComponent(cfg.inventario)
            + '&CODLOC=' + encodeURIComponent(cfg.codloc);

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            signal: emVoo.signal
        })
            .then(function (r) {
                if (r.status === 401) {
                    throw new Error('Sessão expirada. Recarregue a página e entre de novo.');
                }
                return r.json();
            })
            .then(function (dados) {
                if (!dados || !dados.ok) {
                    throw new Error((dados && dados.message) || 'Não foi possível buscar.');
                }
                mostrar(dados);
            })
            .catch(function (erro) {
                if (erro.name === 'AbortError') {
                    return;
                }
                lista.textContent = '';
                aviso(erro.message || 'Não foi possível buscar.', 'is-err');
            });
    }

    termo.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(buscar, ESPERA);
    });

    // Enter busca na hora, sem esperar a pausa - e nao envia nada, porque este
    // campo esta fora da forma de gravacao.
    termo.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            window.clearTimeout(timer);
            buscar();
        }
    });

    if (limpar) {
        limpar.addEventListener('click', function () {
            termo.value = '';
            lista.textContent = '';
            aviso('Digite pelo menos ' + MINIMO + ' caracteres.');
            termo.focus();
        });
    }

    // Abrir o painel ja deixa o cursor no campo: quem abriu quer digitar.
    caixa.addEventListener('toggle', function () {
        if (caixa.open) {
            termo.focus();
        }
    });
}());
