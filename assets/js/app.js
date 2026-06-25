(function () {
    'use strict';

    const overlay = document.getElementById('loading-overlay');

    function showLoading() {
        if (overlay) {
            overlay.classList.remove('d-none');
            overlay.style.display = 'grid';
        }
    }

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', showLoading);
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
            if (event.key === 'Enter' && barcodeInput.value.length === 13) {
                inventoryForm.requestSubmit();
            }
        });
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
