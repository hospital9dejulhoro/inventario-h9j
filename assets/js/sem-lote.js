(function () {
    'use strict';

    var cfg = window.SL_CFG;
    if (!cfg) {
        return;
    }

    var table = document.getElementById('sl-table');
    var busca = document.getElementById('sl-busca');
    var ocultar = document.getElementById('sl-ocultar-contados');
    var statusEl = document.getElementById('sl-status');
    var countsEl = document.getElementById('sl-counts');
    var saving = false;

    // Sem tabela não há nada a ligar. A tela de seleção não carrega este
    // script, mas uma mudança de view que carregue quebraria a página inteira
    // no primeiro addEventListener — a tela de lotes já tem essa guarda.
    if (!table) {
        return;
    }

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
        return Array.prototype.slice.call(table.querySelectorAll('tbody tr.sl-row'));
    }

    function atualizarFiltro() {
        var q = (busca && busca.value ? busca.value : '').toLowerCase().trim();
        var hide = ocultar && ocultar.checked;
        var visiveis = 0;
        rows().forEach(function (tr) {
            var search = tr.getAttribute('data-search') || '';
            var counted = tr.getAttribute('data-counted') === '1';
            var ok = (!q || search.indexOf(q) !== -1) && !(hide && counted);
            tr.style.display = ok ? '' : 'none';
            if (ok) {
                visiveis += 1;
            }
        });
        var total = rows().length;
        var contados = rows().filter(function (tr) {
            return tr.getAttribute('data-counted') === '1';
        }).length;
        if (countsEl) {
            countsEl.textContent = contados + '/' + total + ' contados';
        }
        if (statusEl && !saving) {
            statusEl.textContent = visiveis + ' itens visíveis · Enter grava a quantidade.';
        }
    }

    function focusProximo(fromTr) {
        var list = rows().filter(function (tr) {
            return tr.style.display !== 'none';
        });
        var idx = list.indexOf(fromTr);
        var next = list[idx + 1] || list[0];
        if (next) {
            var input = next.querySelector('.sl-qtd');
            if (input) {
                input.focus();
                input.select();
            }
        }
    }

    // O app.js é carregado depois deste arquivo, então a busca é feita na hora
    // do uso — quando ele já está na página.
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
        statusEl.textContent = msg;
        statusEl.classList.remove('is-ok', 'is-err');
        if (tipo) {
            statusEl.classList.add(tipo);
        }
    }

    var radiosModo = [].slice.call(document.querySelectorAll('input[name="sl-modo"]'));

    function modoAtual() {
        var marcado = radiosModo.filter(function (r) { return r.checked; })[0];
        return marcado ? marcado.value : 'somar';
    }

    function voltarParaSomar() {
        radiosModo.forEach(function (r) { r.checked = (r.value === 'somar'); });
        aplicarModo();
    }

    function aplicarModo() {
        var corrigindo = modoAtual() === 'corrigir';
        document.body.classList.toggle('sl-corrigindo', corrigindo);
        rows().forEach(function (tr) {
            var input = tr.querySelector('.sl-qtd');
            if (input) {
                input.placeholder = corrigindo ? 'total' : '';
            }
        });
    }

    radiosModo.forEach(function (r) { r.addEventListener('change', aplicarModo); });
    aplicarModo();

    function registrar(tr) {
        if (saving || !tr) {
            return;
        }
        var input = tr.querySelector('.sl-qtd');
        var btn = tr.querySelector('.sl-btn');
        var raw = input ? String(input.value || '').trim() : '';
        var corrigindo = modoAtual() === 'corrigir';

        // Faixa (maior que zero, nao negativo) fica com o servidor, que ja
        // recusa com mensagem propria — duplicar a regra aqui foi o erro.
        if (!pareceQuantidade(raw)) {
            setStatus(corrigindo
                ? 'Informe o total correto (zero apaga a contagem deste item).'
                : 'Informe uma quantidade maior que zero.', 'is-err');
            if (input) {
                input.focus();
            }
            return;
        }

        if (corrigindo) {
            var nome = (tr.querySelector('.sl-nome-main') || {}).textContent || 'este item';
            if (!confirm('Substituir a contagem de ' + nome.trim() + ' por ' + raw + '?')) {
                return;
            }
        }

        saving = true;
        if (btn) {
            btn.disabled = true;
        }
        tr.classList.add('is-saving');
        setStatus('Gravando…');

        var body = new FormData();
        body.append('idprd', tr.getAttribute('data-idprd') || '');
        body.append('quantidade', raw);
        body.append('CODINVENTARIO', cfg.inventario);
        body.append('CODLOC', cfg.codloc);
        body.append('modo', modoAtual());
        body.append('_token', cfg.token || '');

        fetch(cfg.saveUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.message) || 'Falha ao gravar');
                }
                var total = fmtQtd(data.total);
                var zerado = Number(data.total) === 0;

                var ja = tr.querySelector('.sl-ja');
                if (ja) {
                    ja.textContent = zerado ? '—' : total;
                }
                tr.classList.toggle('is-counted', !zerado);
                tr.classList.add('is-flash');
                tr.setAttribute('data-counted', zerado ? '0' : '1');
                if (input) {
                    input.value = '';
                }

                // Volta para somar: deixar em corrigir faria o próximo Enter
                // substituir sem querer.
                voltarParaSomar();

                if (data.aviso) {
                    avisar('alerta');
                    setStatus(data.aviso, 'is-err');
                } else if (data.modo === 'corrigir') {
                    avisar('sucesso');
                    setStatus(
                        zerado
                            ? 'Contagem deste item apagada.'
                            : 'Total corrigido para ' + total
                              + (data.apagados > 1 ? ' (' + data.apagados + ' lançamentos substituídos)' : ''),
                        'is-ok'
                    );
                } else {
                    avisar('sucesso');
                    setStatus('Registrado · total ' + total, 'is-ok');
                }

                setTimeout(function () { tr.classList.remove('is-flash'); }, 700);
                atualizarFiltro();
                focusProximo(tr);
            })
            .catch(function (err) {
                avisar('alerta');
                setStatus(err.message || 'Erro ao gravar.', 'is-err');
                if (input) {
                    input.focus();
                    input.select();
                }
            })
            .then(function () {
                saving = false;
                if (btn) {
                    btn.disabled = false;
                }
                tr.classList.remove('is-saving');
            });
    }

    // ---- Totais de outros operadores -------------------------------------
    //
    // Mesmo motivo da tela de lotes: a coluna "Ja" congela quando a tela abre.
    // Sem polling — atualiza no botao e quando a aba volta ao foco.
    var atualizando = false;
    var ultimaAtualizacao = 0;

    function aplicarTotais(totais) {
        rows().forEach(function (tr) {
            var chave = tr.getAttribute('data-idprd');
            var valor = Object.prototype.hasOwnProperty.call(totais, chave) ? Number(totais[chave]) : 0;
            var temContagem = valor > 0;

            tr.setAttribute('data-counted', temContagem ? '1' : '0');
            tr.classList.toggle('is-counted', temContagem);

            var celula = tr.querySelector('.sl-ja');
            if (celula) {
                celula.textContent = temContagem ? fmtQtd(valor) : '—';
            }
        });
        atualizarFiltro();
    }

    function atualizarTotais(silencioso) {
        if (atualizando || !cfg.totaisUrl) {
            return;
        }
        if (silencioso && Date.now() - ultimaAtualizacao < 10000) {
            return;
        }

        atualizando = true;
        if (!silencioso) {
            setStatus('Atualizando o que os outros já contaram…');
        }

        var url = cfg.totaisUrl
            + (cfg.totaisUrl.indexOf('?') === -1 ? '?' : '&')
            + 'CODINVENTARIO=' + encodeURIComponent(cfg.inventario)
            + '&modo=produto';

        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.message) || 'Falha ao atualizar');
                }
                ultimaAtualizacao = Date.now();
                aplicarTotais(data.totais || {});
                if (!silencioso) {
                    setStatus('Totais atualizados às ' + data.atualizado + '.', 'is-ok');
                }
            })
            .catch(function (err) {
                if (!silencioso) {
                    setStatus(err.message || 'Não foi possível atualizar os totais.', 'is-err');
                }
            })
            .then(function () {
                atualizando = false;
            });
    }

    var btnAtualizar = document.getElementById('sl-atualizar');
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
        var btn = e.target.closest('.sl-btn');
        if (btn) {
            registrar(btn.closest('tr'));
        }
    });

    table.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') {
            return;
        }
        var input = e.target.closest('.sl-qtd');
        if (!input) {
            return;
        }
        e.preventDefault();
        registrar(input.closest('tr'));
    });

    // ---- Bipe de etiqueta ------------------------------------------------
    //
    // Produto sem lote tem IDLOTE 0 no layout do codigo, entao a etiqueta dele
    // e IDPRD nos digitos 1-6 e zeros no resto. O leitor digita os 13 numeros
    // no campo de busca; ao completar, a linha e localizada e o cursor cai
    // direto na quantidade. Mesma interacao da tela de lotes.
    function idprdDaEtiqueta(valor) {
        var d = String(valor || '').replace(/\D/g, '');
        if (d.length !== 13) {
            return null;
        }
        return { idprd: parseInt(d.slice(0, 6), 10), idlote: parseInt(d.slice(7, 12), 10) };
    }

    function tratarEtiqueta() {
        var ids = idprdDaEtiqueta(busca.value);
        if (!ids) {
            return false;
        }

        busca.value = '';
        atualizarFiltro();

        var alvo = rows().filter(function (tr) {
            return parseInt(tr.getAttribute('data-idprd'), 10) === ids.idprd;
        })[0];

        if (!alvo) {
            setStatus(
                ids.idlote > 0
                    ? 'Essa etiqueta tem lote (produto ' + ids.idprd + ', lote ' + ids.idlote
                      + '). Conte pela tela "Por lote".'
                    : 'Produto ' + ids.idprd + ' não está nesta lista.',
                'is-err'
            );
            return true;
        }

        // Um item escondido pelo "ocultar ja contados" precisa reaparecer,
        // senao o bipe seleciona uma linha invisivel.
        alvo.style.display = '';
        if (alvo.scrollIntoView) {
            alvo.scrollIntoView({ block: 'center' });
        }
        alvo.classList.add('is-flash');
        setTimeout(function () { alvo.classList.remove('is-flash'); }, 700);

        var input = alvo.querySelector('.sl-qtd');
        if (input) {
            input.focus();
            input.select();
        }

        var nome = (alvo.querySelector('.sl-nome-main') || {}).textContent || '';
        setStatus(nome.trim() + ' — digite a quantidade e Enter.', 'is-ok');
        return true;
    }

    if (busca) {
        busca.addEventListener('input', function () {
            if (!tratarEtiqueta()) {
                atualizarFiltro();
            }
        });

        // O leitor manda Enter depois dos digitos; sem isto ele submeteria
        // algo ou apenas piscaria o campo.
        busca.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();

            if (idprdDaEtiqueta(busca.value)) {
                tratarEtiqueta();
                return;
            }

            // Sem etiqueta: Enter pega a primeira linha visivel da busca.
            var visiveis = rows().filter(function (tr) { return tr.style.display !== 'none'; });
            if (busca.value.trim() !== '' && visiveis.length > 0) {
                var input = visiveis[0].querySelector('.sl-qtd');
                if (input) {
                    input.focus();
                    input.select();
                }
            }
        });
    }
    if (ocultar) {
        ocultar.addEventListener('change', atualizarFiltro);
    }

    atualizarFiltro();

    // Foco na busca, e nao na primeira quantidade: e o campo que recebe o bipe
    // da etiqueta, e depois da primeira gravacao o focusProximo ja leva o
    // cursor de linha em linha para quem prefere descer a lista digitando.
    if (busca) {
        busca.focus();
    } else {
        var first = table.querySelector('tbody tr.sl-row .sl-qtd');
        if (first) {
            first.focus();
        }
    }
})();
