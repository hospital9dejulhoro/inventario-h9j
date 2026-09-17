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

    function registrar(tr) {
        if (saving || !tr) {
            return;
        }
        var input = tr.querySelector('.sl-qtd');
        var btn = tr.querySelector('.sl-btn');
        var raw = input ? String(input.value || '').trim().replace(',', '.') : '';
        if (raw === '' || isNaN(raw) || Number(raw) <= 0) {
            setStatus('Informe uma quantidade maior que zero.', 'is-err');
            if (input) {
                input.focus();
            }
            return;
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
        body.append('_token', cfg.token || '');

        fetch(cfg.saveUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.message) || 'Falha ao gravar');
                }
                var ja = tr.querySelector('.sl-ja');
                if (ja) {
                    ja.textContent = fmtQtd(data.total);
                }
                tr.classList.add('is-counted', 'is-flash');
                tr.setAttribute('data-counted', '1');
                if (input) {
                    input.value = '';
                }
                setStatus('Registrado · total ' + fmtQtd(data.total), 'is-ok');
                setTimeout(function () { tr.classList.remove('is-flash'); }, 700);
                atualizarFiltro();
                focusProximo(tr);
            })
            .catch(function (err) {
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

    if (busca) {
        busca.addEventListener('input', atualizarFiltro);
    }
    if (ocultar) {
        ocultar.addEventListener('change', atualizarFiltro);
    }

    atualizarFiltro();
    var first = table.querySelector('tbody tr.sl-row .sl-qtd');
    if (first) {
        first.focus();
    }
})();
