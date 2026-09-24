(function () {
    'use strict';

    var cfg = window.PL_CFG;
    if (!cfg) {
        return;
    }

    var table = document.getElementById('pl-table');
    var busca = document.getElementById('pl-busca');
    var ocultar = document.getElementById('pl-ocultar-contados');
    var statusEl = document.getElementById('pl-status');
    var countsEl = document.getElementById('pl-counts');

    var form = document.getElementById('pl-form');
    var vazioEl = document.getElementById('pl-form-vazio');
    var bloqueioEl = document.getElementById('pl-bloqueio');
    var grupoQtd = document.getElementById('pl-grupo-qtd');
    var fNome = document.getElementById('pl-f-nome');
    var fGrupo = document.getElementById('pl-f-grupo');
    var fLote = document.getElementById('pl-f-lote');
    var fValidade = document.getElementById('pl-f-validade');
    var fSaldo = document.getElementById('pl-f-saldo');
    var fValor = document.getElementById('pl-f-valor');
    var fJa = document.getElementById('pl-f-ja');
    var fQtd = document.getElementById('pl-f-qtd');
    var fGravar = document.getElementById('pl-f-gravar');
    var fQtdLabel = document.getElementById('pl-f-qtd-label');
    var modoAviso = document.getElementById('pl-modo-aviso');
    var radiosModo = [].slice.call(document.querySelectorAll('input[name="pl-modo"]'));
    var fCancelar = document.getElementById('pl-f-cancelar');

    if (!table || !form) {
        return;
    }

    var selecionada = null;
    var saving = false;

    // O navegador NAO interpreta o valor: so recusa o que nao e numero nenhum
    // e manda o texto como foi digitado. Quem decide quanto vale "1.250" e o
    // servidor (normalizar_quantidade). Havia duas interpretacoes diferentes
    // para o mesmo campo, e era dai que vinha o erro de mil vezes: o navegador
    // lia "1.250" como 1.25 e recusava "1.250,75", que o servidor aceitava.
    function pareceQuantidade(texto) {
        return /\d/.test(texto) && /^[\d.,\s\u00a0]+$/.test(texto);
    }

    function fmtQtd(n) {
        var s = Number(n).toFixed(3).replace('.', ',');
        return s.replace(/,?0+$/, '').replace(/,$/, '') || '0';
    }

    function rows() {
        return Array.prototype.slice.call(table.querySelectorAll('tbody tr.pl-row'));
    }

    function visiveis() {
        return rows().filter(function (tr) {
            return tr.style.display !== 'none';
        });
    }

    // O app.js e carregado depois deste arquivo, entao a busca e feita na hora
    // do uso - quando ele ja esta na pagina.
    function avisar(tipo) {
        var f = window.InventarioFeedback;
        if (f && f[tipo]) {
            f[tipo]();
        }
    }

    function setStatus(msg, tipo) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = msg || '';
        statusEl.classList.remove('is-ok', 'is-err');
        if (tipo) {
            statusEl.classList.add(tipo);
        }
    }

    function atualizarContadores() {
        var todas = rows();
        var contados = todas.filter(function (tr) {
            return tr.getAttribute('data-counted') === '1';
        }).length;
        if (countsEl) {
            countsEl.textContent = contados + '/' + todas.length + ' contados';
        }
    }

    function atualizarFiltro() {
        var q = (busca && busca.value ? busca.value : '').toLowerCase().trim();
        var esconderContados = ocultar && ocultar.checked;

        rows().forEach(function (tr) {
            var termo = tr.getAttribute('data-search') || '';
            var contado = tr.getAttribute('data-counted') === '1';
            var ok = (!q || termo.indexOf(q) !== -1) && !(esconderContados && contado);
            tr.style.display = ok ? '' : 'none';
        });

        atualizarContadores();
    }

    // Mesmo layout do PHP: IDPRD nos digitos 1-6, IDLOTE nos 8-12.
    function idsDaEtiqueta(valor) {
        var d = String(valor || '').replace(/\D/g, '');
        if (d.length !== 13) {
            return null;
        }
        return { idprd: parseInt(d.slice(0, 6), 10), idlote: parseInt(d.slice(7, 12), 10) };
    }

    function linhaDaEtiqueta(ids) {
        return rows().filter(function (tr) {
            return parseInt(tr.getAttribute('data-idprd'), 10) === ids.idprd
                && parseInt(tr.getAttribute('data-idlote'), 10) === ids.idlote;
        })[0] || null;
    }

    // Leitor dispara caractere a caractere; ao completar os 13 digitos a linha
    // e localizada e o campo limpo, pronto para a proxima etiqueta.
    function tratarEtiqueta() {
        var ids = idsDaEtiqueta(busca.value);
        if (!ids) {
            return false;
        }

        var tr = linhaDaEtiqueta(ids);
        busca.value = '';
        atualizarFiltro();

        if (!tr) {
            setStatus(
                'Etiqueta nao esta nesta lista (produto ' + ids.idprd + ', lote ' + ids.idlote
                + '). Se o lote estiver zerado, marque "Incluir lotes zerados".',
                'is-err'
            );
            return true;
        }

        selecionar(tr);
        return true;
    }

    function modoAtual() {
        var marcado = radiosModo.filter(function (r) { return r.checked; })[0];
        return marcado ? marcado.value : 'somar';
    }

    function aplicarModo() {
        var corrigindo = modoAtual() === 'corrigir';

        fQtdLabel.textContent = corrigindo ? 'Total correto do lote' : 'Quantidade contada';
        fGravar.textContent = corrigindo ? 'Corrigir total' : 'Gravar';
        modoAviso.hidden = !corrigindo;
        fQtd.classList.toggle('is-corrigindo', corrigindo);

        // Corrigindo, parte do total atual: o operador ve o que vai substituir
        // em vez de digitar no escuro.
        if (corrigindo && selecionada) {
            var ja = selecionada.getAttribute('data-ja') || '0';
            fQtd.value = ja === '0' ? '' : ja;
        } else {
            fQtd.value = '';
        }
        fQtd.focus();
        fQtd.select();
    }

    function unidade(tr) {
        var und = tr.getAttribute('data-und') || '';
        return und ? ' ' + und : '';
    }

    function dentroDoInventario(tr) {
        return tr.getAttribute('data-dentro') === '1';
    }

    function selecionar(tr) {
        if (!tr) {
            return;
        }

        rows().forEach(function (outra) {
            outra.classList.remove('is-selected');
        });
        tr.classList.add('is-selected');
        selecionada = tr;

        fNome.textContent = tr.getAttribute('data-nome') || '';
        var codigo = tr.getAttribute('data-codigo') || '';
        if (codigo) {
            fNome.textContent += ' · ' + codigo;
        }
        fGrupo.textContent = tr.getAttribute('data-grupo') || '—';
        fLote.textContent = tr.getAttribute('data-lote') || '';
        fValidade.textContent = tr.getAttribute('data-validade') || '';
        fSaldo.textContent = (tr.getAttribute('data-saldo') || '0') + unidade(tr);
        fValor.textContent = tr.getAttribute('data-valor') || '';
        fJa.textContent = (tr.getAttribute('data-ja') || '0') + unidade(tr);

        vazioEl.hidden = true;
        form.hidden = false;
        setStatus('');

        // Item com saldo no local mas fora do inventario: mostra os dados para
        // conferencia, mas nao deixa gravar - o servidor recusaria de qualquer jeito.
        // Inventario encerrado no RM vale para a folha toda, pelo mesmo caminho.
        var podeContar = dentroDoInventario(tr) && !cfg.somenteLeitura;
        if (cfg.somenteLeitura && cfg.motivoBloqueio) {
            bloqueioEl.textContent = cfg.motivoBloqueio;
        }
        bloqueioEl.hidden = podeContar;
        grupoQtd.hidden = !podeContar;
        fGravar.disabled = !podeContar;

        // Cada item comeca somando; corrigir e escolha consciente.
        radiosModo.forEach(function (r) { r.checked = (r.value === 'somar'); });
        aplicarModo();

        if (!podeContar) {
            fQtd.blur();
        }

        if (tr.scrollIntoView) {
            tr.scrollIntoView({ block: 'nearest' });
        }
    }

    function limparSelecao() {
        rows().forEach(function (tr) {
            tr.classList.remove('is-selected');
        });
        selecionada = null;
        form.hidden = true;
        vazioEl.hidden = false;
        setStatus('');
        if (busca) {
            busca.focus();
            busca.select();
        }
    }


    /**
     * Falha que nao pode escapar.
     *
     * A linha de status fica no meio da pagina e some de vista quando a lista e
     * longa. Recusa de gravacao - produto fora do estoque, lote faltando - tem
     * de parar quem esta contando.
     */
    /*
     * Gravacao sem limite de espera deixava a tela presa em "Gravando...", com
     * o botao desligado, ate a pessoa recarregar. Rede de hospital cai, e quem
     * esta contando nao tem como saber se deve esperar mais.
     */
    var LIMITE_GRAVACAO = 15000;

    function falhaVisivel(mensagem, tipo) {
        setStatus(mensagem, tipo === 'warning' ? '' : 'is-err');
        if (typeof window.avisoModal === 'function') {
            window.avisoModal(mensagem, tipo || 'danger');
        }
    }

    function gravar() {
        if (saving || !selecionada || !dentroDoInventario(selecionada)) {
            return;
        }

        var tr = selecionada;
        var raw = String(fQtd.value || '').trim();

        var corrigindo = modoAtual() === 'corrigir';

        // Faixa (maior que zero, nao negativo) fica com o servidor, que ja
        // recusa com mensagem propria — duplicar a regra aqui foi o erro.
        if (!pareceQuantidade(raw)) {
            setStatus(corrigindo
                ? 'Informe o total correto (zero apaga a contagem deste lote).'
                : 'Informe uma quantidade maior que zero.', 'is-err');
            fQtd.focus();
            return;
        }

        if (corrigindo && !confirm('Substituir a contagem deste lote por ' + raw + '?')) {
            return;
        }

        saving = true;
        fGravar.disabled = true;
        tr.classList.add('is-saving');
        setStatus('Gravando...');

        var body = new FormData();
        body.append('idprd', tr.getAttribute('data-idprd') || '');
        body.append('idlote', tr.getAttribute('data-idlote') || '');
        body.append('quantidade', raw);
        body.append('CODINVENTARIO', cfg.inventario);
        body.append('CODLOC', cfg.codloc);
        body.append('modo', modoAtual());
        body.append('_token', cfg.token || '');

        var controle = (typeof AbortController === 'function') ? new AbortController() : null;
        var estourouOTempo = false;
        var relogio = window.setTimeout(function () {
            estourouOTempo = true;
            if (controle) {
                controle.abort();
            }
        }, LIMITE_GRAVACAO);

        fetch(cfg.saveUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            signal: controle ? controle.signal : undefined
        })
            .then(function (r) { window.clearTimeout(relogio); return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.message) || 'Falha ao gravar');
                }

                var total = fmtQtd(data.total);
                var zerado = Number(data.total) === 0;

                tr.setAttribute('data-ja', total);
                tr.setAttribute('data-counted', zerado ? '0' : '1');
                tr.classList.toggle('is-counted', !zerado);
                tr.classList.add('is-flash');

                var celulaJa = tr.querySelector('.pl-ja');
                if (celulaJa) {
                    celulaJa.textContent = zerado ? '—' : total;
                }
                fJa.textContent = (zerado ? '0' : total) + unidade(tr);

                // Volta para somar: deixar em corrigir faria o proximo Enter
                // substituir sem querer.
                radiosModo.forEach(function (r) { r.checked = (r.value === 'somar'); });
                aplicarModo();

                // O servidor avisa quando outro operador mexeu no mesmo lote
                // entre o numero que esta tela mostrou e o clique em Corrigir.
                if (data.aviso) {
                    avisar('alerta');
                    falhaVisivel(data.aviso, 'warning');
                } else if (data.modo === 'corrigir') {
                    avisar('sucesso');
                    setStatus(
                        zerado
                            ? 'Contagem deste lote apagada.'
                            : 'Total corrigido para ' + total
                              + (data.apagados > 1 ? ' (' + data.apagados + ' lancamentos substituidos)' : ''),
                        'is-ok'
                    );
                } else if (data.leituras > 1) {
                    // Pode ser repeticao sua ou contagem de um colega no mesmo
                    // inventario - com varias pessoas contando, as duas coisas
                    // acontecem e a mensagem nao pode acusar so a primeira.
                    avisar('alerta');
                    setStatus(
                        data.leituras + 'a leitura deste lote - total ' + total
                        + '. Se voce nao contou antes, foi outro operador.',
                        'is-err'
                    );
                } else {
                    avisar('sucesso');
                    setStatus('Gravado - total ' + total, 'is-ok');
                }

                setTimeout(function () { tr.classList.remove('is-flash'); }, 700);
                atualizarFiltro();

                if (busca) {
                    busca.focus();
                    busca.select();
                }
            })
            .catch(function (err) {
                window.clearTimeout(relogio);
                avisar('alerta');

                // Tempo esgotado do lado de ca nao quer dizer que o servidor
                // nao gravou: mandar contar de novo seria mandar contar dobrado.
                if (estourouOTempo) {
                    falhaVisivel('A gravação demorou demais e foi interrompida. NÃO conte de novo sem conferir: '
                        + 'pode ter gravado do lado do servidor. Recarregue a tela (F5) e veja o '
                        + 'total antes de repetir.',
                        // "Confira antes de seguir", e nao "Nao foi gravado":
                        // do lado de ca nao da para saber qual dos dois foi.
                        'warning');
                } else {
                    falhaVisivel(err.message || 'Erro ao gravar.');
                }

                fQtd.focus();
                fQtd.select();
            })
            .then(function () {
                saving = false;
                fGravar.disabled = !dentroDoInventario(tr);
                tr.classList.remove('is-saving');
            });
    }

    // ---- Totais de outros operadores -------------------------------------
    //
    // A coluna "Ja" congela no instante em que a tela abre. Com varias pessoas
    // contando o mesmo inventario, ela envelhece em segundos. Nao fica em
    // polling de proposito: recarrega quando a aba volta ao foco (o operador
    // andou pela prateleira e voltou) e no botao, que e quando o numero
    // importa. Polling de 10 telas ocuparia os processos do PHP-FPM a toa.
    var atualizando = false;
    var ultimaAtualizacao = 0;

    function aplicarTotais(totais) {
        rows().forEach(function (tr) {
            var chave = tr.getAttribute('data-idprd') + ':' + tr.getAttribute('data-idlote');
            var valor = Object.prototype.hasOwnProperty.call(totais, chave) ? Number(totais[chave]) : 0;
            var temContagem = valor > 0;

            tr.setAttribute('data-ja', temContagem ? fmtQtd(valor) : '0');
            tr.setAttribute('data-counted', temContagem ? '1' : '0');
            tr.classList.toggle('is-counted', temContagem);

            var celula = tr.querySelector('.pl-ja');
            if (celula) {
                celula.textContent = temContagem ? fmtQtd(valor) : '—';
            }
        });

        if (selecionada) {
            fJa.textContent = (selecionada.getAttribute('data-ja') || '0') + unidade(selecionada);
        }

        // atualizarFiltro recalcula o "N/M contados" a partir de data-counted,
        // que acabou de ser reescrito acima.
        atualizarFiltro();
    }

    function atualizarTotais(silencioso) {
        if (atualizando || !cfg.totaisUrl) {
            return;
        }
        // Voltar o foco varias vezes seguidas nao precisa de uma consulta cada.
        if (silencioso && Date.now() - ultimaAtualizacao < 10000) {
            return;
        }

        atualizando = true;
        if (!silencioso) {
            setStatus('Atualizando o que os outros ja contaram...');
        }

        var url = cfg.totaisUrl
            + (cfg.totaisUrl.indexOf('?') === -1 ? '?' : '&')
            + 'CODINVENTARIO=' + encodeURIComponent(cfg.inventario)
            + '&modo=lote';

        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.message) || 'Falha ao atualizar');
                }
                ultimaAtualizacao = Date.now();
                aplicarTotais(data.totais || {});
                if (!silencioso) {
                    setStatus('Totais atualizados as ' + data.atualizado + '.', 'is-ok');
                }
            })
            .catch(function (err) {
                if (!silencioso) {
                    setStatus(err.message || 'Nao foi possivel atualizar os totais.', 'is-err');
                }
            })
            .then(function () {
                atualizando = false;
            });
    }

    var btnAtualizar = document.getElementById('pl-atualizar');
    if (btnAtualizar) {
        btnAtualizar.addEventListener('click', function () { atualizarTotais(false); });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            atualizarTotais(true);
        }
    });
    window.addEventListener('focus', function () { atualizarTotais(true); });

    table.addEventListener('click', function (e) {
        var tr = e.target.closest('tr.pl-row');
        if (tr) {
            selecionar(tr);
        }
    });

    table.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        var tr = e.target.closest('tr.pl-row');
        if (tr) {
            e.preventDefault();
            selecionar(tr);
        }
    });

    if (busca) {
        busca.addEventListener('input', function () {
            if (!tratarEtiqueta()) {
                atualizarFiltro();
            }
        });

        busca.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            // Leitor termina com Enter; se ainda houver etiqueta no campo,
            // resolve aqui em vez de recarregar a pagina.
            if (idsDaEtiqueta(busca.value)) {
                e.preventDefault();
                tratarEtiqueta();
                return;
            }

            // Campo vazio: nada a procurar. Sem isto, o Enter que o leitor manda
            // depois da etiqueta cairia aqui e selecionaria a primeira linha,
            // atropelando a que a etiqueta acabou de escolher.
            if (busca.value.trim() === '') {
                e.preventDefault();
                return;
            }

            var lista = visiveis();
            if (lista.length > 0) {
                // Tem resultado na tela: Enter pega o primeiro em vez de recarregar.
                e.preventDefault();
                selecionar(lista[0]);
            }
            // Sem resultado local, deixa o form seguir e buscar no RM.
        });
    }

    if (ocultar) {
        ocultar.addEventListener('change', atualizarFiltro);
    }

    radiosModo.forEach(function (r) {
        r.addEventListener('change', aplicarModo);
    });

    // O disparo de [data-autosubmit] mora no app.js: a tela "sem lote" usa o
    // mesmo recurso e não carrega este arquivo.

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        gravar();
    });

    if (fCancelar) {
        fCancelar.addEventListener('click', limparSelecao);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !form.hidden) {
            limparSelecao();
        }
    });

    atualizarFiltro();

    if (busca) {
        busca.focus();
    }
})();
