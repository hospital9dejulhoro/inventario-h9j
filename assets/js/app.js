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
})();
