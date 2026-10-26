/*
 * Rediseño v2 de Suscripción (2026-10-26): solo presentación. Pestañas «Mi plan» e
 * «Historial de pagos» (con #historial para volver a ella al paginar) y el total que sigue
 * al ciclo elegido. Las ventanas las abre dialogos-zooki.js; la lógica de pago sigue en
 * suscripcion.js y fuente-suscripcion.js.
 */
(() => {
    'use strict';
    function iniciar(raiz, entorno = globalThis) {
        if (!raiz) return;
        const pestanas = Array.from(raiz.querySelectorAll('[data-suscripcion-pestana]'));

        function activar(clave, enfocar) {
            if (!pestanas.some(pestana => pestana.dataset.suscripcionPestana === clave)) clave = 'plan';
            raiz.dataset.pestana = clave;
            for (const pestana of pestanas) {
                const activa = pestana.dataset.suscripcionPestana === clave;
                pestana.setAttribute('aria-selected', activa ? 'true' : 'false');
                pestana.tabIndex = activa ? 0 : -1;
                if (activa && enfocar) pestana.focus();
            }
            try {
                entorno.history?.replaceState(null, '', clave === 'plan' ? entorno.location.pathname + entorno.location.search : '#' + clave);
            } catch (_) {
                // Sin historial (vista embebida): la pestaña igual cambia.
            }
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
        if (pestanas.length) activar((entorno.location?.hash || '').slice(1), false);
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
        return { activar, actualizarTotal };
    }
    if (typeof module !== 'undefined') module.exports = { iniciar };
    if (typeof document !== 'undefined') iniciar(document.querySelector('[data-suscripcion-pantalla]'));
})();
