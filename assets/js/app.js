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

    function playSuccessBeep() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) {
                return;
            }
            const ctx = new AudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            osc.type = 'sine';
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.12);
        } catch (e) {
            /* áudio opcional */
        }
    }

    function vibrateSuccess() {
        if (navigator.vibrate) {
            navigator.vibrate(40);
        }
    }

    function focusBarcode() {
        const input = document.getElementById('CODIGOBARRAS');
        if (input) {
            input.value = '';
            input.focus();
            input.select();
        }
    }

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.id === 'inventory-form') {
                const barcode = document.getElementById('CODIGOBARRAS');
                const hasBarcode = barcode && barcode.value.trim() !== '';
                if (!hasBarcode && !event.submitter) {
                    return;
                }
                if (!hasBarcode && event.submitter && event.submitter.name === 'aplicar') {
                    showLoading();
                    return;
                }
                if (hasBarcode) {
                    showLoading();
                    return;
                }
            }
            showLoading();
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

    const barcodeInput = document.getElementById('CODIGOBARRAS');
    const inventoryForm = document.getElementById('inventory-form');

    if (barcodeInput && inventoryForm) {
        barcodeInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const digits = barcodeInput.value.replace(/\D/g, '');
                if (digits.length === 13) {
                    barcodeInput.value = digits;
                    inventoryForm.requestSubmit();
                }
            }
        });

        window.addEventListener('pageshow', function () {
            hideLoading();
            focusBarcode();
        });

        focusBarcode();
    }

    const flashEl = document.querySelector('.flash[data-flash-type]');
    if (flashEl) {
        const type = flashEl.getAttribute('data-flash-type');
        if (type === 'success') {
            playSuccessBeep();
            vibrateSuccess();
        }
        if (type === 'danger' || type === 'warning') {
            vibrateSuccess();
            if (navigator.vibrate) {
                navigator.vibrate([30, 40, 30]);
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
        if (event.key === 'Escape') {
            closeEditModal();
        }
    });
})();
