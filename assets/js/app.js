(function () {
    'use strict';

    const overlay = document.getElementById('loading-overlay');

    function showLoading() {
        if (overlay) {
            overlay.classList.remove('d-none');
            overlay.style.display = 'grid';
        }
    }

    function hideLoading() {
        if (overlay) {
            overlay.classList.add('d-none');
            overlay.style.display = 'none';
        }
    }

    function criarAudioContext() {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        return AudioContext ? new AudioContext() : null;
    }

    function tocarTom(ctx, frequencia, atraso, duracao) {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        const inicio = ctx.currentTime + atraso;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = frequencia;
        osc.type = 'sine';
        gain.gain.setValueAtTime(0.15, inicio);
        gain.gain.exponentialRampToValueAtTime(0.001, inicio + duracao);
        osc.start(inicio);
        osc.stop(inicio + duracao);
    }

    function playSuccessBeep() {
        try {
            const ctx = criarAudioContext();
            if (!ctx) {
                return;
            }
            tocarTom(ctx, 880, 0, 0.12);
        } catch (e) {
            /* áudio opcional */
        }
    }

    // Dois tons graves e mais longos: o operador precisa distinguir releitura do
    // bipe normal sem tirar os olhos da prateleira.
    function playWarningBeep() {
        try {
            const ctx = criarAudioContext();
            if (!ctx) {
                return;
            }
            tocarTom(ctx, 440, 0, 0.16);
            tocarTom(ctx, 300, 0.2, 0.26);
        } catch (e) {
            /* áudio opcional */
        }
    }

    function vibrateSuccess() {
        try {
            // Chrome bloqueia vibrate sem gesto do usuário (ex.: ao carregar flash da página)
            if (!navigator.vibrate) {
                return;
            }
            if (navigator.userActivation && !navigator.userActivation.hasBeenActive) {
                return;
            }
            navigator.vibrate(40);
        } catch (e) {
            /* vibração opcional */
        }
    }

    // As telas de lote e sem lote gravam por fetch, sem recarregar a página, e
    // por isso nunca passavam pelo flash que dispara o bipe. Quem conta está
    // olhando a prateleira, não a tela: sem o retorno sonoro, só descobre que a
    // gravação falhou quando volta os olhos para o monitor.
    window.InventarioFeedback = {
        sucesso: function () { playSuccessBeep(); vibrateSuccess(); },
        alerta: function () { playWarningBeep(); }
    };

    const barcodeInput = document.getElementById('CODIGOBARRAS');
    const qtyInput = document.getElementById('QUANTIDADE');
    const inventoryForm = document.getElementById('inventory-form');

    // Traz o campo para a tela quando ele nao esta visivel - e so nesse caso.
    //
    // preventScroll existe de proposito: no desktop o campo ja esta a vista, e
    // deixar o foco rolar a pagina fazia a tela pular a cada bipe. No celular o
    // efeito era o oposto e pior: depois de cada leitura a pagina volta ao topo
    // e o campo fica em y=1185 numa tela de 812 - com o foco nele. A pessoa
    // digitava as cegas, sem ver o que entrou nem a quantidade.
    function trazerParaTela(input) {
        const r = input.getBoundingClientRect();
        const alturaVisivel = window.innerHeight || document.documentElement.clientHeight;

        if (r.top >= 0 && r.bottom <= alturaVisivel) {
            return;
        }

        if (input.scrollIntoView) {
            input.scrollIntoView({ block: 'center' });
        }
    }

    /** Ha um aviso na frente esperando ser lido? */
    function avisoAberto() {
        const m = document.getElementById('aviso-modal');
        return !!m && !m.classList.contains('hidden');
    }

    function focusBarcode() {
        const input = document.getElementById('CODIGOBARRAS');
        if (!input) {
            return;
        }

        // Com o aviso aberto, o foco fica nele. Senao o campo de leitura - que
        // esta atras do modal - continuaria recebendo o que o leitor disparar,
        // e o bipe seguinte entraria sem ninguem ver a recusa do anterior.
        if (avisoAberto()) {
            return;
        }
        input.value = '';
        input.focus({ preventScroll: true });
        input.select();
        trazerParaTela(input);
    }

    function scheduleFocusBarcode() {
        focusBarcode();
        window.setTimeout(focusBarcode, 0);
        window.setTimeout(focusBarcode, 80);
        window.setTimeout(focusBarcode, 200);
    }

    function submitBarcode(digits) {
        if (!barcodeInput || !inventoryForm || digits.length !== 13) {
            return;
        }
        barcodeInput.value = digits;
        inventoryForm.requestSubmit();
    }

    if (barcodeInput && inventoryForm) {
        barcodeInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const digits = barcodeInput.value.replace(/\D/g, '');
                if (digits.length === 13) {
                    submitBarcode(digits);
                }
            }
        });

        window.addEventListener('pageshow', function () {
            scheduleFocusBarcode();
        });

        scheduleFocusBarcode();
    }

    // Vale para toda tela: voltar pelo historico restaura a pagina com o overlay
    // ainda aceso, e sem isto ele fica preso ate um F5.
    window.addEventListener('pageshow', hideLoading);

    if (qtyInput && inventoryForm) {
        qtyInput.dataset.lastQty = qtyInput.value || '1';

        qtyInput.addEventListener('focus', function () {
            qtyInput.dataset.lastQty = qtyInput.value || '1';
            qtyInput.select();
        });

        qtyInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === 'Tab') {
                const digits = qtyInput.value.replace(/\D/g, '');
                if (digits.length === 13) {
                    event.preventDefault();
                    qtyInput.value = qtyInput.dataset.lastQty || '1';
                    submitBarcode(digits);
                    return;
                }
                if (event.key === 'Enter') {
                    event.preventDefault();
                    qtyInput.dataset.lastQty = qtyInput.value || '1';
                    focusBarcode();
                }
            }
        });

        // Leitor costuma enviar 13 dígitos muito rápido no campo focado
        qtyInput.addEventListener('input', function () {
            const digits = qtyInput.value.replace(/\D/g, '');
            if (digits.length === 13) {
                qtyInput.value = qtyInput.dataset.lastQty || '1';
                submitBarcode(digits);
            }
        });
    }

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.hasAttribute('data-ajax') || form.classList.contains('js-no-loading')) {
                return;
            }
            // Formulário com confirmação acende o overlay por conta própria,
            // depois de confirmado. Aqui ele ficaria aceso mesmo se o usuário
            // desistisse — e sem navegação não há pageshow para apagá-lo.
            if (form.hasAttribute('data-confirmar-codigo')) {
                return;
            }
            // Aqui havia um caso especial para o #inventory-form: escolher
            // inventario e bipar eram a mesma forma, e so dava para saber o que
            // o envio queria dizer olhando se o campo do codigo estava cheio.
            // Agora sao duas formas com metodos diferentes.
            //
            // Nem todo envio navega, porem: o campo de leitura tambem procura,
            // e com um nome escrito nele a busca cancela o POST. O overlay
            // acendia nesse envio que nunca saia do lugar e ficava aceso ate a
            // pessoa recarregar a pagina - parecia uma gravacao travada, e
            // escondia o "nada encontrado" que estava logo atras.
            //
            // O setTimeout espera o evento terminar de percorrer os ouvintes,
            // para valer tambem quando quem cancela roda depois deste.
            window.setTimeout(function () {
                if (!event.defaultPrevented) {
                    showLoading();
                }
            }, 0);
        });
    });

    // Exclusão de inventário inteiro: exige digitar o código.
    //
    // Numa contagem com várias pessoas, este botão apaga o trabalho de todas
    // de uma vez e não há como desfazer. Digitar o código obriga a olhar qual
    // inventário está prestes a sumir — um OK de um clique não obriga.
    document.querySelectorAll('[data-confirmar-codigo]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var codigo = form.getAttribute('data-confirmar-codigo') || '';
            var total = form.getAttribute('data-confirmar-total') || '0';
            // Inclui o artigo: "o inventário" / "a contagem avulsa".
            var rotulo = form.getAttribute('data-confirmar-rotulo') || 'o inventário';
            var itens = total === '1' ? '1 item gravado' : total + ' itens gravados';

            var resposta = window.prompt(
                'Apagar ' + rotulo + ' ' + codigo + ' e ' + itens + '?\n\n'
                + 'Isso remove a contagem de TODOS os operadores '
                + 'e não pode ser desfeito.\n\n'
                + 'Para confirmar, digite o código:'
            );

            if (resposta === null) {
                event.preventDefault();
                return;
            }

            // Aceita com ou sem os pontos da máscara.
            var limpa = function (v) { return String(v || '').replace(/\D/g, ''); };
            if (limpa(resposta) !== limpa(codigo)) {
                event.preventDefault();
                window.alert('O código digitado não confere. Nada foi apagado.');
                return;
            }

            showLoading();
        });
    });

    // Filtros que mudam a consulta no servidor (grupo contábil, "incluir
    // zerados") recarregam a tela sozinhos. Fica aqui, e não na tela de lotes,
    // porque a tela "sem lote" usa o mesmo recurso e não carrega aquele script.
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () {
            if (!el.form) {
                return;
            }
            // requestSubmit dispara o evento submit; form.submit() não, e sem ele
            // a troca de filtro navegava sem nenhum sinal de carregamento.
            if (el.form.requestSubmit) {
                el.form.requestSubmit();
            } else {
                el.form.submit();
            }
        });
    });

    document.querySelectorAll('.env-item input[type="radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.env-item').forEach(function (item) {
                item.classList.remove('is-selected');
            });
            if (radio.checked && radio.closest('.env-item')) {
                radio.closest('.env-item').classList.add('is-selected');
            }
        });
    });

    function formatInventarioMask(raw) {
        const digits = String(raw || '').replace(/\D/g, '').slice(0, 8);
        const parts = [];
        if (digits.length > 0) {
            parts.push(digits.slice(0, 2));
        }
        if (digits.length > 2) {
            parts.push(digits.slice(2, 5));
        }
        if (digits.length > 5) {
            parts.push(digits.slice(5, 8));
        }
        return parts.join('.');
    }

    function loadLocaisEstoque() {
        const el = document.getElementById('locais-estoque-data');
        if (!el) {
            return {};
        }
        try {
            return JSON.parse(el.textContent || '{}');
        } catch (e) {
            return {};
        }
    }

    const locaisEstoque = loadLocaisEstoque();

    function nomeLocal(codloc) {
        const code = String(codloc || '').replace(/\D/g, '').padStart(3, '0').slice(-3);
        return locaisEstoque[code] || '';
    }

    function setLocalFeedback(codloc, options) {
        options = options || {};
        const nomeEl = document.querySelector('[data-codloc-nome]');
        const hintEl = document.getElementById('inventario-mask-hint');
        const invInput = document.getElementById('CODINVENTARIO');
        const digits = String(codloc || '').replace(/\D/g, '');
        const code = digits.length >= 3 ? digits.slice(0, 3) : digits;
        const nome = code.length === 3 ? nomeLocal(code) : '';

        if (nomeEl) {
            if (code.length < 3) {
                nomeEl.textContent = 'Informe o local no código do inventário';
                nomeEl.classList.remove('is-error', 'is-ok');
            } else if (nome) {
                nomeEl.textContent = nome;
                nomeEl.classList.remove('is-error');
                nomeEl.classList.add('is-ok');
            } else {
                nomeEl.textContent = 'Local ' + code + ' não cadastrado';
                nomeEl.classList.add('is-error');
                nomeEl.classList.remove('is-ok');
            }
        }

        if (hintEl) {
            if (code.length === 3 && !nome) {
                hintEl.textContent = 'Local de estoque inválido no código do inventário.';
                hintEl.classList.add('is-error');
            } else {
                hintEl.textContent = 'Formato AA.LLL.NNN — deve existir no RM (TINVENTARIO)';
                hintEl.classList.remove('is-error');
            }
        }

        if (invInput && options.markValidity !== false && digits.length >= 5) {
            if (code.length === 3 && !nome) {
                invInput.setCustomValidity('Local de estoque ' + code + ' não é válido.');
            } else {
                invInput.setCustomValidity('');
            }
        } else if (invInput) {
            invInput.setCustomValidity('');
        }
    }

    function syncCodlocFromInventario(inventarioInput) {
        const locInput = document.getElementById('CODLOC');
        if (!locInput || !inventarioInput) {
            return;
        }
        const digits = inventarioInput.value.replace(/\D/g, '');
        const codloc = digits.length >= 5 ? digits.slice(2, 5) : '';
        locInput.value = codloc;
        setLocalFeedback(codloc);
    }

    document.querySelectorAll('[data-inventario-mask]').forEach(function (input) {
        input.addEventListener('input', function () {
            const formatted = formatInventarioMask(input.value);
            input.value = formatted;
            syncCodlocFromInventario(input);
        });

        if (input.value) {
            input.value = formatInventarioMask(input.value);
            syncCodlocFromInventario(input);
        }
    });

    // Escolha por lista. O local sai dos digitos 3 a 5 do codigo, do mesmo jeito
    // que saia da digitacao - o que muda e so a origem do valor. Nada de
    // formatInventarioMask aqui: o texto ja vem formatado do servidor, e
    // reatribuir um valor que nao seja exatamente uma das opcoes limpa o select.
    document.querySelectorAll('[data-inventario-picker]').forEach(function (select) {
        select.addEventListener('change', function () {
            syncCodlocFromInventario(select);
        });

        if (select.value) {
            syncCodlocFromInventario(select);
        }
    });

    const editLocInput = document.getElementById('edit-loc');
    const editLocNome = document.getElementById('edit-loc-nome');
    if (editLocInput) {
        editLocInput.addEventListener('input', function () {
            const code = editLocInput.value.replace(/\D/g, '').slice(0, 3);
            editLocInput.value = code;
            const nome = nomeLocal(code);
            if (editLocNome) {
                if (code.length < 3) {
                    editLocNome.textContent = '';
                    editLocNome.classList.remove('is-error', 'is-ok');
                } else if (nome) {
                    editLocNome.textContent = nome;
                    editLocNome.classList.add('is-ok');
                    editLocNome.classList.remove('is-error');
                } else {
                    editLocNome.textContent = 'Local ' + code + ' não cadastrado';
                    editLocNome.classList.add('is-error');
                    editLocNome.classList.remove('is-ok');
                }
            }
            if (code.length === 3 && !nome) {
                editLocInput.setCustomValidity('Local de estoque ' + code + ' não é válido.');
            } else {
                editLocInput.setCustomValidity('');
            }
        });
    }

    const flashEl = document.querySelector('.flash[data-flash-type]');
    if (flashEl) {
        const type = flashEl.getAttribute('data-flash-type');
        if (type === 'success') {
            playSuccessBeep();
            // Não chama vibrate no carregamento da página (Chrome bloqueia sem gesto)
        } else if (type === 'warning') {
            // Releitura do mesmo código — antes o aviso era só visual.
            playWarningBeep();
        }
    }

    /* ----------------------------------------------------------------------
     * Aviso que exige um toque para sumir
     *
     * A mensagem no topo da pagina parou de servir quando a tela de leitura
     * passou a rolar sozinha ate o campo de bipagem: o aviso fica acima da
     * dobra e quem bipa em sequencia nao volta la. Recusa de produto fora do
     * estoque, ou de lote faltando, passava batida ate a conferencia.
     *
     * So erro e alerta abrem o modal. Confirmacao de gravacao continua como
     * mensagem discreta: virar modal a cada bipe seria pior que o problema.
     * -------------------------------------------------------------------- */

    const avisoModal = document.getElementById('aviso-modal');
    const avisoTitulo = document.getElementById('aviso-modal-titulo');
    const avisoTexto = document.getElementById('aviso-modal-texto');
    const avisoOk = document.getElementById('aviso-modal-ok');

    const TITULOS = {
        danger: 'Não foi gravado',
        warning: 'Confira antes de seguir'
    };

    function abrirAviso(mensagem, tipo) {
        if (!avisoModal || !mensagem) {
            return;
        }

        tipo = tipo === 'warning' ? 'warning' : 'danger';

        avisoTitulo.textContent = TITULOS[tipo];
        avisoTexto.textContent = mensagem;
        avisoModal.classList.remove('hidden');
        avisoModal.classList.toggle('is-warning', tipo === 'warning');
        avisoModal.setAttribute('aria-hidden', 'false');

        // Som e vibracao: numa farmacia barulhenta, com o celular na mao e a
        // caixa na outra, o aviso visual sozinho escapa.
        try {
            feedback.alerta();
        } catch (e) {
            // Aparelho sem som ou sem vibracao nao pode derrubar o aviso.
        }

        // O foco no botao faz o leitor de tela anunciar o aviso, e deixa fechar
        // com Enter sem tirar a mao do leitor de codigo.
        if (avisoOk) {
            avisoOk.focus();
        }
    }

    function fecharAviso() {
        if (!avisoModal || avisoModal.classList.contains('hidden')) {
            return;
        }
        avisoModal.classList.add('hidden');
        avisoModal.setAttribute('aria-hidden', 'true');
        // De volta ao campo de leitura: o proximo bipe nao pode exigir um toque
        // a mais so porque houve um aviso.
        focusBarcode();
    }

    // As telas de lote gravam por fetch e mostram a falha numa linha de status
    // que some no meio da pagina; elas chamam isto para o erro nao escapar.
    window.avisoModal = abrirAviso;

    if (avisoModal) {
        document.querySelectorAll('[data-fechar-aviso]').forEach(function (el) {
            el.addEventListener('click', fecharAviso);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                fecharAviso();
            }
        });

        // Mensagem que veio do servidor nesta carga da pagina.
        //
        // Nem todo alerta merece parar a contagem. O que nao gravou nada -
        // recusa, sessao vencida, tela desatualizada - precisa do toque, senao
        // passa batido. O que gravou e so quer ser notado, como a releitura do
        // mesmo lote, fica na mensagem do topo com o som de alerta: bipar
        // trinta caixas iguais custaria trinta toques.
        const flash = document.querySelector('[data-flash-type]');
        if (flash) {
            const tipo = flash.getAttribute('data-flash-type');
            const exigeToque = flash.getAttribute('data-flash-toque') !== '0';
            if (exigeToque && (tipo === 'danger' || tipo === 'warning')) {
                abrirAviso(flash.textContent.trim(), tipo);
            }
        }
    }

    const editModal = document.getElementById('edit-modal');
    const editForm = document.getElementById('edit-form');

    function openEditModal(data) {
        if (!editModal || !editForm) return;
        document.getElementById('edit-id').value = data.id || '';
        document.getElementById('edit-barras').value = data.barras || '';
        document.getElementById('edit-qtd').value = data.qtd || '';
        const editLoc = document.getElementById('edit-loc');
        editLoc.value = data.loc || '';
        editLoc.dispatchEvent(new Event('input'));
        editModal.classList.remove('hidden');
        editModal.setAttribute('aria-hidden', 'false');

        // O foco ficava no campo de leitura, atras do modal. Quem abre "Editar"
        // vem mudar a quantidade, entao o cursor comeca nela - e, de quebra, um
        // bipe disparado com o modal aberto para de cair num campo invisivel e
        // enviar a forma de gravacao por baixo da edicao.
        var qtdEdicao = document.getElementById('edit-qtd');
        if (qtdEdicao) {
            qtdEdicao.focus();
            qtdEdicao.select();
        }
    }

    function closeEditModal() {
        if (!editModal) return;
        editModal.classList.add('hidden');
        editModal.setAttribute('aria-hidden', 'true');
        focusBarcode();
    }

    document.querySelectorAll('.btn-edit-item').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openEditModal({
                id: btn.dataset.id,
                barras: btn.dataset.barras,
                qtd: btn.dataset.qtd,
                loc: btn.dataset.loc
            });
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (el) {
        el.addEventListener('click', closeEditModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && editModal && !editModal.classList.contains('hidden')) {
            closeEditModal();
        }
    });
})();
