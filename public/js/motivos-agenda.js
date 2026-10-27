/** Ayudas de entrada para el motivo libre; el servidor recibe el mismo campo de texto. */
(() => {
    'use strict';
    function iniciar() {
        document.querySelectorAll('[data-motivo-agenda]').forEach(grupo => {
            const selector = grupo.querySelector('[data-motivo-opcion]');
            const campo = grupo.querySelector('[name=motivo]');
            let otro = '';
            function actualizar(avisar) {
                const libre = selector.value === 'otro';
                campo.hidden = !libre;
                campo.value = libre ? otro : selector.value;
                if (avisar) campo.dispatchEvent(new Event('input', { bubbles: true }));
            }
            selector.addEventListener('change', () => {
                actualizar(true);
                if (!campo.hidden) campo.focus();
            });
            campo.addEventListener('input', () => {
                if (selector.value === 'otro') otro = campo.value;
            });
            campo.form.addEventListener('reset', () => queueMicrotask(() => {
                otro = '';
                actualizar(false);
            }));
            actualizar(false);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})();
