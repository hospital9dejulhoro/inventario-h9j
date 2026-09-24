(function () {
    'use strict';

    /*
     * Um campo só para bipar e para procurar.
     *
     * Treze dígitos são uma leitura e seguem o caminho de sempre: quem cuida do
     * Enter com código completo é o app.js, que já fazia isso. Qualquer outra
     * coisa — nome, código do produto, lote — vira busca, e o resultado aparece
     * logo abaixo do campo.
     *
     * Escolher um item da lista preenche o mesmo campo com o código de barras
     * dele. Por isso a busca não é um desvio: ela termina exatamente onde a
     * bipagem terminaria.
     */

    var cfg = window.BI_CFG;
    var caixa = document.getElementById('bi-caixa');
    var campo = document.getElementById('CODIGOBARRAS');
    var status = document.getElementById('bi-status');
    var lista = document.getElementById('bi-itens');
    var forma = document.getElementById('inventory-form');

    if (!cfg || !campo || !status || !lista) {
        return;
    }

    var MINIMO = 2;
    var ESPERA_TEXTO = 300;

    /*
     * Leitor de mão manda os 13 dígitos um a um, como se fossem teclas, e os
     * mais lentos gastam ~50ms por caractere. Com a mesma espera do texto, um
     * número curto no meio do bipe já viraria busca — uma consulta cara jogada
     * fora a cada leitura. Esperar mais só atrasa quem procura por código
     * digitado, que é o caso raro.
     */
    var ESPERA_NUMERO = 700;

    var timer = null;
    var emVoo = null;
    var ultimoTermo = '';

    function soDigitos(v) {
        return /^\d+$/.test(v);
    }

    /**
     * Parece leitura, não busca?
     *
     * Treze é o tamanho do código daqui. Doze entra junto porque é o que os
     * leitores de iPhone devolvem quando o código começa com zero, e nenhum
     * código de produto ou lote deste banco chega perto desse tamanho.
     */
    function pareceCodigo(v) {
        return /^\d{12,13}$/.test(v);
    }

    /*
     * A forma de gravacao e uma coluna flex com 16px de espaco entre os itens.
     * Vazia, a caixa de resultados continuava sendo um item da coluna e comia
     * dois desses espacos: 32px de vao entre o campo e a camera, o tempo todo,
     * so para o caso de um dia haver lista.
     */
    function atualizarCaixa() {
        if (caixa) {
            caixa.hidden = status.hidden && lista.children.length === 0;
        }
    }

    function aviso(texto, classe) {
        status.textContent = texto;
        status.className = 'form-hint bi-status' + (classe ? ' ' + classe : '');
        status.hidden = false;
        atualizarCaixa();
    }

    function calar() {
        status.textContent = '';
        status.hidden = true;
        atualizarCaixa();
    }

    function limparLista() {
        lista.textContent = '';
        atualizarCaixa();
    }

    function cancelarBusca() {
        window.clearTimeout(timer);
        if (emVoo) {
            emVoo.abort();
            emVoo = null;
        }
    }

    /**
     * Centralizado e monoespaçado serve para 13 dígitos; para "dipirona" fica
     * um nome espaçado no meio da caixa, difícil de ler e de corrigir.
     */
    function ajustarAparencia() {
        var v = campo.value;
        campo.classList.toggle('is-texto', v !== '' && !soDigitos(v));
    }

    function fmt(n) {
        var v = Number(n) || 0;
        return (Math.round(v * 100) / 100).toLocaleString('pt-BR');
    }

    /**
     * Preenche o campo com o item escolhido e devolve o foco para a quantidade
     * — que e o proximo passo real: quem chegou ate aqui nao vai bipar, vai
     * digitar quanto tem.
     */
    function usarItem(item) {
        var campoQtd = document.getElementById('QUANTIDADE');

        cancelarBusca();
        campo.value = item.codigobarras;
        ajustarAparencia();
        limparLista();

        var rotulo = item.nome + (item.lote ? ' · lote ' + item.lote : '');
        aviso('Selecionado: ' + rotulo + '. Informe a quantidade e grave.', 'is-ok');

        if (campoQtd) {
            campoQtd.focus();
            campoQtd.select();
            // A lista sumiu e a pagina encolheu: sem isto o campo pode ficar
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
        li.className = 'bi-item';

        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'bi-item-botao';

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
        acao.textContent = 'Contar este';
        botao.appendChild(acao);

        // Esta no estoque mas o RM nao gerou no inventario: da para contar, e a
        // marca serve para quem for conferir a divergencia depois.
        if (item.fora_do_rm) {
            var marca = document.createElement('span');
            marca.className = 'bi-item-marca';
            marca.textContent = 'fora do inventário';
            marca.title = 'Está no estoque do local, mas o RM não gerou este item no inventário. Pode contar.';
            meta.appendChild(marca);
        }

        botao.addEventListener('click', function () { usarItem(item); });
        li.appendChild(botao);

        return li;
    }

    function mostrar(dados) {
        limparLista();

        if (!dados.itens || dados.itens.length === 0) {
            aviso('Nada encontrado para "' + ultimoTermo + '". Tente parte do nome, o código do produto ou o lote.');
            return;
        }

        var frag = document.createDocumentFragment();
        dados.itens.forEach(function (item) { frag.appendChild(linhaDoItem(item)); });
        lista.appendChild(frag);
        atualizarCaixa();

        var quantos = dados.itens.length;
        aviso(quantos === 1
            ? '1 item encontrado.'
            : quantos + ' itens encontrados.' + (dados.truncado ? ' Refine a busca para ver o resto.' : ''));
    }

    function buscar() {
        var valor = campo.value.trim();
        ultimoTermo = valor;

        if (valor.length < MINIMO || pareceCodigo(valor)) {
            limparLista();
            calar();
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
                limparLista();
                aviso(erro.message || 'Não foi possível buscar.', 'is-err');
            });
    }

    campo.addEventListener('input', function () {
        ajustarAparencia();
        cancelarBusca();

        var valor = campo.value.trim();

        // Nada a procurar: enquanto o código completo não chega, a tela fica
        // quieta. Avisar "digite mais" a cada dígito do bipe seria pior que
        // não avisar nada.
        if (valor === '' || pareceCodigo(valor) || valor.length < MINIMO) {
            limparLista();
            calar();
            return;
        }

        timer = window.setTimeout(buscar, soDigitos(valor) ? ESPERA_NUMERO : ESPERA_TEXTO);
    });

    // Enter com código completo é bipagem, e quem trata disso é o app.js.
    // Enter com qualquer outra coisa procura na hora, sem esperar a pausa.
    campo.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') {
            return;
        }

        var valor = campo.value.trim();
        if (valor === '' || pareceCodigo(valor)) {
            return;
        }

        e.preventDefault();
        cancelarBusca();
        buscar();
    });

    /*
     * O botão "Registrar leitura" continua sendo o jeito de gravar, mas com um
     * nome no campo ele mandaria "dipirona" para o servidor e receberia de
     * volta um erro. Aqui ele procura, que é o que a pessoa quis dizer.
     */
    if (forma) {
        forma.addEventListener('submit', function (e) {
            var valor = campo.value.trim();
            if (valor === '' || pareceCodigo(valor)) {
                return;
            }

            e.preventDefault();
            cancelarBusca();
            buscar();
        });
    }

    ajustarAparencia();
    atualizarCaixa();
}());
