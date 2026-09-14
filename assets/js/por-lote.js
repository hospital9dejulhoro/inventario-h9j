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
    var fCancelar = document.getElementById('pl-f-cancelar');

    if (!table || !form) {
        return;
    }

    var selecionada = null;
    var saving = false;

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
        var podeContar = dentroDoInventario(tr);
        bloqueioEl.hidden = podeContar;
        grupoQtd.hidden = !podeContar;
        fGravar.disabled = !podeContar;

        fQtd.value = '';
        if (podeContar) {
            fQtd.focus();
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

    function gravar() {
        if (saving || !selecionada || !dentroDoInventario(selecionada)) {
            return;
        }

        var tr = selecionada;
        var raw = String(fQtd.value || '').trim().replace(',', '.');

        if (raw === '' || isNaN(raw) || Number(raw) <= 0) {
            setStatus('Informe uma quantidade maior que zero.', 'is-err');
            fQtd.focus();
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

        fetch(cfg.saveUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.message) || 'Falha ao gravar');
                }

                var total = fmtQtd(data.total);
                tr.setAttribute('data-ja', total);
                tr.setAttribute('data-counted', '1');
                tr.classList.add('is-counted', 'is-flash');

                var celulaJa = tr.querySelector('.pl-ja');
                if (celulaJa) {
                    celulaJa.textContent = total;
                }
                fJa.textContent = total + unidade(tr);
                fQtd.value = '';

                // leituras > 1 inclui o que veio bipado da etiqueta neste inventario
                if (data.leituras > 1) {
                    setStatus(
                        data.leituras + 'a leitura deste lote - total ' + total + '. Confira se nao e repeticao.',
                        'is-err'
                    );
                } else {
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
                setStatus(err.message || 'Erro ao gravar.', 'is-err');
                fQtd.focus();
                fQtd.select();
            })
            .then(function () {
                saving = false;
                fGravar.disabled = !dentroDoInventario(tr);
                tr.classList.remove('is-saving');
            });
    }

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
        busca.addEventListener('input', atualizarFiltro);

        busca.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') {
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

    // Grupo contabil e "incluir zerados" mudam a consulta, entao recarregam.
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () {
            el.form.submit();
        });
    });

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
