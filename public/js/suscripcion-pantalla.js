/*
 * Rediseño de Suscripción (2026-10-26): solo presentación. En el celular, pestañas
 * «Mi plan»/«Historial» y «Pagar o renovar» como hoja inferior; el total sigue al ciclo
 * elegido. La lógica de pago sigue en suscripcion.js y fuente-suscripcion.js.
 */
(() => {
    'use strict';
    function iniciar(raiz) {
        if (!raiz) return;
        const pestanas = Array.from(raiz.querySelectorAll('[data-suscripcion-pestana]'));
        const hoja = raiz.querySelector('[data-suscripcion-hoja="renovar"]');
        const abrir = raiz.querySelector('[data-suscripcion-abrir="renovar"]');
        let anteriorFoco = null;

        function activar(clave, enfocar) {
            raiz.dataset.pestana = clave;
            for (const pestana of pestanas) {
                const activa = pestana.dataset.suscripcionPestana === clave;
                pestana.setAttribute('aria-selected', activa ? 'true' : 'false');
                pestana.tabIndex = activa ? 0 : -1;
                if (activa && enfocar) pestana.focus();
            }
        }

        function abrirHoja() {
            anteriorFoco = document.activeElement;
            raiz.classList.add('is-renovar-abierta');
            raiz.querySelector('[data-suscripcion-cerrar]:not(.suscripcion__velo)')?.focus();
        }

        function cerrarHoja() {
            if (!raiz.classList.contains('is-renovar-abierta')) return;
            raiz.classList.remove('is-renovar-abierta');
            anteriorFoco?.focus?.();
        }

        function actualizarTotal() {
            const elegido = raiz.querySelector('input[name="suscripcion_ciclo"]:checked');
            const total = raiz.querySelector('[data-suscripcion-total]');
            const unidad = raiz.querySelector('[data-suscripcion-unidad]');
            if (!elegido || !total || !unidad) return;
            total.textContent = elegido.dataset.total;
            unidad.textContent = elegido.dataset.unidad;
        }

        pestanas.forEach((pestana, indice) => {
            pestana.addEventListener('click', () => activar(pestana.dataset.suscripcionPestana, false));
            pestana.addEventListener('keydown', evento => {
                const paso = evento.key === 'ArrowRight' ? 1 : evento.key === 'ArrowLeft' ? -1 : 0;
                if (!paso) return;
                evento.preventDefault();
                activar(pestanas[(indice + paso + pestanas.length) % pestanas.length].dataset.suscripcionPestana, true);
            });
        });
        if (pestanas.length) activar('plan', false);
        abrir?.addEventListener('click', abrirHoja);
        for (const cerrar of raiz.querySelectorAll('[data-suscripcion-cerrar]')) cerrar.addEventListener('click', cerrarHoja);
        raiz.addEventListener('keydown', evento => {
            if (evento.key === 'Escape') cerrarHoja();
        });
        for (const ciclo of raiz.querySelectorAll('input[name="suscripcion_ciclo"]')) ciclo.addEventListener('change', actualizarTotal);
        actualizarTotal();

        // El widget de Wompi para guardar el medio se monta en el body: una ventana modal lo
        // dejaría inerte debajo. Al abrirlo, la ventana sigue visible pero deja de ser modal.
        raiz.querySelector('[data-fuente-guardar]')?.addEventListener('click', evento => {
            const dialogo = evento.currentTarget.closest('dialog');
            if (!dialogo || !dialogo.open || typeof dialogo.show !== 'function' || !dialogo.matches(':modal')) return;
            dialogo.close();
            dialogo.show();
        });
        return { activar, abrirHoja, cerrarHoja, actualizarTotal };
    }
    if (typeof module !== 'undefined') module.exports = { iniciar };
    if (typeof document !== 'undefined') iniciar(document.querySelector('[data-suscripcion-pantalla]'));
})();
