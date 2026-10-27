/**
 * Rediseño (2026-10-26): «Ajustes» del administrador (Configuración y Mi perfil en una pantalla).
 * En escritorio el menú lateral y la sección se ven juntos; las pestañas las cambia dialogos-zooki.js.
 * Hasta 1023 px el menú es una lista: tocar una fila abre la sección y «← Ajustes» vuelve a la lista.
 * Sin este archivo se ven la lista y la sección una debajo de la otra.
 */
(function (global) {
    'use strict';

    function iniciar(raiz, entorno) {
        const ventana = entorno.window;
        const titulo = raiz.querySelector('[data-ajustes-titulo]');
        // Se lee antes de que dialogos-zooki.js escriba la primera pestaña en la dirección:
        // sin una sección pedida (#cuenta, #horarios…), el celular abre el menú.
        const pedida = (ventana.location.hash || '').slice(1) || raiz.dataset.ajustesSeccionInicial || '';
        if (!ventana.location.hash && pedida) ventana.history.replaceState(null, '', '#' + pedida);

        function mostrar(vista) {
            raiz.dataset.ajustesVista = vista;
            ventana.document.body.dataset.ajustesVista = vista;
        }

        function rotular() {
            const activa = raiz.querySelector('[data-config-tab][aria-selected="true"]');
            if (activa) titulo.textContent = activa.dataset.ajustesEtiqueta || activa.getAttribute?.('aria-label') || activa.textContent.trim();
            const volverInicio = raiz.dataset.ajustesOrigen === 'inicio' && activa?.dataset.configTab === 'suscripcion';
            raiz.querySelector('[data-ajustes-volver]').hidden = volverInicio;
            const volver = raiz.querySelector('[data-ajustes-volver-inicio]');
            if (volver) volver.hidden = !volverInicio;
        }

        function quitarSeccionDeLaDireccion() {
            try {
                const url = new URL(ventana.location.href);
                for (const clave of ['seccion', 'vista_suscripcion', 'vista_horarios', 'pagina']) url.searchParams.delete(clave);
                ventana.history.replaceState(null, '', url.pathname + url.search);
            } catch (_) {
                // Sin historial (vista embebida): la lista igual se muestra.
            }
        }

        raiz.addEventListener('click', evento => {
            if (evento.target.closest('[data-config-tab]')) {
                mostrar('seccion');
                ventana.scrollTo(0, 0);
                return;
            }
            if (!evento.target.closest('[data-ajustes-volver]')) return;
            mostrar('menu');
            quitarSeccionDeLaDireccion();
        });
        // dialogos-zooki.js escribe la primera pestaña al iniciar; si se sigue en la lista, recargar debe volver a ella.
        ventana.addEventListener('load', () => {
            if (raiz.dataset.ajustesVista === 'menu') quitarSeccionDeLaDireccion();
        });
        // Un enlace que pide una sección (desde Inicio, #horarios) la abre aunque ya se esté en Ajustes.
        ventana.addEventListener('hashchange', () => {
            if (ventana.location.hash.length > 1) mostrar('seccion');
        });
        new ventana.MutationObserver(rotular).observe(raiz, { subtree: true, attributes: true, attributeFilter: ['aria-selected'] });
        mostrar(pedida ? 'seccion' : 'menu');
        rotular();
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { iniciar };
    if (typeof document === 'undefined') return;
    const raiz = document.querySelector('[data-ajustes]');
    if (raiz) iniciar(raiz, { window: global });
})(typeof window !== 'undefined' ? window : globalThis);
