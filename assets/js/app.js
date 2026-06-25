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
        document.getElementById('edit-loc').value = data.loc || '';
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
