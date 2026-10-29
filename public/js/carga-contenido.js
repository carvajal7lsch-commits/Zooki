/* RNF-16: espera compartida de datos; conserva la cabecera y evita velos en consultas de fondo. */
(function () {
    'use strict';

    if (document.zookiCargaContenidoRegistrada || typeof window.fetch !== 'function') return;
    document.zookiCargaContenidoRegistrada = true;
    const fetchOriginal = window.fetch;
    const ESPERA_MS = 400;
    let pendientes = 0;
    let temporizador = null;
    let esperaTerminada = false;
    let area = null;
    let ocupadoAnterior = null;

    function actualizar() {
        if (!area) {
            area = document.querySelector('.content-body');
            if (area) ocupadoAnterior = area.getAttribute('aria-busy');
        }
        const carga = document.getElementById('global-loader');
        if (carga) carga.hidden = pendientes === 0 || !esperaTerminada;
        if (!area) return;
        if (pendientes > 0) area.setAttribute('aria-busy', 'true');
        else if (ocupadoAnterior === null) area.removeAttribute('aria-busy');
        else area.setAttribute('aria-busy', ocupadoAnterior);
    }

    window.fetch = async function (...argumentos) {
        // Mismo contrato de las peticiones ya existentes (correos y avisos de fondo).
        if (argumentos[1]?.sinLoader) return fetchOriginal.apply(window, argumentos);
        pendientes += 1;
        if (pendientes === 1) {
            temporizador = window.setTimeout(() => {
                esperaTerminada = true;
                actualizar();
            }, ESPERA_MS);
        }
        actualizar();
        try {
            return await fetchOriginal.apply(window, argumentos);
        } finally {
            pendientes -= 1;
            if (pendientes === 0) {
                window.clearTimeout(temporizador);
                temporizador = null;
                esperaTerminada = false;
            }
            actualizar();
        }
    };

    document.addEventListener('DOMContentLoaded', actualizar, { once: true });
}());
